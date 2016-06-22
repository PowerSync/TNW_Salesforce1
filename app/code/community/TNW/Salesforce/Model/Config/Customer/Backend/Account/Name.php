<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Config_Customer_Backend_Account_Name extends Mage_Core_Model_Config_Data
{
    /**
     * update address mapping
     * @return $this
     */
    protected function _beforeSave()
    {
        $activate = $this->getValue();

        /** @var TNW_Salesforce_Model_Mysql4_Mapping_Collection $groupCollection */
        $groupCollection = Mage::getResourceModel('tnw_salesforce/mapping_collection')
            ->addFieldToFilter('local_field', array('eq' => 'Customer : sf_company'))
            ->addFieldToFilter('sf_field',    array('eq' => 'Name'))
            ->addFieldToFilter('sf_object',   array('eq' => 'Account'));

        if ($groupCollection->count() > 0) {
            $groupCollection->getFirstItem()
                ->setData('magento_sf_type', $activate ? 'insert' : 'upsert')
                ->save();
        }

        return $this;
    }
}