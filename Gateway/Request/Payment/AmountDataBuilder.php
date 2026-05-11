<?php
namespace Tabby\Checkout\Gateway\Request\Payment;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Tabby\Checkout\Gateway\Helper\Currency as CurrencyHelper;

class AmountDataBuilder implements BuilderInterface
{
    /**
     * Build amount array for request payment object
     *
     * @param  array $buildSubject
     * @return array
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);

        $order = $paymentDO->getPayment()->getOrder();

        return ['amount' => (string)CurrencyHelper::getTabbyPrice($order, 'grand_total')];
    }
}
