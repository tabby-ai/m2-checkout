<?php
namespace Tabby\Checkout\Gateway\Http\TransferFactory;

use Tabby\Checkout\Gateway\Http\TransferFactory;
use Tabby\Checkout\Model\Api\Http\Method as HttpMethod;

class FetchPayment extends TransferFactory 
{
    protected const API_ENDPOINT = 'payments/%s';
    protected const API_METHOD = HttpMethod::METHOD_GET;

    protected function getURI(array &$request) {
        $uri = sprintf(parent::getURI($request), $request['payment_id']);

        unset($request['payment_id']);

        return $uri;
    }
}
