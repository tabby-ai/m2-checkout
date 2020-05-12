<?php
namespace Tabby\Checkout\Block\Product\View;

class Promotion extends \Magento\Catalog\Block\Product\View {

    /**
     * @var \Magento\Framework\Locale\ResolverInterface
     */
    private $localeResolver;

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
		$this->catalogHelper  = $catalogHelper ;
    }
    public function isPromotionsActive() {
        return (bool) ($this->_scopeConfig->getValue('tabby/tabby_api/product_promotions', \Magento\Store\Model\ScopeInterface::SCOPE_STORE)
        && (
            $this->_scopeConfig->getValue('payment/tabby_installments/active', \Magento\Store\Model\ScopeInterface::SCOPE_STORE) ||
            $this->_scopeConfig->getValue('payment/tabby_checkout/active', \Magento\Store\Model\ScopeInterface::SCOPE_STORE)
        ));
    }

	public function getJsonConfigTabby($selector) {
		return json_encode([
            "selector"      => $selector,
			"merchantCode"	=> $this->getStoreCode(),
			"publicKey"		=> $this->getPublicKey(),
			"lang"			=> $this->getLocaleCode(),
			"currency"		=> $this->getCurrencyCode(),
			"price"			=> $this->formatAmount(
                $this->catalogHelper->getTaxPrice($this->getProduct(), $this->getProduct()->getFinalPrice(), true)
            ),
			"email"			=> $this->getCustomerEmail(),
			"phone"			=> $this->getCustomerPhone()
		]);
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
        return $this->_storeManager->getStore()->getCurrentCurrency()->getCode();
    }

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

	protected function formatAmount($amount) {
		return number_format($amount, 2, '.', '');
	}
}
