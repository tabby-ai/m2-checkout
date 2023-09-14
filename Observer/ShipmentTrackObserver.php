<?php

namespace Tabby\Checkout\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Tabby\Checkout\Helper\Order;

class ShipmentTrackObserver implements ObserverInterface
{
    /**
     * @var Order helper
     */
    protected $_orderHelper;

    /**
     * @param Order $orderHelper
     */
    public function __construct(
        Order $orderHelper
    ) {
        $this->_orderHelper = $orderHelper;
    }

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        if ($track = $observer->getEvent()->getTrack()) {
            $this->_orderHelper->updateOrderTrackingInfo($track->getOrderId());
        };
    }
}
