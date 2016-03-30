<?php

class TNW_Salesforce_Helper_Salesforce_Campaign_Salesrule extends TNW_Salesforce_Helper_Salesforce_Campaign_Abstract
{
    /**
     * @var string
     */
    protected $_mappingEntityName = 'CampaignSalesRule';

    /**
     * @comment magento entity model alias
     * @var array
     */
    protected $_magentoEntityModel = 'salesrule/rule';

    /**
     * @param $_entity Mage_SalesRule_Model_Rule
     * @return mixed
     * @throws Exception
     */
    protected function _getEntityNumber($_entity)
    {
        return 'sr_' . $_entity->getId();
    }

    /**
     *
     */
    protected function _massAddAfter()
    {
        // Salesforce lookup, find all orders by Magento order number
        $this->_cache[sprintf('%sLookup', $this->_salesforceEntityName)] = Mage::helper('tnw_salesforce/salesforce_data_campaign')
            ->lookup($this->_cache[self::CACHE_KEY_ENTITIES_UPDATING]);

        return;
    }
}