<?php

class TNW_Salesforce_Model_Config_Customer_Backend_Newsletter_Enable extends Mage_Core_Model_Config_Data
{
    /**
     * update address mapping
     * @return $this
     */
    protected function _beforeSave()
    {

        $activate = $this->getValue();

        $mappings = array(
            'Lead'      => array('HasOptedOutOfEmail'),
            'Contact'   => array('HasOptedOutOfEmail'),
        );

        $where = array();
        foreach ($mappings as $sfObject=>$fields) {
            foreach ($fields as $field) {
                $where[]        = array('sf_object'=>$sfObject, 'sf_field'=>$field);
            }
        }

        Mage::getResourceModel('tnw_salesforce/mapping')
            ->massUpdateEnable(array(
                'magento_sf_enable' => $activate,
                'sf_magento_enable' => $activate
            ), $where);

        return $this;
    }
}