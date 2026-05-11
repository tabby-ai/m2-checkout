<?php
namespace Tabby\Checkout\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Payment\Gateway\ConfigInterface;
use Tabby\Checkout\Gateway\Helper\Data as DataHelper;
use Tabby\Checkout\Gateway\Helper\Availability as AvailabilityHelper;

class PaymentMethodActiveObserver implements ObserverInterface
{
    /**
     * @var AvailabilityHelper
     */
    protected $availabilityHelper;

    /**
     * @var ConfigInterface
     */
    protected $moduleConfig;

    /**
     * @var DataHelper
     */
    protected $dataHelper;

    /**
     * @param AvailabilityHelper $availabilityHelper
     * @param ConfigInterface $moduleConfig
     */
    public function __construct(
        AvailabilityHelper $availabilityHelper,
        DataHelper $dataHelper,
        ConfigInterface $moduleConfig
    ) {
        $this->availabilityHelper = $availabilityHelper;
        $this->dataHelper = $dataHelper;
        $this->moduleConfig = $moduleConfig;
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $methodInstance = $observer->getEvent()->getMethodInstance();
        if (!$this->dataHelper->isTabbyMethod($methodInstance->getCode())) {
            return;
        }

        $result = $observer->getEvent()->getResult();
        if ($result->getData('is_available')) {
            if ($this->isInPromotionOnlyMode()) {
                $result->setData('is_available', false);
            }

            if ($this->isDisabled($methodInstance->getCode())) {
                $result->setData('is_available', false);
            }

            $quote = $observer->getEvent()->getQuote();
            if (!$this->availabilityHelper->isTabbyActiveForCart($quote)) {
                $result->setData('is_available', false);
            }
        }
    }

    /**
     * Checks module in only promotions mode
     *
     * @return bool
     */
    protected function isInPromotionOnlyMode()
    {
        return ($this->moduleConfig->getValue('plugin_mode') != '0');
    }

    /**
     * Checks payment method is disabled for future use
     *
     * @return bool
     */
    protected function isDisabled($code)
    {
        return in_array($code, ['tabby_checkout', 'tabby_cc_installments']);
    }

}

