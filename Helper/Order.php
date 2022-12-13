<?php

namespace Tabby\Checkout\Helper;

use Exception;
use Magento\CatalogInventory\Api\StockManagementInterface;
use Magento\CatalogInventory\Model\Indexer\Stock\Processor;
use Magento\CatalogInventory\Observer\ProductQty;
use Magento\Checkout\Model\Session;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Area;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Tabby\Checkout\Lock\LockManagerInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Registry;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Invoice;
use Tabby\Checkout\Exception\NotAuthorizedException;
use Tabby\Checkout\Exception\NotFoundException;
use Tabby\Checkout\Gateway\Config\Config;
use Tabby\Checkout\Model\Api\DdLog;
use Tabby\Checkout\Model\Method\Checkout;

class Order extends AbstractHelper
{
    /**
     * @var TransactionFactory
     */
    protected $_transactionFactory;

    /**
     * @var OrderRepositoryInterface
     */
    protected $_orderRepository;

    /**
     * @var Registry
     */
    protected $_registry;

    /**
     * @var LockManagerInterface
     */
    protected $_lockManager;

    /**
     * @var Session
     */
    protected $_session;

    /**
     * @var ManagerInterface
     */
    protected $_messageManager;

    /**
     * @var StockManagementInterface
     */
    protected $_stockManagement;

    /**
     * @var Processor
     */
    protected $_stockIndexerProcessor;

    /**
     * @var \Magento\Catalog\Model\Indexer\Product\Price\Processor
     */
    protected $_priceIndexer;

    /**
     * @var ProductQty
     */
    protected $_productQty;

    /**
     * @var ProductMetadataInterface
     */
    protected $_productMetadata;

    /**
     * @var Config
     */
    protected $_config;

    /**
     * @var QuoteIdMaskFactory
     */
    protected $_quoteIdMaskFactory;

    /**
     * @var CartRepositoryInterface
     */
    protected $_cartRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    protected $_searchCriteriaBuilder;

    /**
     * @var Cron
     */
    protected $_cronHelper;

    /**
     * @var DdLog
     */
    protected $_ddlog;

    protected $state;

    /**
     * @param Context $context
     * @param Session $session
     * @param ManagerInterface $messageManager
     * @param TransactionFactory $transactionFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param StockManagementInterface $stockManagement
     * @param Processor $stockIndexerProcessor
     * @param \Magento\Catalog\Model\Indexer\Product\Price\Processor $priceIndexer
     * @param ProductQty $productQty
     * @param ProductMetadataInterface $productMetadata
     * @param Config $config
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param CartRepositoryInterface $cartRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param Cron $cronHelper
     * @param DdLog $ddlog
     * @param Registry $registry
     * @param LockManagerInterface $lockManager
     */
    public function __construct(
        Context $context,
        Session $session,
        ManagerInterface $messageManager,
        TransactionFactory $transactionFactory,
        OrderRepositoryInterface $orderRepository,
        StockManagementInterface $stockManagement,
        Processor $stockIndexerProcessor,
        \Magento\Catalog\Model\Indexer\Product\Price\Processor $priceIndexer,
        ProductQty $productQty,
        ProductMetadataInterface $productMetadata,
        Config $config,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        CartRepositoryInterface $cartRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        Cron $cronHelper,
        DdLog $ddlog,
        Registry $registry,
        LockManagerInterface $lockManager,
        \Magento\Framework\App\State $state
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
        $this->_ddlog = $ddlog;
        $this->_registry = $registry;
        $this->_lockManager = $lockManager;
        $this->state = $state;
        parent::__construct($context);
    }

    /**
     * @param $order
     * @param string $captureCase
     */
    public function createInvoice($order, $captureCase = Invoice::NOT_CAPTURE)
    {
        if ($order->getPayment()->getMethodInstance() instanceof Checkout) {
            $order->getPayment()->getMethodInstance()->createInvoice($order, $captureCase);
        }
    }

    /**
     * @param $name
     * @param $value
     */
    public function register($name, $value)
    {
        $this->_registry->register($name, $value);
    }

    /**
     * @param $incrementId
     * @param string $comment
     * @return bool
     */
    public function cancelCurrentOrderByIncrementId($incrementId, $comment = 'Customer canceled payment')
    {
        try {
            // order can be expired and deleted
            if ($order = $this->getOrderByIncrementId($incrementId)) {
                return $this->cancelOrder($order, $comment);
            }
        } catch (Exception $e) {
            $this->_messageManager->addError($e->getMessage());
            $this->_ddlog->log("error", "could not cancel current order", $e);
            return false;
        }
        return false;
    }

    /**
     * @param $cartId
     * @param string $comment
     * @return bool
     */
    public function cancelCurrentOrder($cartId, $comment = 'Customer canceled payment')
    {
        try {
            if ($order = $this->getOrderByMaskedCartId($cartId)) {
                return $this->cancelOrder($order, $comment);
            }
        } catch (Exception $e) {
            $this->_messageManager->addError($e->getMessage());
            $this->_ddlog->log("error", "could not cancel current order", $e);
            return false;
        }
        return false;
    }

