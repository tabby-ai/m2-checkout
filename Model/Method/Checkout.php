<?php

namespace Tabby\Checkout\Model\Method;

use Exception;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Block\Form;
use Magento\Payment\Model\InfoInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Api\Data\OrderPaymentExtensionInterface;
use Magento\Sales\Api\Data\OrderPaymentExtensionInterfaceFactory;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Sales\Model\Service\OrderService;
use Tabby\Checkout\Block\Info;
use Tabby\Checkout\Exception\NotAuthorizedException;
use Tabby\Checkout\Exception\NotFoundException;
use Tabby\Checkout\Gateway\Config\Config;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Payment\Helper\Data;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Model\Method\Logger;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Directory\Helper\Data as DirectoryHelper;
use Tabby\Checkout\Model\Api\DdLog;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Helper\Data as SalesData;
use Tabby\Checkout\Model\Api\Tabby\Payments;
use Tabby\Checkout\Model\Api\Tabby\Checkout as CheckoutApi;
use Magento\Framework\UrlInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Framework\Locale\ResolverInterface as LocaleResolver;
use Tabby\Checkout\Model\Checkout\Payment\OrderHistory;
use Tabby\Checkout\Model\Checkout\Payment\BuyerHistory;
use Magento\Customer\Model\ResourceModel\CustomerRepository;

/**
 * Base class for payment processing
 */
class Checkout extends AbstractMethod
{
    public const ALLOWED_COUNTRIES = 'AE,SA,KW,BH,QA';
    public const PAYMENT_ID_FIELD = 'checkout_id';
    private const TABBY_CURRENCY_FIELD = 'tabby_currency';

    /**
     * @var string
     */
    protected $_code = 'tabby_checkout';

    /**
     * @var string
     */
    protected $_codeTabby = 'pay_later';

    /**
     * @var string
     */
    protected $_formBlockType = Form::class;

    /**
     * @var string
     */
    protected $_infoBlockType = Info::class;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_isGateway = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_isInitializeNeeded = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canAuthorize = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canCapture = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canCapturePartial = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canRefund = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canRefundInvoicePartial = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canVoid = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canUseInternal = false;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canFetchTransactionInfo = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canReviewPayment = false;

    /**
     * @var bool
     */
    protected $_canCancelInvoice = true;

    /**
     * @var OrderPaymentExtensionInterfaceFactory|null
     */
    protected $paymentExtensionFactory = null;

    /**
     * @var InvoiceService
     */
    protected $_invoiceService;

    /**
     * @var OrderService
     */
    protected $_orderService;

    /**
     * @var Config
     */
    protected $_configModule;

    /**
     * @var TransactionFactory
     */
    protected $_transactionFactory;

    /**
     * @var Payments
     */
    protected $_api;

    /**
     * @var CheckoutApi
     */
    protected $_checkoutApi;

    /**
     * @var DdLog
     */
    protected $_ddlog;

    /**
     * @var salesData
     */
    protected $salesData;

    /**
     * @var invoiceSender
     */
    protected $invoiceSender;

    /**
     * @var localeResolver
     */
    protected $localeResolver;

    /**
     * @var _urlInterface
     */
    protected $_urlInterface;

    /**
     * @var imageHelper
     */
    protected $imageHelper;

    /**
     * @var orderHistory
     */
    protected $orderHistory;

    /**
     * @var buyerHistory
     */
    protected $buyerHistory;

