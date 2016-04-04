<?php

class TNW_Salesforce_Helper_Salesforce_Campaign_Salesrule extends TNW_Salesforce_Helper_Salesforce_Campaign_Abstract
{
    /**
     * @comment magento entity alias "convert from"
     * @var string
     */
    protected $_magentoEntityName = 'salesrule';

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
     * @param $_entity Mage_SalesRule_Model_Rule
     * @param $type string
     * @return mixed
     */
    protected function _getObjectByEntityType($_entity, $type)
    {
        switch($type)
        {
            case 'Shopping Cart Rule':
                $_object = $_entity;
                break;

            case 'Custom':
                $_object = Mage::app()->getStore();
                break;

            default:
                $_object = null;
                break;
        }

        return $_object;
    }
}