<?php
namespace Tabby\Checkout\Observer;

class OrderObserver implements \Magento\Framework\Event\ObserverInterface
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
		if ($this->_config->getValue(\Tabby\Checkout\Gateway\Config\Config::CREATE_PENDING_INVOICE)) {
        	$this->_orderHelper->createInvoice($observer->getEvent()->getOrder()->getId());      
		}
    }

}
