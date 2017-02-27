<?php

class TNW_Salesforce_Helper_Magento_Wishlist extends TNW_Salesforce_Helper_Magento_Abstract
{

    /**
     * @param stdClass $object
     * @return mixed
     * @throws Exception
     */
    public function syncFromSalesforce($object = null)
    {
        $this->_prepare();

        if (empty($object->Id)) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveError('Upserting wishlist into Magento: Opportunity ID is missing');

            $this->_addError('Could not upsert Wishlist into Magento, salesforce ID is missing', 'SALESFORCE_ID_IS_MISSING');
            return false;
        }

        $_swmIdKey = TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL . 'Magento_ID__c';
        $_swmId = !empty($object->$_swmIdKey) ? str_replace(TNW_Salesforce_Helper_Salesforce_Wishlist::SALESFORCE_ENTITY_PREFIX, '', $object->$_swmIdKey) : null;

        $_mwId = null;
        // Lookup product by Magento Id
        if ($_swmId) {
            $wishlist = Mage::getModel('wishlist/wishlist')->load($_swmId);
            $_mwId = $wishlist->getId();

            if ($_mwId) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace('Wishlist loaded using Magento ID: ' . $_mwId);
            }
        }

        if (!$_mwId && $object->Id) {
            $wishlist = Mage::getModel('wishlist/wishlist')->load($object->Id, 'salesforce_id');
            $_mwId = $wishlist->getId();

            if ($_mwId) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace("Wishlist #{$_mwId} Loaded by using Salesforce ID: {$object->Id}");
            }
        }

        if (!$_mwId) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace('SKIPPING: could not find the wishlist by number: '. $object->Id);

            return false;
        }

        return $this->_updateMagento($object, $_mwId);
    }

    /**
     * @param $object
     * @param $_mwId
     * @return Mage_Wishlist_Model_Wishlist
     * @throws Exception
     */
    protected function _updateMagento($object, $_mwId)
    {
        /** @var Mage_Wishlist_Model_Wishlist $wishlist */
        $wishlist = Mage::getModel('wishlist/wishlist')
            ->load($_mwId);

        $wishlist->addData(array(
            'salesforce_id' => $object->Id,
            'sf_insync' => 1
        ));

        $this->addEntityToSave('Wishlist', $wishlist);

        $this->_updateMappedEntityFields($object, $wishlist)
            ->_updateMappedEntityItemFields($object, $wishlist)
            ->saveEntities();

        return $wishlist;
    }

    /**
     * @param $object stdClass
     * @param $wishlist Mage_Wishlist_Model_Wishlist
     * @return $this
     */
    protected function _updateMappedEntityFields($object, $wishlist)
    {
        $mappings = Mage::getResourceModel('tnw_salesforce/mapping_collection')
            ->addObjectToFilter('WishlistOpportunity')
            ->addFilterTypeSM(true)
            ->firstSystem();

        /** @var TNW_Salesforce_Model_Mapping $mapping */
        foreach ($mappings as $mapping) {
            if (strpos($mapping->getLocalFieldType(), 'Wishlist') !== 0) {
                continue;
            }

            $value = property_exists($object, $mapping->getSfField())
                ? $object->{$mapping->getSfField()} : null;

            Mage::getSingleton('tnw_salesforce/mapping_type_wishlist')
                ->setMapping($mapping)
                ->setValue($wishlist, $value);

            $this->addEntityToSave('Wishlist', $wishlist);
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace('Wishlist: ' . $mapping->getLocalFieldAttributeCode() . ' = ' . var_export($wishlist->getData($mapping->getLocalFieldAttributeCode()), true));
        }

        return $this;
    }

    /**
     * @param $object stdClass
     * @param $wishlist Mage_Wishlist_Model_Wishlist
     * @return $this
     * @throws Mage_Core_Exception
     */
    protected function _updateMappedEntityItemFields($object, $wishlist)
    {
        if (!is_array($object->OpportunityLineItems->records)) {
            return $this;
        }

        $_websiteId = false;
        $_websiteSfField = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix()
            . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject();

        if (property_exists($object, $_websiteSfField)) {
            $_websiteSfId = Mage::helper('tnw_salesforce')
                ->prepareId($object->{$_websiteSfField});

            $_websiteId = array_search($_websiteSfId, $this->_websiteSfIds);
        }

        $_websiteId = ($_websiteId === false) ? Mage::app()->getWebsite(true)->getId() : $_websiteId;
        $storeId = Mage::app()->getWebsite($_websiteId)->getDefaultGroup()->getDefaultStoreId();

        //FIX: Delete Item
        $wishlist->getItemCollection()
            ->setVisibilityFilter(false)
            ->setSalableFilter(false);

        foreach ((array)$object->OpportunityLineItems->records as $record) {
            $product = $this->getProductByOpportunityLine($record);
            if (null === $product->getId()) {
                continue;
            }

            if (!$this->isProductValidate($product)) {
                continue;
            }

            //FIX: Store Item
            $product->setData('wishlist_store_id', $storeId);

            $wishlistItem = $wishlist->addNewItem($product, new Varien_Object(array('qty'=>$record->Quantity)), true);
            if (is_string($wishlistItem)) {
                Mage::throwException($wishlistItem);
            }

            $mappings = Mage::getResourceModel('tnw_salesforce/mapping_collection')
                ->addObjectToFilter('WishlistOpportunityLine')
                ->addFilterTypeSM(true)
                ->firstSystem();

            foreach ($mappings as $mapping) {
                if (strpos($mapping->getLocalFieldType(), 'Wishlist Item') !== 0) {
                    continue;
                }

                $value = property_exists($record, $mapping->getSfField())
                    ? $record->{$mapping->getSfField()} : null;

                Mage::getSingleton('tnw_salesforce/mapping_type_wishlist_item')
                    ->setMapping($mapping)
                    ->setValue($wishlistItem, $value);

                $this->addEntityToSave('Wishlist Item '.$wishlistItem->getId(), $wishlistItem);
            }
        }

        return $this;
    }

    /**
     * @param Mage_Catalog_Model_Product $product
     * @return bool
     */
    protected function isProductValidate($product)
    {
        $allowedProductType = array(
            Mage_Catalog_Model_Product_Type::TYPE_SIMPLE,
            Mage_Catalog_Model_Product_Type::TYPE_VIRTUAL,
        );

        if (!in_array($product->getTypeId(), $allowedProductType)) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveNotice('Product (sku:'.$product->getSku().') skipping. Product type "'.$product->getTypeId().'"');

            return false;
        }

        $collection = Mage::getResourceModel('catalog/product_option_collection')
            ->addFieldToFilter('product_id', $product->getId())
            ->addRequiredFilter(1);

        if ($collection->getSize() > 1) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveNotice('Product (sku:'.$product->getSku().') was skipped. It sas custom option(s).');

            return false;
        }

        return true;
    }

    /**
     * @param $record stdClass
     * @return Mage_Catalog_Model_Product
     */
    protected function getProductByOpportunityLine($record)
    {
        /** @var Mage_Catalog_Model_Resource_Product_Collection $collection */
        $collection = Mage::getResourceModel('catalog/product_collection');
        $collection->addAttributeToFilter('salesforce_id', $record->PricebookEntry->Product2Id);

        return $collection->getFirstItem();
    }
}