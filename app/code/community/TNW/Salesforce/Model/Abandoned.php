<?php

class TNW_Salesforce_Model_Abandoned
{
    /**
     * @return Mage_Reports_Model_Resource_Quote_Collection
     */
    public function getAbandonedCollection()
    {
        $collection = Mage::getResourceModel('reports/quote_collection');

        $dateLimit = Mage::helper('tnw_salesforce/config_sales_abandoned')->getDateLimit()->toString(Varien_Date::DATETIME_INTERNAL_FORMAT);
        $collection->addFieldToFilter('main_table.updated_at', array('lteq' => $dateLimit));

        $collection->addFieldToFilter('main_table.is_active', 1);
        $collection->addFieldToFilter('main_table.items_count', array('neq' => 0));
        $collection->addFieldToFilter('main_table.base_subtotal', array('neq' => 0.0000));

        return $collection;
    }
}