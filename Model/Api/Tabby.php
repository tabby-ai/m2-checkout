<?php
namespace Tabby\Checkout\Model\Api;

class Tabby {
    const API_BASE = 'https://api.tabby.ai/api/v1/';
    const API_PATH = '';

    /**
    * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
    * @var \Tabby\Checkout\Model\Api\DdLog
     */
    protected $_ddlog;

    /**
    * @var string
     */
    protected $_secretKey = null;

    /**
    * @var []
     */
    protected $_headers = [];


    /**
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param DdLog $ddlog
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Tabby\Checkout\Gateway\Config\Config $tabbyConfig,
        DdLog $ddlog
    ) {
        $this->_storeManager = $storeManager;
        $this->_tabbyConfig  = $tabbyConfig;
        $this->_ddlog = $ddlog;
    }

    public function request($endpoint = '', $method = \Zend_Http_Client::GET, $data = null) {

        $client = new \Zend_Http_Client($this->getRequestURI($endpoint), array('timeout' => 120));

        $client->setUri($this->getRequestURI($endpoint));
        $client->setMethod($method);
        $client->setHeaders("Authorization", "Bearer " . $this->getSecretKey());

        if ($method !== \Zend_Http_Client::GET) {
            $client->setHeaders(\Zend_Http_Client::CONTENT_TYPE, 'application/json');
            $params = json_encode($data);
            $client->setRawData($params); //json
        }
        foreach ($this->_headers as $key => $value) $client->setHeaders($key, $value);

        $response = $client->request();

        $this->logRequest($this->getRequestURI($endpoint), $client, $response);

        $result = [];

        switch ($response->getStatus()) {
        case 200:
            $result = json_decode($response->getBody());
            break;
        case 404:
            throw new \Tabby\Checkout\Exception\NotFoundException(
                __("Transaction does not exists")
            );
        default:
            $body = $response->getBody();
            $msg = "Server returned: " . $response->getStatus() . '. ';
            if (!empty($body)) {
                $result = json_decode($body);
                $msg .= $result->errorType;
                if (property_exists($result, 'error')) {
                    $msg .= ': ' . $result->error;
                    if ($result->error == 'already closed' && preg_match("#close$#", $endpoint)) return $result;
                }
            }
            throw new \Magento\Framework\Exception\LocalizedException(
                __($msg)
            );
        }

        return $result;
    }
    protected function getSecretKey() {
        if (!$this->_secretKey) {
            $this->_secretKey = $this->_tabbyConfig->getSecretKey();
        }
        return $this->_secretKey;
    }
    public function setSecretKey($value) {
        $this->_secretKey = $value;
        return $this;
    }
    public function reset() {
        $this->_secretKey = null;
        $this->_headers   = [];
        return $this;
    }
    protected function getRequestURI($endpoint) {
        return self::API_BASE . static::API_PATH . $endpoint;
    }
    protected function logRequest($url, $client, $response) {
        $logData = array(
            "request.url"       => $url,
            "request.body"      => $client->getLastRequest(),
            "response.body"     => $response->getBody(),
            "response.code"     => $response->getStatus(),
            "response.headers"  => $response->getHeaders()
        );
        $this->_ddlog->log("info", "api call", null, $logData);

        return $this;
    }
}
