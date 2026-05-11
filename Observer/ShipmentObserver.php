<?php

namespace Tabby\Checkout\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Shipment;
use Tabby\Checkout\Gateway\Helper\Data as DataHelper;
use Tabby\Checkout\Helper\Order;

class ShipmentObserver implements ObserverInterface
{
    /**
     * @var Order
     */
    protected $_orderHelper;

    /**
     * @var ConfigInterface
     */
    protected $moduleConfig;

    /**
     * @param ConfigInterface $moduleConfig
     * @param Order $orderHelper
     */
    public function __construct(
        ConfigInterface $moduleConfig,
        Order $orderHelper
    ) {
        $this->moduleConfig = $moduleConfig;
        $this->_orderHelper = $orderHelper;
    }

    /**
     * Main method, checks if we need to create invoice on shipment creation
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        // capture on shipping creation
        if ($this->moduleConfig->getValue(DataHelper::CAPTURE_ON) == 'shipment') {
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
