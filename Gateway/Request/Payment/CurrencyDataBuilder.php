<?php
namespace Tabby\Checkout\Gateway\Request\Payment;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Tabby\Checkout\Gateway\Helper\Currency as CurrencyHelper;

class CurrencyDataBuilder implements BuilderInterface
{
    /**
     * @var CurrencyHelper
     */
    private $currencyHelper;

    /**
     * @param DomainHelper $domainHelper
     */
    public function __construct(
        CurrencyHelper $currencyHelper
    ) {
        $this->currencyHelper = $currencyHelper;
    }

    /**
     * Build currency array for request payment object
     *
     * @param  array $buildSubject
     * @return array
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);

        return ['currency' => (string)$this->currencyHelper->getTabbyCurrency(
            $paymentDO->getPayment()->getOrder()
        )];
    }
}
