<?php
namespace Tabby\Checkout\Gateway\Request\Payment\Order;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class ReferenceIdDataBuilder implements BuilderInterface
{
    /**
     * Build reference_id array for request order object
     *
     * @param  array $buildSubject
     * @return array
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);

        $order = $paymentDO->getPayment()->getOrder();

        return ['reference_id' => (string)$order->getIncrementId()];
    }
}
