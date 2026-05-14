<?php
namespace Tabby\Checkout\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Registry;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Sales\Helper\Data as SalesData;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Service\InvoiceService;
use Tabby\Checkout\Gateway\Helper\Data as DataHelper;
use Tabby\Checkout\Model\Api\DdLog;

class Invoice extends AbstractHelper
{
    /**
     * @var ConfigInterface
     */
    protected $moduleConfig;

    /**
     * @var TransactionFactory
     */
    protected $transactionFactory;

    /**
     * @var SalesData
     */
    protected $salesData;

    /**
     * @var InvoiceSender
     */
    protected $invoiceSender;

    /**
     * @var InvoiceService
     */
    protected $invoiceService;

    /**
     * @var Registry
     */
    protected $registry;

    /**
     * @var DdLog
     */
    protected $ddlog;

    public function __construct(
        Context $context,
        ConfigInterface $moduleConfig,
        TransactionFactory $transactionFactory,
        SalesData $salesData,
        InvoiceSender $invoiceSender,
        InvoiceService $invoiceService,
        Registry $registry,
        DdLog $ddlog
    ) {
        parent::__construct($context);
        $this->moduleConfig = $moduleConfig;
        $this->transactionFactory = $transactionFactory;
        $this->salesData = $salesData;
        $this->invoiceSender = $invoiceSender;
        $this->invoiceService = $invoiceService;
        $this->registry = $registry;
        $this->ddlog = $ddlog;
    }
    /**
     * Create invoice if no invoices found
     *
     * @param \Magento\Sales\Model\Order $order
     * @return false
     */
    public function possiblyCreateInvoice(\Magento\Sales\Model\Order $order)
    {
        // create invoice for CaptureOn order
        try {
            if ($order->getState() == \Magento\Sales\Model\Order::STATE_PROCESSING && !$order->hasInvoices()) {
                if ($this->moduleConfig->getValue(DataHelper::CAPTURE_ON) == 'order') {
                    $this->createInvoice(
                        $order,
                        \Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE
                    );
                } else {
                    if ($this->moduleConfig->getValue(DataHelper::CREATE_PENDING_INVOICE)) {
                        $this->createInvoice($order);
                    }
                }
            }
        } catch (Exception $e) {
            $this->ddlog->log("warn", "could not possibly create invoice", $e);
            return false;
        }
    }
    /**
     * Creates invoice for given order and captureCase
     *
     * @param \Magento\Sales\Model\Order $order
     * @param string $captureCase
     */
    public function createInvoice($order, $captureCase = \Magento\Sales\Model\Order\Invoice::NOT_CAPTURE)
    {
        try {
            // check order and order payment method code
            if ($order
                && $order->canInvoice()
                && $order->getPayment()
                && $order->getPayment()->getMethodInstance()
            ) {
                if (!$order->hasInvoices()) {

                    $invoice = $this->invoiceService->prepareInvoice($order);
                    if ($captureCase == \Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE) {
                        $this->registry->register('current_invoice', $invoice);
                    }
                    $invoice->setRequestedCaptureCase($captureCase);
                    $invoice->register();
                    $invoice->getOrder()->setCustomerNoteNotify(false);
                    $invoice->getOrder()->setIsInProcess(true);
                    if ($captureCase == \Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE) {
                        $invoice->getOrder()->setStatus($this->moduleConfig->getValue(DataHelper::CAPTURED_STATUS));
                    }
                    $transactionSave = $this->transactionFactory
                        ->create()
                        ->addObject($invoice)
                        ->addObject($order->getPayment())
                        ->addObject($invoice->getOrder());
                    $transactionSave->save();
                    if ($captureCase == \Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE) {
                        $this->registry->unregister('current_invoice');
                    }

                    $this->sendInvoice($invoice);
                }
            } else {
                $this->ddlog->log("warn", "could not create invoice for order");
            }
        } catch (Exception $e) {
            $this->ddlog->log("warn", "could not create invoice", $e);
        }
    }

    /**
     * Creates invoice for autocapture feature. Used to create invoices on order authorization.
     *
     * @param \Magento\Sales\Model\Order $order
     * @throws Exception
     */
    public function createInvoiceForAutoCapture(\Magento\Sales\Model\Order $order)
    {

        $payment = $order->getPayment();

        $txn = $payment->getAuthorizationTransaction();
        if (!$txn) return;

        $response = $txn->getAdditionalInformation(Transaction::RAW_DETAILS);
        if (!is_array($response) || !array_key_exists('status', $response)) {
            return;
        }

        array_walk_recursive($response, function(&$value, $key) {
            if ($new_val = json_decode($value, true)) $value = $new_val;
        });

        // create invoice for Tabby end autoCapture
        if ($response['status'] == 'CLOSED' && count($response['captures']) > 0 && $order->canInvoice()) {
            $txnId = $response['captures'][0]['id'];
            $invoice = $order->prepareInvoice();
            $captureCase = \Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE;
            $invoice->setRequestedCaptureCase($captureCase);
            $invoice->setTransactionId($txnId);

            $invoice->pay();

            $invoice->register();

            $payment->setParentTransactionId($response['id']);
            $payment->setTransactionId($txnId);
            $payment->setShouldCloseParentTransaction(true);

            $txn = $payment->AddTransaction(
                \Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE,
                $invoice,
                true
            );

            $formatedPrice = $order->getBaseCurrency()->formatTxt(
                $invoice->getOrder()->getGrandTotal()
            );

            $message = __('The Captured amount is %1.', $formatedPrice);
            $payment->addTransactionCommentsToOrder(
                $txn,
                $message
            );

            $transactionSave = $this->transactionFactory
                ->create()
                ->addObject($invoice)
                ->addObject($payment)
                ->addObject($order);

            $transactionSave->save();

            $this->sendInvoice($invoice);
        }
    }

    /**
     * Send invoice email.
     *
     * @param \Magento\Sales\Model\Order\Invoice $invoice
     */
    public function sendInvoice(\Magento\Sales\Model\Order\Invoice $invoice)
    {
        // send invoice emails
        try {
            if ($this->salesData->canSendNewInvoiceEmail($invoice->getOrder()->getStoreId())) {
                $this->ddlog->log("info", "sending invoice email");
                $this->invoiceSender->send($invoice);
            }
        } catch (\Exception $e) {
            $this->ddlog->log("error", "could not send invoice email", $e);
        }
    }
}
