<?php

namespace Tabby\Checkout\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use \Magento\Framework\App\Request\Http;
use Tabby\Checkout\Helper\Order;

class ShipmentTrackObserver implements ObserverInterface
{
    /**
     * @var Order helper
     */
    protected $_orderHelper;

    /**
     * @var Http _request
     */
    protected $_request;

    /**
     * @param Order $orderHelper
     */
    public function __construct(
        Http $request,
        Order $orderHelper
    ) {
        $this->_request = $request;
        $this->_orderHelper = $orderHelper;
    }

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        if ($this->_request->getFullActionName() != 'adminhtml_order_shipment_save') {
            if ($track = $observer->getEvent()->getTrack()) {
                $this->_orderHelper->updateOrderTrackingInfo($track->getShipment()->getOrder());
            };
        }
    }
}
