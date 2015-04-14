<?php

/**
 * Class TNW_Salesforce_Helper_Salesforce_Product
 */
class TNW_Salesforce_Helper_Salesforce_Product extends TNW_Salesforce_Helper_Salesforce_Abstract
{
    /* Salesforce ID for default Pricebook set in Magento */
    protected $_defaultPriceBook = NULL;

    /* Salesforce ID for default Pricebook set in Salesforce */
    protected $_standardPricebookId = NULL;

    protected $_isSoftUpdate = false;

    protected $_isMultiSync = false;
    protected $_currentScope = NULL;
    protected $_sqlToRun = NULL;
    protected $_orderStoreId = NULL;

    /**
     * @return bool
     */
    public function process()
    {
        try {
            if (!Mage::helper('tnw_salesforce/salesforce_data')->isLoggedIn()) {
                Mage::helper('tnw_salesforce')->log("CRITICAL: Connection to Salesforce could not be established! Check API limits and/or login info.");
                if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                    Mage::getSingleton('adminhtml/session')->addWarning('WARNING: SKIPPING synchronization, could not establish Salesforce connection.');
                }
                return false;
            }
            // Dump products that will be synced
            if (!$this->isFromCLI()) {
                foreach ($this->_cache['productsToSync'] as $_key => $_tmpObject) {
                    foreach ($_tmpObject as $_mId => $_obj) {
                        Mage::helper('tnw_salesforce')->log("------ Product ID: " . $_mId . " ------");
                        foreach ($_obj as $key => $value) {
                            Mage::helper('tnw_salesforce')->log("Product Object: " . $key . " = '" . $value . "'");
                        }
                        Mage::helper('tnw_salesforce')->log("---------------------------------------");
                    }
                }
            }

            $this->_pushProducts();
            $this->clearMemory();

            $this->_updateMagento();
            $this->clearMemory();

            $this->_onComplete();

            // Logout
            Mage::helper('tnw_salesforce')->log("================= MASS SYNC: END =================");
            return true;
        } catch (Exception $e) {
            if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                Mage::getSingleton('adminhtml/session')->addError('WARNING: ' . $e->getMessage());
            }
            Mage::helper("tnw_salesforce")->log("CRITICAL: " . $e->getMessage());
            return false;
        }
    }

    protected function _onComplete()
    {
        parent::_onComplete();

        if (Mage::helper('tnw_salesforce')->isRemoteLogEnabled()) {
            $logger = Mage::helper('tnw_salesforce/report');
            $logger->reset();
            if (array_key_exists('Id', $this->_cache['productsToSync'])) {
                $logger->add('Salesforce', 'Product2', $this->_cache['productsToSync']['Id'], $this->_cache['responses']['products']);
            }
            if (array_key_exists($this->_magentoId, $this->_cache['productsToSync'])) {
                $logger->add('Salesforce', 'Product2', $this->_cache['productsToSync'][$this->_magentoId], $this->_cache['responses']['products']);
            }

            $logger->add('Salesforce', 'PricebookEntry', $this->_cache['pricebookEntriesForUpsert'], $this->_cache['responses']['pricebooks']);
            $logger->send();
        }

        $this->reset();
        $this->clearMemory();
    }

    /**
     * @param array $ids
     */
    public function massAdd($ids = array())
    {
        try {
            if (!$this->_isMultiSync) {
                if (!Mage::app()->isSingleStoreMode()) {
                    if (
                        Mage::helper('tnw_salesforce')->getStoreId() == 0
                        && Mage::helper('tnw_salesforce')->getWebsiteId() == 0
                    ) {
                        $this->_isMultiSync = true;
                    }
                }
                if (Mage::helper('tnw_salesforce')->getPriceScope() == 0) {
                    $this->_isMultiSync = true;
                }
            }

            $skuArray = array();

            $productsCollection = Mage::getModel('catalog/product')
                ->getCollection()
                ->addAttributeToFilter('entity_id', array('in' => $ids));
            $productsCollection->addAttributeToSelect('salesforce_disable_sync');
            Mage::register('product_sync_collection', $productsCollection);
            foreach ($productsCollection as $product) {
                // we check product type and skip synchronization if needed
                if (intval($product->getData('salesforce_disable_sync')) == 1) {
                    if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                        Mage::getSingleton('adminhtml/session')->addNotice('SKIPPING: Product (ID: ' . $product->getData('entity_id') . ') is excluded from synchronization');
                    }
                    Mage::helper("tnw_salesforce")->log("SKIPPING: Product (ID: ' . $product->getData('entity_id') . ') is excluded from synchronization");
                    continue;
                }

                if (!$product->getId() || !$product->getSku()) {
                    if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                        Mage::getSingleton('adminhtml/session')->addNotice('SKIPPING: Product #' . $product->getId() . ', product sku is missing!');
                    }
                    Mage::helper("tnw_salesforce")->log("SKIPPING: Product #' . $product->getId() . ', product sku is missing!");
                    continue;
                }
                if ($product->getSku()) {
                    $this->_cache['productIdToSku'][$product->getId()] = trim($product->getSku());
                    $skuArray[] = trim($product->getSku());
                }
            }
            // Look up products in Salesforce
            if (empty($this->_cache['productsLookup'])) {
                $this->_cache['productsLookup'] = Mage::helper('tnw_salesforce/salesforce_lookup')->productLookup($skuArray);
            }

            // If multiple websites AND scope is per website AND looking as All Store Views
            if ($this->_isMultiSync) {
                $this->_syncStoreProducts();
                foreach (Mage::app()->getStores() as $_storeId => $_store) {
                    $this->_syncStoreProducts($_storeId);
                }
            } else {
                $this->_currentScope = Mage::helper('tnw_salesforce')->getStoreId();
                $this->_syncStoreProducts($this->_currentScope);
            }
            Mage::unregister('product_sync_collection');
            $productsCollection = NULL;
            unset($productsCollection);
        } catch (Exception $e) {
            if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                Mage::getSingleton('adminhtml/session')->addError('WARNING: ' . $e->getMessage());
            }
            Mage::helper("tnw_salesforce")->log("CRITICAL: " . $e->getMessage());
        }
    }

    /**
     * @param int $_storeId
     */
    protected function _syncStoreProducts($_storeId = 0)
    {
        $_collection = Mage::registry('product_sync_collection');
        foreach ($_collection as $_prod) {
            // we check product type and skip synchronization if needed
            if (intval($_prod->getData('salesforce_disable_sync')) == 1) {
                continue;
            }

            $_product = Mage::getModel('catalog/product')->setStoreId($_storeId)->load($_prod->getId());

            // Product does not exist in the store
            if (!$_product->getId()) {
                if (!array_key_exists($_storeId, $this->_cache['skipMagentoUpdate'])) {
                    $this->_cache['skipMagentoUpdate'][$_storeId] = array();
                }
                $this->_cache['skipMagentoUpdate'][$_storeId][] = $_prod->getId();
                continue;
            }

            if (Mage::helper('tnw_salesforce')->getPriceScope() != 0) {
                $_product->setStoreId($_storeId);
            }
            $this->_obj = new stdClass();

            $productPrice = number_format($_product->getPrice(), 2, ".", "");

            $_sfProductId = (is_array($this->_cache['productsLookup']) && array_key_exists($_product->getSku(), $this->_cache['productsLookup'])) ? $this->_cache['productsLookup'][$_product->getSku()]->Id : NULL;
            if ($_sfProductId) {
                $_product->setSalesforceId($_sfProductId);
            }

            $this->_buildProductObject($_product, $_sfProductId);
            if (!array_key_exists($_product->getId(), $this->_cache['productPrices'])) {
                $this->_cache['productPrices'][$_product->getId()] = array();
            }
            $this->_cache['productPrices'][$_product->getId()][$_storeId] = (float)$productPrice;
        }
        $_collection = NULL;
        unset($_collection);
        $this->clearMemory();
    }

    public function processSql()
    {
        if (!empty($this->_sqlToRun)) {
            try {
                if (!$this->_write) {
                    $this->_write = Mage::getSingleton('core/resource')->getConnection('core_write');
                }
                $this->_write->query($this->_sqlToRun . 'commit;');
            } catch (Exception $e) {
                Mage::helper('tnw_salesforce')->log("Exception: " . $e->getMessage());
            }
        }
    }

    protected function _updateMagento()
    {
        Mage::helper('tnw_salesforce')->log("---------- Start: Magento Update ----------");
        $this->_sqlToRun = "";
        $ids = array();

        foreach ($this->_cache['toSaveInMagento'] as $_magentoId => $_product) {

            $_product->salesforceId = (isset($_product->salesforceId)) ? $_product->salesforceId : NULL;
            $_product->pricebookEntryIds = (isset($_product->pricebookEntryIds)) ? $_product->pricebookEntryIds : array();
            $_product->SfInSync = isset($_product->SfInSync) ? $_product->SfInSync : 0;

            $this->updateMagentoEntityValue($_magentoId, $_product->salesforceId, 'salesforce_id');

            $this->updateMagentoEntityValue($_magentoId, $_product->SfInSync, 'sf_insync', 'catalog_product_entity_int', 0);
            foreach (Mage::app()->getStores() as $_storeId => $_store) {
                if (
                    array_key_exists($_storeId, $this->_cache['skipMagentoUpdate'])
                    && in_array($_magentoId, $this->_cache['skipMagentoUpdate'][$_storeId])
                ) {
                    continue;
                }
                $this->updateMagentoEntityValue($_magentoId, $_product->SfInSync, 'sf_insync', 'catalog_product_entity_int', $_storeId);
            }

            // Remove Standard Pricebook ID from being written into Magento, only store value for Store 0
            if (
                array_key_exists('Standard', $_product->pricebookEntryIds)
                && array_key_exists(0, $_product->pricebookEntryIds)
            ) {
                unset($_product->pricebookEntryIds['Standard']);
            }
            foreach ($_product->pricebookEntryIds as $_key => $_pbeId) {
                $this->updateMagentoEntityValue($_magentoId, $_pbeId, 'salesforce_pricebook_id', 'catalog_product_entity_varchar', $_key);
            }
            $ids[] = $_magentoId;
        }

        $this->processSql();
        /*
        if (!empty($this->_sqlToRun)) {
            //$this->processSql();

            try {
                $productsCollection = Mage::getModel('catalog/product')->getCollection();
                $productsCollection->addAttributeToFilter('entity_id', array('in' => $ids));
                $productsCollection->addAttributeToSelect('*');

                foreach ($productsCollection as $_prod) {
                    $_product = Mage::getModel('catalog/product');
                    if ($this->getOrderStoreId() !== NULL) {
                        $_product->setStoreId($_storeId);
                    }
                    $_product->load($_prod->getId());

                    //if ($this->_useCache) {
                    //    $this->_mageCache->save(serialize($_product), 'product_cache_' . $_product->getId() . '_' . $_storeId, array("TNW_SALESFORCE"));
                    //} else {
                    //    if (Mage::registry('product_cache_' . $_product->getId() . '_' . $this->getOrderStoreId())) {
                    //        Mage::unregister('product_cache_' . $_product->getId() . '_' . $this->getOrderStoreId());
                    //    }
                    //    Mage::register('product_cache_' . $_product->getId() . '_' . $this->getOrderStoreId(), $_product);
                    //}
                }
            } catch (Exception $e) {
                Mage::helper('tnw_salesforce')->log("Exception: " . $e->getMessage());
            }
        }
    */

        Mage::helper('tnw_salesforce')->log("Updated: " . count($this->_cache['toSaveInMagento']) . " products!");
        Mage::helper('tnw_salesforce')->log("---------- End: Magento Update ----------");
    }

    public function updateMagentoEntityValue($_entityId = NULL, $_value = 0, $_attributeName = NULL, $_tableName = 'catalog_product_entity_varchar', $_storeId = NULL)
    {
        $_table = Mage::helper('tnw_salesforce')->getTable($_tableName);
        $_storeId = ($_storeId == NULL || $_storeId == 'Standard') ? Mage::helper('tnw_salesforce')->getStoreId() : $_storeId;
        $storeIdQuery = ($_storeId !== NULL) ? " store_id = '" . $_storeId . "' AND" : NULL;
        if (!$_attributeName) {
            Mage::helper('tnw_salesforce')->log('Could not update Magento product values: attribute name is not specified', 1, "sf-errors");
            return false;
        }
        $sql = '';
        if ($_value || $_value === 0) {
            // Update Account Id
            $sqlCheck = "SELECT value_id FROM `" . $_table . "` WHERE " . $storeIdQuery . " attribute_id = '" . $this->_attributes[$_attributeName] . "' AND entity_id = " . $_entityId;
            $row = $this->_write->query($sqlCheck)->fetch();
            if ($row && array_key_exists('value_id', $row)) {
                //Update
                $sql .= "UPDATE `" . $_table . "` SET value = '" . $_value . "' WHERE value_id = " . $row['value_id'] . ";";
            } else {
                // Insert
                $sql .= "INSERT INTO `" . $_table . "` VALUES (NULL," . $this->_productEntityTypeCode . "," . $this->_attributes[$_attributeName] . "," . $_storeId . "," . $_entityId . ",'" . $_value . "');";
            }
        } else {
            // Reset value
            $sqlCheck = "SELECT value_id FROM `" . $_table . "` WHERE " . $storeIdQuery . " attribute_id = " . $this->_attributes[$_attributeName] . " AND entity_id = " . $_entityId;
            $row = $this->_write->query($sqlCheck)->fetch();
            if ($row && array_key_exists('value_id', $row)) {
                //Update
                $sql .= "DELETE FROM `" . $_table . "` WHERE value_id = " . $row['value_id'] . ";";
            }
        }
        if (!empty($sql)) {
            $this->_sqlToRun .= $sql;
            Mage::helper('tnw_salesforce')->log("SQL: " . $sql);
        }
    }

    protected function _buildProductObject($prod, $sfId = NULL)
    {
        if ($sfId) {
            $this->_obj->Id = $sfId;
        }

        $this->_obj->IsActive = TRUE;
        $this->_obj->ProductCode = $prod->getSku();

        //Process mapping
        Mage::getSingleton('tnw_salesforce/sync_mapping_product_product')
            ->setSync($this)
            ->processMapping($prod);

        // if "Synchronize product attributes" is set to "yes" we replace sf description with product attributes
        if (intval(Mage::helper('tnw_salesforce')->getProductAttributesSync()) == 1) {
            $this->_obj->Description = $this->_formatProductAttributesForSalesforce($prod, false);
        }

        if (property_exists($this->_obj, 'IsActive')) {
            $this->_obj->IsActive = ($this->_obj->IsActive == "Enabled") ? 1 : 0;
        }

        $syncParam = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix('enterprise') . "disableMagentoSync__c";
        $this->_obj->$syncParam = TRUE;

        if ($prod->getId()) {
            $this->_obj->{$this->_magentoId} = $prod->getId();

            if (!property_exists($this->_obj, 'Name')) {
                if (
                    empty($this->_cache['productsLookup'])
                    || !array_key_exists($prod->getSku(), $this->_cache['productsLookup'])
                    || !property_exists($this->_cache['productsLookup'][$prod->getSku()], 'Name')
                ) {
                    $this->_obj->Name = $prod->getName();
                } else {
                    $this->_obj->Name = $this->_cache['productsLookup'][$prod->getSku()]->Name;
                }
            }

            if (
                empty($this->_cache['productsLookup'])
                || !array_key_exists($prod->getSku(), $this->_cache['productsLookup'])
                || $this->_hasChanges($this->_cache['productsLookup'][$prod->getSku()], $this->_obj)
            ) {
                // New product or product has changes
                if (property_exists($this->_obj, 'Id')) {
                    $this->_cache['productsToSync']['Id'][$prod->getId()] = $this->_obj;
                } else {
                    $this->_cache['productsToSync'][$this->_magentoId][$prod->getId()] = $this->_obj;
                }
            } else if (
                !empty($this->_cache['productsLookup'])
                && array_key_exists($prod->getSku(), $this->_cache['productsLookup'])
            ) {
                // Skip product update, BUT check for price changes
                $_sfProductId = $this->_cache['productsLookup'][$prod->getSku()]->Id;
                $this->_addPriceBookEntry($prod->getId(), $_sfProductId);
                if ($this->_standardPricebookId != $this->_defaultPriceBook) {
                    $this->_addPriceBookEntry($prod->getId(), $_sfProductId, 'Default');
                }
            }
        } else {
            if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                Mage::getSingleton('adminhtml/session')->addError('WARNING: Could not synchronize product (sku: ' . $prod->getSku() . '), product ID is missing!');
            }
            Mage::helper('tnw_salesforce')->log("ERROR: Magento product ID is undefined, skipping!", 1, "sf-errors");
        }
    }

    protected function _hasChanges($_lookupProduct = NULL, $_newProduct = NULL)
    {
        // Always sync until we can figure out a way how to check all mapped data
        return true;
    }

    /**
     * get all product attributes, build array from that data and serialize the array
     * so it may be unserialized in salesforce or any other third party tool
     * as well it's human readable just in case we need to see that data in salesforce
     *
     * @param $product
     * @param bool $serialize
     * @return string
     */
    protected function _formatProductAttributesForSalesforce($product, $serialize = true)
    {
        // collect attribute set
        $sfDescriptionSerializeFormat = array();
        $sfDescriptionCustomFormat = '';
        if ($product instanceof Mage_Catalog_Model_Product) {
            $attributes = $product->getAttributes();
            foreach ($attributes as $attribute) {
                $attrName = $attribute->getData('attribute_code');
                $attrValue = $attribute->getFrontend()->getValue($product);
                // build serialize format data
                $sfDescriptionSerializeFormat[$attrName] = $attrValue;
                // build custom format data in this foreach loop just not to make foreach again below in the code
                $sfDescriptionCustomFormat .= "[$attrName=$attrValue]";
            }
        }

        // format data
        if ($serialize) {
            $sfDescription = serialize($sfDescriptionSerializeFormat);
        }
        else {
            $sfDescription = $sfDescriptionCustomFormat;
        }

        return $sfDescription;
    }

    protected function _pushProductsSegment($chunk = array(), $_upsertOn = 'Id')
    {
        if (empty($chunk)) {
            return false;
        }

        $_productIds = array_keys($chunk);

        try {
            Mage::dispatchEvent("tnw_salesforce_product_send_before",array("data" => $chunk));
            $_responses = $this->_mySforceConnection->upsert($_upsertOn, array_values($chunk), 'Product2');
            Mage::dispatchEvent("tnw_salesforce_product_send_after",array("data" => $chunk, "result" => $_responses));
        } catch (Exception $e) {
            $_response = $this->_buildErrorResponse($e->getMessage());
            foreach($_productIds as $_id) {
                $this->_cache['responses']['products'][$_id] = $_response;
            }
            $_responses = array();
            Mage::helper('tnw_salesforce')->log('CRITICAL: Push of products to Salesforce failed' . $e->getMessage());
        }

        $_success = false;

        foreach ($_responses as $_key => $_response) {
            $_magentoId = $_productIds[$_key];
            //Report Transaction
            $this->_cache['responses']['products'][$_magentoId] = $_response;

            if (property_exists($_response, 'success') && $_response->success) {
                $_success = true;

                Mage::helper('tnw_salesforce')->log('PRODUCT: magentoID (' . $_magentoId . ') : salesforceID (' . $_response->id . ')');
                $_product = new stdClass();
                $_product->salesforceId = $_response->id;
                $_product->SfInSync = 1;
                $this->_cache['toSaveInMagento'][$_magentoId] = $_product;

                $this->_addPriceBookEntry($_magentoId, $_response->id);

                unset($_product);
            } else {
                $this->_processErrors($_response, 'product', $chunk[$_magentoId]);
            }
        }
        if (count($_responses) == 0) {
            $this->_processErrors($_response, 'product', $chunk[$_productIds[0]]);
        }
        return $_success;
    }

    protected function _addPriceBookEntry($_magentoId, $_sfProductId)
    {
        $_standardPbeId = $_defaultPbeId = NULL;

        $_prod = NULL;
        if (is_array($this->_cache['productsLookup'])) {
            foreach ($this->_cache['productsLookup'] as $_sfProduct) {
                if ($_sfProduct->Id != $_sfProductId) {
                    continue;
                }
                $_prod = $_sfProduct;
                break;
            }
        }

        if ($_prod) {
            if (!Mage::helper('tnw_salesforce')->isMultiCurrency()) {
                // Check if Pricebooks Entry exist already
                foreach ($_prod->PriceBooks as $_key => $_pbeObject) {
                    if ($_pbeObject->Pricebook2Id == $this->_standardPricebookId) {
                        $_standardPbeId = $_pbeObject->Id;
                    }
                    if ($_pbeObject->Pricebook2Id == $this->_defaultPriceBook) {
                        $_defaultPbeId = $_pbeObject->Id;
                    }
                }
            } else {
                $_currencyCode = Mage::app()->getStore($this->_currentScope)->getDefaultCurrencyCode();
                $_standardKey = $this->_doesPricebookEntryExist($_prod, $this->_standardPricebookId, $_currencyCode);
                $_defaultKey = $this->_doesPricebookEntryExist($_prod, $this->_defaultPriceBook, $_currencyCode);
                if (!is_bool($_standardKey)) {
                    $_standardPbeId = $_prod->PriceBooks[$_standardKey]->Id;
                }
                if (!is_bool($_defaultKey)) {
                    $_defaultPbeId = $_prod->PriceBooks[$_defaultKey]->Id;
                }
            }
        }

        // Is Multi Site Sync?
        if (!$this->_isMultiSync) {
            $_currencyCode = Mage::app()->getStore($this->_currentScope)->getDefaultCurrencyCode();

            $_price = $this->_cache['productPrices'][$_magentoId][$this->_currentScope];
            /* Do I need to create Standard Pricebook Entry or update existing one? */
            if (
                !$_prod
                || !$_standardPbeId
            ) {
                $_flag = (!$_prod || !$_standardPbeId) ? true : false;
                if (!array_key_exists('Standard:::' . $_magentoId . ':::' . $_currencyCode, $this->_cache['pricebookEntryToSync'])) {
                    $this->_cache['pricebookEntryToSync']['Standard:::' . $_magentoId . ':::' . $_currencyCode] = array();
                }
                $this->_cache['pricebookEntryToSync']['Standard:::' . $_magentoId . ':::' . $_currencyCode] = $this->_addEntryToQueue($_price, $_flag, $this->_standardPricebookId, $_sfProductId, $_prod, $_magentoId, $this->_currentScope, $_currencyCode);
            }
            /* Do I need to create Default Pricebook Entry or update existing one? */
            if (
                $this->_standardPricebookId != $this->_defaultPriceBook
            ) {
                $_flag = ($this->_standardPricebookId != $this->_defaultPriceBook && !$_defaultPbeId) ? true : false;
                if (!array_key_exists($this->_currentScope . ':::' . $_magentoId . ':::' . $_currencyCode, $this->_cache['pricebookEntryToSync'])) {
                    $this->_cache['pricebookEntryToSync'][$this->_currentScope . ':::' . $_magentoId . ':::' . $_currencyCode] = array();
                }
                $this->_cache['pricebookEntryToSync'][$this->_currentScope . ':::' . $_magentoId . ':::' . $_currencyCode] = $this->_addEntryToQueue($_price, $_flag, $this->_defaultPriceBook, $_sfProductId, $_prod, $_magentoId, $this->_currentScope, $_currencyCode);
                if ($this->_currentScope == 0) {
                    foreach (Mage::app()->getStores() as $_storeId => $_store) {
                        $_currencyCode = Mage::app()->getStore($_storeId)->getDefaultCurrencyCode();
                        $_storePriceBookId = (Mage::helper('tnw_salesforce')->getPricebookId($_storeId)) ? Mage::helper('tnw_salesforce')->getPricebookId($_storeId) : $this->_defaultPriceBook;
                        $_price = (
                            is_array($this->_cache['productPrices'][$_magentoId])
                            && array_key_exists($_storeId, $this->_cache['productPrices'][$_magentoId])
                        ) ? $this->_cache['productPrices'][$_magentoId][$_storeId] : 0;

                        $this->_cache['pricebookEntryToSync'][$_storeId . ':::' . $_magentoId . ':::' . $_currencyCode] = $this->_addEntryToQueue($_price, $_flag, $_storePriceBookId, $_sfProductId, $_prod, $_magentoId, $_storeId, $_currencyCode);
                    }
                }
            }
        } else {
            // Load entire product to figure out what stores and currencies need to be created / updated
            // TODO: place this into cache? in massAdd and read from it?
            $_magentoProduct = Mage::getModel('catalog/product')->load($_magentoId);

            // Sync Admin (Store 0)
            $_currencyCode = Mage::app()->getStore(0)->getDefaultCurrencyCode();

            // Get price from the cache or default it to zero
            $_price = (
                is_array($this->_cache['productPrices'][$_magentoId])
                && array_key_exists(0, $this->_cache['productPrices'][$_magentoId])
            ) ? $this->_cache['productPrices'][$_magentoId][0] : 0;

            // If $_flag == true - create a standard Pricebook as well
            $_flag = (!$_prod || !$_standardPbeId) ? true : false;

            if ($_flag) {
                if (!array_key_exists('Standard:::' . $_magentoId . ':::' . $_currencyCode, $this->_cache['pricebookEntryToSync'])) {
                    $this->_cache['pricebookEntryToSync']['Standard:::' . $_magentoId . ':::' . $_currencyCode] = array();
                }
                $this->_cache['pricebookEntryToSync']['Standard:::' . $_magentoId . ':::' . $_currencyCode] = $this->_addEntryToQueue($_price, $_flag, $this->_standardPricebookId, $_sfProductId, $_prod, $_magentoId, $this->_currentScope, $_currencyCode);
            }

            foreach ($this->getCurrencies() as $_storeId => $_code) {
                // Skip store if product is not assigned to it
                if (!in_array($_storeId, $_magentoProduct->getStoreIds())) {
                    continue;
                }
                // Create Standard Pricebook
                $_currencyCode = Mage::app()->getStore($_storeId)->getDefaultCurrencyCode();
                $_price = (
                    is_array($this->_cache['productPrices'][$_magentoId])
                    && array_key_exists($_storeId, $this->_cache['productPrices'][$_magentoId])
                ) ? $this->_cache['productPrices'][$_magentoId][$_storeId] : 0;
                $_priceBookEntry = $this->_addEntryToQueue($_price, $_flag, $this->_standardPricebookId, $_sfProductId, $_prod, $_magentoId, $_storeId, $_currencyCode);
                if (!empty($_priceBookEntry)) {
                    $this->_cache['pricebookEntryToSync']['0:::' . $_magentoId . ':::' . $_code] = $_priceBookEntry;
                }
            }

            // Sync remaining Stores
            foreach ($_magentoProduct->getStoreIds() as $_storeId) {
                $_currencyCode = Mage::app()->getStore($_storeId)->getDefaultCurrencyCode();
                $_storePriceBookId = (Mage::helper('tnw_salesforce')->getPricebookId($_storeId)) ? Mage::helper('tnw_salesforce')->getPricebookId($_storeId) : $this->_defaultPriceBook;
                $_price = (
                    is_array($this->_cache['productPrices'][$_magentoId])
                    && array_key_exists($_storeId, $this->_cache['productPrices'][$_magentoId])
                ) ? $this->_cache['productPrices'][$_magentoId][$_storeId] : 0;

                if (!array_key_exists($_storeId . ':::' . $_magentoId . ':::' . $_currencyCode, $this->_cache['pricebookEntryToSync'])) {
                    $this->_cache['pricebookEntryToSync'][$_storeId . ':::' . $_magentoId . ':::' . $_currencyCode] = array();
                }
                $this->_cache['pricebookEntryToSync'][$_storeId . ':::' . $_magentoId . ':::' . $_currencyCode] = $this->_addEntryToQueue($_price, NULL, $_storePriceBookId, $_sfProductId, $_prod, $_magentoId, $_storeId, $_currencyCode);
            }

            // If product does not appear on any website, sync standard pricebook only
            foreach ($this->getCurrencies() as $_storeId => $_code) {
                // Skip store if product is not assigned to it
                if (!in_array($_storeId, $_magentoProduct->getStoreIds())) {
                    continue;
                }
                $_currencyCode = Mage::app()->getStore($_storeId)->getDefaultCurrencyCode();
                if (!array_key_exists('0:::' . $_magentoId . ':::' . $_currencyCode, $this->_cache['pricebookEntryToSync'])) {
                    $_priceBookEntry = $this->_addEntryToQueue($_price, $_flag, $this->_standardPricebookId, $_sfProductId, $_prod, $_magentoId, $this->_currentScope, $_currencyCode);
                    if (!empty($_priceBookEntry)) {
                        $this->_cache['pricebookEntryToSync']['0:::' . $_magentoId . ':::' . $_currencyCode] = $_priceBookEntry;
                    }
                }
            }
        }
    }

    protected function _doesPricebookEntryExist($_sfProduct, $_priceBookId, $_currencyCode)
    {
        $_return = false;
        if (
            is_object($_sfProduct)
            && property_exists($_sfProduct, 'PriceBooks')
            && !empty($_sfProduct->PriceBooks)
        ) {
            foreach ($_sfProduct->PriceBooks as $_key => $_pricebookEntry) {
                if (
                    property_exists($_pricebookEntry, 'Pricebook2Id')
                    && $_pricebookEntry->Pricebook2Id == $_priceBookId
                ) {
                    if (
                        Mage::helper('tnw_salesforce')->isMultiCurrency()
                        && property_exists($_pricebookEntry, 'CurrencyIsoCode')
                        && $_pricebookEntry->CurrencyIsoCode == $_currencyCode
                    ) {
                        // Multi-currency enabled and a match is found
                        $_return = $_key;
                        break;
                    } elseif (Mage::helper('tnw_salesforce')->isMultiCurrency()) {
                        // Multi-currency enabled and a match is NOT found
                        $_return = false;
                    } else {
                        // Multi-currency disabled
                        $_return = $_key;
                        break;
                    }
                }
            }
        }
        return $_return;
    }

    protected function _addEntryToQueue($_price, $_flag, $_priceBookId, $_sfProductId, $_sfProduct, $_magentoId, $_storeId = 0, $_currencyCode)
    {
        // Create Default Pricebook Entry
        $_obj = new stdClass();
        $_obj->UseStandardPrice = 0;
        $_obj->UnitPrice = $_price;
        $_obj->IsActive = TRUE;

        $_key = $this->_doesPricebookEntryExist($_sfProduct, $_priceBookId, $_currencyCode);
        if (!is_bool($_key)) {
            $_obj->Id = $_sfProduct->PriceBooks[$_key]->Id;
            //$this->_cache['toSaveInMagento'][$_magentoId]->pricebookEntryId = $_obj->Id;
        } else {
            $_obj->Pricebook2Id = $_priceBookId;
            $_obj->Product2Id = $_sfProductId;

            if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {
                $_obj->CurrencyIsoCode = Mage::app()->getStore($_storeId)->getDefaultCurrencyCode();
            }
        }
        return $_obj;
    }

    protected function _pushProductChunked($_entities = array(), $_upsertOn = 'Id')
    {
        //Push On ID
        if (!empty($_entities)) {
            $_ttl = count($_entities);
            $_success = true;
            if ($_ttl > 199) {
                $_steps = ceil($_ttl / 199);
                for ($_i = 0; $_i < $_steps; $_i++) {
                    $_start = $_i * 200;
                    $_itemsToPush = array_slice($_entities, $_start, $_start + 199);
                    $_success = $this->_pushProductsSegment($_itemsToPush, $_upsertOn);
                }
            } else {
                $_success = $this->_pushProductsSegment($_entities, $_upsertOn);
            }
            if (!$_success) {
                if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                    Mage::getSingleton('adminhtml/session')->addError('WARNING: Product upserts failed, skipping PriceBook synchronization');
                }
                Mage::helper('tnw_salesforce')->log('ERROR: Product upsert failed, skipping PriceBook upserts', 1, "sf-errors");
                return false;
            }
        }
        return true;
    }

    /**
     * @param string $type
     */
    protected function _pushProducts($type = 'update')
    {
        if (empty($this->_cache['productsToSync'])) {
            return; // Skip if nothing to push
        }

        Mage::helper('tnw_salesforce')->log('----------' . strtoupper($type) . ' PRODUCTS: Start----------');

        $this->_pushProductChunked($this->_cache['productsToSync']['Id']);
        $this->_pushProductChunked($this->_cache['productsToSync'][$this->_magentoId], $this->_magentoId);

        Mage::helper('tnw_salesforce')->log('----------' . strtoupper($type) . ' PRODUCTS: End----------');
        Mage::helper('tnw_salesforce')->log('----------UPSERTING Pricebook: Start----------');

        $_cache = array();
        $_duplicates = array();
        foreach ($this->_cache['pricebookEntryToSync'] as $_key => $_tmpObject) {
            // Dump products that will be synced
            if (!$this->isFromCLI()) {
                foreach ($_tmpObject as $key => $value) {
                    Mage::helper('tnw_salesforce')->log("Pricebook Object: " . $key . " = '" . $value . "'");
                }
            }

            // Don't insert duplicate Pricebook Entries
            if (
                (
                    property_exists($_tmpObject, 'Pricebook2Id')
                    && in_array($_tmpObject->Pricebook2Id . $_tmpObject->Product2Id, $_cache)
                ) || (
                    property_exists($_tmpObject, 'Id')
                    && in_array($_tmpObject->Id, $_cache)
                )
            ) {
                if (property_exists($_tmpObject, 'Id')) {
                    $_foundKey = array_search($_tmpObject->Id, $_cache);
                } else {
                    $_newKey = $_tmpObject->Pricebook2Id . $_tmpObject->Product2Id;
                    if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {
                        $_newKey .= (property_exists($_tmpObject, 'CurrencyIsoCode')) ? $_tmpObject->CurrencyIsoCode : NULL;
                    }
                    $_foundKey = array_search($_newKey, $_cache);
                }
                $_duplicates[$_key] = $_foundKey;
            } else {
                $this->_cache['pricebookEntriesForUpsert'][$_key] = $_tmpObject;
                if (property_exists($_tmpObject, 'Pricebook2Id')) {
                    $_newKey = $_tmpObject->Pricebook2Id . $_tmpObject->Product2Id;
                    if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {
                        $_newKey .= (property_exists($_tmpObject, 'CurrencyIsoCode')) ? $_tmpObject->CurrencyIsoCode : NULL;
                    }
                    $_cache[$_key] = $_newKey;
                } else if (property_exists($_tmpObject, 'Id')) {
                    $_cache[$_key] = $_tmpObject->Id;
                }
            }

            Mage::helper('tnw_salesforce')->log("---------------------------------------");
        }

        $this->_pushPricebookChunked($this->_cache['pricebookEntriesForUpsert']);

        //Make sure duplicates are updated in Magento
        foreach ($_duplicates as $_toUpdate => $_fromWhere) {
            $_tmp = explode(':::', $_toUpdate);
            $_updateStoreId = $_tmp[0];
            $_productId = $_tmp[1];
            $_fromStoreId = strstr($_fromWhere, ':::' . $_productId, true);
            $this->_cache['toSaveInMagento'][$_productId]->pricebookEntryIds[$_updateStoreId] = $this->_cache['toSaveInMagento'][$_productId]->pricebookEntryIds[$_fromStoreId];
        }
        Mage::helper('tnw_salesforce')->log('----------UPSERTING Pricebook: End----------');

    }

    protected function _pushPriceBookSegment($chunk = array())
    {
        if (empty($chunk)) {
            return false;
        }

        $_keys = array_keys($chunk);
        try {
            $_responses = $this->_mySforceConnection->upsert('Id', array_values($chunk), 'PricebookEntry');
        } catch (Exception $e) {
            $_response = $this->_buildErrorResponse($e->getMessage());
            foreach($_keys as $_id) {
                $this->_cache['responses']['products'][$_id] = $_response;
            }
            $_responses = array();
            Mage::helper('tnw_salesforce')->log('CRITICAL: Push of products to Salesforce failed' . $e->getMessage());
        }


        foreach ($_responses as $_key => $_response) {

            $_tmp = explode(':::', $_keys[$_key]);
            $_magentoId = $_tmp[1];
            $_storeId = $_tmp[0];

            //Report Transaction
            $this->_cache['responses']['pricebooks'][$_keys[$_key]] = $_response;

            if (property_exists($_response, 'success') && $_response->success) {
                Mage::helper('tnw_salesforce')->log('PRICEBOOK ENTRY: magentoID (' . $_magentoId . ') : salesforceID (' . $_response->id . ')');
                if (!property_exists($this->_cache['toSaveInMagento'][$_magentoId], 'pricebookEntryIds')) {
                    $this->_cache['toSaveInMagento'][$_magentoId]->pricebookEntryIds = array();
                }
                $this->_cache['toSaveInMagento'][$_magentoId]->pricebookEntryIds[$_storeId] = $_response->id;
            } else {
                $this->_cache['toSaveInMagento'][$_magentoId]->SfInSync = 0;
                $this->_processErrors($_response, 'pricebook', $chunk[$_keys[$_key]]);
            }
        }
        if (count($_responses) == 0) {
            $this->_processErrors($_response, 'product', $chunk[$_keys[0]]);
        }
    }

    protected function _pushPricebookChunked($_entities = array())
    {
        //Push On ID
        if (!empty($_entities)) {
            $_ttl = count($_entities);
            if ($_ttl > 0) {
                if ($_ttl > 199) {
                    $_steps = ceil($_ttl / 199);
                    for ($_i = 0; $_i < $_steps; $_i++) {
                        $_start = $_i * 200;
                        $_itemsToPush = array_slice($_entities, $_start, $_start + 199);
                        $this->_pushPriceBookSegment($_itemsToPush);
                    }
                } else {
                    $this->_pushPriceBookSegment($_entities);
                }
            }
        }

        return true;
    }

    public function reset()
    {
        parent::reset();

        Mage::helper('tnw_salesforce')->log("================ MASS SYNC: START ================");

        // Reset cache (need to conver to magento cache
        $this->_cache = array(
            'productsLookup' => array(),
            'productsToSync' => array(
                'Id' => array(),
                $this->_magentoId => array()
            ),
            'pricebookEntryToSync' => array(),
            'pricebookEntriesForUpsert' => array(),
            'productIdToSku' => array(),
            'productPrices' => array(),
            'toSaveInMagento' => array(),
            'skipMagentoUpdate' => array(),
            'responses' => array(
                'products' => array(),
                'pricebooks' => array()
            ),
        );

        $this->_standardPricebookId = Mage::helper('tnw_salesforce/salesforce_data')->getStandardPricebookId();
        $this->_defaultPriceBook = (Mage::helper('tnw_salesforce')->getDefaultPricebook()) ? Mage::helper('tnw_salesforce')->getDefaultPricebook() : $this->_standardPricebookId;

        $resource = Mage::getResourceModel('eav/entity_attribute');
        $this->_attributes['salesforce_id'] = $resource->getIdByCode('catalog_product', 'salesforce_id');
        $this->_attributes['salesforce_pricebook_id'] = $resource->getIdByCode('catalog_product', 'salesforce_pricebook_id');
        $this->_attributes['sf_insync'] = $resource->getIdByCode('catalog_product', 'sf_insync');

        return $this->check();
    }

    public function setIsSoftUpdate($val = false)
    {
        $this->_isSoftUpdate = (bool)$val;
        return $this;
    }

    public function isSoftUpdate()
    {
        return $this->_isSoftUpdate;
    }

    public function setOrderStoreId($val = 0)
    {
        $this->_orderStoreId = $val;
        return $this;
    }

    public function getOrderStoreId()
    {
        return $this->_orderStoreId;
    }
}