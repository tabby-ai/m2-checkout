<?php

namespace Tabby\Checkout\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Shipment;
use Tabby\Checkout\Gateway\Config\Config;
use Tabby\Checkout\Helper\Order;

class ShipmentObserver implements ObserverInterface
{
    /**
     * @var Order
     */
    protected $_orderHelper;
    /**
     * @var Config
     */
    protected $_config;

    /**
     * @param Config $config
     * @param Order $orderHelper
     */
    public function __construct(
        Config $config,
        Order $orderHelper
    ) {
        $this->_config = $config;
        $this->_orderHelper = $orderHelper;
    }

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        if ($this->_config->getValue(Config::CAPTURE_ON) == 'shipment') {
            /** @var Shipment $shipment */
            $shipment = $observer->getEvent()->getShipment();
            if (!$shipment->getOrder()->hasInvoices()) {
                $this->_orderHelper->createInvoice(
                    $shipment->getOrder(),
                    Invoice::CAPTURE_ONLINE
                );
            } else {
                /** @var InvoiceInterface $invoice */
                foreach ($shipment->getOrder()->getInvoiceCollection() as $invoice) {

                    if ($invoice->canCapture()) {
                        $this->_orderHelper->register('current_invoice', $invoice);
                        $invoice->capture();
                        $invoice->save();
                    }
                }
            }
        }
    }
}
