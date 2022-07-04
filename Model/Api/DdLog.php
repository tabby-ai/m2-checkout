<?php

namespace Tabby\Checkout\Model\Api;

use Magento\Framework\Module\ModuleList;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\StoresConfig;

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
        StoresConfig $storesConfig
    ) {
        $this->_storeManager = $storeManager;
        $this->_moduleList = $moduleList;
        $this->_storesConfig = $storesConfig;
    }

    /**
     * @param string $status
     * @param string $message
     * @param null $e
     * @param null $data
     */
    public function log($status = "error", $message = "Something went wrong", $e = null, $data = null)
    {
        try {
            $client = new \Zend_Http_Client("https://http-intake.logs.datadoghq.eu/v1/input");

            $client->setMethod(\Zend_Http_Client::POST);
            $client->setHeaders("DD-API-KEY", "pubd0a8a1db6528927ba1877f0899ad9553");
            $client->setHeaders(\Zend_Http_Client::CONTENT_TYPE, 'application/json');

            $storeURL = parse_url($this->_storeManager->getStore()->getBaseUrl());

            $moduleInfo = $this->_moduleList->getOne('Tabby_Checkout');

            $log = array(
                "status" => $status,
                "message" => $message,

                "service" => "magento2",
                "hostname" => $storeURL["host"],
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
            $client->setRawData($params);

            $client->request();
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
                     'payment/tabby_installments' => 'Installments'
                 ] as $path => $name) {
            $config = $this->_storesConfig->getStoresConfigByPath($path);
            foreach ($stores as $store) {
                if (!array_key_exists($store->getCode(), $settings)) {
                    $settings[$store->getCode()] = [];
                }
                $settings[$store->getCode()][$name] = array_key_exists($store->getId(),
                    $config) ? $config[$store->getId()] : [];
            }
        }
        return $settings;
    }
}
