<?php

namespace Tabby\Checkout\Helper;

use Exception;
use Magento\Checkout\Model\Session;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Area;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Lock\Backend\Database as LockManagerDatabase;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Registry;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Service\OrderService;
use Tabby\Checkout\Exception\NotAuthorizedException;
use Tabby\Checkout\Exception\NotFoundException;
use Tabby\Checkout\Gateway\Helper\Data as DataHelper;
use Tabby\Checkout\Helper\Invoice as InvoiceHelper;
use Tabby\Checkout\Model\Api\DdLog;
use Tabby\Checkout\Model\Method\Checkout;

class Order extends AbstractHelper
{
    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var OrderService
     */
    protected $orderService;

    /**
     * @var Registry
     */
    protected $registry;

    /**
     * @var LockManagerDatabase
     */
    protected $lockManager;

    /**
     * @var Session
     */
    protected $session;

    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * @var InvoiceHelper
     */
    protected $invoiceHelper;

    /**
     * @var ConfigInterface
     */
    protected $moduleConfig;

    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @var Cron
     */
    protected $cronHelper;

    /**
     * @var DdLog
     */
    protected $ddlog;

    /**
     * @var \Magento\Framework\App\State
     */
    protected $state;

    /**
     * @param Context $context
     * @param Session $session
     * @param ManagerInterface $messageManager
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderService $orderService
     * @param InvoiceHelper $invoiceHelper
     * @param ConfigInterface $moduleConfig
     * @param CartRepositoryInterface $cartRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param Cron $cronHelper
     * @param DdLog $ddlog
     * @param Registry $registry
     * @param LockManagerDatabase $lockManager
     * @param \Magento\Framework\App\State $state
     */
    public function __construct(
        Context $context,
        Session $session,
        ManagerInterface $messageManager,
        OrderRepositoryInterface $orderRepository,
        OrderService $orderService,
        InvoiceHelper $invoiceHelper,
        ConfigInterface $moduleConfig,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        Cron $cronHelper,
        DdLog $ddlog,
        Registry $registry,
        LockManagerDatabase $lockManager,
        \Magento\Framework\App\State $state
    ) {
        $this->session = $session;
        $this->messageManager = $messageManager;
        $this->orderRepository = $orderRepository;
        $this->orderService = $orderService;
        $this->invoiceHelper = $invoiceHelper;
        $this->moduleConfig = $moduleConfig;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->cronHelper = $cronHelper;
        $this->ddlog = $ddlog;
        $this->registry = $registry;
        $this->lockManager = $lockManager;
        $this->state = $state;
        parent::__construct($context);
    }

    /**
     * Register value in registry
     *
     * @param string $name
     * @param string $value
     */
    public function register($name, $value)
    {
        $this->registry->register($name, $value);
    }

    /**
     * Cancel created order based on increment id
     *
     * @param string $incrementId
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
            $this->messageManager->addError($e->getMessage());
            $this->ddlog->log("error", "could not cancel current order", $e);
            return false;
        }
        return false;
    }

    /**
     * Get order by increment id
     *
     * @param string $incrementId
     * @return ?Magento\Sales\Api\Data\OrderInterface
     * @throws NoSuchEntityException
     */
    public function getOrderByIncrementId($incrementId)
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('increment_id', $incrementId, 'eq')
            ->create();
        $orders = $this->orderRepository->getList($searchCriteria);

