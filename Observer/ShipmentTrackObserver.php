<?php

namespace Tabby\Checkout\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Tabby\Checkout\Helper\ShipmentTrack;

class ShipmentTrackObserver implements ObserverInterface
{
    /**
     * @var ShipmentTrack helper
     */
    protected $_shipmentTrackHelper;

    /**
     * @param ShipmentTrack $shipmentTrackHelper
     */
    public function __construct(
        ShipmentTrack $shipmentTrackHelper
    ) {
        $this->_shipmentTrackHelper = $shipmentTrackHelper;
    }

    /**
     * Main method, register track changes if track updated
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        if ($track = $observer->getEvent()->getTrack()) {
            $this->_shipmentTrackHelper->registerOrderTrackChanges($track->getShipment()->getOrder());
        };
    }
}
