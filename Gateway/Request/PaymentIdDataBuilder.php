<?php
namespace Tabby\Checkout\Gateway\Request;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Tabby\Checkout\Gateway\Helper\Data as DataHelper;

class PaymentIdDataBuilder implements BuilderInterface
{
    /**
     * Build payment id for request
     *
     * @param  array $buildSubject
     * @return array
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);

        return ['payment_id' => $paymentDO->getPayment()->getAdditionalInformation(DataHelper::PAYMENT_ID_FIELD)];
    }
}
