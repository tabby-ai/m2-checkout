<?php
namespace Tabby\Checkout\Gateway\Validator;

use Magento\Payment\Gateway\Validator\ResultInterface;
use Tabby\Checkout\Gateway\Helper\Currency as CurrencyHelper;

class PaymentAmountValidator extends AbstractCurrencyValidator
{
    public function validate(array $validationSubject): ResultInterface
    {
        $response = $this->subjectReader->readResponse($validationSubject);
        $paymentDO = $this->subjectReader->readPayment($validationSubject);

        $payment = $paymentDO->getPayment();
        $order = $payment->getOrder();

        $t_amount = $payment->formatAmount($response['amount']);
        $o_amount = $payment->formatAmount($this->currencyHelper->getTabbyPrice($order, 'grand_total'));

        if ($t_amount !== $o_amount) {
            return $this->createResult(false, [__(
                'Amount mismatch for order (%1) and transaction (%2).',
                $o_amount,
                $t_amount
            )]);
        }

        return $this->createResult(true);
    }
}

