<?php
namespace Tabby\Checkout\Observer;

class ShipmentObserver implements \Magento\Framework\Event\ObserverInterface
{

    /**
    * @var \Tabby\Checkout\Helper\Order
    */
    protected $_orderHelper;

    /**
    * @param \Tabby\Checkout\Gateway\Config\Config $config
    * @param \Tabby\Checkout\Helper\Order $orderHelper
    */
    public function __construct(
		\Tabby\Checkout\Gateway\Config\Config $config,
        \Tabby\Checkout\Helper\Order $orderHelper
    ) {
		$this->_config      = $config;
        $this->_orderHelper = $orderHelper;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {   
		if ($this->_config->getValue(\Tabby\Checkout\Gateway\Config\Config::CAPTURE_ON) == 'shipment') {
            if (!$observer->getEvent()->getShipment()->getOrder()->hasInvoices()) {
        	    $this->_orderHelper->createInvoice(
                    $observer->getEvent()->getShipment()->getOrder(), 
                    \Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE
                );      
            } else {
                // get invoices collection, check to be paid
                foreach ($observer->getEvent()->getShipment()->getOrder()->getInvoiceCollection() as $invoice) {
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
