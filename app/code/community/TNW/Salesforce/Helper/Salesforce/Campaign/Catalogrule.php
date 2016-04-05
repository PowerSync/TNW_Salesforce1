<?php

class TNW_Salesforce_Helper_Salesforce_Campaign_Catalogrule extends TNW_Salesforce_Helper_Salesforce_Campaign_Abstract
{
    /**
     * @comment magento entity alias "convert from"
     * @var string
     */
    protected $_magentoEntityName = 'catalogrule';

    /**
     * @var string
     */
    protected $_mappingEntityName = 'CampaignCatalogRule';

    /**
     * @comment magento entity model alias
     * @var array
     */
    protected $_magentoEntityModel = 'catalogrule/rule';

    /**
     * @param $_entity Mage_CatalogRule_Model_Rule
     * @return mixed
     * @throws Exception
     */
    protected function _getEntityNumber($_entity)
    {
        return 'cr_' . $_entity->getId();
    }

    /**
     * @param $_entity Mage_CatalogRule_Model_Rule
     * @param $type string
     * @return mixed
     */
    protected function _getObjectByEntityType($_entity, $type)
    {
        switch($type)
        {
            case 'Catalog Rule':
                $_object = $_entity;
                break;

            case 'Custom':
                $websiteIds = $_entity->getWebsiteIds();
                $_object = Mage::app()->getWebsite(reset($websiteIds))->getDefaultStore();
                break;

            default:
                $_object = null;
                break;
        }

        return $_object;
    }
}