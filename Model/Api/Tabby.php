<?php

namespace Tabby\Checkout\Model\Api;

use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Tabby\Checkout\Exception\NotFoundException;
use Tabby\Checkout\Exception\NotAuthorizedException;
use Tabby\Checkout\Gateway\Config\Config;

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
     * @var string
     */
    protected $_secretKey = [];

    /**
     * @var []
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
     * @throws \Zend_Http_Client_Exception
     */
    public function request($storeId, $endpoint = '', $method = \Zend_Http_Client::GET, $data = null)
    {

        $client = new \Zend_Http_Client($this->getRequestURI($endpoint), array('timeout' => 120));

        $client->setUri($this->getRequestURI($endpoint));
        $client->setMethod($method);
        $client->setHeaders("Authorization", "Bearer " . $this->getSecretKey($storeId));

        if ($method !== \Zend_Http_Client::GET) {
            $client->setHeaders(\Zend_Http_Client::CONTENT_TYPE, 'application/json');
            $params = json_encode($data);
            $client->setRawData($params); //json
        }
        foreach ($this->_headers as $key => $value) {
            $client->setHeaders($key, $value);
        }

        $response = $client->request();

        $this->logRequest($this->getRequestURI($endpoint), $client, $response);

        $result = [];

        switch ($response->getStatus()) {
            case 200:
                $result = json_decode($response->getBody());
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
                $body = $response->getBody();
                $msg = "Server returned: " . $response->getStatus() . '. ';
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
    protected function logRequest($url, $client, $response)
    {
        $logData = array(
            "request.url" => $url,
            "request.body" => $client->getLastRequest(),
            "response.body" => $response->getBody(),
            "response.code" => $response->getStatus(),
            "response.headers" => $response->getHeaders()
        );
        $this->_ddlog->log("info", "api call", null, $logData);

        return $this;
    }
}