    /**
     * @param $cartId
     * @param $customerId
     * @param string $comment
     * @return bool
     */
    public function cancelCurrentCustomerOrder($cartId, $customerId, $comment = 'Customer canceled payment')
    {
        try {
            if ($order = $this->getOrderByCartId($cartId, $customerId)) {
                return $this->cancelOrder($order, $comment);
            }
        } catch (Exception $e) {
            $this->_messageManager->addError($e->getMessage());
            $this->_ddlog->log("error", "could not cancel current customer order", $e);
            return false;
        }
        return false;
    }

    /**
     * @param $cartId
     * @param $customerId
     * @return |null
     * @throws NoSuchEntityException
     */
    public function getOrderByCartId($cartId, $customerId)
    {
        $quote = $this->_cartRepository->get($cartId);

        if ($quote->getCustomerId() == $customerId) {
            $incrementId = $quote->getReservedOrderId();
            return $this->getOrderByIncrementId($incrementId);
        }

        return null;
    }

    /**
     * @param $incrementId
     */
    public function getOrderByIncrementId($incrementId)
    {
        $searchCriteria = $this->_searchCriteriaBuilder
            ->addFilter('increment_id', $incrementId, 'eq')
            ->create();
        $orders = $this->_orderRepository->getList($searchCriteria);

        foreach ($orders as $order) {
            return $order;
        }
        return null;
    }

    /**
     * @param $cartId
     * @throws NoSuchEntityException
     */
    public function getOrderByMaskedCartId($cartId)
    {
        // load QuoteIdMask
        $quoteIdMask = $this->_quoteIdMaskFactory->create()->load($cartId, 'masked_id');
        // load Quote
        $quote = $this->_cartRepository->get($quoteIdMask->getQuoteId());
        $incrementId = $quote->getReservedOrderId();

        return $this->getOrderByIncrementId($incrementId);
    }

    /**
     * @param $order
     * @return false
     */
    public function expireOrder($order)
    {
        try {
            if ($paymentId = $order->getPayment()->getAdditionalInformation(Checkout::PAYMENT_ID_FIELD)) {
                $payment = $order->getPayment();
                $data = ["payment.id" => $paymentId, "order.id" => $order->getIncrementId()];
                try {
                    $payment->getMethodInstance()->authorizePayment($payment, $paymentId);
                } catch (NotAuthorizedException $e) {
                    // if payment not authorized just cancel order
                    $this->_ddlog->log("info", "Order expired, transaction not authorized", null, $data);
                    $this->cancelOrder($order, __("Order expired, transaction not authorized."));
                } catch (NotFoundException $e) {
                    // if payment not found just cancel order
                    $this->_ddlog->log("info", "Order expired, transaction not found", null, $data);
                    $this->cancelOrder($order, __("Order expired, transaction not found."));
                } catch (Exception $e) {
                    $this->_ddlog->log("error", "could not expire order", $e, $data);
                }
            } else {
                // if no payment id provided
                $data = ["order.id" => $order->getIncrementId()];
                $this->_ddlog->log("info", "Order not have payment id assigned", null, $data);
                $this->cancelOrder($order, __("Order expired, no transaction available."));
            }
        } catch (Exception $e) {
            $this->_messageManager->addError($e->getMessage());
            $this->_ddlog->log("error", "could not expire order", $e);
            return false;
        }
    }

    /**
     * @param $order
     * @param $comment
     * @return bool
     * @throws LocalizedException
     */
    public function cancelOrder($order, $comment)
    {
        if (!empty($comment)) {
            $comment = 'Tabby Checkout :: ' . $comment;
        }
        /** @var \Magento\Sales\Model\Order $order */
        if ($order->getId() && $order->getState() != \Magento\Sales\Model\Order::STATE_CANCELED) {
            $order->registerCancellation($comment)->cancel()->save();
            // restore Quote when cancel order
            if ($this->state->getAreaCode() === Area::AREA_FRONTEND) {
                $this->restoreQuote();
            }

            // delete order if needed
            if ($this->_config->getValue('order_action_failed_payment') == 'delete') {
                if ($this->_registry->registry('isSecureArea')) {
                    $this->_orderRepository->delete($order);
                } else {
                    $this->_registry->register('isSecureArea', true);
                    $this->_orderRepository->delete($order);
                    $this->_registry->unregister('isSecureArea');
                }
            }

            return true;
        }
        return false;
    }

    /**
     * @param $cartId
     * @param $paymentId
     * @return false
     */
    public function registerPayment($cartId, $paymentId)
    {
        $this->checkCronActive();
        try {
            if ($order = $this->getOrderByMaskedCartId($cartId)) {
                $data = ["payment.id" => $paymentId, "order.reference_id" => $order->getIncrementId()];
                $this->_ddlog->log("info", "save payment", null, $data);
                return $order->getPayment()->getMethodInstance()->registerPayment($order->getPayment(), $paymentId);
            } else {
                throw new Exception("registerPayment: No order found for Masked Cart ID: " . $cartId);
            }
        } catch (Exception $e) {
            $this->_messageManager->addError($e->getMessage());

            $data = ["payment.id" => $paymentId];
            $this->_ddlog->log("error", "could not register payment", $e, $data);
            return false;
        }
    }

