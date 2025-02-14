<?php

namespace Tabby\Checkout\Model;

use Tabby\Checkout\Api\MerchantCodeProviderInterface;
use Magento\Store\Model\StoreManagerInterface;
use Tabby\Checkout\Gateway\Config\Config;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Catalog\Api\Data\ProductInterface;

class MerchantCodeProvider implements MerchantCodeProviderInterface
{
    /**
     * @var Config
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
     * @param Config $moduleConfig
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        Config $moduleConfig
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
     * Get merchant code
     *
     * @return string
     * @throws NoSuchEntityException
     */
    protected function getMerchantCode()
    {
        $merchantCode = $this->storeManager->getStore()->getGroup()->getCode() . (
            $this->moduleConfig->getUseLocalCurrency()
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
        return $this->moduleConfig->getUseLocalCurrency()
            ? $this->storeManager->getStore()->getCurrentCurrency()->getCode()
            : $this->storeManager->getStore()->getBaseCurrency()->getCode();
    }
}
