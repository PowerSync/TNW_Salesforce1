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

        $_sOpportunityId = !empty($object->Id) ? $object->Id : null;
        if ($_sOpportunityId) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveError('Upserting wishlist into Magento: Opportunity ID is missing');

            $this->_addError('Could not upsert Wishlist into Magento, salesforce ID is missing', 'SALESFORCE_ID_IS_MISSING');
            return false;
        }

        $_mwIdKey = TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL . 'Magento_ID__c';
        $_mwId = !empty($object->$_mwIdKey) ? $object->$_mwIdKey : null;

        // Lookup product by Magento Id
        if ($_mwId) {
            $wishlist = Mage::getModel('wishlist/wishlist')->load($_mwId);
            $_mwId = $wishlist->getId();

            if ($_mwId) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace('Wishlist loaded using Magento ID: ' . $_mwId);
            }
        }

        if (!$_mwId && $_sOpportunityId) {
            $wishlist = Mage::getModel('wishlist/wishlist')->load($_sOpportunityId, 'salesforce_id');
            $_mwId = $wishlist->getId();

            if ($_mwId) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace("Wishlist #{$_mwId} Loaded by using Salesforce ID: {$_sOpportunityId}");
            }
        }

        if (!$_mwId) {
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

        foreach ((array)$object->OpportunityLineItems->records as $record) {
            $product = $this->getProductByOpportunityLine($record);
            if (null === $product->getId()) {
                continue;
            }

            $wishlistItem = $wishlist->addNewItem($product, new Varien_Object(array('qty'=>$record->Quantity)));
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

                $this->addEntityToSave('Wishlist Item '.$record->Id, $wishlistItem);
            }
        }

        return $this;
    }

    /**
     * @param $record stdClass
     * @return Mage_Catalog_Model_Product
     */
    protected function getProductByOpportunityLine($record)
    {
        /** @var Mage_Catalog_Model_Resource_Product_Collection $collection */
        $collection = Mage::getResourceModel('catalog/product');
        $collection->addAttributeToFilter('salesforce_id', $record->PricebookEntry->Product2Id);

        return $collection->getFirstItem();
    }
}