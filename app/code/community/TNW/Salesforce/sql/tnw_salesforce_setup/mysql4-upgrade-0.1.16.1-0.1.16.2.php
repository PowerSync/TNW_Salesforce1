<?php

$installer = $this;

$installer->startSetup();

$setup = new Mage_Eav_Model_Entity_Setup('core_setup');

/* Adding Salesforce Lead ID to Customer object */
$setup->addAttribute('customer', 'salesforce_lead_id', array(
    'label' => 'Salesforce Lead ID',
    'type' => 'varchar',
    'input' => 'text',
    'visible' => false,
    'required' => false,
    'position' => 1,
));

/* Dropping the table for old instalations */
/*
$sqlToUpdate = '';
$db = Mage::getSingleton('core/resource')->getConnection('core_write');
$salesforceIdAttribute = Mage::getResourceModel('eav/entity_attribute')->getIdByCode('customer','salesforce_account_id');
$groupCollection = Mage::getModel('tnw_salesforce/group')->getCollection();
$accountIdTable = Mage::getSingleton('core/resource')->getTableName('customer_entity_varchar');
foreach($groupCollection as $_group) {
    $groupId = $_group->getCustomerGroupId();
    $accountId = $_group->getSfAccountCode();
    if ($groupId && $accountId) {
        //Test if user exists
        $sql = "SELECT entity_id FROM `".Mage::getSingleton('core/resource')->getTableName('customer_entity') ."` WHERE group_id = " . $groupId;
        $rows = $db->query($sql)->fetchAll();
        if ($rows) {
            foreach ($rows as $_row) {
                if (array_key_exists('entity_id',$_row)) {
                    $sql = "SELECT value_id FROM `" . $accountIdTable . "` WHERE entity_id = " . $_row['entity_id'] . " AND attribute_id = " . $salesforceIdAttribute;
                    $foundAccountId = $db->query($sql)->fetch();
                    if (!$foundAccountId) {
                            $sqlToUpdate .= "INSERT INTO `" . $accountIdTable . "` VALUES (NULL,1," . $salesforceIdAttribute . "," . $_row['entity_id'] . ",'" . $accountId . "');";
                    } else {
                            $sqlToUpdate .= "UPDATE `" . $accountIdTable . "` SET value = '" . $accountId . "' WHERE entity_id = " . $_row['entity_id'] . " AND attribute_id = " . $salesforceIdAttribute . ";";
                    }
                }
            }
        }
    }
}
if (!empty($sqlToUpdate)) {
    $db->query($sqlToUpdate);
}
*/
$installer->endSetup(); 