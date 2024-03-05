<?php

namespace Tabby\Checkout\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Tabby\Checkout\Helper\ShipmentTrack;

class ControllerPostDispatchObserver implements ObserverInterface
{
    /**
     * @var ShipmentTrack
     */
    protected $_shipmentTrackHelper;

    /**
     * Constructor
     *
     * @param ShipmentTrack $shipmentTrackHelper
     */
    public function __construct(
        ShipmentTrack $shipmentTrackHelper
    ) {
        $this->_shipmentTrackHelper = $shipmentTrackHelper;
    }

    /**
     * Main method, register order track changes
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        $this->_shipmentTrackHelper->syncOrderTrackChanges();
    }
}
