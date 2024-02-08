<?php

namespace Tabby\Checkout\Observer;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Url;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManager;
use Tabby\Checkout\Gateway\Config\Config;
use Tabby\Checkout\Model\Api\Tabby\Webhooks;

class ConfigObserver implements ObserverInterface
{
    public const ALLOWED_CURRENCIES = ['AED', 'BHD', 'KWD', 'SAR', 'QAR'];

    /**
     * @var array
     */
    private $_secretKey = [];

    /**
     * @var Webhooks
     */
    protected $_api;

    /**
     * @var Url
     */
    protected $_urlHelper;

    /**
     * @var ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * @var StoreManager
     */
    protected $_storeManager;

    /**
     * ConfigObserver constructor.
     *
     * @param Webhooks $webhooks
     * @param Url $urlHelper
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManager $storeManager
     */
    public function __construct(
        Webhooks $webhooks,
        Url $urlHelper,
        ScopeConfigInterface $scopeConfig,
        StoreManager $storeManager
    ) {
        $this->_api = $webhooks;
        $this->_urlHelper = $urlHelper;
        $this->_scopeConfig = $scopeConfig;
        $this->_storeManager = $storeManager;
    }

    /**
     * Main method, check for webhooks to be registered with Tabby
     *
     * @param EventObserver $observer
     */
    public function execute(EventObserver $observer)
    {
        try {
            foreach ($this->_storeManager->getWebsites(false, true) as $websiteCode => $website) {
                $this->checkWebhooks($website);
            }
        } catch (LocalizedException $e) {
            // ignore exceptions
            $e->getCode();
        }
    }

    /**
     * Checks webhooks is registered for website
     *
     * @param \Magento\Store\Model\Website $website
     * @throws LocalizedException
     */
    private function checkWebhooks($website)
    {
        if (!$this->isConfigured($website->getCode())) {
            return;
        }

        $stores = $this->_storeManager->getStores();
        $register_hooks = [];
        foreach ($stores as $store) {
            if ($store->getWebsiteId() != $website->getId()) {
                continue;
            }
            if ($this->isMethodActive($store->getId())) {
                if (!array_key_exists($store->getGroupId(), $register_hooks)) {
                    $register_hooks[$store->getGroupId()] = [];
                }
                foreach ($store->getAvailableCurrencyCodes() as $code) {
                    if (!in_array($code, $register_hooks[$store->getGroupId()])) {
                        $register_hooks[$store->getGroupId()][] = $code;
                    }
                }
            }
        }
        foreach ($register_hooks as $groupId => $currencies) {
            $group = $this->_storeManager->getGroup($groupId);
/*
            $webhookUrl = $this->_urlHelper->getUrl('tabby/result/webhook', ['_scope' => $group->getDefaultStoreId()]);
*/
            $webhookUrl = $this->_urlHelper->getUrl('rest/V1/tabby/webhook', [
                '_path' => 'enquiry',
                '_secure' => true,
                '_direct' => 'rest/V1/tabby/webhook',
                '_scope' => $group->getDefaultStoreId()
            ]);

            if ($this->getWebsiteConfigValue('tabby/tabby_api/local_currency', $website->getCode())) {
                $currencies = array_unique($currencies);
                foreach ($currencies as $currencyCode) {
                    // bypass not supported currencies
                    if (!in_array($currencyCode, self::ALLOWED_CURRENCIES)) {
                        continue;
                    }

                    $this->_api->registerWebhook(
                        $group->getDefaultStoreId(),
                        $group->getCode() . '_' . $currencyCode,
                        $webhookUrl
                    );
                }
            } else {
                $this->_api->registerWebhook($group->getDefaultStoreId(), $group->getCode(), $webhookUrl);
            }
        }
    }

    /**
     * Check at least one method active for given store id
     *
     * @param int $storeId
     * @return bool
     */
    private function isMethodActive($storeId)
    {
        $active = false;
        foreach (Config::ALLOWED_SERVICES as $method => $title) {
            if ($this->_scopeConfig->getValue(
                'payment/' . $method . '/active',
                ScopeInterface::SCOPE_STORE,
                $storeId
            )) {
                $active = true;
            }
        }
        return $active;
    }

    /**
     * Is secret key saved for website
     *
     * @param string $websiteCode
     * @return bool
     */
    private function isConfigured($websiteCode)
    {
        return (bool)$this->getSecretKey($websiteCode);
    }

    /**
     * Return secret key based on website code
     *
     * @param string $websiteCode
     * @return mixed
     */
    private function getSecretKey($websiteCode)
    {
        if (!array_key_exists($websiteCode, $this->_secretKey)) {
            $this->_secretKey[$websiteCode] = $this->getWebsiteConfigValue('tabby/tabby_api/secret_key', $websiteCode);
        }
        return $this->_secretKey[$websiteCode];
    }

    /**
     * Return config value by website code
     *
     * @param string $path
     * @param string $websiteCode
     * @return mixed
     */
    private function getWebsiteConfigValue($path, $websiteCode)
    {
        return $this->_scopeConfig->getValue($path, ScopeInterface::SCOPE_WEBSITE, $websiteCode);
    }
}
