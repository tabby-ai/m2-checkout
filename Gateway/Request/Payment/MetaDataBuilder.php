<?php
namespace Tabby\Checkout\Gateway\Request\Payment;

use Magento\Payment\Gateway\Request\BuilderInterface;
use Tabby\Checkout\Gateway\Helper\Meta as MetaHelper;

class MetaDataBuilder implements BuilderInterface
{
    /**
     * @var MetaHelper
     */
    protected $metaHelper;

    /*
     * @param MetaHelper $metaHelper
     */
    public function __construct(
        MetaHelper $metaHelper
    ) {
        $this->metaHelper = $metaHelper;
    }
    /**
     * Build buyer array for request payment object
     *
     * @param  array $buildSubject
     * @return array
     */
    public function build(array $buildSubject): array
    {
        return [
            'meta' => $this->metaHelper->getPaymentObjectMetaFields()
        ];
    }
}
