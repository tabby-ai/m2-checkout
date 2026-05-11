<?php
namespace Tabby\Checkout\Gateway\Request;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Gateway\ConfigInterface;
use Tabby\Checkout\Gateway\Helper\Data as DataHelper;

class SecretKeyDataBuilder implements BuilderInterface
{
    /**
     * @var ConfigInterface
     */
    private $moduleConfig;

    /**
     * @param MerchantCodeProviderInterface $merchantCodeProvider
     */
    public function __construct(
        ConfigInterface $moduleConfig
    ) {
        $this->moduleConfig = $moduleConfig;
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

        return ['secret_key' => $this->moduleConfig->getValue(
            DataHelper::KEY_SECRET_KEY,
            $paymentDO->getPayment()->getOrder()->getStoreId()
        )];
    }
}
