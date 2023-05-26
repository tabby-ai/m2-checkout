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
     * @return void
     */
    public function setIsOnShoppingCartPage()
    {
        $this->onShoppingCartPage = true;
    }

    /**
     * @return bool
     */
    public function getIsOnShoppingCartPage()
    {
        return $this->onShoppingCartPage;
    }

    /**
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
     * @return bool
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function isPromotionsActiveForCartSkus()
    {
        $quote = $this->checkoutSession->getQuote();

        return $this->moduleConfig->isTabbyActiveForCart($quote);
    }

    /**
     * @return bool
     */
    public function isPromotionsActiveForProductSku()
    {
        return $this->moduleConfig->isTabbyActiveForProduct($this->getProduct());
    }

    private function getBaseCurrency() {
        return $this->_storeManager->getStore()->getBaseCurrency(); // @phan-suppress-current-line PhanUndeclaredMethod
    }

    /**
     * @return bool
     * @throws NoSuchEntityException
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
     * @return bool
     * @throws NoSuchEntityException
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
     * @return bool
     * @throws NoSuchEntityException
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
     * @return bool
     * @throws LocalizedException
     * @throws NoSuchEntityException
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
     * @param $selector
     * @return false|string
     */
    public function getJsonConfigTabby($selector)
    {
        return json_encode([
            "selector" => $selector,
            "merchantCode" => $this->getStoreCode(),
            "publicKey" => $this->getPublicKey(),
            "lang" => $this->getLocaleCode(),
            "source" => $this->onShoppingCartPage ? 'cart' : 'product',
            "currency" => $this->getCurrencyCode(),
            "currencyRate" => $this->getCurrencyRate(),
            "theme" => $this->getTabbyTheme(),
            "installmentsCount" => $this->getTabbyInstallmentsCount(),
            "productType" => $this->getProductType(),
            // we do not set cart price, because we need to update snippet from quote totals in javascript
            "price" => (float)$this->formatAmount($this->onShoppingCartPage ? 0 : $this->getTabbyProductPrice())/*,
            "email"			=> $this->getCustomerEmail(),
            "phone"			=> $this->getCustomerPhone()*/
        ]);
    }

    /**
     * @return string
     */
    public function getProductType()
    {
        return $this->isCreditCardInstallmentsActive() && !$this->isInstallmentsOrPayLaterActive() ? 'creditCardInstallments' : 'installments';
    }

    public function getTabbyThemeConfig() {
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
     * @return mixed
     */
    public function getTabbyTheme()
    {
        return $this->getTabbyThemeConfig()['theme'];
    }

    /**
     * @return mixed
     */
    public function getTabbyInstallmentsCount()
    {
        return $this->getTabbyThemeConfig()['installmentsCount'];
    }

    /**
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
     * @return float|int
     * @throws NoSuchEntityException
     */
    public function getCurrencyRate()
    {
        $from = $this->getCurrencyCode();
        $to = $this->_storeManager->getStore()->getCurrentCurrency()->getCode(); // @phan-suppress-current-line PhanUndeclaredMethod
        return $from == $to ? 1 : 1 / $this->getBaseCurrency()->getRate($to);
    }

    /**
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
     * @return string
     * @throws NoSuchEntityException
     */
    public function getStoreCode()
    {
        return $this->_storeManager->getStore()->getCode();
    }

    /**
     * @return mixed
     */
    public function getPublicKey()
    {
        return $this->_scopeConfig->getValue(
            'tabby/tabby_api/public_key',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return string
     */
    public function getLocaleCode()
    {
        return $this->localeResolver->getLocale();
    }

    /**
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function getCurrencyCode()
    {
        return $this->getUseLocalCurrency() ? $this->_storeManager->getStore()->getCurrentCurrency()->getCode() : $this->getBaseCurrency()->getCode(); // @phan-suppress-current-line PhanUndeclaredMethod
    }

    /*
        public function getCustomerEmail() {
            return $this->customerSession->getCustomer() ? $this->customerSession->getCustomer()->getEmail() : null;
        }

        public function getCustomerPhone() {
            $phones = [];
            if ($this->customerSession->getCustomer()) {
                foreach ($this->customerSession->getCustomer()->getAddresses() as $address) {
                    $phones[] = $address->getTelephone();
                }
            }
            return implode('|', array_filter($phones));
        }
    */

    /**
     * @param $amount
     * @return string
     */
    protected function formatAmount($amount)
    {
        return number_format($amount, 2, '.', '');
    }
}
