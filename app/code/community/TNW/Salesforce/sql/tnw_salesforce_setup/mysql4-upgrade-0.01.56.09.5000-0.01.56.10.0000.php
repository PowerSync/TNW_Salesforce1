<?php
/**
 * @var $this TNW_Salesforce_Model_Mysql4_Setup
 */
$installer = $this;
$installer->startSetup();

$connection = $this->getConnection();

$tableImport = $installer->getTable('tnw_salesforce/import');
$connection->addColumn($installer->getTable('tnw_salesforce/queue_storage'), 'sync_type', 'varchar(50) DEFAULT \'outgoing\'');
$connection->addColumn($tableImport, 'object_id', 'varchar(50)');
$connection->addColumn($tableImport, 'object_type', 'varchar(50)');
// Need a better solution for older MySQL version. Need to create a triger for this table?
//$connection->addColumn($tableImport, 'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
$connection->addColumn($tableImport, 'created_at', 'DATETIME NOT NULL');

//$installer->run("CREATE TRIGGER `insert_import_trigger` BEFORE INSERT ON `" . $installer->getTable('tnw_salesforce/queue_storage') . "` FOR EACH ROW SET NEW.created_at = NOW();");


$select = $connection->select()
    ->from($tableImport);

$items = $connection->fetchAll($select);
if (is_array($items)) {
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