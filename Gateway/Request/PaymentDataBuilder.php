<?php
namespace Tabby\Checkout\Gateway\Request;

Use Magento\Payment\Gateway\Request\BuilderCompositeFactory;
use Magento\Payment\Gateway\Request\BuilderInterface;

class PaymentDataBuilder implements BuilderInterface
{
    /**
     * @var BuilderCompositeFactory
     */
    private $builderCompositeFactory;

    /**
     * @param BuilderCompositeFactory $builderCompositeFactory
     */
    public function __construct(
        BuilderCompositeFactory $builderCompositeFactory
    ) {
        $this->builderCompositeFactory = $builderCompositeFactory;
    }

    /**
     * Build payment array for request
     *
     * @param  array $buildSubject
     * @return array
     */
    public function build(array $buildSubject): array
    {
        $builders = [
            \Tabby\Checkout\Gateway\Request\Payment\AmountDataBuilder::class,
            \Tabby\Checkout\Gateway\Request\Payment\CurrencyDataBuilder::class,
            \Tabby\Checkout\Gateway\Request\Payment\BuyerDataBuilder::class,
            \Tabby\Checkout\Gateway\Request\Payment\ShippingAddressDataBuilder::class,
            \Tabby\Checkout\Gateway\Request\Payment\OrderDataBuilder::class,
            \Tabby\Checkout\Gateway\Request\Payment\MetaDataBuilder::class,
            \Tabby\Checkout\Gateway\Request\Payment\BuyerAndOrderHistoryDataBuilder::class,
        ];
        return [
            'payment' => $this->builderCompositeFactory
                ->create(['builders' => $builders])
                ->build($buildSubject)
        ];
    }
}
