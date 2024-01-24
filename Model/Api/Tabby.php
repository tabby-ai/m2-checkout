<?php

namespace Tabby\Checkout\Model\Api;

use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Tabby\Checkout\Exception\NotFoundException;
use Tabby\Checkout\Exception\NotAuthorizedException;
use Tabby\Checkout\Gateway\Config\Config;
use Tabby\Checkout\Model\Api\Http\Method as HttpMethod;
use Tabby\Checkout\Model\Api\Http\Client as HttpClient;

class Tabby
{
    const API_BASE = 'https://api.tabby.ai/api/%s/';
    const API_VERSION = 'v1';
    const API_PATH = '';

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var DdLog
     */
    protected $_ddlog;

    /**
     * @var Array
     */
    protected $_secretKey = [];

    /**
     * @var Array
     */
    protected $_headers = [];

    /**
     * @var Config
     */
    protected $_tabbyConfig;

    /**
     * @param StoreManagerInterface $storeManager
     * @param Config $tabbyConfig
     * @param DdLog $ddlog
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        Config $tabbyConfig,
        DdLog $ddlog
    ) {
        $this->_storeManager = $storeManager;
        $this->_tabbyConfig = $tabbyConfig;
        $this->_ddlog = $ddlog;
    }

    /**
     * @param $storeId
     * @param string $endpoint
     * @param string $method
     * @param null $data
     * @return mixed
     * @throws NotFoundException
     * @throws LocalizedException
     */

    public function request($storeId, $endpoint = '', $method = HttpMethod::METHOD_GET, $data = null)
    {

        $url = $this->getRequestURI($endpoint);

        $client = new HttpClient();
        $client->setTimeout(120);
        $client->addHeader('Authorization', 'Bearer ' . $this->getSecretKey($storeId));

        foreach ($this->_headers as $key => $value) {
            $client->addHeader($key, $value);
        }

        $client->send($method, $url, $data);

        $this->logRequest($url, $client, $data);

        $result = [];

        switch ($client->getStatus()) {
            case 200:
                $result = json_decode($client->getBody());
                break;
            case 404:
                throw new NotFoundException(
                    __("Transaction does not exists")
                );
                break;
            case 401:
                throw new NotAuthorizedException(
                    __("Not Authorized")
                );
                break;
            default:
                $body = $client->getBody();
                $msg = "Server returned: " . $client->getStatus() . '. ';
                if (!empty($body)) {
                    $result = json_decode($body);
                    $msg .= $result->errorType;
                    if (property_exists($result, 'error')) {
                        $msg .= ': ' . $result->error;
                        if ($result->error == 'already closed' && preg_match("#close$#", $endpoint)) {
                            return $result;
                        }
                    }
                }
                throw new LocalizedException(
                    __($msg)
                );
        }

        return $result;
    }

    /**
     * @param $storeId
     * @return mixed|string|null
     */
    protected function getSecretKey($storeId)
    {
        if (!array_key_exists($storeId, $this->_secretKey)) {
            $this->_secretKey[$storeId] = $this->_tabbyConfig->getSecretKey($storeId);
        }
        return $this->_secretKey[$storeId];
    }

    /**
     * @param $storeId
     * @param $value
     * @return $this
     */
    public function setSecretKey($storeId, $value)
    {
        $this->_secretKey[$storeId] = $value;
        return $this;
    }

    /**
     * @return $this
     */
    public function reset()
    {
        $this->_secretKey = [];
        $this->_headers = [];
        return $this;
    }

    /**
     * @param $endpoint
     * @return string
     */
    protected function getRequestURI($endpoint)
    {
        return sprintf(self::API_BASE, static::API_VERSION) . static::API_PATH . $endpoint;
    }

    /**
     * @param $url
     * @param $client
     * @param $response
     * @return $this
     */
    protected function logRequest($url, $client, $requestData)
    {
        $logData = array(
            "request.url" => $url,
            "request.body" => json_encode($requestData),
            "response.body" => $client->getBody(),
            "response.code" => $client->getStatus(),
            "response.headers" => $client->getHeaders()
        );
        $this->_ddlog->log("info", "api call", null, $logData);

        return $this;
    }
}
