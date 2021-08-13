<?php

namespace Tabby\Checkout\Model\Api\Tabby;

class Webhooks extends \Tabby\Checkout\Model\Api\Tabby {
    const API_PATH = 'webhooks';

    public function getWebhooks($merchantCode = null) {
        if (!is_null($merchantCode)) $this->setMerchantCode($merchantCode);

        return $this->request();
    }

    public function setMerchantCode($merchantCode) {
        $this->_headers['X-Merchant-Code'] = $merchantCode;
    }

    public function registerWebhook($merchantCode, $url) {

        try {
            $webhooks = $this->getWebhooks($merchantCode);
        } catch (\Tabby\Checkout\Exception\NotFoundException $e) {
            return;
        }

        $this->_ddlog->log("info", "check webhooks for " . $merchantCode, null, ['webhooks' => $webhooks, 'url' => $url]);

        if (is_object($webhooks) && property_exists($webhooks, 'errorType') && $webhooks->errorType == 'not_authorized') {
            $this->_ddlog->log("info", "Store code not authorized for merchant", null, ['code' => $merchantCode]);
            return false;
        }

        $registered = false;
        foreach ($webhooks as $webhook) {
            if ($webhook->url == $url) {
                if ($webhook->is_test != $this->getIsTest()) {
                    $webhook->is_test = $this->getIsTest();
                    $this->updateWebhook($merchantCode, $webhook);
                }
                $registered = true;
            }
        }

        if (!$registered) {
            $this->createWebhook($merchantCode, ['url' => $url, 'is_test' => $this->getIsTest()]);
            $registered = true;
        }
        return $registered;
    }

    protected function getIsTest() {
        return (substr($this->_secretKey, 0, 7) === 'sk_test');
    }

    public function updateWebhook($merchantCode, $data) {
        $data = (array)$data;

        $this->setMerchantCode($merchantCode);

        return $this->request('/' . $data['id'], \Zend_Http_Client::PUT, [
            'url'     => $data['url'],
            'is_test' => $data['is_test']
        ]);
    }
    public function createWebhook($merchantCode, $data) {
        $data = (array)$data;

        if (array_key_exists('id', $data)) return $this->updateWebhook($merchantCode, $data); 

        $this->setMerchantCode($merchantCode);

        return $this->request('', \Zend_Http_Client::POST, [
            'url'     => $data['url'],
            'is_test' => $data['is_test']
        ]);
    }
}
