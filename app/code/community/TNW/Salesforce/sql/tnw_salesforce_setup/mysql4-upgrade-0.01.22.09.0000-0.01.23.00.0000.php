<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

$installer = $this;

$installer->startSetup();


$sqlToUpdate = '';
$groupCollection = Mage::getModel('tnw_salesforce/mapping')->getCollection();
$tableName = Mage::getSingleton('core/resource')->getTableName('tnw_salesforce_mapping');
foreach ($groupCollection as $_mapping) {
    $mappingId = $_mapping->getMappingId();

    $localField = explode(" : ", $_mapping->getLocalField());
    if ($localField[0] == "Order") {
        $newValue = NULL;
        switch ($localField[1]) {
            case 'status':
                $newValue = "state";
                break;
            case 'sub_total':
                $newValue = "subtotal";
                break;
            case 'tax':
                $newValue = "tax_amount";
                break;
            case 'shipping_method':
                $newValue = "shipping_description";
                break;
            case 'shipping':
                $newValue = "shipping_amount";
                break;
            case 'discount_total':
                $newValue = "discount_amount";
                break;
            case 'refunded_total':
                $newValue = "total_refunded";
                break;
            case 'due_total':
                $newValue = "total_due";
                break;
            case 'paid_total':
                $newValue = "total_paid";
                break;
            default:
                break;
        }
        if ($newValue) {
            $sql = "UPDATE `" . $tableName . "` SET local_field = 'Order : " . $newValue . "' WHERE mapping_id = " . $mappingId;
            //Execute
            $db = Mage::getSingleton('core/resource')->getConnection('core_write');
            $db->query($sql);
        }
    }
}

$installer->endSetup();