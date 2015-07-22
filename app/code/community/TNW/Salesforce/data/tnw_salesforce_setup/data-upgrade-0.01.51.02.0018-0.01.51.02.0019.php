<?php
/**
 * Author: Tech-N-Web, LLC (dba PowerSync)
 * Email: support@powersync.biz
 * Developer: Evgeniy Ermolaev
 * Date: 16.07.15
 * Time: 12:57
 */
/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;

$installer->startSetup();

$updateMapping = array(
    'default_value' => 'salesforce_customer/lead_config/lead_source'
);

$where = array(
    'local_field = ?' => 'Custom : lead_source',
    'sf_field = ?' => 'LeadSource',
    'sf_object = ?' => 'Lead'
);


$installer->getConnection()->update(
    $installer->getTable('tnw_salesforce/mapping'),
    $updateMapping,
    $where

);


$installer->endSetup();