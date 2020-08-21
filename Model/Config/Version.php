<?php

namespace Tabby\Checkout\Model\Config;

/**
 * Config backend model for version display.
 */
class Version extends \Magento\Framework\App\Config\Value
{
    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param ScopeConfigInterface $config
     * @param \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList
     * @param \Magento\Framework\Module\ResourceInterface $resource
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Magento\Framework\Module\ResourceInterface $moduleResource,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);

        $this->resource = $moduleResource;
    }

    /**
     * Inject current installed module version as the config value.
     *
     * @return void
     */
    public function afterLoad()
    {

        $version = $this->resource->getDataVersion('Tabby_Checkout');

        $this->setValue($version);
    }
}
