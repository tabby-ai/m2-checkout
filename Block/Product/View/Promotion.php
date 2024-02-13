<?php

namespace Tabby\Checkout\Block\Product\View;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Block\Product\Context;
use Magento\Catalog\Block\Product\View;
use Magento\Catalog\Helper\Data;
use Magento\Catalog\Helper\Product;
use Magento\Catalog\Model\ProductTypes\ConfigInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Locale\FormatInterface;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Stdlib\StringUtils;
use Magento\Framework\Url\EncoderInterface;
use Magento\Store\Model\ScopeInterface;
use Tabby\Checkout\Gateway\Config\Config;

class Promotion extends View
{

    /**
     * @var ResolverInterface
     */
    private $localeResolver;

    /**
     * @var Data
     */
    private $catalogHelper;

    /**
     * @var bool
     */
    protected $onShoppingCartPage = false;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var Tabby\Checkout\Gateway\Config\Config
     */
    protected $moduleConfig;

    /**
     * @param Context $context
     * @param EncoderInterface $urlEncoder
     * @param \Magento\Framework\Json\EncoderInterface $jsonEncoder
     * @param StringUtils $string
     * @param Product $productHelper
     * @param ConfigInterface $productTypeConfig
     * @param Config $moduleConfig
     * @param FormatInterface $localeFormat
     * @param Session $customerSession
     * @param ProductRepositoryInterface $productRepository
     * @param PriceCurrencyInterface $priceCurrency
     * @param ResolverInterface $localeResolver
     * @param Data $catalogHelper
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param array $data
     * @codingStandardsIgnoreStart
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Context $context,
        EncoderInterface $urlEncoder,
        \Magento\Framework\Json\EncoderInterface $jsonEncoder,
        StringUtils $string,
        Product $productHelper,
        ConfigInterface $productTypeConfig,
        Config $moduleConfig,
        FormatInterface $localeFormat,
        Session $customerSession,
        ProductRepositoryInterface $productRepository,
        PriceCurrencyInterface $priceCurrency,
        ResolverInterface $localeResolver,
        Data $catalogHelper,
        \Magento\Checkout\Model\Session $checkoutSession,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $urlEncoder,
            $jsonEncoder,
            $string,
            $productHelper,
            $productTypeConfig,
            $localeFormat,
            $customerSession,
            $productRepository,
            $priceCurrency,
            $data
        );
        $this->localeResolver = $localeResolver;
        $this->catalogHelper = $catalogHelper;
        $this->checkoutSession = $checkoutSession;
        $this->moduleConfig = $moduleConfig;
    }

    /**
     * Setter for onShoppingCartPage
     *
     * @return void
     */
    public function setIsOnShoppingCartPage()
    {
        $this->onShoppingCartPage = true;
    }

    /**
     * Getter for onShoppingCartPage
     *
     * @return bool
     */
    public function getIsOnShoppingCartPage()
    {
        return $this->onShoppingCartPage;
    }

    /**
     * Get is promotions block active for current placement
     *
     * @return bool
     */
    public function isPromotionsActive()
    {
        return (bool)(
            ($this->isPromotionsActiveForProduct() || $this->isPromotionsActiveForCart())
            && (
                $this->isInstallmentsOrPayLaterActive() ||
                $this->isCreditCardInstallmentsActive()
            )
        );
    }

    /**
     * Get is promotions block active for shopping cart skus
     *
     * @return bool
     */
    public function isPromotionsActiveForCartSkus()
    {
        $quote = $this->checkoutSession->getQuote();

        return $this->moduleConfig->isTabbyActiveForCart($quote);
    }

    /**
     * Get is promotions block active for product sku
     *
     * @return bool
     */
    public function isPromotionsActiveForProductSku()
    {
        return $this->moduleConfig->isTabbyActiveForProduct($this->getProduct());
    }

    /**
     * Get base currency for current store
     *
     * @return \Magento\Directory\Model\Currency
     */
    private function getBaseCurrency()
    {
        return $this->_storeManager->getStore()->getBaseCurrency();
    }

