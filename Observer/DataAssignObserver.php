<?php

namespace Tabby\Checkout\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Tabby\Checkout\Gateway\Helper\Currency as CurrencyHelper;
use Tabby\Checkout\Gateway\Helper\Data as DataHelper;

class DataAssignObserver extends AbstractDataAssignObserver
{
    /**
     * @var ConfigInterface
     */
    private $moduleConfig;

    /**
     * @param ConfigInterface $moduleConfig
     */
    public function __construct(
        ConfigInterface $moduleConfig
    ) {
        $this->moduleConfig = $moduleConfig;
    }
    /**
     * Main method, assigns payment id to payment instance
     *
     * @param Observer $observer
     * @return void
     * @throws LocalizedException
     */
    public function execute(Observer $observer)
    {
        $method = $this->readMethodArgument($observer);
        $data = $this->readDataArgument($observer);
        
        if ($this->moduleConfig->getValue(DataHelper::KEY_LOCAL_CURRENCY)) {
            $method->getInfoInstance()
                ->setAdditionalInformation(CurrencyHelper::TABBY_CURRENCY_FIELD, 'order');
        }
    }
}
