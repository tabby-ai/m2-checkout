<?php
namespace Tabby\Checkout\Gateway\Validator;

use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;

class PaymentStatusValidator extends AbstractValidator
{
    const STATUS_FIELD = 'status';
    /**
     * Performs validation of result code
     *
     * @param array $validationSubject
     * @return ResultInterface
     */
    public function validate(array $validationSubject)
    {
        if (!isset($validationSubject['response']) || !is_array($validationSubject['response'])) {
            throw new \InvalidArgumentException('Response does not exist');
        }

        $response = $validationSubject['response'];

        if ($this->isAuthorized($response)) {
            return $this->createResult(
                true,
                []
            );
        } else {
            return $this->createResult(
                false,
                [__('Payment is not authorized.')]
            );
        }
    }

    /**
     * Is Tabby payment Authorized
     *
     * @param array $response
     * @return bool
     */
    protected function isAuthorized($response)
    {
        $result = false;
        switch ($response['status']) {
            case 'AUTHORIZED':
                $result = true;
                break;
            case 'CLOSED':
                $result = (count($response['captures']) > 0 && ($response['captures'][0]['amount'] == $response['amount']));
                break;
        }
        return $result;
    }

}
