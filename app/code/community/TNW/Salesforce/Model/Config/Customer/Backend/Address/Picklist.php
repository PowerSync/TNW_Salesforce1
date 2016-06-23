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

        $sfObject = array('Lead', 'Contact', 'Order', 'OrderCreditMemo');
        $sfField  = array(
            'State',         'Country',
            'MailingState',  'MailingCountry',
            'OtherState',    'OtherCountry',
            'BillingState',  'BillingCountry',
            'ShippingState', 'ShippingCountry'
        );

        $sfFieldCode = array_map(function($field) {
            return $field . 'Code';
        }, $sfField);

        $tableName = Mage::getResourceModel('tnw_salesforce/mapping')->getMainTable();

        /**
         * Retrieve the write connection
         */
        $writeConnection = Mage::getSingleton('core/resource')->getConnection('core_write');

        $writeConnection->update($tableName, array(
            'magento_sf_enable' => !$activatePicklist,
            'sf_magento_enable' => !$activatePicklist
        ), array('sf_field IN(?)' => $sfField, 'sf_object IN(?)' => $sfObject));

        $writeConnection->update($tableName, array(
            'magento_sf_enable' => $activatePicklist,
            'sf_magento_enable' => $activatePicklist
        ), array('sf_field IN(?)' => $sfFieldCode, 'sf_object IN(?)' => $sfObject));

        return $this;
    }
}