<?php

namespace Tabby\Checkout\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;

class ConfigObserver implements ObserverInterface
{
    const ALLOWED_CURRENCIES = ['AED', 'BHD', 'KWD', 'SAR'];
    const API_URI = 'https://api.tabby.ai/api/v1/';
    private $_secretKey = [];

    public function __construct(
        \Tabby\Checkout\Model\Api\Tabby\Webhooks $webhooks,
        \Magento\Framework\Url $urlHelper,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Store\Model\StoreManager   $storeManager
    ) {
        $this->_api          = $webhooks;
        $this->_urlHelper    = $urlHelper;
        $this->_scopeConfig  = $scopeConfig;
        $this->_storeManager = $storeManager;
    }
    public function execute(EventObserver $observer)
    {
        try {
            foreach ($this->_storeManager->getWebsites(false, true) as $websiteCode => $website) {
                $this->checkWebhooks($website);
            }
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            // ignore exceptions
        }
    }
    private function checkWebhooks($website) {

        if (!$this->isConfigured($website->getCode())) return;

        // set website specific secret key
        $this->_api->setSecretKey($this->getSecretKey($website->getCode()));

        $stores = $this->_storeManager->getStores();
        $register_hooks = [];
        foreach ($stores as $store) {
            if ($store->getWebsiteId() != $website->getId()) continue;
            if ($this->isMethodActive($store->getId())) {
                if (!array_key_exists($store->getGroupId(), $register_hooks)) $register_hooks[$store->getGroupId()] = [];
                $register_hooks[$store->getGroupId()] = array_unique(array_merge($register_hooks[$store->getGroupId()], $store->getAvailableCurrencyCodes()));
            }
        }
        foreach ($register_hooks as $groupId => $currencies) {
            $group = $this->_storeManager->getGroup($groupId);
            $webhookUrl = $this->_urlHelper->getUrl('tabby/result/webhook', ['_scope' => $group->getDefaultStoreId()]);

            if ($this->getWebsiteConfigValue('tabby/tabby_api/local_currency', $website->getCode())) {
                $currencies = array_unique($currencies);
                foreach ($currencies as $currencyCode) {
                    // bypass not supported currencies
                    if (!in_array($currencyCode, self::ALLOWED_CURRENCIES)) continue;
                    $this->_api->registerWebhook($group->getCode() . '_' . $currencyCode, $webhookUrl);
                }
            } else {
                $this->_api->registerWebhook($group->getCode(), $webhookUrl);
            }
        }
    }

    private function isMethodActive($storeId) {
        $active = false;
        $methods = ['tabby_checkout', 'tabby_installments'];
        foreach ($methods as $method) {
            if ($this->_scopeConfig->getValue('payment/' . $method . '/active', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId)) $active = true;
        }
        return $active;
    }
    private function isConfigured($websiteCode) {
        return (bool)$this->getSecretKey($websiteCode);
    }
    private function getSecretKey($websiteCode) {
        if (!array_key_exists($websiteCode, $this->_secretKey)) {
            $this->_secretKey[$websiteCode] = $this->getWebsiteConfigValue('tabby/tabby_api/secret_key', $websiteCode);
        };
        return $this->_secretKey[$websiteCode];
    }
    private function getWebsiteConfigValue($path, $websiteCode) {
        return  $this->_scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITE, $websiteCode);
    }
}
