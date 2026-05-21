<?php
namespace Tabby\Checkout\Gateway\Helper;

use Magento\Payment\Gateway\ConfigInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\AbstractModel;

class Currency
{
    const TABBY_CURRENCY_FIELD = 'tabby_currency';

    /**
     * @var ConfigInterface
     */
    private $moduleConfig;

    public function __construct(
        ConfigInterface $moduleConfig
    ) {
        $this->moduleConfig = $moduleConfig;
    }

    public function getIsInLocalCurrency(OrderInterface $order): bool
    {
        return (bool)($order->getPayment()->getAdditionalInformation(self::TABBY_CURRENCY_FIELD) == 'order');
    }
    public function getTabbyCurrency(OrderInterface $order): string
    {
        return $this->getIsInLocalCurrency($order)
            ? $order->getOrderCurrencyCode()
            : $order->getBaseCurrencyCode();
    }
    public function getTabbyPrice(AbstractModel $salesModel, string $field): string
    {
        return $this->getItemTabbyPrice($salesModel, $salesModel, $field);
    }
    public function getItemTabbyPrice(AbstractModel $salesModel, $item, string $field): string
    {
        if ($salesModel instanceof OrderInterface) {
            $order = $salesModel;
        } else {
            $order = $salesModel->getOrder();
        }

        return $order->getPayment()->formatAmount(
            $this->getIsInLocalCurrency($order)
                ? $item->getData($field)
                : $item->getData('base_' . $field)
        );
    }
    public function getTabbyCurrencyForQuote(CartInterface $quote)
    {
        return $this->getUseLocalCurrency()
            ? $quote->getCurrency()->getQuoteCurrencyCode()
            : $quote->getCurrency()->getBaseCurrencyCode();
    }

    public function getUseLocalCurrency(): bool
    {
        return (bool)$this->moduleConfig->getValue('local_currency');
    }
}
