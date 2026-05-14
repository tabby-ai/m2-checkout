<?php
namespace Tabby\Checkout\Gateway\Request\Payment\Order;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Tabby\Checkout\Gateway\Helper\Currency as CurrencyHelper;

class DiscountAmountDataBuilder implements BuilderInterface
{
    /**
     * @var CurrencyHelper
     */
    private $currencyHelper;

    /**
     * @param DomainHelper $domainHelper
     */
    public function __construct(
        CurrencyHelper $currencyHelper
    ) {
        $this->currencyHelper = $currencyHelper;
    }

    /**
     * Build discount amount array for request order object
     *
     * @param  array $buildSubject
     * @return array
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);

        $order = $paymentDO->getPayment()->getOrder();

        return ['discount_amount' => (string)$this->currencyHelper->getTabbyPrice($order, 'discount_amount')];
    }
}