        if ($orders->getTotalCount() > 0) {
            foreach ($orders->getItems() as $order) {
                return $order;
            }
        }
        return null;
    }

    /**
     * Expire given order in case transaction not authorized or not found
     *
     * @param Magento\Sales\Api\Data\OrderInterfsace $order
     */
    public function expireOrder($order)
    {
        try {
            if ($paymentId = $order->getPayment()->getAdditionalInformation(DataHelper::PAYMENT_ID_FIELD)) {
                $payment = $order->getPayment();
                $data = ["payment.id" => $paymentId, "order.id" => $order->getIncrementId()];
                try {
                    //$payment->getMethodInstance()->authorizePayment($payment, $paymentId, 'expireOrder');
                    $this->authorizeOrder($order->getIncrementId(), $paymentId, 'expireOrder');
                } catch (NotAuthorizedException $e) {
                    // if payment not authorized just cancel order
                    $this->ddlog->log("info", "Order expired, transaction not authorized", null, $data);
                    $this->cancelOrder($order, __("Order expired, transaction not authorized."));
                } catch (NotFoundException $e) {
                    // if payment not found just cancel order
                    $this->ddlog->log("info", "Order expired, transaction not found", null, $data);
                    $this->cancelOrder($order, __("Order expired, transaction not found."));
                } catch (Exception $e) {
                    $this->ddlog->log("error", "could not expire order", $e, $data);
                }
            } else {
                // if no payment id provided
                $data = ["order.id" => $order->getIncrementId()];
                $this->ddlog->log("info", "Order not have payment id assigned", null, $data);
                $this->cancelOrder($order, __("Order expired, no transaction available."));
            }
        } catch (Exception $e) {
            $this->messageManager->addError($e->getMessage());
            $this->ddlog->log("error", "could not expire order", $e);
        }
    }

    /**
     * Cancel order in some case logic
     *
     * @param Magento\Sales\Api\Data\OrderInterfsace $order
     * @param string $comment
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
            if ($this->moduleConfig->getValue('order_action_failed_payment') == 'delete') {
                if ($this->registry->registry('isSecureArea')) {
                    $this->orderRepository->delete($order);
                } else {
                    $this->registry->register('isSecureArea', true);
                    $this->orderRepository->delete($order);
                    $this->registry->unregister('isSecureArea');
                }
            }

            return true;
        }
        return false;
    }

    /**
     * Check cron is runned for our tasks, log msg if not
     */
    public function checkCronActive()
    {
        if (!$this->cronHelper->isCronActive()) {
            $this->ddlog->log("error", "cron not active");
        }
    }

    /**
     * Add note about Rejected/Expired payment to order
     *
     * @param StdClass $webhook
     * @return bool
     */
    public function noteRejectedOrExpired($webhook)
    {
        try {
            // order can be expired and deleted
            if ($order = $this->getOrderByIncrementId($webhook->order->reference_id)) {
                return $order->addStatusHistoryComment(
                    sprintf("Webhook payment %s status is %s.", $webhook->id, $webhook->status),
                    false
                );
            }
        } catch (Exception $e) {
            $this->messageManager->addError($e->getMessage());
            $this->ddlog->log("error", "could not add message about rejected/expired webhook for current order", $e);
            return false;
        }
        return false;
    }
    /**
     * Process payment authorization for order
     *
     * @param string $incrementId
     * @param string $paymentId
     * @param string $source
     * @return bool
     */
    public function authorizeOrder($incrementId, $paymentId, $source = 'checkout')
    {
        $result = true;
        // try to lock on order/transaction ID
        $lockName = hash('sha256', sprintf("%s-%s", $incrementId, $paymentId));
        // max 10 sec wait
        $this->lockManager->lock($lockName, 10);
        try {
            if ($order = $this->getOrderByIncrementId($incrementId)) {
                if (!in_array($order->getState(), [
                    \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT,
                    \Magento\Sales\Model\Order::STATE_NEW
                ])) {
                    return true;
                }

                $order->getPayment()->authorize(true, $order->getBaseGrandTotal());

                $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING);
                $order->setStatus($this->moduleConfig->getValue(DataHelper::AUTHORIZED_STATUS));

                $this->orderRepository->save($order);

                $this->invoiceHelper->createInvoiceForAutoCapture($order);

                $this->invoiceHelper->possiblyCreateInvoice($order);

                if ($this->moduleConfig->getValue(DataHelper::MARK_COMPLETE) == 1) {
                    $order->setState(\Magento\Sales\Model\Order::STATE_COMPLETE);
                    $order->setStatus($order->getConfig()->getStateDefaultStatus(
                        \Magento\Sales\Model\Order::STATE_COMPLETE
                    ));
                    $order->addStatusHistoryComment(
                        "Autocomplete by Tabby",
                        $order->getConfig()->getStateDefaultStatus(\Magento\Sales\Model\Order::STATE_COMPLETE)
                    );

                    $this->orderRepository->save($order);
                }

                $this->orderService->notify($order->getId());
            } else {
                $data = [
                    "payment.id" => $paymentId,
                    "payment.order.reference_id" => $incrementId,
                    "auth.source" => $source,
                ];
                $this->ddlog->log("error", "could not find order", null, $data);
            }
        } catch (Exception $e) {
            $this->messageManager->addError($e->getMessage());

            $data = ["payment.id" => $paymentId];
            $this->ddlog->log("error", "could not authorize payment", $e, $data);
            $result = false;
        }
        $this->lockManager->unlock($lockName);
        return $result;
    }

    /**
     * Quote object restore after order cancelled
     */
    public function restoreQuote()
    {
        try {
            $this->session->restoreQuote();
        } catch (Exception $e) {
            $this->ddlog->log("error", "could not restore quote", $e);
        }
    }

    /**
     * Write to DataDog
     *
     * @param string $status
     * @param string $message
     * @param ?\Exception $e
     * @param ?array $data
     */
    public function ddlog($status = "error", $message = "Something went wrong", $e = null, $data = null)
    {
        $this->ddlog->log($status, $message, $e, $data);
    }

    /**
     * Store id getter for given increment id
     *
     * @param string $incrementId
     * @return int|bool
     */
    public function getOrderStoreId($incrementId)
    {
        if ($order = $this->getOrderByIncrementId($incrementId)) {
            return $order->getStore()->getId();
        }
        return false;
    }

    /**
     * Getter for redirect url for order
     *
     * @param string $incrementId
     * @return string
     */
    public function getOrderRedirectUrl($incrementId)
    {
        //return $this->getOrderByIncrementId($incrementId)->getPayment()->getMethodInstance()->getOrderRedirectUrl();
        return $this->getOrderByIncrementId($incrementId)->getPayment()->getAdditionalInformation('tabby_web_url');
    }
}
