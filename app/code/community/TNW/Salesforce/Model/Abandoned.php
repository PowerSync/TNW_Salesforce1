<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Abandoned
{
    /**
     * @return Mage_Reports_Model_Resource_Quote_Collection
     */
    public function getAbandonedCollection($showSynchronized = false)
    {
        $collection = Mage::getResourceModel('reports/quote_collection');

        $dateLimit = Mage::helper('tnw_salesforce/config_sales_abandoned')->getDateLimit()->toString(Varien_Date::DATETIME_INTERNAL_FORMAT);
        if ($showSynchronized) {
            $collection->addFieldToFilter(array('main_table.updated_at', 'main_table.salesforce_id'), array(array('lteq' => $dateLimit), array('notnull' => true)));
        } else {
            $collection->addFieldToFilter('main_table.updated_at', array('lteq' => $dateLimit));
        }

        $collection->addFieldToFilter('main_table.is_active', 1);
        $collection->addFieldToFilter('main_table.items_count', array('neq' => 0));
        $collection->addFieldToFilter('main_table.base_subtotal', array('neq' => 0.0000));

        return $collection;
    }
}