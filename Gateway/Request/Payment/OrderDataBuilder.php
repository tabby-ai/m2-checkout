<?php
namespace Tabby\Checkout\Gateway\Request\Payment;

use Magento\Payment\Gateway\Helper\SubjectReader;
Use Magento\Payment\Gateway\Request\BuilderCompositeFactory;
use Magento\Payment\Gateway\Request\BuilderInterface;

class OrderDataBuilder implements BuilderInterface
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
     * Build buyer array for request payment object
     *
     * @param  array $buildSubject
     * @return array
     */
    public function build(array $buildSubject): array
    {
        $builders = [
            \Tabby\Checkout\Gateway\Request\Payment\Order\TaxAmountDataBuilder::class,
            \Tabby\Checkout\Gateway\Request\Payment\Order\ShippingAmountDataBuilder::class,
            \Tabby\Checkout\Gateway\Request\Payment\Order\DiscountAmountDataBuilder::class,
            \Tabby\Checkout\Gateway\Request\Payment\Order\ReferenceIdDataBuilder::class,
            \Tabby\Checkout\Gateway\Request\Payment\Order\ItemsDataBuilder::class,
        ];
        return [
            'order' => $this->builderCompositeFactory
                ->create(['builders' => $builders])
                ->build($buildSubject),
        ];
    }
}
