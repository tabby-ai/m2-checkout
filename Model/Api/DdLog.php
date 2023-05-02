<?php

namespace Tabby\Checkout\Model\Api;

use Magento\Framework\Module\ModuleList;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\StoresConfig;
use Laminas\Http\Request;
use Laminas\Http\Client;


class DdLog
{
    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var ModuleList
     */
    protected $_moduleList;

    /**
     * @var StoresConfig
     */
    protected $_storesConfig;

    /**
     * @param StoreManagerInterface $storeManager
     * @param ModuleList $moduleList
     * @param StoresConfig $storesConfig
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ModuleList $moduleList,
        Client $httpClient,
        StoresConfig $storesConfig
    ) {
        $this->_storeManager = $storeManager;
        $this->_moduleList = $moduleList;
        $this->_storesConfig = $storesConfig;
    }

    /**
     * @param string $status
     * @param string $message
     * @param ?\Exception $e
     * @param ?array $data
     */
    public function log($status = "error", $message = "Something went wrong", $e = null, $data = null)
    {
        try {
            $client = new Client("https://http-intake.logs.datadoghq.eu/v1/input");

            $client->setMethod(Request::METHOD_POST);
            $client->setHeaders(array("DD-API-KEY" => "pubd0a8a1db6528927ba1877f0899ad9553"));
            $client->setEncType('application/json');

            $storeURL = parse_url($this->_storeManager->getStore()->getBaseUrl());

            $moduleInfo = $this->_moduleList->getOne('Tabby_Checkout');

            $log = array(
                "status" => $status,
                "message" => $message,

                "service" => "magento2",
                "hostname" => array_key_exists('host', $storeURL) ? $storeURL['host'] : 'unknown',
                "settings" => $this->getModuleSettings(),
                "code" => $this->_storeManager->getStore()->getCode(),

                "ddsource" => "php",
                "ddtags" => sprintf("env:prod,version:%s", $moduleInfo["setup_version"])
            );

            if ($e) {
                $log["error.kind"] = $e->getCode();
                $log["error.message"] = $e->getMessage();
                $log["error.stack"] = $e->getTraceAsString();
            }

            if ($data) {
                $log["data"] = $data;
            }

            $params = json_encode($log);
            $client->setRawBody($params);

            $client->send();
        } catch (\Exception $e) {
            // do not generate any exceptions
        }
    }

    /**
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
                $settings[$store->getCode()][$name] = array_key_exists($store->getId(),
                    $config) ? $config[$store->getId()] : [];
                foreach ($settings[$store->getCode()][$name] as $key => $value) {
                    if ($key == 'secret_key' && !strstr($settings[$store->getCode()][$name][$key], '_test_')) $settings[$store->getCode()][$name][$key] = strstr($settings[$store->getCode()][$name][$key], '-', true);
                }
            }
        }
        return $settings;
    }
}
