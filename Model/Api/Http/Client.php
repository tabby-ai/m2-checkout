<?php
namespace Tabby\Checkout\Model\Api\Http;

use \Magento\Framework\HTTP\Client\Curl;
use Tabby\Checkout\Model\Api\Http\Method as HttpMethod;

class Client extends Curl {
    public function send($method, $url, $data) {
        $params = [];
        if ($method !== HttpMethod::METHOD_GET) {
            $params = json_encode($data);
            $this->addHeader('Content-type', 'application/json');
            $this->addHeader('Content-length', strlen($params));
        }

        return $this->makeRequest($method, $url, $params);
    }
}
