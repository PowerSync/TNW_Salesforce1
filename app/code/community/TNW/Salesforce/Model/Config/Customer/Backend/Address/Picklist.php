<?php

/**
 * Author: Tech-N-Web, LLC (dba PowerSync)
 * Email: support@powersync.biz
 * Developer: Evgeniy Ermolaev
 * Date: 01.12.15
 * Time: 16:07
 */
class TNW_Salesforce_Model_Config_Customer_Backend_Address_Picklist extends Mage_Core_Model_Config_Data
{
    /**
     * update address mapping
     * @return $this
     */
    protected function _beforeSave()
    {

        $activatePicklist = $this->getValue();

        $regularFields = array(
            'Lead:State',
            'Lead:Country',

            'Contact:MailingState',
            'Contact:MailingCountry',

            'Contact:OtherState',
            'Contact:OtherCountry',

            'Order:BillingState',
            'Order:BillingCountry',

            'Order:ShippingState',
            'Order:ShippingCountry',
        );

        /** @var TNW_Salesforce_Model_Mysql4_Mapping_Collection $groupCollection */
        $groupCollection = Mage::getModel('tnw_salesforce/mapping')->getCollection();
        $tableName = $groupCollection->getMainTable();

        $recordsToUpdate = array();

        /**
         *
         */
        foreach ($regularFields as $value) {

            foreach ($groupCollection as $_mapping) {
                $mappingId = $_mapping->getMappingId();
                $_tmp = explode(':', $value);
                $_objectName = $_tmp[0];
                $_fieldName = $_tmp[1];

                /**
                 * change
                 */
                if ($_mapping->getSfField() == $_fieldName && $_mapping->getSfObject() == $_objectName) {
                    $recordsToUpdate[!$activatePicklist][] = $mappingId;
                } elseif ($_mapping->getSfField() == ($_fieldName . 'Code') && $_mapping->getSfObject() == $_objectName) {
                    $recordsToUpdate[$activatePicklist][] = $mappingId;
                }
            }
        }

        /**
         * Get the resource model
         */
        $resource = Mage::getSingleton('core/resource');

        /**
         * Retrieve the write connection
         */
        $writeConnection = $resource->getConnection('core_write');

        foreach ($recordsToUpdate as $activateFlag => $ids) {

            $writeConnection->update(
                $tableName,
                array('magento_sf_enable'=>$activateFlag, 'sf_magento_enable'=>$activateFlag),
                array('mapping_id IN(?)'=>$ids)
            );
        }

        return $this;
    }
}