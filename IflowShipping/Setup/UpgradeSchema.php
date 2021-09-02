<?php
/**
 * @author Drubu Team
 * @copyright Copyright (c) 2021 Drubu
 * @package Iflow_IflowShipping
 */

namespace Iflow\IflowShipping\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\DB\Ddl\Table;

class UpgradeSchema implements UpgradeSchemaInterface
{
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        if(version_compare($context->getVersion(), '1.0.1', '<')) {
            $soTable = $setup->getTable('sales_order');
            $sogTable = $setup->getTable('sales_order_grid');
            $ssTable = $setup->getTable('sales_shipment');
            $ssgTable = $setup->getTable('sales_shipment_grid');

            $columns = [
                [
                    'name' => 'iflow_status',
                    'detail' => [
                        'type' => Table::TYPE_TEXT,
                        'nullable' => false,
                        'length' => 100,
                        'comment' => 'Estado de seguimiento de orden/shipment',
                        'default' => 'NO TRACKED'
                    ],
                    'tables' => [$soTable,$sogTable,$ssTable,$ssgTable]
                ],
                [
                    'name' => 'iflow_shipment_id',
                    'detail' => [
                        'type' => Table::TYPE_TEXT,
                        'nullable' => true,
                        'length' => 100,
                        'comment' => 'Iflow shipment id',
                    ],
                    'tables' => [$ssTable,$ssgTable]
                ],
            ];
            foreach ($columns as $column){
                foreach ($column['tables'] as $currentTable) {
                    if (!$setup->getConnection()->tableColumnExists($currentTable, $column['name'])) {
                        $setup->getConnection()->addColumn($currentTable, $column['name'], $column['detail']);
                    }
                }
            }

        }
        $setup->endSetup();
    }
}
