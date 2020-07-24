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
        \Tabby\Checkout\Gateway\Config\Config $config,
        \Magento\Quote\Model\QuoteIdMaskFactory $quoteIdMaskFactory,
        \Magento\Quote\Api\CartRepositoryInterface $cartRepository,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
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
        $this->_config = $config;
        $this->_quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->_cartRepository = $cartRepository;
        $this->_searchCriteriaBuilder = $searchCriteriaBuilder;
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

    public function cancelCurrentOrder($cartId, $comment = 'Customer cancel payment') {

        if ($order = $this->getOrderByMaskedCartId($cartId)) {
            return $this->cancelOrder($order, $comment);
        };

        return false;
    }
    public function cancelCurrentCustomerOrder($cartId, $customerId, $comment = 'Customer cancel payment') {
        if ($order = $this->getOrderByCartId($cartId, $customerId)) {
            return $this->cancelOrder($order, $comment);
        };

        return false;
    }
    public function getOrderByCartId($cartId, $customerId) {
        $quote = $this->_cartRepository->get($cartId);

        if ($quote->getCustomerId() == $customerId) {
            $increment_id = $quote->getReservedOrderId();
            $searchCriteria = $this->_searchCriteriaBuilder
                ->addFilter('increment_id', $increment_id, 'eq')
                ->create();
            $orders = $this->_orderRepository->getList($searchCriteria);

            foreach ($orders as $order) {
                return $order;
            }
        }

        return null;
    }
    public function getOrderByMaskedCartId($cartId) {
        // load QuoteIdMask
        $quoteIdMask = $this->_quoteIdMaskFactory->create()->load($cartId, 'masked_id');
        // load Quote
        $quote = $this->_cartRepository->get($quoteIdMask->getQuoteId());
        $increment_id = $quote->getReservedOrderId();
        $searchCriteria = $this->_searchCriteriaBuilder
            ->addFilter('increment_id', $increment_id, 'eq')
            ->create();
        $orders = $this->_orderRepository->getList($searchCriteria);

        foreach ($orders as $order) {
            return $order;
        }

        return null;
    }

    public function expireOrder($order) {
        if ($paymentId = $order->getPayment()->getAdditionalInformation(\Tabby\Checkout\Model\Method\Checkout::PAYMENT_ID_FIELD)) {
            $payment = $order->getPayment();
            try {
                $payment->getMethodInstance()->authorizePayment($payment, $paymentId);
            } catch (\Tabby\Checkout\Exception\NotAuthorizedException $e) {
                // if payment not authorized just cancel order
                $this->cancelOrder($order, __("Order expired, transaction not authorized."));
            } catch (\Tabby\Checkout\Exception\NotFoundException $e) {
                // if payment not found just cancel order
                $this->cancelOrder($order, __("Order expired, transaction not found."));
            } catch (\Exception $e) {
            }
        } else {
            // if no payment id provided
            $this->cancelOrder($order, __("Order expired, no transaction available."));
        };
    }
    public function cancelOrder($order, $comment) {
        if(!empty($comment)) {
            $comment = 'Tabby Checkout :: ' . $comment;
        }
        if ($order->getId() && $order->getState() != \Magento\Sales\Model\Order::STATE_CANCELED) {
            $order->registerCancellation($comment)->save();
            // delete order if needed
            if ($this->_config->getValue('order_action_failed_payment') == 'delete') {
                $this->_registry->register('isSecureArea', true);
                $this->_orderRepository->delete($order);
                $this->_registry->unregister('isSecureArea');
            }

            return true;
        }
        return false;
    }

    public function registerPayment($cartId, $paymentId) {
        if ($order = $this->getOrderByMaskedCartId($cartId)) {
            return $order->getPayment()->getMethodInstance()->registerPayment($order->getPayment(), $paymentId);
        }
        return false;
    }
    public function registerCustomerPayment($cartId, $paymentId, $customerId) {
        if ($order = $this->getOrderByCartId($cartId, $customerId)) {
            return $order->getPayment()->getMethodInstance()->registerPayment($order->getPayment(), $paymentId);
        }
        return false;
    }
    public function authorizePayment($cartId, $paymentId) {
        $result = true;
        try {
            if ($order = $this->getOrderByMaskedCartId($cartId)) {

                $result = $order->getPayment()->getMethodInstance()->authorizePayment($order->getPayment(), $paymentId);

                $this->possiblyCreateInvoice($order);
            }
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->_messageManager->addError($e->getMessage());
            return false;
        }
        return $result;
    }
    public function authorizeCustomerPayment($cartId, $paymentId, $customerId) {
        $result = true;
        try {
            if ($order = $this->getOrderByCartId($cartId, $customerId)) {

                $result = $order->getPayment()->getMethodInstance()->authorizePayment($order->getPayment(), $paymentId);

                $this->possiblyCreateInvoice($order);
            }
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->_messageManager->addError($e->getMessage());
            return false;
        }
        return $result;
    }
    public function possiblyCreateInvoice($order) {
        if ($order->getState() == \Magento\Sales\Model\Order::STATE_PROCESSING && !$order->hasInvoices()) {
            if ($this->_config->getValue(\Tabby\Checkout\Gateway\Config\Config::CAPTURE_ON) == 'order') {
                $this->createInvoice(
                    $order->getId(),
                    \Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE
                );
            } else {
                if ($this->_config->getValue(\Tabby\Checkout\Gateway\Config\Config::CREATE_PENDING_INVOICE)) {
                    $this->createInvoice($order->getId());
                }
            }
        }
    }

    public function restoreQuote()
    {
        $result = $this->_session->restoreQuote();

        return $result;
    }
}
