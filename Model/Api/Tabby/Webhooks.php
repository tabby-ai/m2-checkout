<?php

namespace Tabby\Checkout\Model\Api\Tabby;

use Magento\Framework\Exception\LocalizedException;
use Tabby\Checkout\Exception\NotFoundException;
use Tabby\Checkout\Exception\NotAuthorizedException;
use Tabby\Checkout\Model\Api\Tabby;
use Tabby\Checkout\Model\Api\Http\Method as HttpMethod;

class Webhooks extends Tabby
{
    protected const API_PATH = 'webhooks';

    /**
     * Webhook list getter
     *
     * @param int $storeId
     * @param ?string $merchantCode
     * @return mixed
     * @throws LocalizedException
     * @throws NotFoundException
     */
    public function getWebhooks($storeId, $merchantCode = null)
    {
        if ($merchantCode !== null) {
            $this->setMerchantCode($merchantCode);
        }

        return $this->request($storeId);
    }

    /**
     * Merchant code setter for requests
     *
     * @param string $merchantCode
     */
    public function setMerchantCode($merchantCode)
    {
        $this->_headers['X-Merchant-Code'] = $merchantCode;
    }

    /**
     * Register webhook for store and merchant code
     *
     * @param int $storeId
     * @param string $merchantCode
     * @param string $url
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

        $this->_ddlog->log("info", "check webhooks for " . $merchantCode, null, [
            'webhooks' => $webhooks,
            'url' => $url
        ]);

        if (is_object($webhooks)
            && property_exists($webhooks, 'errorType')
            && $webhooks->errorType == 'not_authorized'
        ) {
            $this->_ddlog->log("info", "Store code not authorized for merchant", null, ['code' => $merchantCode]);
            return false;
        }

        $registered = false;
        foreach ($webhooks as $webhook) {
            if ($webhook->url == $url) {
                try {
                    if ($webhook->is_test != $this->getIsTest($storeId)) {
                        $webhook->is_test = $this->getIsTest($storeId);
                        $this->updateWebhook($storeId, $merchantCode, $webhook);
                    }
                    $registered = true;
                } catch (\Exception $e) {
                    $this->_ddlog->log(
                        "error",
                        "Error updating webhook",
                        $e,
                        ['code' => $merchantCode, 'webhook' => $webhook]
                    );
                }
            }
        }

        if (!$registered) {
            try {
                $this->createWebhook($storeId, $merchantCode, ['url' => $url, 'is_test' => $this->getIsTest($storeId)]);
                $registered = true;
            } catch (\Exception $e) {
                $this->_ddlog->log("error", "Error creating webhook", $e, [
                    'code' => $merchantCode,
                    'url' => $url,
                    'is_test' => $this->getIsTest($storeId)
                ]);
            }
        }
        return $registered;
    }

    /**
     * Check secret key is test one
     *
     * @param string $storeId
     * @return bool
     */
    protected function getIsTest($storeId)
    {
        return (substr($this->getSecretKey($storeId), 0, 7) === 'sk_test');
    }

    /**
     * Update webhook url and is_test by webhook id
     *
     * @param int $storeId
     * @param string $merchantCode
     * @param array $data
     * @return mixed
     * @throws LocalizedException
     * @throws NotFoundException
     */
    public function updateWebhook($storeId, $merchantCode, $data)
    {
        $data = (array)$data;

        $this->setMerchantCode($merchantCode);

        return $this->request($storeId, '/' . $data['id'], HttpMethod::METHOD_PUT, [
            'url' => $data['url'],
            'is_test' => $data['is_test']
        ]);
    }

    /**
     * Create webhook for specific store and merchant code
     *
     * @param int $storeId
     * @param string $merchantCode
     * @param array $data
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

        return $this->request($storeId, '', HttpMethod::METHOD_POST, [
            'url' => $data['url'],
            'is_test' => $data['is_test']
        ]);
    }
}
