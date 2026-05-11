<?php
namespace Tabby\Checkout\Gateway\Http\TransferFactory;

use Tabby\Checkout\Gateway\Http\TransferFactory\FetchPayment;
use Tabby\Checkout\Model\Api\Http\Method as HttpMethod;

class CapturePayment extends FetchPayment 
{
    protected const API_ENDPOINT = 'payments/%s/captures';
    protected const API_METHOD = HttpMethod::METHOD_POST;
}
