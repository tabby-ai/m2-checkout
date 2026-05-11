<?php
namespace Tabby\Checkout\Gateway\Validator;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Tabby\Checkout\Gateway\Helper\Currency as CurrencyHelper;

class PaymentCurrencyValidator extends AbstractValidator
{
    public function validate(array $validationSubject): ResultInterface
    {
        $response = SubjectReader::readResponse($validationSubject);
        $paymentDO = SubjectReader::readPayment($validationSubject);

        $order = $paymentDO->getPayment()->getOrder();

        if ($response['currency'] !== CurrencyHelper::getTabbyCurrency($order)) {
            return $this->createResult(false, [__(
                'Currency mismatch for order (%1) and transaction (%2).',
                CurrencyHelper::getTabbyCurrency($order),
                $response['currency']
            )]);
        }

        return $this->createResult(true);
    }
}

