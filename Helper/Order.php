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
        \Magento\Checkout\Model\Session $session,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\DB\TransactionFactory $transactionFactory,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\CatalogInventory\Api\StockManagementInterface $stockManagement,
        \Magento\CatalogInventory\Model\Indexer\Stock\Processor $stockIndexerProcessor,
        \Magento\Catalog\Model\Indexer\Product\Price\Processor $priceIndexer,
        \Magento\CatalogInventory\Observer\ProductQty $productQty,
        \Magento\Framework\App\ProductMetadataInterface $productMetadata,
        \Magento\Framework\Registry $registry
    ) {
        $this->_invoiceService = $invoiceService;
        $this->_session = $session;
        $this->_messageManager = $messageManager;
        $this->_transactionFactory = $transactionFactory;
        $this->_orderRepository = $orderRepository;
        $this->_stockManagement = $stockManagement;
        $this->_stockIndexerProcessor = $stockIndexerProcessor;
        $this->_priceIndexer = $priceIndexer;
        $this->_productQty = $productQty;
        $this->_productMetadata = $productMetadata;
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

    public function cancelCurrentOrder($comment = '') {

        $order = $this->_session->getLastRealOrder();
        if(!empty($comment)) {
            $comment = 'Tabby Checkout :: ' . $comment;
        }
        if ($order->getId() && $order->getState() != \Magento\Sales\Model\Order::STATE_CANCELED) {
            $order->registerCancellation($comment)->save();
            return true;
        }
        return false;
    }

    public function registerPaymentForOrder($paymentId) {
        try {
            if ($order = $this->_session->getLastRealOrder()) {

                if ($order->getId() && $order->getState() == \Magento\Sales\Model\Order::STATE_NEW) {
                    $payment = $order->getPayment();
                    
                    if (!$payment->getAuthorizationTransaction()) {
                        $payment->setAdditionalInformation(['checkout_id' => $paymentId]);

                        $payment->authorize(true, $order->getBaseGrandTotal());

                        $transaction = $payment->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_AUTH, $order, true);

                        $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING);
                        $order->save();
                    }
                };
            }
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->_messageManager->addError($e->getMessage());
        }
        return true;
    }

    public function restoreQuote()
    {
        $result = $this->_session->restoreQuote();

        // Versions 2.2.4 onwards need an explicit action to return items.
        if ($result && $this->isReturnItemsToInventoryRequired()) {
            $this->returnItemsToInventory();
        }

        return $result;
    }

    /**
     * Checks if version requires restore quote fix.
     *
     * @return bool
     */
    private function isReturnItemsToInventoryRequired()
    {
        $version = $this->getMagentoVersion();
        return version_compare($version, "2.2.4", ">=");
    }

    /**
     * Gets the Magento version.
     *
     * @return string
     */
    public function getMagentoVersion() {
        return $this->_productMetadata->getVersion();
    }

    /**
     * Returns items to inventory.
     *
     */
    private function returnItemsToInventory()
    {
        // Code from \Magento\CatalogInventory\Observer\RevertQuoteInventoryObserver
        $quote = $this->_session->getQuote();
        $items = $this->_productQty->getProductQty($quote->getAllItems());
        $revertedItems = $this->_stockManagement->revertProductsSale($items, $quote->getStore()->getWebsiteId());

        // If the Magento 2 server has multi source inventory enabled, 
        // the revertProductsSale method is intercepted with new logic that returns a boolean.
        // In such case, no further action is necessary.
        if (gettype($revertedItems) === "boolean") {
            return;
        }

        $productIds = array_keys($revertedItems);
        if (!empty($productIds)) {
            $this->_stockIndexerProcessor->reindexList($productIds);
            $this->_priceIndexer->reindexList($productIds);
        }
        // Clear flag, so if order placement retried again with success - it will be processed
        $quote->setInventoryProcessed(false);
    }
}
