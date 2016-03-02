<?php
/**
 * @var $installer Mage_Core_Model_Resource_Setup
 * @comment change current values in DB to the default. Old values can be too big and it is the reason of lookup problems
 *@see PCMI-562
 */
$installer = $this;

$installer->startSetup();

$configFromDb = Mage::getModel('core/config_data')
    ->getCollection();

$batchSizeLimitPaths = Mage::helper('tnw_salesforce/config_bulk')->getPaths();
$configFromDb->addFieldToFilter('path', array('in' => $batchSizeLimitPaths));

$configFile = Mage::getConfig()->getModuleDir('etc', 'TNW_Salesforce') . DS . 'config.xml';
$string = file_get_contents($configFile);
$xmlModuleConfig = simplexml_load_string($string, 'Varien_Simplexml_Element');

$tmpArray = $xmlModuleConfig->xpath('default');
$xmlDefaultModuleConfig = array_pop($tmpArray);

foreach ($configFromDb as $config) {

    /**
     * @var $xmlValue SimpleXMLElement
     */
    $tmpArray = $xmlDefaultModuleConfig->xpath($config->getPath());
    $xmlValue = array_pop($tmpArray);
    $defaultValue = (string)$xmlValue;
    $config->setValue($defaultValue);
}
$configFromDb->save();

$installer->endSetup();
