<?php
/**
 * @var $installer Mage_Core_Model_Resource_Setup
 */
$installer = $this;
$installer->startSetup();

$mappingTable = $installer->getTable('tnw_salesforce/mapping');
$installer->getConnection()->update($mappingTable, array(
    'sf_field' => new Zend_Db_Expr("REPLACE(sf_field, 'tnw_fulfilment__', 'tnw_shipment__')")
), array('sf_object IN(?)' => array('OrderShipment', 'OrderShipmentItem')));
$installer->getConnection()->update($mappingTable, array(
    'sf_field' => new Zend_Db_Expr("REPLACE(sf_field, 'tnw_fulfilment__', 'tnw_invoice__')")
), array('sf_object IN(?)' => array('OrderInvoice', 'OrderInvoiceItem')));

$installer->endSetup();
