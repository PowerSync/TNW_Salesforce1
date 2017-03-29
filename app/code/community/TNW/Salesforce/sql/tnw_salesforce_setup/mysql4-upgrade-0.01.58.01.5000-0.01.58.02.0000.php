<?php
/**
 * @var $this TNW_Salesforce_Model_Mysql4_Setup
 */
$installer = $this;
$installer->startSetup();

$configTable = $installer->getTable('core/config_data');
$adapter = $installer->getConnection();

$select = $adapter->select()
    ->from($configTable)
    ->where('path', 'salesforce_order/customer_opportunity/order_or_opportunity');

$rows = $adapter->fetchAll($select);
if (is_array($rows)) {
    foreach ($rows as $row) {
        $row['path'] = 'salesforce_order/customer_opportunity/integration_option';
        switch ($row['value']){
            case TNW_Salesforce_Model_Config_Objects::OPPORTUNITY_OBJECT:
            case TNW_Salesforce_Model_Config_Objects::ORDER_OBJECT:
                $row['value'] = strtolower($row['value']);
                break;

            default:
                continue 2;
        }

        $where = $adapter->prepareSqlCondition('config_id', $row['config_id']);
        unset($row['config_id']);
        $adapter->update($configTable, $row, $where);
    }
}

$orderItemTable = $installer->getTable('sales/order_item');
if (!$adapter->tableColumnExists($orderItemTable, 'opportunity_id')) {
    $adapter->addColumn($orderItemTable, 'opportunity_id', 'varchar(50)');
}

$orderStatusHistoryTable = $installer->getTable('sales/order_status_history');
if (!$adapter->tableColumnExists($orderStatusHistoryTable, 'opportunity_id')) {
    $adapter->addColumn($orderStatusHistoryTable, 'opportunity_id', 'varchar(50)');
}

$newAttributes = array(
    'first_purchase' => array(
        'type' => Varien_Db_Ddl_Table::TYPE_TIMESTAMP,
        'label' => 'First Purchase Date',
        'input' => 'datetime',

    ),
    'first_transaction_id' => array(
        'type' => Varien_Db_Ddl_Table::TYPE_TEXT,
        'label' => 'First Transaction Id',
    ),
);

foreach ($newAttributes as $code => $newAttributeData) {

//    $installer->removeAttribute('customer', $code);

    $installer->getConnection()->addColumn(
        $installer->getTable('customer/entity'),
        $code,
        array(
            'comment' => (isset($newAttributeData['label']) ? $newAttributeData['label'] : $code),
            'type' => $newAttributeData['type'],
            'length' => (isset($newAttributeData['length']) ? $newAttributeData['length'] : null),
        )
    );

    $installer->addAttribute(
        'customer',
        $code,
        array(
            'label' => (isset($newAttributeData['label']) ? $newAttributeData['label'] : $code),
            'type' => 'static',
            'visible' => true,
            'input' => (isset($newAttributeData['input']) ? $newAttributeData['input'] : ''),
        )
    );

}

$installer->endSetup();