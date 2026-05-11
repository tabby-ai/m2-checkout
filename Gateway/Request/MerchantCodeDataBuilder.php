<?php
namespace Tabby\Checkout\Gateway\Request;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Tabby\Checkout\Api\MerchantCodeProviderInterface;

class MerchantCodeDataBuilder implements BuilderInterface
{
    /**
     * @var MerchantCodeProviderInterface
     */
    private $merchantCodeProvider;

    /**
     * @param MerchantCodeProviderInterface $merchantCodeProvider
     */
    public function __construct(
        MerchantCodeProviderInterface $merchantCodeProvider
    ) {
        $this->merchantCodeProvider = $merchantCodeProvider;
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

        return ['merchant_code' => $this->merchantCodeProvider->getMerchantCodeForOrder(
            $paymentDO->getPayment()->getOrder()
        )];
    }
}
