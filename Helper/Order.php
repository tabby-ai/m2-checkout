<?php
namespace Tabby\Checkout\Helper;

use Tabby\Checkout\Gateway\Config\Config;
use Magento\Framework\Event\ObserverInterface;

class Order extends \Magento\Framework\App\Helper\AbstractHelper
{

    /**
    * @var \Magento\Sales\Model\Service\InvoiceService
    */
    protected $_invoiceService;

    /**
     * @var \Magento\Framework\DB\TransactionFactory
     */
    protected $_transactionFactory;

    /**
    * @var \Magento\Sales\Api\OrderRepositoryInterface
    */
    protected $_orderRepository;

    /**
    * @var \Magento\Framework\Registry
    */
    protected $_registry;

    /**
    * @param \Magento\Framework\App\Helper\Context $context
    * @param \Magento\Sales\Model\Service\InvoiceService $invoiceService
    * @param \Magento\Framework\DB\TransactionFactory $transactionFactory
    * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
    */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\DB\TransactionFactory $transactionFactory,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Framework\Registry $registry
    ) {
        $this->_invoiceService = $invoiceService;
        $this->_transactionFactory = $transactionFactory;
        $this->_orderRepository = $orderRepository;
        $this->_registry = $registry;
        parent::__construct($context);
    }

    public function createInvoice($orderId, $captureCase = \Magento\Sales\Model\Order\Invoice::NOT_CAPTURE)
    {
        try 
        {
            $order = $this->_orderRepository->get($orderId);
			// check order and order payment method code
            if (
				   $order 
				&& $order->canInvoice()
				&& $order->getPayment() 
				&& $order->getPayment()->getMethodInstance() 
				&& preg_match("/^tabby_/is", $order->getPayment()->getMethodInstance()->getCode())
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
                	$transactionSave = $this->_transactionFactory
						->create()
						->addObject($invoice)
						->addObject($invoice->getOrder());
                	$transactionSave->save();
                }

            }
        } catch (\Exception $e) {
        }
    }
    public function register($name, $value) {
        $this->_registry->register($name, $value);
    }
}
