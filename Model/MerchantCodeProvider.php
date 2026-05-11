<?php

namespace Tabby\Checkout\Model;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Model\StoreManagerInterface;
use Tabby\Checkout\Api\MerchantCodeProviderInterface;
use Tabby\Checkout\Gateway\Helper\Data as DataHelper;

class MerchantCodeProvider implements MerchantCodeProviderInterface
{
    /**
     * @var ConfigInterface
     */
    private $moduleConfig;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * Constructor
     *
     * @param StoreManagerInterface $storeManager
     * @param ConfigInterface $moduleConfig
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ConfigInterface $moduleConfig
    ) {
        $this->storeManager = $storeManager;
        $this->moduleConfig = $moduleConfig;
    }

    /**
     * @inheritdoc
     */
    public function getMerchantCodeForProduct(ProductInterface $product) : string
    {
        return $this->getMerchantCode();
    }

    /**
     * @inheritdoc
     */
    public function getMerchantCodeForCart(CartInterface $quote) : string
    {
        return $this->getMerchantCode();
    }

    /**
     * @inheritdoc
     */
    public function getMerchantCodeForOrder(OrderInterface $order) : string
    {
        return $this->getMerchantCode();
    }

    /**
     * Get merchant code by Currency code
     *
     * @param string $currencyCode
     * @return string
     */
    public function getMerchantCodeByCurrency($currencyCode)
    {
        return substr($currencyCode, 0, 2);
    }
    /**
     * Get Base merchant code
     *
     * @param string $currencyCode
     * @return string
     */
    protected function getBaseMerchantCode()
    {
        return $this->moduleConfig->getValue(DataHelper::KEY_AGGREGATE_CODE)
            ? $this->getMerchantCodeByCurrency($this->storeManager->getStore()->getBaseCurrencyCode())
            : $this->storeManager->getStore()->getGroup()->getCode();
    }
    /**
     * Get merchant code
     *
     * @return string
     * @throws NoSuchEntityException
     */
    protected function getMerchantCode()
    {
        $merchantCode = $this->getBaseMerchantCode() . (
            $this->getUseLocalCurrency()
                ? '_' . $this->getCurrencyCode()
                : ''
        );
        return $merchantCode;
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
            ? $this->storeManager->getStore()->getCurrentCurrency()->getCode()
            : $this->storeManager->getStore()->getBaseCurrency()->getCode();
    }

    /**
     * Getter for Local or Base currency used
     *
     * @return string
     * @throws NoSuchEntityException
     */
    public function getUseLocalCurrency()
    {
        return $this->moduleConfig->getValue(DataHelper::KEY_LOCAL_CURRENCY);
    }
}
