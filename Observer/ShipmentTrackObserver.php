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
     * Main method, register track changes if track updated
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        if ($track = $observer->getEvent()->getTrack()) {
            $this->_orderHelper->registerOrderTrackChanges($track->getShipment()->getOrder());
        };
    }
}
