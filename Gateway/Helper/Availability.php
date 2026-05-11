<?php
namespace Tabby\Checkout\Gateway\Helper;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Quote\Api\Data\CartInterface;

class Availability
{
    /**
     * @var array
     */
    private $disable_for_sku = null;

    /**
     * Check config for Tabby be active for shopping cart
     *
     * @param CartInterface|null $quote
     * @return bool
     */
    public function __construct(
        ConfigInterface $moduleConfig
    ) {
        $this->disable_for_sku = array_filter(explode("\n", $moduleConfig->getValue('disable_for_sku') ?: ''));
    }
    /**
     * Check config for Tabby be active for shopping cart
     *
     * @param CartInterface|null $quote
     * @return bool
     */
    public function isTabbyActiveForCart(?CartInterface $quote = null)
    {
        $result = true;

        if ($quote) {
            if (count($this->disable_for_sku) > 0) {
                foreach ($quote->getAllVisibleItems() as $item) {
                    if (!$this->isTabbyActiveForProduct($item->getProduct())) {
                        $result = false;
                        break;
                    }
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
    public function isTabbyActiveForProduct(ProductInterface $product)
    {
        $result = true;

        foreach ($this->disable_for_sku as $sku) {
            if ($product->getSku() == trim($sku, "\r\n ")) {
                $result = false;
                break;
            }
        }

        return $result;
    }
}
