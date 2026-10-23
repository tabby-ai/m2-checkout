<?php
namespace Tabby\Checkout\Gateway\Request\Payment\Order;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Tabby\Checkout\Gateway\Helper\Currency as CurrencyHelper;

class DiscountAmountDataBuilder implements BuilderInterface
{
    /**
     * @var CurrencyHelper
     */
    private $currencyHelper;

    /**
     * @param DomainHelper $domainHelper
     */
    public function __construct(
        CurrencyHelper $currencyHelper
    ) {
        $this->currencyHelper = $currencyHelper;
    }

    /**
     * Build discount amount array for request order object
     *
     * @param  array $buildSubject
     * @return array
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);

        $order = $paymentDO->getPayment()->getOrder();

        // recalculate discount based on Tabby rules
        //return ['discount_amount' => (string)$this->currencyHelper->getTabbyPrice($order, 'discount_amount')];
        $items_price = 0;
        foreach ($order->getAllVisibleItems() as $item) {
            $qty = $item->getQtyOrdered() * 1;
            $items_price +=  $qty * ($this->currencyHelper->getItemTabbyPrice($order, $item, 'price')
                        - $this->currencyHelper->getItemTabbyPrice($order, $item, 'discount_amount') / $qty
                        + $this->currencyHelper->getItemTabbyPrice($order, $item, 'tax_amount') / $qty);
        }
        $shipping = $this->currencyHelper->getTabbyPrice($order, 'shipping_incl_tax');
        $grand_total = $this->currencyHelper->getTabbyPrice($order, 'grand_total');
        $discount = max(0, $items_price + $shipping - $grand_total);
        return ['discount_amount' => $order->getPayment()->formatAmount($discount)];
    }
}
