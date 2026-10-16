<?php
namespace Tabby\Checkout\Model\Api;

use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Module\ModuleList;
use GuzzleHttp\ClientFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\StoresConfig;

class DdLog
{
    private const LOG_URL = 'https://http-intake.logs.datadoghq.eu/v1/input';
    private const DD_CLIENT_TOKEN = "pubc0ef0c7691ebf9eb4c1d08d6c711cfc5";
    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var ModuleList
     */
    protected $_moduleList;

    /**
     * @var ClientFactory
     */
    protected $_clientFactory;

    /**
     * @var ProductMetadataInterface
     */
    protected $_productMetadata;

    /**
     * @var StoresConfig
     */
    protected $_storesConfig;

    /**
     * Class constructor
     *
     * @param StoreManagerInterface $storeManager
     * @param ModuleList $moduleList
     * @param ClientFactory $httpClientFactory
     * @param ProductMetadataInterface $productMetadata
     * @param StoresConfig $storesConfig
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ModuleList $moduleList,
        ClientFactory $httpClientFactory,
        ProductMetadataInterface $productMetadata,
        StoresConfig $storesConfig
    ) {
        $this->_storeManager = $storeManager;
        $this->_moduleList = $moduleList;
        $this->_clientFactory = $httpClientFactory;
        $this->_productMetadata = $productMetadata;
        $this->_storesConfig = $storesConfig;
    }

    /**
     * Log data to datadog
     *
     * @param string $status
     * @param string $message
     * @param ?\Exception $e
     * @param ?array $data
     */
    public function log($status = "error", $message = "Something went wrong", $e = null, $data = null)
    {
        try {
            $storeUrl = $this->_storeManager->getStore()->getBaseUrl();
            $storeHost = 'unknown';
            if (preg_match("/https?\:\/\/([^\/]+)\/?/is", $storeUrl, $matches)) {
                $storeHost = $matches[1];
            }

            $moduleInfo = $this->_moduleList->getOne('Tabby_Checkout');

            $log = [
                "status" => $status,
                "message" => $message,
                "service"  => "magento2",
                "sversion" => $this->_productMetadata->getVersion(),
                "sedition" => $this->_productMetadata->getEdition(),
                "hostname" => $storeHost,
                "settings" => $this->getModuleSettings(),
                "code" => $this->_storeManager->getStore()->getCode(),
                "ddsource" => "php",
                "ddtags" => sprintf("env:prod,version:%s", $moduleInfo["setup_version"]),
            ];

            if ($e) {
                $log["error.kind"] = $e->getCode();
                $log["error.message"] = $e->getMessage();
                $log["error.stack"] = $e->getTraceAsString();
            }

            if ($data) {
                $log["data"] = self::maskData($data);
            }

            $log_data = json_encode($log);

            $this->send($log_data);
        } catch (\Exception $e) {
            // do not generate any exceptions
            $e->getCode();
        }
    }

    /**
     * Send data to datadog
     *
     * @param array $log_data
     */
    private function send($log_data)
    {
        $client = $this->_clientFactory->create([
        ]);
        $p = $client->postAsync(self::LOG_URL, [
            'json' => [$log_data],
            'headers' => [
                "Content-type"  => 'application/json',
                "DD-API-KEY"    => self::DD_CLIENT_TOKEN,
            ]
        ]);
        register_shutdown_function(function () use(&$p) {
            try {
                $p->wait();
            } catch (\Exception $e) {
            }
        });
    }

    /**
     * Build current configuration data for Tabby module
     *
     * @return array
     */
    private function getModuleSettings()
    {
        $settings = [];
        $stores = $this->_storeManager->getStores(true);
        foreach ([
                     'tabby/tabby_api' => 'Tabby Api',
                     'payment/tabby_checkout' => 'Pay Later',
                     'payment/tabby_installments' => 'Installments',
                     'payment/tabby_cc_installments' => 'CC Installments',
                 ] as $path => $name) {
            $config = $this->_storesConfig->getStoresConfigByPath($path);
            foreach ($stores as $store) {
                if (!array_key_exists($store->getCode(), $settings)) {
                    $settings[$store->getCode()] = [];
                }
                $settings[$store->getCode()][$name] = array_key_exists(
                    $store->getId(),
                    $config
                ) ? $config[$store->getId()] : [];
                foreach ($settings[$store->getCode()][$name] as $key => $value) {
                    if ($key == 'secret_key') {
                        $settings[$store->getCode()][$name][$key] = strstr(
                            $settings[$store->getCode()][$name][$key],
                            '-',
                            true
                        );
                    }
                }
            }
        }
        return $settings;
    }

    public static function maskData($data, $parent = '') {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = is_array($value)
                    ? static::maskData($value, is_string($key) ? $key : $parent)
                    : static::maskScalar($key, $value, $parent);
            }
            return $data;
        }
        return static::maskScalar('', $data, $parent);
    }

    protected static function maskScalar($key, $value, $parent = '') {
        if (is_null($value) || $value === '') return $value;
        $k = strtolower((string)$key);
        if ($k == 'email') return static::maskEmail($value);
        if ($k == 'phone' || $k == 'telephone') return '***' . substr((string)$value, -3);
        if (in_array($k, array('dob', 'address', 'address_1', 'address_2', 'first_name', 'last_name'))) return '***';
        if ($k == 'name' && strtolower($parent) == 'buyer') return '***';
        if (is_string($value)) return static::maskString($value);
        return $value;
    }

    protected static function maskEmail($value) {
        if (!is_string($value) || ($pos = strrpos($value, '@')) === false) return '***';
        // keep the domain - an allowed plaintext fragment
        return '***' . substr($value, $pos);
    }

    protected static function maskString($value) {
        // secret keys (Authorization headers, request bodies)
        $value = preg_replace('/sk_[0-9a-zA-Z_.\-]+([0-9a-zA-Z]{4})/', 'sk_...$1', $value);
        // PII fields inside JSON-encoded bodies; keep email domain and phone tail as debug fragments
        $value = preg_replace('/("email"\s*:\s*")((?:\\\\.|[^"\\\\])*)(@(?:\\\\.|[^"\\\\])*")/u', '$1***$3', $value);
        $value = preg_replace('/("(?:phone|telephone)"\s*:\s*")((?:\\\\.|[^"\\\\])*?)([0-9]{0,3}")/u', '$1***$3', $value);
        $value = preg_replace('/("(?:name|first_name|last_name|dob|address|address_1|address_2)"\s*:\s*")((?:\\\\.|[^"\\\\])*)(")/u', '$1***$3', $value);
        return $value;
    }

}
