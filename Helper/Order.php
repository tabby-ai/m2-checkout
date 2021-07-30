<?php
namespace Tabby\Checkout\Helper;

use Tabby\Checkout\Gateway\Config\Config;
use Magento\Framework\Event\ObserverInterface;

class Order extends \Magento\Framework\App\Helper\AbstractHelper
{

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
     * @param \Magento\Framework\DB\TransactionFactory $transactionFactory
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param \Tabby\Checkout\Helper\Cron $cronHelper
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Checkout\Model\Session $session,
        \Magento\Framework\Message\ManagerInterface $messageManager,
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
        \Tabby\Checkout\Helper\Cron $cronHelper,
        \Magento\Framework\Registry $registry
    ) {
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
        $this->_cronHelper = $cronHelper;
        $this->_registry = $registry;
        parent::__construct($context);
    }

    public function createInvoice($order, $captureCase = \Magento\Sales\Model\Order\Invoice::NOT_CAPTURE)
    {
        if ($order->getPayment()->getMethodInstance() instanceof \Tabby\Checkout\Model\Method\Checkout) {
            $order->getPayment()->getMethodInstance()->createInvoice($order, $captureCase);
        }
    }

    public function register($name, $value) {
        $this->_registry->register($name, $value);
    }

    public function cancelCurrentOrderByIncrementId($incrementId, $comment = 'Customer canceled payment') {
        try {
            // order can be expired and deleted
            if ($order = $this->getOrderByIncrementId($incrementId)) {
                return $this->cancelOrder($order, $comment);
            };
        } catch (\Exception $e) {
            $this->_messageManager->addError($e->getMessage());
            $this->ddlog("error", "could not cancel current order", $e);
            return false;
        }
        return false;
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
            $incrementId = $quote->getReservedOrderId();
            return $this->getOrderByIncrementId($incrementId);
        }

        return null;
    }
    public function getOrderByIncrementId($incrementId) {
        $searchCriteria = $this->_searchCriteriaBuilder
                               ->addFilter('increment_id', $incrementId, 'eq')
                               ->create();
        $orders = $this->_orderRepository->getList($searchCriteria);

        foreach ($orders as $order) {
            return $order;
        }
        return null;
    }

    public function getOrderByMaskedCartId($cartId) {
        // load QuoteIdMask
        $quoteIdMask = $this->_quoteIdMaskFactory->create()->load($cartId, 'masked_id');
        // load Quote
        $quote = $this->_cartRepository->get($quoteIdMask->getQuoteId());
        $incrementId = $quote->getReservedOrderId();

        return $this->getOrderByIncrementId($incrementId);
    }

    public function expireOrder($order) {
        try {
            if ($paymentId = $order->getPayment()->getAdditionalInformation(\Tabby\Checkout\Model\Method\Checkout::PAYMENT_ID_FIELD)) {
                $payment = $order->getPayment();
                $data = array("payment.id" => $paymentId, "order.id" => $order->getIncrementId());
                try {
                    $payment->getMethodInstance()->authorizePayment($payment, $paymentId);
                } catch (\Tabby\Checkout\Exception\NotAuthorizedException $e) {
                    // if payment not authorized just cancel order
                    $this->ddlog("info", "Order expired, transaction not authorized", null, $data);
                    $this->cancelOrder($order, __("Order expired, transaction not authorized."));
                } catch (\Tabby\Checkout\Exception\NotFoundException $e) {
                    // if payment not found just cancel order
                    $this->ddlog("info", "Order expired, transaction not found", null, $data);
                    $this->cancelOrder($order, __("Order expired, transaction not found."));
                } catch (\Exception $e) {
                    $this->ddlog("error", "could not expire order", $e, $data);
                }
            } else {
                // if no payment id provided
                $data = array("order.id" => $order->getIncrementId());
                $this->ddlog("info", "Order not have payment id assigned", null, $data);
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
            $order->registerCancellation($comment)->cancel()->save();

            // restore Quote when cancel order
            $this->restoreQuote();

            // delete order if needed
            //if ($this->_config->getValue('order_action_failed_payment') == 'delete') {
                if ($this->_registry->registry('isSecureArea')) {
                    $this->_orderRepository->delete($order);
                } else {
                    $this->_registry->register('isSecureArea', true);
                    $this->_orderRepository->delete($order);
                    $this->_registry->unregister('isSecureArea');
                }
            //}

            return true;
        }
        return false;
    }

    public function registerPayment($cartId, $paymentId) {
        $this->checkCronActive();
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
        $this->checkCronActive();
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

    public function checkCronActive() {
        if (!$this->_cronHelper->isCronActive()) {
            $this->ddlog("error", "cron not active");
        }
    }

    public function authorizeOrder($incrementId, $paymentId) {
        $result = true;
        try {
            if ($order = $this->getOrderByIncrementId($incrementId)) {
                $result = $order->getPayment()->getMethodInstance()->authorizePayment($order->getPayment(), $paymentId);
            }
        } catch (\Exception $e) {
            $this->_messageManager->addError($e->getMessage());

            $data = array("payment.id" => $paymentId);
            $this->ddlog("error", "could not authorize payment", $e, $data);
            return false;
        }
        return $result;

    }
    public function authorizePayment($cartId, $paymentId) {
        $result = true;
        try {
            if ($order = $this->getOrderByMaskedCartId($cartId)) {
                $result = $order->getPayment()->getMethodInstance()->authorizePayment($order->getPayment(), $paymentId);
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
            }
        } catch (\Exception $e) {
            $this->_messageManager->addError($e->getMessage());

            $data = array("payment.id" => $paymentId);
            $this->ddlog("error", "could not authorize customer payment", $e, $data);
            return false;
        }
        return $result;
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
