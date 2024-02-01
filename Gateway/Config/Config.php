<?php

namespace Tabby\Checkout\Gateway\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Catalog\Model\Product;

class Config extends \Magento\Payment\Gateway\Config\Config
{
    public const CODE = 'tabby_api';

    public const DEFAULT_PATH_PATTERN = 'tabby/%s/%s';

    public const KEY_PUBLIC_KEY = 'public_key';
    public const KEY_SECRET_KEY = 'secret_key';

    public const KEY_ORDER_HISTORY_USE_PHONE = 'order_history_use_phone';

    public const CREATE_PENDING_INVOICE = 'create_pending_invoice';
    public const CAPTURE_ON = 'capture_on';
    public const CAPTURED_STATUS = 'captured_status';
    public const MARK_COMPLETE = 'mark_complete';
    public const AUTHORIZED_STATUS = 'authorized_status';

    public const ALLOWED_SERVICES = [
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
     * Getter for public key
     *
     * @param ?int $storeId
     * @return mixed|null
     */
    public function getPublicKey($storeId = null)
    {
        return $this->getValue(self::KEY_PUBLIC_KEY, $storeId);
    }

    /**
     * Getter for secret key
     *
     * @param ?int $storeId
     * @return mixed|null
     */
    public function getSecretKey($storeId = null)
    {
        return $this->getValue(self::KEY_SECRET_KEY, $storeId);
    }

    /**
     * Getter for scope config
     *
     * @return ScopeConfigInterface
     */
    public function getScopeConfig()
    {
        return $this->scopeConfig;
    }

    /**
     * Check config for Tabby be active for shopping cart
     *
     * @param CartInterface|null $quote
     * @return bool
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
     * Check config for Tabby be active for product
     *
     * @param Product $product
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
     * Get skus Tabby disabled for
     *
     * @return string|string[]
     */
    private function getDisableForSku()
    {
        return array_filter(explode("\n", $this->getValue('disable_for_sku') ?: ''));
    }
}
