<?php
namespace Tabby\Checkout\Block\Product\View;

class Promotion extends \Magento\Catalog\Block\Product\View {

    /**
     * @var \Magento\Framework\Locale\ResolverInterface
     */
    private $localeResolver;
    private $catalogHelper;
    protected $onShoppingCartPage = false;

    /**
     * @param Context $context
     * @param \Magento\Framework\Url\EncoderInterface $urlEncoder
     * @param \Magento\Framework\Json\EncoderInterface $jsonEncoder
     * @param \Magento\Framework\Stdlib\StringUtils $string
     * @param \Magento\Catalog\Helper\Product $productHelper
     * @param \Magento\Catalog\Model\ProductTypes\ConfigInterface $productTypeConfig
     * @param \Magento\Framework\Locale\FormatInterface $localeFormat
     * @param \Magento\Customer\Model\Session $customerSession
     * @param ProductRepositoryInterface|\Magento\Framework\Pricing\PriceCurrencyInterface $productRepository
     * @param \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency
	 * @param \Magento\Framework\Locale\ResolverInterface $localeResolver
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param array $data
     * @codingStandardsIgnoreStart
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Catalog\Block\Product\Context $context,
        \Magento\Framework\Url\EncoderInterface $urlEncoder,
        \Magento\Framework\Json\EncoderInterface $jsonEncoder,
        \Magento\Framework\Stdlib\StringUtils $string,
        \Magento\Catalog\Helper\Product $productHelper,
        \Magento\Catalog\Model\ProductTypes\ConfigInterface $productTypeConfig,
        \Magento\Framework\Locale\FormatInterface $localeFormat,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency,
		\Magento\Framework\Locale\ResolverInterface $localeResolver,
        \Magento\Catalog\Helper\Data $catalogHelper,
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
		$this->localeResolver = $localeResolver ;
		$this->catalogHelper  = $catalogHelper  ;
		$this->checkoutSession= $checkoutSession;
    }
    public function setIsOnShoppingCartPage() {
        $this->onShoppingCartPage = true;
    }
    public function getIsOnShoppingCartPage() {
        return $this->onShoppingCartPage;
    }
    public function isPromotionsActive() {
        return (bool) (
            ($this->isPromotionsActiveForProduct() || $this->isPromotionsActiveForCart())
        && (
            $this->_scopeConfig->getValue('payment/tabby_installments/active', \Magento\Store\Model\ScopeInterface::SCOPE_STORE) ||
            $this->_scopeConfig->getValue('payment/tabby_checkout/active', \Magento\Store\Model\ScopeInterface::SCOPE_STORE)
        ));
    }
    private function getDisableForSku() {
        return array_filter(explode("\n", $this->_scopeConfig->getValue('tabby/tabby_api/disable_for_sku', \Magento\Store\Model\ScopeInterface::SCOPE_STORE)));
    }
    public function isPromotionsActiveForCartSkus() {
        $quote = $this->checkoutSession->getQuote();

        $skus = $this->getDisableForSku();
        $result = true;

        foreach ($skus as $sku) {
            if (!$quote) break;
            foreach ($quote->getAllVisibleItems() as $item) {
                if ($item->getSku() == trim($sku, "\r\n ")) {
                    $result = false;
                    break 2;
                }
            }
        }

        return $result;
    }

    public function isPromotionsActiveForProductSku() {

        $skus = $this->getDisableForSku();
        $result = true;

        foreach ($skus as $sku) {
            if ($this->getProduct()->getSku() == trim($sku, "\r\n ")) {
                $result = false;
                break;
            }
        }

        return $result;
    }
    public function isPromotionsActiveForPrice() {
        $max_base_price = $this->_scopeConfig->getValue('tabby/tabby_api/promo_limit', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        if ($max_base_price > 0) {
            $max_price = $this->_storeManager->getStore()->getBaseCurrency()->convert(
                $max_base_price,
                $this->getCurrencyCode()
            );
            $price = $this->onShoppingCartPage ? $this->getTabbyCartPrice() : $this->getTabbyProductPrice();
            return $price <= $max_price;
        }
        return true;
    }
    public function isPromotionsActiveForProduct() {
        return $this->_scopeConfig->getValue('tabby/tabby_api/product_promotions', \Magento\Store\Model\ScopeInterface::SCOPE_STORE) 
            && $this->isPromotionsActiveForPrice()
            && $this->isPromotionsActiveForProductSku();
    }
    public function isPromotionsActiveForCart() {
        return $this->_scopeConfig->getValue('tabby/tabby_api/cart_promotions', \Magento\Store\Model\ScopeInterface::SCOPE_STORE)
            && $this->isPromotionsActiveForPrice()
            && $this->isPromotionsActiveForCartSkus();
    }

	public function getJsonConfigTabby($selector) {
		return json_encode([
            "selector"      => $selector,
			"merchantCode"	=> $this->getStoreCode(),
			"publicKey"		=> $this->getPublicKey(),
			"lang"			=> $this->getLocaleCode(),
			"source"		=> $this->onShoppingCartPage ? 'cart' : 'product',
			"currency"		=> $this->getCurrencyCode(),
            "currencyRate"  => $this->getCurrencyRate(),
            "theme"         => $this->getTabbyTheme(),
            // we do not set cart price, because we need to update snippet from quote totals in javascript
			"price"			=> (float)$this->formatAmount($this->onShoppingCartPage ? 0 : $this->getTabbyProductPrice())/*,
			"email"			=> $this->getCustomerEmail(),
			"phone"			=> $this->getCustomerPhone()*/
		]);
	}
    public function getTabbyTheme() {
        return $this->_scopeConfig->getValue('tabby/tabby_api/promo_theme', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }
    public function getTabbyCartPrice() {
        return $this->_storeManager->getStore()->getBaseCurrency()->convert(
            $this->checkoutSession->getQuote()->getBaseGrandTotal(),
            $this->getCurrencyCode()
        );
    }
    public function getTabbyProductPrice() {
        return $this->catalogHelper->getTaxPrice(
            $this->getProduct(), 
            $this->_storeManager->getStore()->getBaseCurrency()->convert(
                $this->getProduct()->getFinalPrice(),
                $this->getCurrencyCode()
            ), 
            true
        );
    }
    public function getCurrencyRate() {
        $from = $this->getCurrencyCode();
        $to   = $this->_storeManager->getStore()->getCurrentCurrency()->getCode();
        return $from == $to ? 1 : 1 / $this->_storeManager->getStore()->getBaseCurrency()->getRate($to);
    }
    public function getUseLocalCurrency() {
        return $this->_scopeConfig->getValue('tabby/tabby_api/local_currency', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    public function getStoreCode() {
        return $this->_storeManager->getStore()->getCode();
    }

    public function getPublicKey() {
        return $this->_scopeConfig->getValue(
            'tabby/tabby_api/public_key',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getLocaleCode() {
        return $this->localeResolver->getLocale();
    }

    public function getCurrencyCode() {
        return $this->getUseLocalCurrency() ? $this->_storeManager->getStore()->getCurrentCurrency()->getCode() : $this->_storeManager->getStore()->getBaseCurrency()->getCode();
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
	protected function formatAmount($amount) {
		return number_format($amount, 2, '.', '');
	}
}
