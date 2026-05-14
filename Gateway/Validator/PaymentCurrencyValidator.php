<?php
namespace Tabby\Checkout\Gateway\Validator;

use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Tabby\Checkout\Gateway\Helper\Currency as CurrencyHelper;

class PaymentCurrencyValidator extends AbstractCurrencyValidator
{
    public function validate(array $validationSubject): ResultInterface
    {
        $response = $this->subjectReader->readResponse($validationSubject);
        $paymentDO = $this->subjectReader->readPayment($validationSubject);

        $order = $paymentDO->getPayment()->getOrder();

        if ($response['currency'] !== $this->currencyHelper->getTabbyCurrency($order)) {
            return $this->createResult(false, [__(
                'Currency mismatch for order (%1) and transaction (%2).',
                $this->currencyHelper->getTabbyCurrency($order),
                $response['currency']
            )]);
        }

        return $this->createResult(true);
    }
}

