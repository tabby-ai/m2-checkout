<?php
namespace Tabby\Checkout\Gateway\Validator;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Tabby\Checkout\Gateway\Helper\Currency as CurrencyHelper;

abstract class AbstractCurrencyValidator extends AbstractValidator
{
    /**
     * @var CurrencyHelper
     */
    protected $currencyHelper;

    /**
     * @var SubjectReader
     */
    protected $subjectReader;

    /**
     * @param ResultInterfaceFactory $resultFactory
     * @param CurrencyHelper $currencyHelper
     * @param SubjectReader $subjectReader
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        CurrencyHelper $currencyHelper,
        SubjectReader $subjectReader
    ) {
        parent::__construct($resultFactory);
        $this->currencyHelper = $currencyHelper;
        $this->subjectReader = $subjectReader;
    }
}

