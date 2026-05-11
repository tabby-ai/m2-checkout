<?php
namespace Tabby\Checkout\Gateway\Request\Payment;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class ShippingAddressDataBuilder implements BuilderInterface
{
    /**
     * Build buyer array for request payment object
     *
     * @param  array $buildSubject
     * @return array
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);

        $order = $paymentDO->getPayment()->getOrder();

        $address = $order->getShippingAddress() ?: $order->getBillingAddress();

        return [
            'shipping_address' => [
                'city'      => $address ? $address->getCity() : '',
                'address'   => $address ? implode(PHP_EOL, $address->getStreet()) : '',
                'zip'       => $address ? $address->getPostcode() : '',
            ]
        ];
    }
}
