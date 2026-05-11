<?php
namespace Tabby\Checkout\Gateway\Http\TransferFactory;

use Tabby\Checkout\Gateway\Http\TransferFactory\FetchPayment;
use Tabby\Checkout\Model\Api\Http\Method as HttpMethod;

class RefundPayment extends FetchPayment 
{
    protected const API_ENDPOINT = 'payments/%s/refunds';
    protected const API_METHOD = HttpMethod::METHOD_POST;
}
