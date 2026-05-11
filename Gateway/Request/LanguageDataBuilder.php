<?php
namespace Tabby\Checkout\Gateway\Request;

Use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class LanguageDataBuilder implements BuilderInterface
{
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param ConfigInterface $config
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Create Session request body
     *
     * @param PaymentDataObjectInterface $paymentDO
     * @return array
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);

        $localeCode = $this->scopeConfig->getValue(
            'general/locale/code',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $paymentDO->getPayment()->getOrder()->getStoreId()
        );

        return ["lang" => strstr($localeCode, '_', true) == 'en' ? 'en' : 'ar'];
    }
}
