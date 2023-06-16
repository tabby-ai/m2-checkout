<?php

namespace Tabby\Checkout\Gateway\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Catalog\Model\Product;

class Config extends \Magento\Payment\Gateway\Config\Config
{
    const CODE = 'tabby_api';

    const DEFAULT_PATH_PATTERN = 'tabby/%s/%s';

    const KEY_PUBLIC_KEY = 'public_key';
    const KEY_SECRET_KEY = 'secret_key';

    const KEY_ORDER_HISTORY_USE_PHONE = 'order_history_use_phone';

    const CREATE_PENDING_INVOICE = 'create_pending_invoice';
    const CAPTURE_ON = 'capture_on';
    const CAPTURED_STATUS = 'captured_status';
    const MARK_COMPLETE = 'mark_complete';
    const AUTHORIZED_STATUS = 'authorized_status';

    const ALLOWED_SERVICES = [
        'tabby_cc_installments' => "Credit Card installments",
        'tabby_installments' => "Pay in installments",
        'tabby_checkout' => "Pay after delivery"
    ];

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * Tabby config constructor
     *
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        parent::__construct($scopeConfig, self::CODE, self::DEFAULT_PATH_PATTERN);
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @param null $storeId
     * @return mixed|null
     */
    public function getPublicKey($storeId = null)
    {
        return $this->getValue(self::KEY_PUBLIC_KEY, $storeId);
    }

    /**
     * @param null $storeId
     * @return mixed|null
     */
    public function getSecretKey($storeId = null)
    {
        return $this->getValue(self::KEY_SECRET_KEY, $storeId);
    }

    /**
     * @return ScopeConfigInterface
     */
    public function getScopeConfig()
    {
        return $this->scopeConfig;
    }
    /**
     * @param CartInterface|null $quote
     * @return bool
     * @throws LocalizedException
     */
    public function isTabbyActiveForCart(CartInterface $quote = null)
    {
        $result = true;

        if ($quote) {
            foreach ($quote->getAllVisibleItems() as $item) {
                if (!$this->isTabbyActiveForProduct($item->getProduct())) {
                    $result = false;
                    break;
                }
            }
        }

        return $result;
    }
    /**
     * @return bool
     */
    public function isTabbyActiveForProduct(Product $product)
    {
        $skus = $this->getDisableForSku();
        $result = true;

        foreach ($skus as $sku) {
            if ($product->getSku() == trim($sku, "\r\n ")) {
                $result = false;
                break;
            }
        }

        return $result;
    }
    /**
     * @return false|string[]
     */
    private function getDisableForSku()
    {
        return array_filter(explode("\n", $this->getValue('disable_for_sku') ?: ''));
    }
}
