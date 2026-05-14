<?php
namespace Tabby\Checkout\Gateway\Request;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Tabby\Checkout\Gateway\Helper\Domain as DomainHelper;
use Tabby\Checkout\Gateway\Helper\Currency as CurrencyHelper;

class ApiDomainDataBuilder implements BuilderInterface
{
    /**
     * @var DomainHelper
     */
    private $domainHelper;

    /**
     * @var CurrencyHelper
     */
    private $currencyHelper;

    /**
     * @param DomainHelper $domainHelper
     */
    public function __construct(
        CurrencyHelper $currencyHelper,
        DomainHelper $domainHelper
    ) {
        $this->currencyHelper = $currencyHelper;
        $this->domainHelper = $domainHelper;
    }

    /**
     * Build merchant code for request
     *
     * @param  array $buildSubject
     * @return array
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);

        return ['api_domain' => $this->domainHelper->getTabbyDomainByCurrencyCode(
            (string)$this->currencyHelper->getTabbyCurrency($paymentDO->getPayment()->getOrder())
        )];
    }
}
