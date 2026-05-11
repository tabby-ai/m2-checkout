<?php
namespace Tabby\Checkout\Gateway\Helper;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\AbstractModel;

class Currency
{
    const TABBY_CURRENCY_FIELD = 'tabby_currency';

    public static function getIsInLocalCurrency(OrderInterface $order): bool
    {
        return (bool)($order->getPayment()->getAdditionalInformation(self::TABBY_CURRENCY_FIELD) == 'order');
    }
    public static function getTabbyCurrency(OrderInterface $order): string
    {
        return self::getIsInLocalCurrency($order)
            ? $order->getOrderCurrencyCode()
            : $order->getBaseCurrencyCode();
    }
    public static function getTabbyPrice(AbstractModel $salesModel, string $field): string
    {
        return self::getItemTabbyPrice($salesModel, $salesModel, $field);
    }
    public static function getItemTabbyPrice(AbstractModel $salesModel, $item, string $field): string
    {
        if ($salesModel instanceof OrderInterface) {
            $order = $salesModel;
        } else {
            $order = $salesModel->getOrder();
        }

        return $order->getPayment()->formatAmount(
            self::getIsInLocalCurrency($order)
            ? $item->getData($field)
            : $item->getData('base_' . $field)
        );
    }
}