    /**
     * Check if promotion block active for price limits
     *
     * @return bool
     */
    public function isPromotionsActiveForPrice()
    {
        $max_base_price = $this->_scopeConfig->getValue(
            'tabby/tabby_api/promo_limit',
            ScopeInterface::SCOPE_STORE
        );
        if ($max_base_price > 0) {
            $max_price = $this->getBaseCurrency()->convert(
                $max_base_price,
                $this->getCurrencyCode()
            );
            $price = $this->onShoppingCartPage ? $this->getTabbyCartPrice() : $this->getTabbyProductPrice();
            return $price <= $max_price;
        }
        return true;
    }

    /**
     * Check if promotion block active for cart total limits
     *
     * @return bool
     */
    public function isPromotionsActiveForCartTotal()
    {
        $min_base_price = $this->_scopeConfig->getValue(
            'tabby/tabby_api/promo_min_total',
            ScopeInterface::SCOPE_STORE
        );
        if ($min_base_price > 0) {
            $min_price = $this->getBaseCurrency()->convert(
                $min_base_price,
                $this->getCurrencyCode()
            );
            return $this->getTabbyCartPrice() >= $min_price;
        }
        return true;
    }

    /**
     * Check if promotion block active for product minimum price
     *
     * @return bool
     */
    public function isPromotionsActiveForProductMinPrice()
    {
        $min_base_price = $this->_scopeConfig->getValue(
            'tabby/tabby_api/promo_min_price',
            ScopeInterface::SCOPE_STORE
        );
        if ($min_base_price > 0) {
            $min_price = $this->getBaseCurrency()->convert(
                $min_base_price,
                $this->getCurrencyCode()
            );
            return $this->getTabbyProductPrice() >= $min_price;
        }
        return true;
    }

    /**
     * Check if promotion block active for product
     *
     * @return bool
     */
    public function isPromotionsActiveForProduct()
    {
        return $this->_scopeConfig->getValue(
            'tabby/tabby_api/product_promotions',
            ScopeInterface::SCOPE_STORE
        )
            && $this->isPromotionsActiveForPrice()
            && $this->isPromotionsActiveForProductMinPrice()
            && $this->isPromotionsActiveForProductSku();
    }

    /**
     * Check if promotion block active for shopping cart content
     *
     * @return bool
     */
    public function isPromotionsActiveForCart()
    {
        return $this->_scopeConfig->getValue(
            'tabby/tabby_api/cart_promotions',
            ScopeInterface::SCOPE_STORE
        )
            && $this->isPromotionsActiveForPrice()
            && $this->isPromotionsActiveForCartTotal()
            && $this->isPromotionsActiveForCartSkus();
    }

    /**
     * Check if Tabby methods active for checkout
     *
     * @return bool
     */
    public function isInstallmentsOrPayLaterActive()
    {
        return $this->_scopeConfig->getValue(
            'payment/tabby_installments/active',
            ScopeInterface::SCOPE_STORE
        )
            || $this->_scopeConfig->getValue(
                'payment/tabby_checkout/active',
                ScopeInterface::SCOPE_STORE
            );
    }

