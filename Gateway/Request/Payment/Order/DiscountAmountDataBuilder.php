<?php
namespace Tabby\Checkout\Gateway\Request\Payment\Order;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Tabby\Checkout\Gateway\Helper\Currency as CurrencyHelper;

class DiscountAmountDataBuilder implements BuilderInterface
{
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

        return ['discount_amount' => (string)CurrencyHelper::getTabbyPrice($order, 'discount_amount')];
    }
}
