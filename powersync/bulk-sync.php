<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

/* Instantiate Magento */
$mageFilename = '../app/Mage.php';
require_once $mageFilename;
unset($mageFilename);

Mage::app();

if (Mage::helper('tnw_salesforce')->getType() != "PRO") {
    echo "You will need to upgrade to Enterprise version of the connector to use this feature.\r\n";
    die();
}

$options = getopt("c:t:b:f:");
$_salesforceSessionId = NULL;
$_salesforceServerDomain = NULL;

if (
    !array_key_exists('t', $options)
    || !array_key_exists('b', $options)
) {
    echo "#######################################\r\n";
    echo "##    PowerSync CLI Tool v.001(a)\r\n";
    echo "#######################################\r\n";
    echo "\r\n";
    echo "Usage: \r\n";
    echo "  -t     | Type: product, order, customer\r\n";
    echo "\r\n";
    echo "  -b     | Batch size, depends on your system\r\n";
    echo "         | suggested not to go above 5000, default is 100\r\n";
    echo "\r\n";
    echo "  -c     | Number of parallel threads to spawn, default value is 1.\r\n";
    echo "         | for 64-bit systems with a lot of RAM you can increase this\r\n";
    echo "         | Example: php bulk-sync.php -s admin.mystore.com -t product -b 1200 -c 5'\r\n";
    echo "\r\n";
    echo "  -force | Forces to synchronize all entities and not just\r\n";
    echo "         | the once that are out of sync.\r\n";
    echo "         | Example: php bulk-sync.php -s admin.mystore.com -t product -b 1000 -force'\r\n";
    echo "\r\n";
    echo "#######################################\r\n";
    die();
}

$threadCount = (array_key_exists('c', $options)) ? intval($options['c']) : 1;
//$threadCount = 1;
$_type = (array_key_exists('t', $options)) ? $options['t'] : NULL;
if (!$_type || ($_type != 'product' && $_type != 'customer' && $_type != 'order')) {
    echo "Invalid entity type specified! Please run 'php cli-sync.php' for more information.\r\n";
    die();
}
$_batchSize = (array_key_exists('b', $options)) ? intval($options['b']) : 100;
if ($_batchSize > 5000) {
    echo "Warning, batch size may be too large for some systems.\r\n";
}
$_isForced = (array_key_exists('f', $options) && $options['f'] == "force") ? TRUE : FALSE;

$cpbAdapter = new Zend_ProgressBar_Adapter_Console(
    array(
        'elements' => array(
            Zend_ProgressBar_Adapter_Console::ELEMENT_PERCENT,
            Zend_ProgressBar_Adapter_Console::ELEMENT_BAR,
            Zend_ProgressBar_Adapter_Console::ELEMENT_ETA,
            Zend_ProgressBar_Adapter_Console::ELEMENT_TEXT
        )
    )
);

switch ($_type) {
    case 'product':
        $collection = Mage::getModel('catalog/product')->getCollection()
            ->addAttributeToSelect('price');
        if (!$_isForced) {
            $attribute = Mage::getModel('eav/entity_attribute')->loadByCode('catalog_product', 'sf_insync');
            $collection->getSelect()->joinLeft(
                array('at_sf_insync' => Mage::helper('tnw_salesforce')->getTable('catalog_product_entity_int')),
                '`at_sf_insync`.`entity_id` = `e`.`entity_id` AND `at_sf_insync`.`attribute_id` = "' . $attribute->getId() . '" AND `at_sf_insync`.`store_id` = 0',
                array('sf_insync' => 'value')
            );

            // we skip some types of products
            $collection->getSelect()->where("`e`.`type_id` not in ('" . Mage_Catalog_Model_Product_Type::TYPE_GROUPED . "', '" . Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE . "')");

            $collection->getSelect()->where('`at_sf_insync`.`value` != 1 OR `at_sf_insync`.`value` IS NULL');
            //echo $collection->getSelect()->__toString(); die;
        }
        break;
    case 'customer':
        $collection = Mage::getModel('customer/customer')->getCollection();
        if (!$_isForced) {
            $attribute = Mage::getModel('eav/entity_attribute')->loadByCode('customer', 'sf_insync');
            $collection->getSelect()->joinLeft(
                array('at_sf_insync' => Mage::helper('tnw_salesforce')->getTable('customer_entity_int')),
                '`at_sf_insync`.`entity_id` = `e`.`entity_id` AND `at_sf_insync`.`attribute_id` = "' . $attribute->getId() . '"',
                array('sf_insync' => 'value')
            );
            $collection->getSelect()->where('`at_sf_insync`.`value` != 1 OR `at_sf_insync`.`value` IS NULL');
        }
        break;
    case 'order':
        $collection = Mage::getModel('sales/order')->getCollection();
        if (!$_isForced) {
            $collection->addAttributeToFilter('sf_insync', array('neq' => 1));
        }
        break;
}
$_allIds = $collection->getAllIds();
$collection = NULL;
unset($collection);

if (empty($_allIds)) {
    echo "All " . $_type . "s are in sync!\r\n";
    die();
}

$batches = ceil(count($_allIds) / $_batchSize);
$progressBar = new Zend_ProgressBar($cpbAdapter, 0, $batches);
$i = 0;

$child = 'php cli-sync.php';
$processesCheck = "ps x | grep \"$child\" | grep -v grep";

$_allIds = NULL;
unset($_allIds);

if (!gc_enabled()) {
    gc_enable();
}
gc_collect_cycles();
gc_disable();

for (; $batches; $batches--) {
    $output = '';
    waitForChildren($processesCheck, $threadCount);
    $_command = $child . " -all -t " . $_type . " -e " . $_batchSize . " -b " . ($i + 1);
    if ($_isForced) {
        $_command .= " -force";
    }
    $_command .= " > /dev/null &";
    //$_command .= " &";
    //exec($_command, $output, $status);
    exec($_command);
    $progressBar->update(++$i, '');
}
waitForChildren($processesCheck, 1);
$progressBar->finish();

die();

/**
 * @param $command
 * @param $count
 */
function waitForChildren($command, $count)
{
    $out = array();
    exec($command, $out);
    $processCount = count($out);
    while ($processCount >= $count) {
        sleep(4);
        $out = array();
        exec($command, $out);
        $processCount = count($out);
    }
    if (!gc_enabled()) {
        gc_enable();
    }
    gc_collect_cycles();
    gc_disable();
}