    /**
     * @param $cartId
     * @param $paymentId
     * @param $customerId
     * @return false
     */
    public function registerCustomerPayment($cartId, $paymentId, $customerId)
    {
        $this->checkCronActive();
        try {
            if ($order = $this->getOrderByCartId($cartId, $customerId)) {
                $data = ["payment.id" => $paymentId, "order.reference_id" => $order->getIncrementId()];
                $this->_ddlog->log("info", "save customer payment", null, $data);
                return $order->getPayment()->getMethodInstance()->registerPayment($order->getPayment(), $paymentId);
            } else {
                throw new Exception("registerCustomerPayment: No order found for Cart ID: " . $cartId);
            }
        } catch (Exception $e) {
            $this->_messageManager->addError($e->getMessage());

            $data = ["payment.id" => $paymentId];
            $this->_ddlog->log("error", "could not register customer payment", $e, $data);
            return false;
        }
    }

    /**
     *
     */
    public function checkCronActive()
    {
        if (!$this->_cronHelper->isCronActive()) {
            $this->_ddlog->log("error", "cron not active");
        }
    }

    public function noteRejectedOrExpired($webhook) {
        try {
            // order can be expired and deleted
            if ($order = $this->getOrderByIncrementId($webhook->order->reference_id)) {
                return $order->addStatusHistoryComment(sprintf("Webhook payment %s status is %s.", $webhook->id, $webhook->status), false);
            }
        } catch (Exception $e) {
            $this->_messageManager->addError($e->getMessage());
            $this->_ddlog->log("error", "could not add message about rejected or expired webhook for current order", $e);
            return false;
        }
        return false;
    }
    /**
     * @param $incrementId
     * @param $paymentId
     * @param string $source
     * @return bool
     */
    public function authorizeOrder($incrementId, $paymentId, $source = 'checkout')
    {
        $result = true;
        // try to lock on order/transaction ID
        $lockName = md5(sprintf("%s-%s", $incrementId, $paymentId));
        // max 10 sec wait
        $this->_lockManager->lock($lockName, 10);
        try {
            if ($order = $this->getOrderByIncrementId($incrementId)) {
                $result = $order->getPayment()->getMethodInstance()->authorizePayment(
                    $order->getPayment(),
                    $paymentId,
                    $source
                );
            } else {
                $data = [
                    "payment.id" => $paymentId,
                    "payment.order.reference_id" => $incrementId,
                    "auth.source" => $source
                ];
                $this->_ddlog->log("error", "could not find order", null, $data);
            }
        } catch (Exception $e) {
            $this->_messageManager->addError($e->getMessage());

            $data = ["payment.id" => $paymentId];
            $this->_ddlog->log("error", "could not authorize payment", $e, $data);
            $result = false;
        }
        $this->_lockManager->unlock($lockName);
        return $result;
    }

    /**
     * @param $cartId
     * @param $paymentId
     * @return bool
     */
    public function authorizePayment($cartId, $paymentId)
    {
        $result = true;
        try {
            if ($order = $this->getOrderByMaskedCartId($cartId)) {
                $result = $order->getPayment()->getMethodInstance()->authorizePayment($order->getPayment(), $paymentId);
            }
        } catch (Exception $e) {
            $this->_messageManager->addError($e->getMessage());

            $data = ["payment.id" => $paymentId];
            $this->_ddlog->log("error", "could not authorize payment", $e, $data);
            return false;
        }
        return $result;
    }

    /**
     * @param $cartId
     * @param $paymentId
     * @param $customerId
     * @return bool
     */
    public function authorizeCustomerPayment($cartId, $paymentId, $customerId)
    {
        $result = true;
        try {
            if ($order = $this->getOrderByCartId($cartId, $customerId)) {
                $result = $order->getPayment()->getMethodInstance()->authorizePayment($order->getPayment(), $paymentId);
            }
        } catch (Exception $e) {
            $this->_messageManager->addError($e->getMessage());

            $data = ["payment.id" => $paymentId];
            $this->_ddlog->log("error", "could not authorize customer payment", $e, $data);
            return false;
        }
        return $result;
    }

    /**
     * @return bool
     */
    public function restoreQuote()
    {
        try {
            return $this->_session->restoreQuote();
        } catch (Exception $e) {
            $this->_ddlog->log("error", "could not restore quote", $e);
        }
    }

    /**
     * @param string $status
     * @param string $message
     * @param null $e
     * @param null $data
     */
    public function ddlog($status = "error", $message = "Something went wrong", $e = null, $data = null)
    {
        $this->_ddlog->log($status, $message, $e, $data);
    }

    public function getOrderStoreId($incrementId) {
        if ($order = $this->getOrderByIncrementId($incrementId)) {
            return $order->getStore()->getId();
        }
        return false;
    }
    public function getOrderRedirectUrl($incrementId) {
        return $this->getOrderByIncrementId($incrementId)->getPayment()->getMethodInstance()->getOrderRedirectUrl();
    }
}
