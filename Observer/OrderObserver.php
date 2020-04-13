<?php
namespace Tabby\Checkout\Observer;

use Tabby\Checkout\Gateway\Config\Config;
use Magento\Framework\Event\ObserverInterface;

class OrderObserver implements ObserverInterface
{

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\Invoice\CollectionFactory
     */
    protected $_invoiceCollectionFactory;

    /**
     * @var \Magento\Sales\Api\InvoiceRepositoryInterface
     */
    protected $_invoiceRepository;

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
    * @param \Magento\Sales\Model\ResourceModel\Order\Invoice\CollectionFactory $invoiceCollectionFactory
    * @param \Magento\Sales\Model\Service\InvoiceService $invoiceService
    * @param \Magento\Framework\DB\TransactionFactory $transactionFactory
    * @param \Magento\Sales\Api\InvoiceRepositoryInterface $invoiceRepository
    * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
    */
    public function __construct(
		Config $config,
        \Magento\Sales\Model\ResourceModel\Order\Invoice\CollectionFactory $invoiceCollectionFactory,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\DB\TransactionFactory $transactionFactory,
        \Magento\Sales\Api\InvoiceRepositoryInterface $invoiceRepository,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
        ) {
		  $this->_config = $config;
          $this->_invoiceCollectionFactory = $invoiceCollectionFactory;
          $this->_invoiceService = $invoiceService;
          $this->_transactionFactory = $transactionFactory;
          $this->_invoiceRepository = $invoiceRepository;
          $this->_orderRepository = $orderRepository;
    }




    public function execute(\Magento\Framework\Event\Observer $observer)
    {   
		if ($this->_config->getValue(Config::CREATE_PENDING_INVOICE)) {
        	$orderId = $observer->getEvent()->getOrder()->getId();
        	$this->createInvoice($orderId);      
		}
    }

    protected function createInvoice($orderId)
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
                $invoices = $this->_invoiceCollectionFactory->create()
                  ->addAttributeToFilter('order_id', array('eq' => $order->getId()));

                $invoices->getSelect()->limit(1);

                if ($invoices->count() == 0) {

                	$invoice = $this->_invoiceService->prepareInvoice($order);
                	$invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::NOT_CAPTURE);
                	$invoice->register();
                	$invoice->getOrder()->setCustomerNoteNotify(false);
                	$invoice->getOrder()->setIsInProcess(true);
                	//$order->addStatusHistoryComment(__('Automatically INVOICED'), false);
                	$transactionSave = $this->_transactionFactory
						->create()
						->addObject($invoice)
						->addObject($invoice->getOrder());
                	$transactionSave->save();
                }

            }
        } catch (\Exception $e) {
/*
            throw new \Magento\Framework\Exception\LocalizedException(
                __($e->getMessage())
            );
*/
        }
    }
}
