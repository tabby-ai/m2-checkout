<?php
namespace Tabby\Checkout\Gateway\Http;

use Magento\Payment\Gateway\Http\TransferBuilder;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Tabby\Checkout\Model\Api\Http\Method as HttpMethod;

class TransferFactory implements TransferFactoryInterface
{
    protected const API_BASE = 'https://api.%s/api/%s/%s';
    protected const API_METHOD = HttpMethod::METHOD_POST;
    protected const API_ENDPOINT = '';
    protected const API_VERSION = 'v2';

    private $transferBuilder;

    public function __construct(TransferBuilder $transferBuilder) {
        $this->transferBuilder = $transferBuilder;
    }

    public function create(array $request): TransferInterface
    {
        $uri = $this->getURI($request);

        $secretKey = $request['secret_key'];
        unset($request['secret_key']);

        return $this->transferBuilder
            ->setBody($request)
            ->setMethod(static::API_METHOD)
            ->setUri($uri)
            ->setHeaders([
                'Authorization' => 'Bearer ' . $secretKey,
                'Content-Type' => 'application/json'
            ])
            ->build();
    }

    protected function getURI(array &$request) {
        $uri = sprintf(self::API_BASE, $request['api_domain'], static::API_VERSION, static::API_ENDPOINT);
        unset($request['api_domain']);

        return $uri;
    }
}

