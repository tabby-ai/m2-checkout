<?php
namespace Tabby\Checkout\Model\Api;

use Magento\Framework\Module\ModuleList;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\StoresConfig;
use Magento\Framework\HTTP\ClientFactory;

class DdLog
{
    private const LOG_URL = 'https://http-intake.logs.datadoghq.eu/v1/input';
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
     * @var StoresConfig
     */
    protected $_storesConfig;

    /**
     * Class constructor
     *
     * @param StoreManagerInterface $storeManager
     * @param ModuleList $moduleList
     * @param ClientFactory $httpClientFactory
     * @param StoresConfig $storesConfig
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ModuleList $moduleList,
        ClientFactory $httpClientFactory,
        StoresConfig $storesConfig
    ) {
        $this->_storeManager = $storeManager;
        $this->_moduleList = $moduleList;
        $this->_clientFactory = $httpClientFactory;
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

                "service" => "magento2",
                "hostname" => $storeHost,
                "settings" => $this->getModuleSettings(),
                "code" => $this->_storeManager->getStore()->getCode(),

                "ddsource" => "php",
                "ddtags" => sprintf("env:prod,version:%s", $moduleInfo["setup_version"])
            ];

            if ($e) {
                $log["error.kind"] = $e->getCode();
                $log["error.message"] = $e->getMessage();
                $log["error.stack"] = $e->getTraceAsString();
            }

            if ($data) {
                $log["data"] = $data;
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
        $client = $this->_clientFactory->create();
        $client->addHeader("DD-API-KEY", 'pubd0a8a1db6528927ba1877f0899ad9553');
        $client->addHeader("Content-type", 'application/json');
        $client->post(self::LOG_URL, $log_data);
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
                     'payment/tabby_cc_installments' => 'CC Installments'
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
                    if ($key == 'secret_key' && !strstr($settings[$store->getCode()][$name][$key], '_test_')) {
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
}
