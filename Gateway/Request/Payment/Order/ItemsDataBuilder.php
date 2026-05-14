<?php
namespace Tabby\Checkout\Gateway\Request\Payment\Order;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Tabby\Checkout\Gateway\Helper\Currency as CurrencyHelper;

class ItemsDataBuilder implements BuilderInterface
{
    /**
     * @var imageHelper
     */
    protected $imageHelper;

    /**
     * @var CurrencyHelper
     */
    private $currencyHelper;

    /*
     * @param ImageHelper $imageHelper
     * @param CurrencyHelper $currencyHelper
     */
    public function __construct(
        CurrencyHelper $currencyHelper,
        ImageHelper $imageHelper
    ) {
        $this->imageHelper = $imageHelper;
        $this->currencyHelper = $currencyHelper;
    }


    /**
     * Build items array for request order object
     *
     * @param  array $buildSubject
     * @return array
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);

        $order = $paymentDO->getPayment()->getOrder();

        return ['items' => $this->getOrderItemsData($order)];
    }

    /**
     * Creates order items array for given order.
     *
     * @param \Magento\Sales\Model\Order $order
     * @return array
     */
    protected function getOrderItemsData($order)
    {
        $items = [];
        foreach ($order->getAllVisibleItems() as $item) {
            $items[] = [
                'title'         => $item->getName(),
                'description'   => $item->getDescription(),
                'quantity'      => $item->getQtyOrdered() * 1,
                'unit_price'    => $order->getPayment()->formatAmount(
                    $this->currencyHelper->getItemTabbyPrice($order, $item, 'price')
                        - $this->currencyHelper->getItemTabbyPrice($order, $item, 'discount_amount')
                        + $this->currencyHelper->getItemTabbyPrice($order, $item, 'tax_amount')
                ),
                'tax_amount'    => $this->currencyHelper->getItemTabbyPrice($order, $item, 'tax_amount'),
                'reference_id'  => $item->getSku(),
                'image_url'     => $this->getItemImageUrl($item),
                'product_url'   => $item->getProduct()->getUrlInStore(),
                'category'      => $this->getItemCategoryName($item),
            ];
        }
        return $items;
    }

    /**
     * Generates order item image url.
     *
     * @param \Magento\Sales\Model\Order\Item $item
     * @return string
     */
    protected function getItemImageUrl($item)
    {
        $image = $this->imageHelper->init($item->getProduct(), 'product_page_image_large');

        return $image->getUrl();
    }

    /**
     * Generates order item category name.
     *
     * @param \Magento\Sales\Model\Order\Item $item
     * @return string
     */
    protected function getItemCategoryName($item)
    {
        $category_name = '';
        if ($collection = $item->getProduct()->getCategoryCollection()->addNameToResult()) {
            if ($collection->getSize()) {
                $category_name = $collection->getFirstItem()->getName();
            }
        }
        return $category_name;
    }
}