    /**
     * @var customerRepository
     */
    protected $customerRepository;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ExtensionAttributesFactory $extensionFactory
     * @param AttributeValueFactory $customAttributeFactory
     * @param Data $paymentData
     * @param ScopeConfigInterface $scopeConfig
     * @param Logger $logger
     * @param OrderService $orderService
     * @param Config $config ,
     * @param TransactionFactory $transactionFactory
     * @param OrderPaymentExtensionInterfaceFactory $paymentExtensionFactory
     * @param InvoiceService $invoiceService
     * @param Payments $api
     * @param CheckoutApi $checkoutApi
     * @param DdLog $ddlog
     * @param SalesData $salesData
     * @param InvoiceSender $invoiceSender
     * @param LocaleResolver $localeResolver
     * @param UrlInterface $urlInterface
     * @param ImageHelper $imageHelper
     * @param OrderHistory $orderHistory
     * @param BuyerHistory $buyerHistory
     * @param CustomerRepository $customerRepository
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     * @param DirectoryHelper|null $directory
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        Data $paymentData,
        ScopeConfigInterface $scopeConfig,
        Logger $logger,
        OrderService $orderService,
        Config $config,
        TransactionFactory $transactionFactory,
        OrderPaymentExtensionInterfaceFactory $paymentExtensionFactory,
        InvoiceService $invoiceService,
        Payments $api,
        CheckoutApi $checkoutApi,
        DdLog $ddlog,
        SalesData $salesData,
        InvoiceSender $invoiceSender,
        LocaleResolver $localeResolver,
        UrlInterface $urlInterface,
        ImageHelper $imageHelper,
        OrderHistory $orderHistory,
        BuyerHistory $buyerHistory,
        CustomerRepository $customerRepository,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = [],
        DirectoryHelper $directory = null
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data,
            $directory
        );
        $this->_invoiceService = $invoiceService;
        $this->_orderService = $orderService;
        $this->_configModule = $config;
        $this->_transactionFactory = $transactionFactory;
        $this->paymentExtensionFactory = $paymentExtensionFactory;
        $this->_api = $api;
        $this->_checkoutApi = $checkoutApi;
        $this->_ddlog = $ddlog;
        $this->salesData = $salesData;
        $this->invoiceSender = $invoiceSender;
        $this->localeResolver = $localeResolver;
        $this->_urlInterface = $urlInterface;
        $this->imageHelper = $imageHelper;
        $this->orderHistory = $orderHistory;
        $this->buyerHistory = $buyerHistory;
        $this->customerRepository = $customerRepository;
    }

    /**
     * To check billing country is allowed for the payment method
     *
     * @param string $country
     * @return bool
     */
    public function canUseForCountry($country)
    {
        return parent::canUseForCountry($country) && in_array($country, explode(',', static::ALLOWED_COUNTRIES));
    }

    /**
     * Assign data to info model instance
     *
     * @param DataObject|mixed $data
     * @return $this
     * @throws LocalizedException
     */
    public function assignData(DataObject $data)
    {
        $additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);
        if (!is_object($additionalData)) {
            $additionalData = new DataObject($additionalData ?: []);
        }

        /** @var DataObject $info */
        $info = $this->getInfoInstance();
        $info->setAdditionalInformation(
            [
                self::PAYMENT_ID_FIELD => $additionalData->getCheckoutId()
            ]
        );

        return $this;
    }

    /**
     * Instantiate state and set it to state object
     *
     * @param string $paymentAction
     * @param DataObject $stateObject
     * @return void
     * @throws LocalizedException
     */
    public function initialize($paymentAction, $stateObject)
    {
        $payment = $this->getInfoInstance();

        $order = $payment->getOrder();
        $order->setCanSendNewEmailFlag(false);

        $stateObject->setState(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
        //$stateObject->setStatus(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
        $stateObject->setIsNotified(false);
    }

    /**
     * Authorize payment Tabby Checkout
     *
     * @param InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws LocalizedException
     * @throws NotAuthorizedException
     * @throws NotFoundException
     */
    public function authorize(InfoInterface $payment, $amount)
    {
        $id = $payment->getAdditionalInformation(self::PAYMENT_ID_FIELD);
        if ($this->getConfigData('local_currency')) {
            $payment->setAdditionalInformation(self::TABBY_CURRENCY_FIELD, 'order');
            $payment->save();
        }
        $result = $this->_api->getPayment($payment->getOrder()->getStoreId(), $id);
        $this->logger->debug(['authorize - result - ', (array)$result]);

        // check transaction details
        $order = $payment->getOrder();

        $logData = [
            "payment.id" => $id,
            "order.reference_id" => $order->getIncrementId()
        ];

        // check if payment authorized
        if (!$this->isAuthorized($result)) {
            $logData["payment.status"] = $result->status;
            $this->_ddlog->log("info", "payment is not authorized", null, $logData);
            throw new NotAuthorizedException(
                __("Payment not authorized for your transaction, please contact support.")
            );
        }

        if ($this->getIsInLocalCurrency()) {
            // currency must match when use local_currency setting
            if ($order->getOrderCurrencyCode() != $result->currency) {
                $logData = [
                    "payment.id" => $id,
                    "payment.currency" => $result->currency,
                    "order.currency" => $order->getOrderCurrencyCode()
                ];
                $this->_ddlog->log("error", "wrong currency code", null, $logData);
                throw new LocalizedException(
                    __("Something wrong with your transaction, please contact support.")
                );
            }
            if ($payment->formatAmount($order->getGrandTotal(), true) !=
                $payment->formatAmount($result->amount, true)) {
                $logData = [
                    "payment.id" => $id,
                    "payment.amount" => $result->amount,
                    "order.amount" => $order->getGrandTotal()
                ];
                $this->_ddlog->log("error", "wrong transaction amount", null, $logData);
                throw new LocalizedException(
                    __("Something wrong with your transaction, please contact support.")
                );
            }
            $payment->setBaseAmountAuthorized($order->getGrandTotal());
            $message = 'Authorized amount of %1.';
            $this->getPaymentExtensionAttributes($payment)
                ->setNotificationMessage(__(
                    $message,
                    $order->getOrderCurrency()->formatTxt($order->getGrandTotal())
                )->render());
        } else {
            if ($payment->formatAmount($amount, true) != $payment->formatAmount($result->amount, true)) {
                $logData = [
                    "payment.id" => $id,
                    "payment.amount" => $result->amount,
                    "order.amount" => $amount
                ];
                $this->_ddlog->log("error", "wrong transaction amount", null, $logData);
                throw new LocalizedException(
                    __("Something wrong with your transaction, please contact support.")
                );
            }
        }
        $logData = [
            "payment.id" => $id
        ];
        $this->_ddlog->log("info", "set transaction ID", null, $logData);
        $payment->setLastTransId($payment->getAdditionalInformation(self::PAYMENT_ID_FIELD));
        $payment->setTransactionId($payment->getAdditionalInformation(self::PAYMENT_ID_FIELD))
            ->setIsTransactionClosed(0);

        $payment->setBaseAmountAuthorized($amount);

        $this->logger->debug(['authorize', 'end']);

        $this->setAuthResponse($result);

        return $this;
    }

    /**
     * Creates invoice for autocapture feature. Used to create invoices on order authorization.
     *
     * @param InfoInterface $payment
     * @param StdClass $response
     * @throws Exception
     */
    protected function createInvoiceForAutoCapture(InfoInterface $payment, $response)
    {

        // create invoice for Tabby end autoCapture
        if ($response->status == 'CLOSED' && count($response->captures) > 0 && $payment->getOrder()->canInvoice()) {
            $txnId = $response->captures[0]->id;
            $invoice = $payment->getOrder()->prepareInvoice();
            $captureCase = \Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE;
            $invoice->setRequestedCaptureCase($captureCase);
            $invoice->setTransactionId($txnId);

            $invoice->pay();

            $invoice->register();

            $payment->setParentTransactionId($payment->getAdditionalInformation(self::PAYMENT_ID_FIELD));
            $payment->setTransactionId($txnId);
            $payment->setShouldCloseParentTransaction(true);

            $txn = $payment->AddTransaction(
                \Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE,
                $invoice,
                true
            );

            $formatedPrice = $invoice->getOrder()->getBaseCurrency()->formatTxt(
                $invoice->getOrder()->getGrandTotal()
            );

            $message = __('The Captured amount is %1.', $formatedPrice);
            $payment->addTransactionCommentsToOrder(
                $txn,
                $message
            );

            $transactionSave = $this->_transactionFactory
                ->create()
                ->addObject($invoice)
                ->addObject($payment)
                ->addObject($invoice->getOrder());

            $transactionSave->save();

            $this->sendInvoice($invoice);
        }
    }

    /**
     * Create invoice if no invoices found
     *
     * @param \Magento\Sales\Model\Order $order
     * @return false
     */
    protected function possiblyCreateInvoice(\Magento\Sales\Model\Order $order)
    {
        // create invoice for CaptureOn order
        try {
            if ($order->getState() == \Magento\Sales\Model\Order::STATE_PROCESSING && !$order->hasInvoices()) {
                if ($this->getConfigData(Config::CAPTURE_ON) == 'order') {
                    $this->createInvoice(
                        $order,
                        \Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE
                    );
                } else {
                    if ($this->getConfigData(Config::CREATE_PENDING_INVOICE)) {
                        $this->createInvoice($order);
                    }
                }
            }
        } catch (Exception $e) {
            $this->_ddlog->log("error", "could not possibly create invoice", $e);
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

                    $invoice = $this->_invoiceService->prepareInvoice($order);
                    if ($captureCase == \Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE) {
                        $this->_registry->register('current_invoice', $invoice);
                    }
                    $invoice->setRequestedCaptureCase($captureCase);
                    $invoice->register();
                    $invoice->getOrder()->setCustomerNoteNotify(false);
                    $invoice->getOrder()->setIsInProcess(true);
                    if ($captureCase == \Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE) {
                        $invoice->getOrder()->setStatus($this->getConfigData(Config::CAPTURED_STATUS));
                    }
                    $transactionSave = $this->_transactionFactory
                        ->create()
                        ->addObject($invoice)
                        ->addObject($order->getPayment())
                        ->addObject($invoice->getOrder());
                    $transactionSave->save();
                    if ($captureCase == \Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE) {
                        $this->_registry->unregister('current_invoice');
                    }

                    $this->sendInvoice($invoice);
                }

            }
        } catch (Exception $e) {
            $this->_ddlog->log("error", "could not create invoice", $e);
        }
    }

    /**
     * Is Tabby payment Authorized
     *
     * @param StdClass $response
     * @return bool
     */
    protected function isAuthorized($response)
    {
        $result = false;
        switch ($response->status) {
            case 'AUTHORIZED':
                $result = true;
                break;
            case 'CLOSED':
                $result = (count($response->captures) > 0 && ($response->captures[0]->amount == $response->amount));
                break;
        }
        return $result;
    }

    /**
     * Capture payment method
     *
     * @param InfoInterface $payment
     * @param float $amount
     * @return $this|Checkout
     * @throws LocalizedException
     * @throws NotFoundException
     */
    public function capture(InfoInterface $payment, $amount)
    {
        $auth = $payment->getAuthorizationTransaction();
        if (!$auth) {
            $logData = [
                "order.id" => $payment->getOrder()->getIncrementId(),
                "noauth" => true
            ];
            $this->_ddlog->log("error", "capture error, no authorization transaction available", null, $logData);
            throw new Exception(
                __("No information about authorization transaction.")
            );
        }
        $payment_id = $auth->getTxnId();

        // bypass payment capture
        if ($this->getConfigData(Config::CAPTURE_ON) == 'nocapture') {
            $logData = [
                "payment.id" => $payment_id,
                "nocapture" => true
            ];
            $this->_ddlog->log("info", "bypass payment capture", null, $logData);
            return $this;
        }

        $invoice = $this->_registry->registry('current_invoice');
        $data = [
            "amount" => $payment->formatAmount($this->getTabbyPrice($invoice, 'grand_total')),
            "tax_amount" => $payment->formatAmount($this->getTabbyPrice($invoice, 'tax_amount')),
            "shipping_amount" => $payment->formatAmount($this->getTabbyPrice($invoice, 'shipping_amount')),
            "created_at" => null
        ];

        $data['items'] = [];
        foreach ($invoice->getItems() as $item) {
            if (!$item->getOrderItem()->getParentItem()) {
                $data['items'][] = [
                    'title' => $item->getName() ?: '',
                    'description' => $item->getName() ?: '',
                    'quantity' => (int)$item->getQty(),
                    'unit_price' => $payment->formatAmount($this->getTabbyPrice($item, 'price_incl_tax')),
                    'reference_id' => $item->getProductId() . '|' . $item->getSku()
                ];
            };
        }

        $logData = [
            "payment.id" => $payment_id
        ];
        $this->_ddlog->log("info", "capture payment", null, $logData);

        $this->logger->debug(['capture', $payment_id, $data]);
        $result = $this->_api->capturePayment($payment->getOrder()->getStoreId(), $payment_id, $data);
        $this->logger->debug(['capture - result', (array)$result]);

        $txn = $this->getLatestItem($result->captures);
        if (!$txn) {
            $this->_ddlog->log("error", "capture error, check Tabby response", null, $logData);
            throw new Exception(
                __("Something wrong")
            );
        }

        $payment->setLastTransId($txn->id);
        $payment->setTransactionId($txn->id)
            ->setParentTransactionId($payment_id)
            ->setIsTransactionClosed(0);

        if ($this->getIsInLocalCurrency()) {
            $message = 'Captured amount of %1 online.';
            $this->getPaymentExtensionAttributes($payment)
                ->setNotificationMessage(__(
                    $message,
                    $payment->getOrder()->getOrderCurrency()->formatTxt($this->getTabbyPrice($invoice, 'grand_total'))
                )->render());
        }

        return $this;
    }

    /**
     * Get latest item from array based on created_at property
     *
     * @param array $items
     * @return mixed
     */
    protected function getLatestItem($items)
    {
        $item = array_pop($items);
        foreach ($items as $temp) {
            if ($temp->created_at > $item->created_at) {
                $item = $temp;
            }
        }
        return $item;
    }

    /**
     * Check if order in local currency
     *
     * @return bool
     * @throws LocalizedException
     */
    protected function getIsInLocalCurrency()
    {
        return ($this->getInfoInstance()->getAdditionalInformation(self::TABBY_CURRENCY_FIELD) == 'order');
    }

    /**
     * Returns tabby price based on price type and currency used in order
     *
     * @param Magento\Sales\Model\AbstractModel $object
     * @param string $field
     * @return mixed
     * @throws LocalizedException
     */
    public function getTabbyPrice($object, $field)
    {
        return $this->getInfoInstance()->formatAmount(
            $this->getIsInLocalCurrency()
            ? $object->getData($field)
            : $object->getData('base_' . $field)
        );
    }

    /**
     * Payment refund method
     *
     * @param InfoInterface $payment
     * @param float $amount
     * @return $this|Checkout
     * @throws LocalizedException
     * @throws NotFoundException
     */
    public function refund(InfoInterface $payment, $amount)
    {
        $creditmemo = $this->_registry->registry('current_creditmemo');
        $invoice = $creditmemo->getInvoice();
        $capture_txn = $payment->getAuthorizationTransaction();

        $payment_id = $capture_txn->getParentTxnId();

        $data = [
            "capture_id" => $invoice->getTransactionId(),
            "amount" => $payment->formatAmount($this->getTabbyPrice($creditmemo, 'grand_total'))
        ];

        $data['items'] = [];
        foreach ($creditmemo->getItems() as $item) {
            $data['items'][] = [
                'title' => $item->getName() ?: '',
                'description' => $item->getName() ?: '',
                'quantity' => (int)$item->getQty(),
                'unit_price' => $payment->formatAmount($this->getTabbyPrice($creditmemo, 'price_incl_tax')),
                'reference_id' => $item->getProductId() . '|' . $item->getSku()
            ];
        }

        $logData = [
            "payment.id" => $payment_id
        ];
        $this->_ddlog->log("info", "refund payment", null, $logData);

        $this->logger->debug(['refund', $payment_id, $data]);
        $result = $this->_api->refundPayment($payment->getOrder()->getStoreId(), $payment_id, $data);
        $this->logger->debug(['refund - result', (array)$result]);

        $txn = $this->getLatestItem($result->refunds);
        if (!$txn) {
            $this->_ddlog->log("error", "refund error, check Tabby response", null, $logData);
            throw new Exception(
                __("Something wrong")
            );
        }

        if ($this->getIsInLocalCurrency()) {
            $message = 'We refunded %1 online.';
            $msg = __($message, $payment->getOrder()->getOrderCurrency()->formatTxt(
                $this->getTabbyPrice($creditmemo, 'grand_total')
            ));
            $this->getPaymentExtensionAttributes($payment)
                ->setNotificationMessage($msg->render());
        }

        $payment->setLastTransId($txn->id);
        $payment->setTransactionId($txn->id)
            ->setIsTransactionClosed(0);

        return $this;
    }

    /**
     * Void payment method
     *
     * @param DataObject|InfoInterface $payment
     * @return $this
     * @throws LocalizedException
     * @api
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function void(InfoInterface $payment)
    {
        $this->logger->debug(['void - txn_id', $payment->getParentTransactionId()]);

        $logData = [
            "payment.id" => $payment->getParentTransactionId()
        ];
        $this->_ddlog->log("info", "void payment", null, $logData);
        $result = $this->_api->closePayment($payment->getOrder()->getStoreId(), $payment->getParentTransactionId());

        $this->logger->debug(['void - result', (array)$result]);

        return $this;
    }

    /**
     * Payment cancel method
     *
     * @param InfoInterface $payment
     * @return $this|Checkout
     * @throws LocalizedException
     */
    public function cancel(InfoInterface $payment)
    {
        return $this->void($payment);
    }

    /**
     * Fetch transaction info
     *
     * @param InfoInterface $payment
     * @param string $transactionId
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @api
     */
    public function fetchTransactionInfo(InfoInterface $payment, $transactionId)
    {

        $transactionId = preg_replace("/-void$/is", "", $transactionId);

        $txn = $payment->getAuthorizationTransaction();
        $this->logger->debug([$transactionId]);
        $response = $this->_api->getPayment($payment->getOrder()->getStoreId(), $txn->getTxnId());

        $result = [];
        if ($txn->getTxnId() == $transactionId) {
            foreach ($response as $key => $value) {
                if ($key == 'order_history') {
                    continue;
                }
                if (!is_scalar($value)) {
                    $value = json_encode($value);
                }
                $result[$key] = $value;
            }
        } else {
            // search transaction in captures and refunds
            foreach ($response->captures as $capture) {
                if ($capture->id != $transactionId) {
                    continue;
                }
                foreach ($capture as $key => $value) {
                    if (!is_scalar($value)) {
                        $value = json_encode($value);
                    }
                    $result[$key] = $value;
                }
            }
            foreach ($response->refunds as $refund) {
                if ($refund->id != $transactionId) {
                    continue;
                }
                foreach ($refund as $key => $value) {
                    if (!is_scalar($value)) {
                        $value = json_encode($value);
                    }
                    $result[$key] = $value;
                }
            }
        }

        return $result;
    }

    /**
     * Retrieve information from payment configuration
     *
     * @param string $field
     * @param int|string|null|\Magento\Store\Model\Store $storeId
     *
     * @return mixed
     * @throws LocalizedException
     */
    public function getConfigData($field, $storeId = null)
    {
        // bypass initial authorize
        //if ($field == 'payment_action') return null;

        if ('order_place_redirect_url' === $field) {
            return $this->getOrderPlaceRedirectUrl();
        }
        if (null === $storeId) {
            $storeId = $this->getStore();
        }

        if (in_array($field, ['active', 'title', 'sort_order'])) {
            $path = 'payment/' . $this->getCode() . '/' . $field;
        } else {
            $path = 'tabby/tabby_api/' . $field;
        }
        return $this->_scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Retrieve payment method title
     *
     * @return string
     * @throws LocalizedException
     */
    public function getTitle()
    {
        return __($this->getConfigData('title'));
    }

    /**
     * Checks payment method is available for checkout
     *
     * @param CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(CartInterface $quote = null)
    {
        return $this->isNotInPromotionOnlyMode()
            && parent::isAvailable($quote)
            && $this->_configModule->isTabbyActiveForCart($quote)
            && !$this->isDisabled();
    }

    /**
     * Checks module in only promotions mode
     *
     * @return bool
     */
    protected function isNotInPromotionOnlyMode()
    {
        return ($this->getConfigData('plugin_mode') == '0');
    }

    /**
     * Checks payment method is disabled for future use
     *
     * @return bool
     */
    protected function isDisabled()
    {
        return in_array($this->_code, ['tabby_checkout', 'tabby_cc_installments']);
    }

    /**
     * Assign payment ID to order and update reference id on tabby
     *
     * @param InfoInterface $payment
     * @param string $paymentId
     * @return bool
     * @throws LocalizedException
     * @throws NotFoundException
     */
    public function registerPayment(InfoInterface $payment, $paymentId)
    {
        $payment->setAdditionalInformation(self::PAYMENT_ID_FIELD, $paymentId);
        $payment->save();

        $this->_api->updateReferenceId(
            $payment->getOrder()->getStoreId(),
            $paymentId,
            $payment->getOrder()->getIncrementId()
        );

        return true;
    }

    /**
     * Process payment Authorization logic
     *
     * @param InfoInterface $payment
     * @param string $paymentId
     * @param string $source
     * @return bool
     * @throws LocalizedException
     */
    public function authorizePayment(InfoInterface $payment, $paymentId, $source = 'checkout')
    {

        $order = $payment->getOrder();

        if ($order->getId() && in_array($order->getState(), [
            \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT,
            \Magento\Sales\Model\Order::STATE_NEW
        ])) {

            if (!$payment->getAuthorizationTransaction()) {

                $this->setStore($order->getStoreId());

                $payment->setAdditionalInformation(['checkout_id' => $paymentId]);

                $this->_ddlog->log('info', 'authorize payment from ' . $source, null, [
                    'payment.id' => $paymentId,
                    "order.reference_id" => $order->getIncrementId()
                ]);

                $payment->authorize(true, $order->getBaseGrandTotal());

                $transaction = $payment->addTransaction(
                    \Magento\Sales\Model\Order\Payment\Transaction::TYPE_AUTH,
                    $order,
                    true
                );

                if ($this->getAuthResponse()->status == 'CLOSED') {
                    $transaction->setIsClosed(true);
                }

                $this->createInvoiceForAutoCapture($payment, $this->getAuthResponse());

                $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING);
                $order->setStatus($this->getConfigData(Config::AUTHORIZED_STATUS));

                $transactionSave = $this->_transactionFactory
                    ->create()
                    ->addObject($order)
                    ->addObject($payment)
                    ->addObject($transaction);

                $transactionSave->save();

                $this->possiblyCreateInvoice($order);

                if ($this->getConfigData(Config::MARK_COMPLETE) == 1) {
                    $order->setState(\Magento\Sales\Model\Order::STATE_COMPLETE);
                    $order->setStatus($order->getConfig()->getStateDefaultStatus(
                        \Magento\Sales\Model\Order::STATE_COMPLETE
                    ));
                    $order->addStatusHistoryComment(
                        "Autocomplete by Tabby",
                        $order->getConfig()->getStateDefaultStatus(\Magento\Sales\Model\Order::STATE_COMPLETE)
                    );

                    $order->save();
                }

                $this->_orderService->notify($order->getId());

                return true;
            } else {
                $this->_ddlog->log('info', 'order not have auth transaction assigned', null, [
                    'payment.id' => $paymentId,
                    "order.reference_id" => $order->getIncrementId()
                ]);
            }
        } else {
            $this->_ddlog->log('info', 'order state is not valid for auth', null, [
                'payment.id' => $paymentId,
                "order.reference_id" => $order->getIncrementId(),
                "order.state" => $order->getState()
            ]);
        };
        return false;
    }

    /**
     * Returns payment extension attributes instance.
     *
     * @param OrderPaymentInterface $payment
     * @return OrderPaymentExtensionInterface
     */
    private function getPaymentExtensionAttributes(OrderPaymentInterface $payment)
    {
        $extensionAttributes = $payment->getExtensionAttributes();
        if ($extensionAttributes === null) {
            $extensionAttributes = $this->paymentExtensionFactory->create();
            $payment->setExtensionAttributes($extensionAttributes);
        }

        return $extensionAttributes;
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
                $this->_ddlog->log("info", "sending invoice email");
                $this->invoiceSender->send($invoice);
            }
        } catch (\Exception $e) {
            $this->_ddlog->log("error", "could not send invoice email", $e);
        }
    }

    /**
     * Create Tabby session and return redirect url for order.
     *
     * @return string
     */
    public function getOrderRedirectUrl()
    {
        $data = [
            "lang"          => strstr($this->localeResolver->getLocale(), '_', true) == 'en' ? 'en' : 'ar',
            "merchant_code" => $this->getInfoInstance()->getOrder()->getStore()->getGroup()->getCode() .
                ($this->getConfigData('local_currency')
                    ? '_' . $this->getInfoInstance()->getOrder()->getOrderCurrencyCode()
                    : ''
                ),
            "merchant_urls" => $this->getMerchantUrls(),
            "payment"       => $this->getSessionPaymentObject($this->getInfoInstance()->getOrder())
        ];
        // cancel order on any errors
        $redirectUrl = $this->_urlInterface->getUrl('tabby/result/failure');

        try {
            $result = $this->_checkoutApi->createSession($this->getInfoInstance()->getOrder()->getStoreId(), $data);

            if ($result && property_exists($result, 'status') && $result->status == 'created') {
                if (property_exists($result->configuration->available_products, $this->_codeTabby)) {
                    // register new payment id for order
                    $this->getInfoInstance()->setAdditionalInformation([
                        self::PAYMENT_ID_FIELD => $result->payment->id
                    ]);
                    $this->getInfoInstance()->save();
/*
                    $payment = $this->getInfoInstance();
                    $payment->setTransactionId($result->payment->id);

                    $transaction = $payment->addTransaction(
                        \Magento\Sales\Model\Order\Payment\Transaction::TYPE_AUTH,
                        $this->getOrder(),
                        true
                    );

                    $transactionSave = $this->_transactionFactory
                        ->create()
                        ->addObject($payment)
                        ->addObject($transaction);

                    $transactionSave->save();
*/

                    $redirectUrl = $result->configuration->available_products->{$this->_codeTabby}[0]->web_url;
                } else {
                    throw new LocalizedException(__("Selected payment method not available."));
                }
            } else {
                throw new LocalizedException(__("Response not have status field or payment rejected"));
            }
        } catch (\Exception $e) {
            $this->_ddlog->log("error", "createSession exception", $e, $data);
            // be silent, no exception require here. just redirect to checkout again
            //throw new LocalizedException(__("Something went wrong. Please try again later or contact support."));
        }

        return $redirectUrl;
    }

    /**
     * Returns Merchant transaction result urls.
     *
     * @return array
     */
    protected function getMerchantUrls()
    {
        return [
            "success"   => $this->_urlInterface->getUrl('tabby/result/success'),
            "cancel"    => $this->_urlInterface->getUrl('tabby/result/cancel'),
            "failure"   => $this->_urlInterface->getUrl('tabby/result/failure')
        ];
    }

    /**
     * Creates payment object for given order.
     *
     * @param \Magento\Sales\Model\Order $order
     * @return array
     */
    protected function getSessionPaymentObject($order)
    {
        if ($this->getConfigData('local_currency')) {
            $payment = $order->getPayment();
            $payment->setAdditionalInformation(self::TABBY_CURRENCY_FIELD, 'order');
            $payment->save();
        }
        $address = $order->getShippingAddress() ?: $order->getBillingAddress();
        $customer = $order->getCustomer();
        if (!$order->getCustomerIsGuest()) {
            $customer = $this->customerRepository->getById($order->getCustomerId());
        }

        $orderHistory = $this->orderHistory->getOrderHistoryObject(
            $customer,
            $order->getCustomerEmail(),
            $address ? $address->getTelephone() : null
        );
        return [
            "amount"    => $this->getTabbyPrice($order, 'grand_total'),
            "currency"  => $this->getIsInLocalCurrency()
                ? $order->getOrderCurrencyCode()
                : $order->getBaseCurrencyCode(),
            "buyer"     => [
                "phone"     => $address ? $address->getTelephone() : '',
                "email"     => $order->getCustomerEmail(),
                "name"      => $order->getCustomerName()
            ],
            "shipping_address" => [
                "city"      => $address ? $address->getCity() : '',
                "address"   => $address ? implode(PHP_EOL, $address->getStreet()) : '',
                "zip"       => $address ? $address->getPostcode() : ''
            ],
            "order"     => [
                "tax_amount"        => $this->getTabbyPrice($order, 'tax_amount'),
                "shipping_amount"   => $this->getTabbyPrice($order, 'shipping_amount'),
                "discount_amount"   => $this->getTabbyPrice($order, 'discount_amount'),
                "reference_id"      => $order->getIncrementId(),
                "items"             => $this->getSessionOrderItems($order)
            ],
            "buyer_history"     => $this->buyerHistory->getBuyerHistoryObject($customer, $orderHistory),
            "order_history"     => $this->orderHistory->limitOrderHistoryObject($orderHistory)
        ];
    }

    /**
     * Creates order items array for given order.
     *
     * @param \Magento\Sales\Model\Order $order
     * @return array
     */
    protected function getSessionOrderItems($order)
    {
        $items = [];
        foreach ($order->getAllVisibleItems() as $item) {
            $items[] = [
                'title'         => $item->getName(),
                'description'   => $item->getDescription(),
                'quantity'      => $item->getQtyOrdered() * 1,
                'unit_price'    => $this->getInfoInstance()->formatAmount(
                    $this->getTabbyPrice($item, 'price') - $this->getTabbyPrice($item, 'discount_amount')
                        + $this->getTabbyPrice($item, 'tax_amount')
                ),
                'tax_amount'    => $this->getTabbyPrice($item, 'tax_amount'),
                'reference_id'  => $item->getSku(),
                'image_url'     => $this->getSessionItemImageUrl($item),
                'product_url'   => $item->getProduct()->getUrlInStore(),
                'category'      => $this->getSessionCategoryName($item)
            ];
        }
        return $items;
    }

    /**
     * Generates order item image url.
     *
     * @param \Magento\Sales\Model\Order\Item $item
     * @return string
     */
    protected function getSessionItemImageUrl($item)
    {
        $image = $this->imageHelper->init($item->getProduct(), 'product_page_image_large');

        return $image->getUrl();
    }

    /**
     * Generates order item category name.
     *
     * @param \Magento\Sales\Model\Order\Item $item
     * @return string
     */
    protected function getSessionCategoryName($item)
    {
        $category_name = '';
        if ($collection = $item->getProduct()->getCategoryCollection()->addNameToResult()) {
            if ($collection->getSize()) {
                $category_name = $collection->getFirstItem()->getName();
            }
        }
        return $category_name;
    }

    /**
     * Updates order tracking information.
     *
     * @param array|null $tracks
     * @return string
     */
    public function updateOrderTracking($tracks = null)
    {

        $order = $this->getInfoInstance()->getOrder();
        $order->load($order->getId());

        $data = [
            "order" => [
                "reference_id"  => $order->getIncrementId()
            ],
            "delivery_tracking" => []
        ];
        if (!$tracks) {
            $tracks = [];
            foreach ($order->getShipmentsCollection()->load() as $shipment) {
                foreach ($shipment->getTracksCollection() as $track) {
                    $tracks[] = $track;
                }
            }
        }
        foreach ($tracks as $track) {
            $data["delivery_tracking"][] = [
                "tracking_number"   => $track->getTrackNumber(),
                "courier_code"      => $track->getCarrierCode()
            ];
        }
        try {
            $this->_ddlog->log("info", "Updating tracking information", null, $data);
            $this->_api->updatePayment(
                $order->getStoreId(),
                $this->getInfoInstance()->getAdditionalInformation(self::PAYMENT_ID_FIELD),
                $data
            );
        } catch (\Exception $e) {
            $this->_ddlog->log("error", "Error updating tracking information", $e, $data);
        }
    }
}
