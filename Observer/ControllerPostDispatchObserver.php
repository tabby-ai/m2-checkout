<?php

namespace Tabby\Checkout\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Tabby\Checkout\Helper\Order;

class ControllerPostDispatchObserver implements ObserverInterface
{
    /**
     * @var Order
     */
    protected $_orderHelper;

    /**
     * Constructor
     *
     * @param Order $orderHelper
     */
    public function __construct(
        Order $orderHelper
    ) {
        $this->_orderHelper = $orderHelper;
    }

    /**
     * Main method, register order track changes
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        $this->_orderHelper->syncOrderTrackChanges();
    }
}
