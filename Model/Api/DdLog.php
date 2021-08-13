<?php
namespace Tabby\Checkout\Model\Api;

class DdLog {

    /**
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\Module\ModuleList $moduleList 
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Module\ModuleList $moduleList
    ) {
        $this->_storeManager = $storeManager;
        $this->_moduleList   = $moduleList;
    }

    public function log($status = "error", $message = "Something went wrong", $e = null, $data = null) {
        try {
            $client = new \Zend_Http_Client("https://http-intake.logs.datadoghq.eu/v1/input");
    
            $client->setMethod(\Zend_Http_Client::POST);
            $client->setHeaders("DD-API-KEY", "a06dc07e2866305cda6ed90bf4e46936");
            $client->setHeaders(\Zend_Http_Client::CONTENT_TYPE, 'application/json');
    
            $storeURL = parse_url($this->_storeManager->getStore()->getBaseUrl());
    
            $moduleInfo =  $this->_moduleList->getOne('Tabby_Checkout');
    
            $log = array(
                "status"  => $status,
                "message" => $message,

                "service"  => "magento2",
                "hostname" => $storeURL["host"],
    
                "ddsource" => "php",
                "ddtags"   => sprintf("env:prod,version:%s", $moduleInfo["setup_version"])
            );

            if ($e) {
                $log["error.kind"]    = $e->getCode();
                $log["error.message"] = $e->getMessage();
                $log["error.stack"]   = $e->getTraceAsString();
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
}
