<?php
namespace Tabby\Checkout\Setup;

use Magento\Config\Model\ResourceModel\Config as ConfigResource;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\InstallDataInterface;

class InstallData implements InstallDataInterface
{
    /**
     * @var ConfigResourceData
     */
    private $configResource;

    /**
     * Constructor
     *
     * @param ConfigResource $configResource
     */
    public function __construct(
        ConfigResource $configResource
    ) {
        $this->configResource = $configResource;
    }

    /**
     * {@inheritdoc}
     */
    public function install(
        ModuleDataSetupInterface $setup,
        ModuleContextInterface $context
    ) {
        $this->configResource->saveConfig('tabby/tabby_api/aggregate_code', 1);
    }
}
