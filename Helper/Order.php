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
        \Magento\Sales\Model\Service\OrderService $orderService,
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
        $this->_orderService = $orderService;
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
            $this->ddlog("error", "could not create invoice", $e);
        }
    }

    public function register($name, $value) {
        $this->_registry->register($name, $value);
    }

    public function cancelCurrentOrder($cartId, $comment = 'Customer canceled payment') {
        try {
            if ($order = $this->getOrderByMaskedCartId($cartId)) {
                return $this->cancelOrder($order, $comment);
            };
        } catch (\Exception $e) {
            $this->_messageManager->addError($e->getMessage());
            $this->ddlog("error", "could not cancel current order", $e);
            return false;
        }
        return false;
    }

    public function cancelCurrentCustomerOrder($cartId, $customerId, $comment = 'Customer canceled payment') {
        try {
            if ($order = $this->getOrderByCartId($cartId, $customerId)) {
                return $this->cancelOrder($order, $comment);
            };
        } catch (\Exception $e) {
            $this->_messageManager->addError($e->getMessage());
            $this->ddlog("error", "could not cancel current customer order", $e);
            return false;
        }
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
        try {
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

                    $data = array("payment.id" => $paymentId);
                    $this->ddlog("error", "could not expire order", $e, $data);
                }
            } else {
                // if no payment id provided
                $this->cancelOrder($order, __("Order expired, no transaction available."));
            };
        } catch (\Exception $e) {
            $this->_messageManager->addError($e->getMessage());
            $this->ddlog("error", "could not expire order", $e);
            return false;
        }
    }

    public function cancelOrder($order, $comment) {
        if(!empty($comment)) {
            $comment = 'Tabby Checkout :: ' . $comment;
        }
        if ($order->getId() && $order->getState() != \Magento\Sales\Model\Order::STATE_CANCELED) {
            $order->registerCancellation($comment)->save();

            // restore Quote when cancel order
            $this->restoreQuote();

            // delete order if needed
            if ($this->_registry->registry('isSecureArea')) {
                $this->_orderRepository->delete($order);
            } else {
                $this->_registry->register('isSecureArea', true);
                $this->_orderRepository->delete($order);
                $this->_registry->unregister('isSecureArea');
            }

            return true;
        }
        return false;
    }

    public function registerPayment($cartId, $paymentId) {
        try {
            if ($order = $this->getOrderByMaskedCartId($cartId)) {
                return $order->getPayment()->getMethodInstance()->registerPayment($order->getPayment(), $paymentId);
            }
        } catch (\Exception $e) {
            $this->_messageManager->addError($e->getMessage());

            $data = array("payment.id" => $paymentId);
            $this->ddlog("error", "could not register payment", $e, $data);
            return false;
        }
    }

    public function registerCustomerPayment($cartId, $paymentId, $customerId) {
        try {
            if ($order = $this->getOrderByCartId($cartId, $customerId)) {
                return $order->getPayment()->getMethodInstance()->registerPayment($order->getPayment(), $paymentId);
            }
        } catch (\Exception $e) {
            $this->_messageManager->addError($e->getMessage());

            $data = array("payment.id" => $paymentId);
            $this->ddlog("error", "could not register customer payment", $e, $data);
            return false;
        }
    }

    public function authorizePayment($cartId, $paymentId) {
        $result = true;
        try {
            if ($order = $this->getOrderByMaskedCartId($cartId)) {
                $result = $order->getPayment()->getMethodInstance()->authorizePayment($order->getPayment(), $paymentId);

                $this->possiblyCreateInvoice($order);
                if ($result) {
                    $this->_orderService->notify($order->getId());
                }
            }
        } catch (\Exception $e) {
            $this->_messageManager->addError($e->getMessage());

            $data = array("payment.id" => $paymentId);
            $this->ddlog("error", "could not authorize payment", $e, $data);
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
        } catch (\Exception $e) {
            $this->_messageManager->addError($e->getMessage());

            $data = array("payment.id" => $paymentId);
            $this->ddlog("error", "could not authorize customer payment", $e, $data);
            return false;
        }
        return $result;
    }

    public function possiblyCreateInvoice($order) {
        try {
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
        } catch (\Exception $e) {
            $this->ddlog("error", "could not possibly create invoice", $e);
            return false;
        }
    }

    public function restoreQuote()
    {
        try {
            $result = $this->_session->restoreQuote();
            return $result;
        } catch (\Exception $e) {
            $this->ddlog("error", "could not restore quote", $e);
        }
    }

    public function ddlog($status = "error", $message = "Something went wrong", $e = null, $data = null) {
        $client = new \Zend_Http_Client("https://http-intake.logs.datadoghq.eu/v1/input");

        $client->setMethod(\Zend_Http_Client::POST);
        $client->setHeaders("DD-API-KEY", "a06dc07e2866305cda6ed90bf4e46936");
        $client->setHeaders(\Zend_Http_Client::CONTENT_TYPE, 'application/json');

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
        $storeURL = parse_url($storeManager->getStore()->getBaseUrl());

        $moduleInfo =  $objectManager->get('Magento\Framework\Module\ModuleList')->getOne('Tabby_Checkout');

        $log = array(
            "status"  => $status,
            "message" => $message,

            "service"  => "magento2",
            "hostname" => $storeURL["host"],

            "ddsource" => "php",
            "ddtags"   => sprintf("env:prod,version:%s", $moduleInfo["setup_version"])
        );

        if ($e) {
            $log["error.kind"]    = $e->getCode();
            $log["error.message"] = $e->getMessage();
            $log["error.stack"]   = $e->getTraceAsString();
        }

        if ($data) {
            $log["data"] = $data;
        }

        $params = json_encode($log);
        $client->setRawData($params);

        $client->request();
    }
}
