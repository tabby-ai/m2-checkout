<?php

namespace Tabby\Checkout\Model\Api\Tabby;

use Magento\Framework\Exception\LocalizedException;
use Tabby\Checkout\Exception\NotFoundException;
use Tabby\Checkout\Exception\NotAuthorizedException;
use Tabby\Checkout\Model\Api\Tabby;
use Zend_Http_Client_Exception;

class Webhooks extends Tabby
{
    const API_PATH = 'webhooks';

    /**
     * @param $storeId
     * @param null $merchantCode
     * @return mixed
     * @throws LocalizedException
     * @throws NotFoundException
     */
    public function getWebhooks($storeId, $merchantCode = null)
    {
        if (!is_null($merchantCode)) {
            $this->setMerchantCode($merchantCode);
        }

        return $this->request($storeId);
    }

    /**
     * @param $merchantCode
     */
    public function setMerchantCode($merchantCode)
    {
        $this->_headers['X-Merchant-Code'] = $merchantCode;
    }

    /**
     * @param $storeId
     * @param $merchantCode
     * @param $url
     * @return bool|void
     * @throws LocalizedException
     */
    public function registerWebhook($storeId, $merchantCode, $url)
    {
        try {
            $webhooks = $this->getWebhooks($storeId, $merchantCode);
        } catch (NotFoundException $e) {
            return;
        } catch (NotAuthorizedException $e) {
            return;
        }

        $this->_ddlog->log("info", "check webhooks for " . $merchantCode, null,
            ['webhooks' => $webhooks, 'url' => $url]);

        if (is_object($webhooks) && property_exists($webhooks,
                'errorType') && $webhooks->errorType == 'not_authorized') {
            $this->_ddlog->log("info", "Store code not authorized for merchant", null, ['code' => $merchantCode]);
            return false;
        }

        $registered = false;
        foreach ($webhooks as $webhook) {
            if ($webhook->url == $url) {
                if ($webhook->is_test != $this->getIsTest($storeId)) {
                    $webhook->is_test = $this->getIsTest($storeId);
                    $this->updateWebhook($storeId, $merchantCode, $webhook);
                }
                $registered = true;
            }
        }

        if (!$registered) {
            $this->createWebhook($storeId, $merchantCode, ['url' => $url, 'is_test' => $this->getIsTest($storeId)]);
            $registered = true;
        }
        return $registered;
    }

    /**
     * @param $storeId
     * @return bool
     */
    protected function getIsTest($storeId)
    {
        return (substr($this->getSecretKey($storeId), 0, 7) === 'sk_test');
    }

    /**
     * @param $storeId
     * @param $merchantCode
     * @param $data
     * @return mixed
     * @throws LocalizedException
     * @throws NotFoundException|Zend_Http_Client_Exception
     */
    public function updateWebhook($storeId, $merchantCode, $data)
    {
        $data = (array)$data;

        $this->setMerchantCode($merchantCode);

        return $this->request($storeId, '/' . $data['id'], \Zend_Http_Client::PUT, [
            'url' => $data['url'],
            'is_test' => $data['is_test']
        ]);
    }

    /**
     * @param $storeId
     * @param $merchantCode
     * @param $data
     * @return mixed
     * @throws LocalizedException
     * @throws NotFoundException
     */
    public function createWebhook($storeId, $merchantCode, $data)
    {
        $data = (array)$data;

        if (array_key_exists('id', $data)) {
            return $this->updateWebhook($storeId, $merchantCode, $data);
        }

        $this->setMerchantCode($merchantCode);

        return $this->request($storeId, '', \Zend_Http_Client::POST, [
            'url' => $data['url'],
            'is_test' => $data['is_test']
        ]);
    }
}
