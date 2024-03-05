<?php

namespace Tabby\Checkout\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Registry;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\OrderRepositoryInterface;

class ShipmentTrack extends AbstractHelper
{
    /**
     * @var Registry
     */
    protected $_registry;

    /**
     * @var OrderRepositoryInterface
     */
    protected $_orderRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    protected $_searchCriteriaBuilder;

    /**
     * @param Context $context
     * @param OrderRepositoryInterface $orderRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param Registry $registry
     */
    public function __construct(
        Context $context,
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        Registry $registry
    ) {
        $this->_registry = $registry;
        $this->_orderRepository = $orderRepository;
        $this->_searchCriteriaBuilder = $searchCriteriaBuilder;
        parent::__construct($context);
    }

    /**
     * Update order tracking information on Tabby merchant dashboard
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @param \Magento\Sales\Api\Data\ShipmentTrackInterface[] $tracks
     */
    public function updateOrderTrackingInfo($order, $tracks = null)
    {
        if (($method = $order->getPayment()->getMethodInstance()) instanceof \Tabby\Checkout\Model\Method\Checkout) {
            $method->updateOrderTracking($tracks);
        }
    }

    /**
     * Register orders with tracking information changes
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     */
    public function registerOrderTrackChanges($order)
    {
        if ($orders = $this->_registry->registry('tabby_orders_track_changed')) {
            $this->_registry->unregister('tabby_orders_track_changed');
        } else {
            $orders = [];
        }
        $orders[] = $order->getIncrementId();
        $this->_registry->register('tabby_orders_track_changed', $orders);
    }

    /**
     * Update tracking information for registered orders
     */
    public function syncOrderTrackChanges()
    {
        if ($orders = $this->_registry->registry('tabby_orders_track_changed')) {
            foreach (array_unique($orders) as $incrementId) {
                $this->updateOrderTrackingInfo($this->getOrderByIncrementId($incrementId));
            }
        }
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
        $searchCriteria = $this->_searchCriteriaBuilder
            ->addFilter('increment_id', $incrementId, 'eq')
            ->create();
        $orders = $this->_orderRepository->getList($searchCriteria);

        foreach ($orders as $order) {
            return $order;
        }
        return null;
    }
}
