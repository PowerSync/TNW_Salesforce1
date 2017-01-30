<?php

class TNW_Salesforce_Helper_Salesforce_Wishlist extends TNW_Salesforce_Helper_Salesforce_Abstract_Base
{
    /**
     * @comment magento entity alias "convert from"
     * @var string
     */
    protected $_magentoEntityName = 'wishlist';

    /**
     * @comment salesforce entity alias "convert to"
     * @var string
     */
    protected $_salesforceEntityName = 'opportunity';

    /**
     * @var string
     */
    protected $_mappingEntityName = 'WishlistOpportunity';

    /**
     * @var string
     */
    protected $_mappingEntityItemName = 'WishlistOpportunityLine';

    /**
     * @comment magento entity model alias
     * @var array
     */
    protected $_magentoEntityModel = 'wishlist/wishlist';

    /**
     * @param $_entity
     * @return bool
     * @throws Exception
     */
    protected function _checkMassAddEntity($_entity)
    {
        return true;
    }

    /**
     * @param $_entity Mage_Wishlist_Model_Wishlist
     * @return mixed
     */
    protected function _getEntityNumber($_entity)
    {
        return "wish_{$_entity->getId()}";
    }

    /**
     * @param $entityItem Mage_Wishlist_Model_Item
     * @return Mage_Wishlist_Model_Wishlist
     */
    public function getEntityByItem($entityItem)
    {
        return $this->getEntityCache($entityItem->getWishlistId());
    }

    /**
     *
     */
    protected function _massAddAfter()
    {
        $this->_cache[sprintf('%sLookup', $this->_salesforceEntityName)] = Mage::helper('tnw_salesforce/salesforce_data')
            ->opportunityLookup($this->_cache[self::CACHE_KEY_ENTITIES_UPDATING]);
    }

    /**
     * @comment return entity items
     * @param $_entity Mage_Wishlist_Model_Wishlist
     * @return Mage_Wishlist_Model_Item[]
     */
    public function getItems($_entity)
    {
        $entityNumber = $this->_getEntityNumber($_entity);
        if (empty($this->_cache['entityItems'][$entityNumber])) {
            /** @var Mage_Wishlist_Model_Resource_Item_Collection $collection */
            $collection = Mage::getResourceModel('wishlist/item_collection')
                ->addWishlistFilter($this)
                ->setVisibilityFilter();

            $this->_cache['entityItems'][$entityNumber] = $collection->getItems();
        }

        return $this->_cache['entityItems'][$entityNumber];
    }

    /**
     * @param $_entity
     * @param $key
     */
    protected function _prepareEntityObjCustom($_entity, $key)
    {
        if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {
            /** @var Mage_Core_Model_Store $store */
            $store = $this->_getObjectByEntityType($_entity, 'Custom');
            $this->_obj->CurrencyIsoCode = $store->getBaseCurrency()->getCode();
        }

        $this->_obj->{TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE . 'disableMagentoSync__c'}
            = true;
    }

    /**
     * @param $_entity Mage_Wishlist_Model_Wishlist
     * @param $type string
     * @return mixed
     */
    protected function _getObjectByEntityType($_entity, $type)
    {
        switch ($type) {
            case 'Wishlist':
                $_object = $_entity;
                break;

            case 'Customer':
                $_entityNumber = $this->_getEntityNumber($_entity);
                $_object = $this->_cache[sprintf('%sCustomers', $this->_magentoEntityName)][$_entityNumber];
                break;

            case 'Custom':
                /** @var Mage_Customer_Model_Customer $customer */
                $customer = $this->_getObjectByEntityType($_entity, 'Customer');
                $_object = $customer->getStore();
                break;

            default:
                $_object = null;
                break;
        }

        return $_object;
    }

    /**
     * @throws Exception
     */
    protected function _pushEntity()
    {

    }

    /**
     * @param $_entityItem Mage_Wishlist_Model_Item
     */
    protected function _prepareEntityItemObjCustom($_entityItem)
    {
        $key = $_entityItem->getId();
        $this->_cache[sprintf('%sToUpsert', lcfirst($this->getItemsField()))]['cart_' . $key] = $this->_obj;
    }

    /**
     * @param $_entityItem Mage_Wishlist_Model_Item
     * @param $_type string
     * @return mixed
     * @throws \Mage_Core_Exception
     */
    protected function _getObjectByEntityItemType($_entityItem, $_type)
    {
        switch($_type)
        {
            case 'Wishlist':
                $_object = $this->getEntityByItem($_entityItem);
                break;

            case 'Product':
                $_object = $_entityItem->getProduct();
                break;

            case 'Product Inventory':
                $product = $this->_getObjectByEntityItemType($_entityItem, 'Product');
                $_object = Mage::getModel('cataloginventory/stock_item')
                    ->loadByProduct($product);
                break;

            case 'Custom':
                $entity = $this->getEntityByItem($_entityItem);
                $_object = $this->_getObjectByEntityType($entity, 'Custom');
                break;

            default:
                $_object = null;
                break;
        }

        return $_object;
    }

    /**
     * @param $_key
     * @return bool
     */
    protected function _checkPrepareEntityItem($_key)
    {
        return true;
    }

    /**
     * @param array $chunk
     * @throws Exception
     */
    protected function _pushEntityItems($chunk = array())
    {

    }
}