    /**
     * Check if credit card installments active for checkout
     *
     * @return mixed
     */
    public function isCreditCardInstallmentsActive()
    {
        return $this->_scopeConfig->getValue(
            'payment/tabby_cc_installments/active',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Create json config for promotions block
     *
     * @param string $selector
     * @return string
     */
    public function getJsonConfigTabby($selector)
    {
        return json_encode($this->addPublicKeyToConfig([
            "selector" => $selector,
            "merchantCode" => $this->getStoreCode(),
            "lang" => $this->getLocaleCode(),
            "source" => $this->onShoppingCartPage ? 'cart' : 'product',
            "sourcePlugin" => "magento2",
            "currency" => $this->getCurrencyCode(),
            "currencyRate" => $this->getCurrencyRate(),
            "theme" => $this->getTabbyTheme(),
            "installmentsCount" => $this->getTabbyInstallmentsCount(),
            "productType" => $this->getProductType(),
            // we do not set cart price, because we need to update snippet from quote totals in javascript
            "price" => (float)$this->formatAmount($this->onShoppingCartPage ? 0 : $this->getTabbyProductPrice())
        ]));
    }

    /**
     * Get product type to be used for promotions block
     *
     * @return string
     */
    public function getProductType()
    {
        return $this->isCreditCardInstallmentsActive() && !$this->isInstallmentsOrPayLaterActive()
            ? 'creditCardInstallments'
            : 'installments';
    }

    /**
     * Get promotions block theme configuration
     *
     * @return array
     */
    public function getTabbyThemeConfig()
    {
        $theme = explode(':', $this->_scopeConfig->getValue(
            'tabby/tabby_api/promo_theme',
            ScopeInterface::SCOPE_STORE
        ) ?: '');
        return [
            'theme' => array_shift($theme),
            'installmentsCount' => !empty($theme) ? 0 : 4
        ];
    }

    /**
     * Getter for promotions theme configured
     *
     * @return mixed
     */
    public function getTabbyTheme()
    {
        return $this->getTabbyThemeConfig()['theme'];
    }

    /**
     * Getter for Tabby installments count
     *
     * @return mixed
     */
    public function getTabbyInstallmentsCount()
    {
        return $this->getTabbyThemeConfig()['installmentsCount'];
    }

    /**
     * Getter for shopping cart total price
     *
     * @return mixed
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getTabbyCartPrice()
    {
        return $this->getBaseCurrency()->convert(
            $this->checkoutSession->getQuote()->getBaseGrandTotal(),
            $this->getCurrencyCode()
        );
    }

    /**
     * Getter for product price in base currency
     *
     * @return float
     * @throws NoSuchEntityException
     */
    public function getTabbyProductPrice()
    {
        return $this->catalogHelper->getTaxPrice(
            $this->getProduct(),
            $this->getBaseCurrency()->convert(
                $this->getProduct()->getFinalPrice(),
                $this->getCurrencyCode()
            ),
            true
        );
    }

    /**
     * Getter for currency rate related to base currency
     *
     * @return float|int
     * @throws NoSuchEntityException
     */
    public function getCurrencyRate()
    {
        $from = $this->getCurrencyCode();
        $to = $this->_storeManager->getStore()->getCurrentCurrency()->getCode();
        return $from == $to ? 1 : 1 / $this->getBaseCurrency()->getRate($to);
    }

    /**
     * Getter for use local currency config option
     *
     * @return mixed
     */
    public function getUseLocalCurrency()
    {
        return $this->_scopeConfig->getValue(
            'tabby/tabby_api/local_currency',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Getter for store code
     *
     * @return string
     * @throws NoSuchEntityException
     */
    public function getStoreCode()
    {
        return $this->_storeManager->getStore()->getCode();
    }

    /**
     * Adds public key to config array
     *
     * @param array $config
     * @return array
     */
    public function addPublicKeyToConfig($config)
    {
        $plugin_mode = $this->_scopeConfig->getValue(
            'tabby/tabby_api/plugin_mode',
            ScopeInterface::SCOPE_STORE
        );

        if ($plugin_mode != '1') {
            $config['public_key'] = $this->getPublicKey();
        }

        return $config;
    }

    /**
     * Getter for public key
     *
     * @return string
     */
    public function getPublicKey()
    {
        return $this->_scopeConfig->getValue(
            'tabby/tabby_api/public_key',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Getter for locale code
     *
     * @return string
     */
    public function getLocaleCode()
    {
        return $this->localeResolver->getLocale();
    }

    /**
     * Getter for currency code
     *
     * @return string
     * @throws NoSuchEntityException
     */
    public function getCurrencyCode()
    {
        return $this->getUseLocalCurrency()
            ? $this->_storeManager->getStore()->getCurrentCurrency()->getCode()
            : $this->getBaseCurrency()->getCode();
    }

    /**
     * Format price
     *
     * @param float $amount
     * @return string
     */
    protected function formatAmount($amount)
    {
        return number_format($amount, 2, '.', '');
    }
}
