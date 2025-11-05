<?php
namespace Tabby\Checkout\Setup;

use Magento\Config\Model\ResourceModel\Config\Data\CollectionFactory;
use Magento\Config\Model\ResourceModel\Config\Data as ConfigResourceData;
use Magento\Framework\DB\FieldDataConverterFactory;
use Magento\Framework\DB\Select\QueryModifierFactory;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\UpgradeDataInterface;
use Tabby\Checkout\Setup\DB\DescriptionTypeDataConverter;

class UpgradeData implements UpgradeDataInterface
{
    /**
     * @var CollectionFactory
     */
    private $configCollectionFactory;

    /**
     * @var ConfigResourceData
     */
    private $configResource;

    /**
     * @var FieldDataConverterFactory
     */
    private $fieldDataConverterFactory;

    /**
     * @var QueryModifierFactory
     */
    private $queryModifierFactory;

    /**
     * Constructor
     *
     * @param CollectionFactory $configCollectionFactory
     * @param ConfigResourceData $configResource
     * @param FieldDataConverterFactory $fieldDataConverterFactory
     * @param QueryModifierFactory $queryModifierFactory
     */
    public function __construct(
        CollectionFactory $configCollectionFactory,
        ConfigResourceData $configResource,
        FieldDataConverterFactory $fieldDataConverterFactory,
        QueryModifierFactory $queryModifierFactory
    ) {
        $this->configCollectionFactory = $configCollectionFactory;
        $this->configResource = $configResource;
        $this->fieldDataConverterFactory = $fieldDataConverterFactory;
        $this->queryModifierFactory = $queryModifierFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function upgrade(
        ModuleDataSetupInterface $setup,
        ModuleContextInterface $context
    ) {
        if (version_compare($context->getVersion(), '6.3.0', '<')) {
            $this->updateDescriptionTypeFieldAndRemoveCardTheme($setup);
        }
    }


    /**
     * Upgrade to version 6.0.1
     * Update DescriptionType configuration from 0,1 to 2
     * Remove configuration for Card Theme from DB
     *
     * @param ModuleDataSetupInterface $setup
     * @return void
     */
    private function updateDescriptionTypeFieldAndRemoveCardTheme(ModuleDataSetupInterface $setup)
    {
/*
        // delete absolete config data
        $configCollection = $this->configCollectionFactory->create()
            ->addFieldToFilter('path', ['like' => "payment/tabby_%/card_theme"]);
        foreach ($configCollection as $configItem) {
            $this->configResource->delete($configItem);
        }
*/
        // update description type for all methods from 0,2 to 1
        $fieldDataConverter = $this->fieldDataConverterFactory->create(DescriptionTypeDataConverter::class);

        // replace description_type values from 0,2 to 1
        $queryModifier = $this->queryModifierFactory->create(
            'like',
            [
                'values' => [
                    'path' => [
                        'payment/tabby_%/description_type'
                    ]
                ]
            ]
        );

        $fieldDataConverter->convert(
            $setup->getConnection(),
            $setup->getTable('core_config_data'),
           'config_id',
           'value',
            $queryModifier
        );
    }
}
