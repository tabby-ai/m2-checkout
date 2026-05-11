<?php

namespace Tabby\Checkout\Model\Api;

use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Tabby\Checkout\Exception\NotAuthorizedException;
use Tabby\Checkout\Exception\NotFoundException;
use Tabby\Checkout\Exception\NonJsonException;
use Tabby\Checkout\Gateway\Helper\Domain as DomainHelper;
use Tabby\Checkout\Gateway\Helper\Data as DataHelper;
use Tabby\Checkout\Model\Api\Http\Client as HttpClient;
use Tabby\Checkout\Model\Api\Http\Method as HttpMethod;

class Tabby
{
    protected const API_BASE = 'https://api.%s/api/%s/';
    protected const API_VERSION = 'v2';
    protected const API_PATH = '';

    /**
     * @var DdLog
     */
    protected $ddlog;

    /**
     * @var Array
     */
    protected $_secretKey = [];

    /**
     * @var Array
     */
    protected $_headers = [];

    /**
     * @var ConfigInterface
     */
    protected $moduleConfig;

    /**
     * @var DomainHelper
     */
    protected $domainHelper;

    /**
     * @var string
     */
    protected $_country = 'AE';

    /*
     * @var HttpClient
     */
    protected $client;

    /**
     * @param ConfigInterface $moduleConfig
     * @param DomainHelper $domainHelper
     * @param HttpClient $client
     * @param DdLog $ddlog
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        ConfigInterface $moduleConfig,
        DomainHelper $domainHelper,
        HttpClient $client,
        DdLog $ddlog
    ) {
        $this->moduleConfig = $moduleConfig;
        $this->domainHelper = $domainHelper;
        $this->client = $client;
        $this->ddlog = $ddlog;
    }

    /**
     * Processing http request to Tabby API
     *
     * @param int $storeId
     * @param string $endpoint
     * @param string $method
     * @param array|null $data
     * @return mixed
     * @throws NotFoundException
     * @throws LocalizedException
     */

    public function request($storeId, $endpoint = '', $method = HttpMethod::METHOD_GET, $data = null)
    {

        $url = $this->getRequestURI($endpoint);

        $this->client->setTimeout(120);
        $this->client->addHeader('Authorization', 'Bearer ' . $this->getSecretKey($storeId));

        foreach ($this->_headers as $key => $value) {
            $this->client->addHeader($key, $value);
        }

        $this->client->send($method, $url, $data);

        $this->logRequest($url, $this->client, $data);

        $result = [];

        switch ($this->client->getStatus()) {
            case 100:
            case 200:
                $result = json_decode($this->client->getBody());
                if ($result === null) {
                    $this->logRequest($url, $this->client, $data, "warn", "non json reply received from Tabby API");
                }
                break;
            case 404:
                throw new NotFoundException(
                    __("Transaction does not exists")
                );
            case 401:
                throw new NotAuthorizedException(
                    __("Not Authorized")
                );
            default:
                $body = $this->client->getBody();
                $msg = "Server returned: " . $this->client->getStatus() . '. ';
                if (!empty($body)) {
                    $result = json_decode($body);
                    if (!$result) {
                        throw new LocalizedException(
                            __($body)
                        );
                    }
                    $msg .= property_exists($result, 'errorType') ? $result->errorType : '';
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
     * Secret key getter for specific store
     *
     * @param int $storeId
     * @return mixed|string|null
     */
    protected function getSecretKey($storeId)
    {
        if (!array_key_exists($storeId, $this->_secretKey)) {
            $this->_secretKey[$storeId] = $this->moduleConfig
                ->getValue(DataHelper::KEY_SECRET_KEY, $storeId);
        }
        return $this->_secretKey[$storeId];
    }

    /**
     * Secret key setter for specific store
     *
     * @param int $storeId
     * @param string $value
     * @return $this
     */
    public function setSecretKey($storeId, $value)
    {
        $this->_secretKey[$storeId] = $value;
        return $this;
    }

    /**
     * Reset secret keys/headers
     *
     * @return $this
     */
    public function reset()
    {
        $this->_secretKey = [];
        $this->_headers = [];
        return $this;
    }

    /**
     * Set API currency
     *
     * @param string $currency
     * @return $this
     */
    public function setCurrency($currency)
    {
        if ($currency !== null) {
            $country = substr($currency, 0, 2);
        }

        return $this->setCountry($country);
    }
    /**
     * Set API country
     *
     * @param string $country
     * @return $this
     */
    protected function setCountry($country)
    {
        $this->_country = $country;

        return $this;
    }

    /**
     * Construct API request URL
     *
     * @param string $endpoint
     * @return string
     */
    protected function getRequestURI($endpoint)
    {
        return sprintf(self::API_BASE, $this->domainHelper->getTabbyDomain($this->_country), static::API_VERSION) . static::API_PATH . $endpoint;
    }

    /**
     * Write request to logs
     *
     * @param string $url
     * @param HttpClient $client
     * @param array $requestData
     * @param string $level
     * @param string $msg
     * @return $this
     */
    protected function logRequest($url, $client, $requestData, $level = 'info', $msg = 'api call')
    {
        $logData = [
            "request.url" => $url,
            "request.body" => json_encode($requestData),
            "request.headers" => json_encode($this->_headers),
            "response.body" => $client->getBody(),
            "response.code" => $client->getStatus(),
            "response.headers" => $client->getHeaders(),
        ];
        if ($obj = json_decode($client->getBody(), true)) {
            $payment = [];
            if (isset($obj['payment']) && isset($obj['payment']['id'])) {
                $payment = $obj['payment'];
            } elseif (isset($obj['captures'])) {
                $payment = $obj;
            }
            if (isset($payment['id'])) {
                $logData['payment.id'] = $payment['id'];
            }
            if (isset($payment['order']['reference_id'])) {
                $logData['order.reference_id'] = $payment['order']['reference_id'];
            }
        }
        $this->ddlog->log($level, $msg, null, $logData);

        return $this;
    }
}
