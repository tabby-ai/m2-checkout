<?php
namespace Tabby\Checkout\Gateway\Validator;

use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;

class CurrencyValidator extends AbstractValidator
{
    /**
     * @var array
     */
    private $allowedCurrencies;

    /**
     * @param ResultInterfaceFactory $resultFactory
     * @param array $allowedCurrencies
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        array $allowedCurrencies = []
    ) {
        parent::__construct($resultFactory);
        $this->allowedCurrencies = $allowedCurrencies;
    }

    public function validate(array $validationSubject): ResultInterface
    {
        $currency = $validationSubject['currency'];

        if (!in_array($currency, $this->allowedCurrencies)) {
            return $this->createResult(false, [__('Currency %1 is not supported.', $currency)]);
        }

        return $this->createResult(true);
    }
}

