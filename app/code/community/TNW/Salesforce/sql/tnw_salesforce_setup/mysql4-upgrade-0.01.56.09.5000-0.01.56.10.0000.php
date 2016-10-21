<?php
/**
 * @var $this TNW_Salesforce_Model_Mysql4_Setup
 */
$installer = $this;
$installer->startSetup();

/** @comment В одном элементе может содержаться неограниченное число подэлементов */
$pageSize = 5;

$connection = $this->getConnection();

$tableImport = $installer->getTable('tnw_salesforce/import');
$connection->delete($tableImport, 'is_processing IS NOT NULL');
$connection->addColumn($installer->getTable('tnw_salesforce/queue_storage'), 'sync_type', 'varchar(50) DEFAULT \'outgoing\'');
$connection->addColumn($tableImport, 'object_id', 'varchar(50)');
$connection->addColumn($tableImport, 'object_type', 'varchar(50)');
$connection->addColumn($tableImport, 'created_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');

$select         = $connection->select()->from($tableImport);
$selectSize     = $connection->select()->from($tableImport, array(new Zend_Db_Expr('count(*)')));
$sizeAll        = (int)$connection->fetchOne($selectSize);
$lastPageNumber = ceil($sizeAll/$pageSize);

for($i = 1;$i<=$lastPageNumber;$i++) {
    $select->limitPage(1, $pageSize);

    $items = $connection->fetchAll($select);
    if (!is_array($items)) {
        continue;
    }

    foreach ($items as $item) {
        $json = @unserialize($item['json']);
        if (empty($json)) {
            continue;
        }

        $objects = json_decode($json);
        if (is_null($objects)) {
            $connection->delete($tableImport, sprintf('import_id = "%s"', $item['import_id']));
            continue;
        }

        if (!is_array($objects)) {
            continue;
        }

        foreach ($objects as $object) {
            $connection->insert($tableImport, array(
                'import_id'   => TNW_Salesforce_Model_Mysql4_Import::generateId(),
                'object_id'   => !empty($object->Id) ? $object->Id : '',
                'object_type' => !empty($object->attributes->type) ? $object->attributes->type : '',
                'json'        => json_encode($object),
            ));
        }

        $connection->delete($tableImport, sprintf('import_id = "%s"', $item['import_id']));
    }
}

$installer->endSetup();