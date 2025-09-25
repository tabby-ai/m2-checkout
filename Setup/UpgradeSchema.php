<?php
namespace Tabby\Checkout\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();

        $tableName = $installer->getTable('sales_order_address');

        if ($installer->getConnection()->isTableExists($tableName)) {
            $indexes = $installer->getConnection()->getIndexList($tableName);
            $index_found = false;
            foreach ($indexes as $iname => $ifields) {
                if (substr($iname, -9) == 'TELEPHONE') {
                    $index_found = true;
                    break;
                }
                foreach ($ifields['COLUMNS_LIST'] as $icolumn) {
                    if ($icolumn == 'telephone') {
                        $index_found = true;
                        break 2;
                    }
                }
            }
            if (!$index_found) {
                $installer->getConnection()->addIndex(
                    $tableName,
                    $installer->getConnection()->getIndexName(
                        $tableName,
                        ['telephone'],
                        AdapterInterface::INDEX_TYPE_INDEX
                    ),
                    ['telephone'],
                    AdapterInterface::INDEX_TYPE_INDEX
                );
            }

        }

        $installer->endSetup();
    }
}
