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

        $mappings = array(
            'Lead'              => array('State', 'Country'),
            'Contact'           => array('MailingState', 'MailingCountry', 'OtherState', 'OtherCountry'),
            'Order'             => array('BillingState', 'BillingCountry', 'ShippingState', 'ShippingCountry'),
            'OrderCreditMemo'   => array('BillingState', 'BillingCountry', 'ShippingState', 'ShippingCountry'),
        );

        $where = $whereCode = array();
        foreach ($mappings as $sfObject=>$fields) {
            foreach ($fields as $field) {
                $where[]        = array('sf_object'=>$sfObject, 'sf_field'=>$field);
                $whereCode[]    = array('sf_object'=>$sfObject, 'sf_field'=>$field.'Code');
            }
        }

        $mapping = Mage::getResourceModel('tnw_salesforce/mapping');
        $mapping->massUpdateEnable(array(
            'magento_sf_enable' => !$activatePicklist,
            'sf_magento_enable' => !$activatePicklist
        ), $where);
        $mapping->massUpdateEnable(array(
            'magento_sf_enable' => $activatePicklist,
            'sf_magento_enable' => $activatePicklist
        ), $whereCode);

        // CreditMemo Update
        $cmWhere = $cmWhereCode = array();
        foreach (array('BillingState', 'BillingCountry', 'ShippingState', 'ShippingCountry') as $field) {
            $cmWhere[]        = array('sf_object'=>'OrderCreditMemo', 'sf_field'=>$field);
            $cmWhereCode[]    = array('sf_object'=>'OrderCreditMemo', 'sf_field'=>$field.'Code');
        }

        $mapping->massUpdateEnable(array(
            'magento_sf_enable' => !$activatePicklist,
            'sf_magento_enable' => '0'
        ), $cmWhere);
        $mapping->massUpdateEnable(array(
            'magento_sf_enable' => $activatePicklist,
            'sf_magento_enable' => '0'
        ), $cmWhereCode);

        return $this;
    }
}