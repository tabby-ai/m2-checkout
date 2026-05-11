<?php
namespace Tabby\Checkout\Gateway\Request;

use Magento\Framework\UrlInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;

class MerchantUrlsDataBuilder implements BuilderInterface
{
    /**
     * @var UrlInterface
     */
    private $urlInterface;

    /**
     * @param UrlInterface $urlInterface
     */
    public function __construct(
        UrlInterface $urlInterface
    ) {
        $this->urlInterface = $urlInterface;
    }

    /**
     * Build merchant urls array for request
     *
     * @param  array $buildSubject
     * @return array
     */
    public function build(array $buildSubject): array
    {
        return [
            'merchant_urls' => [
                'success'   => $this->urlInterface->getUrl('tabby/result/success'),
                'cancel'    => $this->urlInterface->getUrl('tabby/result/cancel'),
                'failure'   => $this->urlInterface->getUrl('tabby/result/failure'),
            ]
        ];
    }

}
