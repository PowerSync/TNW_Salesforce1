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

    protected $_isMultiSync = null;
    protected $_sqlToRun = NULL;
    protected $_orderStoreId = NULL;

    /**
     * currency models array
     * @var array
     */
    protected $_currencies = array();

    /**
     * @return TNW_Salesforce_Helper_Data
     */
    protected function getHelper()
    {
        return Mage::helper('tnw_salesforce');
    }

    /**
     * @return bool
     */
    public function process()
    {
        try {
            if (!Mage::helper('tnw_salesforce/salesforce_data')->isLoggedIn()) {
                $this->getHelper()->log(
                    "CRITICAL: Connection to Salesforce could not be established! Check API limits and/or login info.");
                if ($this->canDisplayErrors()) {
                    Mage::getSingleton('adminhtml/session')->addWarning(
                        'WARNING: SKIPPING synchronization, could not establish Salesforce connection.');
                }
                return false;
            }
            // Dump products that will be synced
            if (!$this->isFromCLI()) {
                foreach ($this->_cache['productsToSync'] as $_key => $tmpObject) {
                    foreach ($tmpObject as $_mId => $_obj) {
                        $this->getHelper()->log("------ Product ID: " . $_mId . " ------");
                        foreach ($_obj as $key => $value) {
                            $this->getHelper()->log("Product Object: " . $key . " = '" . $value . "'");
                        }
                        $this->getHelper()->log("---------------------------------------");
                    }
                }
            }

            $this->_pushProducts();
            $this->clearMemory();

            $this->_updateMagento();
            $this->clearMemory();

            $this->_onComplete();

            // Logout
            $this->getHelper()->log("================= MASS SYNC: END =================");
            return true;
        } catch (Exception $e) {
            if ($this->canDisplayErrors()) {
                Mage::getSingleton('adminhtml/session')->addError('WARNING: ' . $e->getMessage());
            }
            $this->getHelper()->log("CRITICAL: " . $e->getMessage());
            return false;
        }
    }

    protected function _onComplete()
    {
        parent::_onComplete();

        if ($this->getHelper()->isRemoteLogEnabled()) {
            $logger = Mage::helper('tnw_salesforce/report');
            $logger->reset();
            if (array_key_exists('Id', $this->_cache['productsToSync'])) {
                $logger->add('Salesforce', 'Product2', $this->_cache['productsToSync']['Id'], $this->_cache['responses']['products']);
            }
            if (array_key_exists($this->_magentoId, $this->_cache['productsToSync'])) {
                $logger->add('Salesforce', 'Product2', $this->_cache['productsToSync'][$this->_magentoId], $this->_cache['responses']['products']);
            }

            $logger->add('Salesforce', 'PricebookEntry', $this->_cache['pricebookEntryToSync'], $this->_cache['responses']['pricebooks']);
            $logger->send();
        }

        $this->reset();
        $this->clearMemory();
    }

    /**
     * @return bool
     */
    protected function isMultiSync()
    {
        if (is_null($this->_isMultiSync)) {
            if (!Mage::app()->isSingleStoreMode()) {
                if ($this->getHelper()->getPriceScope() == 0) {
                    //prices per website
                    $this->_isMultiSync = true;
                } elseif (!$this->getHelper()->getStoreId() && !$this->getHelper()->getWebsiteId()) {
                    //currently on admin side
                    $this->_isMultiSync = true;
                }
            }
            if (!$this->_isMultiSync) {
                $this->_isMultiSync = false;
            }
        }

        return $this->_isMultiSync;
    }

    /**
     * @return bool
     */
    protected function canDisplayErrors()
    {
        return !$this->isFromCLI() && !$this->isCron() && $this->getHelper()->displayErrors();
    }

    /**
     * @param array $ids
     */
    public function massAdd($ids = array())
    {
        try {
            //get product collection
            $productsCollection = Mage::getModel('catalog/product')
                ->getCollection()
                ->addIdFilter($ids)
                ->addAttributeToSelect('salesforce_disable_sync');
            Mage::register('product_sync_collection', $productsCollection);

            $skuArray = array();
            foreach ($productsCollection as $product) {
                // we check product type and skip synchronization if needed
                if (intval($product->getData('salesforce_disable_sync')) == 1) {
                    $message = 'SKIPPING: Product (ID: ' . $product->getId() . ') is excluded from synchronization';
                    $this->getHelper()->log($message);
                    if ($this->canDisplayErrors()) {
                        Mage::getSingleton('adminhtml/session')->addNotice($message);
                    }
                    continue;
                }

                if (!$product->getSku()) {
                    $message = 'SKIPPING: Product #' . $product->getId() . ', product sku is missing!';
                    $this->getHelper()->log($message);
                    if ($this->canDisplayErrors()) {
                        Mage::getSingleton('adminhtml/session')->addNotice($message);
                    }
                    continue;
                }

                $sku = trim($product->getSku());
                $this->_cache['productIdToSku'][$product->getId()] = $sku;
                $skuArray[$product->getId()] = $sku;
            }

            // Look up products in Salesforce
            if (empty($this->_cache['productsLookup'])) {
                $this->_cache['productsLookup'] = Mage::helper('tnw_salesforce/salesforce_lookup')
                    ->productLookup($skuArray);
            }

            // If multiple websites AND scope is per website AND looking as All Store Views
            if ($this->isMultiSync()) {
                foreach (array_keys(Mage::app()->getStores(true)) as $storeId) {
                    $this->_syncStoreProducts($storeId);
                }
            } else {
                $this->_syncStoreProducts($this->getHelper()->getStoreId());
            }
            Mage::unregister('product_sync_collection');
        } catch (Exception $e) {
            if ($this->canDisplayErrors()) {
                Mage::getSingleton('adminhtml/session')->addError('WARNING: ' . $e->getMessage());
            }
            $this->getHelper()->log("CRITICAL: " . $e->getMessage());
        }
    }

    /**
     * @param int $_storeId
     */
    protected function _syncStoreProducts($storeId = 0)
    {
        $_collection = Mage::registry('product_sync_collection');
        foreach ($_collection as $product) {
            $productId = $product->getId();
            $storeProduct = Mage::getModel('catalog/product')
                ->setStoreId($storeId)
                ->load($productId);

            // Product does not exist in the store
            if (!$storeProduct->getId()) {
                if (!array_key_exists($storeId, $this->_cache['skipMagentoUpdate'])) {
                    $this->_cache['skipMagentoUpdate'][$storeId] = array();
                }
                $this->_cache['skipMagentoUpdate'][$storeId][] = $productId;
                continue;
            }

            $this->_cache['productPerStore'][$storeId][$productId] = $storeProduct;
            $this->_buildProductObject($storeProduct);
            $this->_cache['productPrices'][$productId][$storeId] = $this->numberFormat($storeProduct->getPrice());
        }
        $_collection = NULL;
        unset($_collection);
        $this->clearMemory();
    }

    public function processSql()
    {
        if (!empty($this->_sqlToRun)) {
            try {
                $chunks = array_chunk($this->_sqlToRun, 500);
                foreach ($chunks as $chunk) {
                    Mage::helper('tnw_salesforce')->getDbConnection()->query(implode('', $chunk));
                }
            } catch (Exception $e) {
                $this->getHelper()->log("Exception: " . $e->getMessage());
            }
        }
    }

    protected function _updateMagento()
    {
        $this->getHelper()->log("---------- Start: Magento Update ----------");
        $this->_sqlToRun = array();
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

        $this->getHelper()->log("Updated: " . count($this->_cache['toSaveInMagento']) . " products!");
        $this->getHelper()->log("---------- End: Magento Update ----------");
    }

    public function updateMagentoEntityValue($_entityId = NULL, $_value = 0, $_attributeName = NULL, $_tableName = 'catalog_product_entity_varchar', $_storeId = NULL)
    {
        $_table = $this->getHelper()->getTable($_tableName);
        $_storeId = ($_storeId == NULL || $_storeId == 'Standard') ? $this->getHelper()->getStoreId() : $_storeId;
        $storeIdQuery = ($_storeId !== NULL) ? " store_id = '" . $_storeId . "' AND" : NULL;
        if (!$_attributeName) {
            $this->getHelper()->log('Could not update Magento product values: attribute name is not specified', 1, "sf-errors");
            return false;
        }
        $sql = '';
        if ($_value || $_value === 0) {
            // Update Account Id
            $sqlCheck = "SELECT value_id FROM `" . $_table . "` WHERE " . $storeIdQuery . " attribute_id = '" . $this->_attributes[$_attributeName] . "' AND entity_id = " . $_entityId;
            $row = Mage::helper('tnw_salesforce')->getDbConnection('read')->query($sqlCheck)->fetch();
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
            $row = Mage::helper('tnw_salesforce')->getDbConnection('read')->query($sqlCheck)->fetch();
            if ($row && array_key_exists('value_id', $row)) {
                //Update
                $sql .= "DELETE FROM `" . $_table . "` WHERE value_id = " . $row['value_id'] . ";";
            }
        }
        if (!empty($sql)) {
            $this->_sqlToRun[] = $sql;
            $this->getHelper()->log("SQL: " . $sql);
        }
    }

    protected function _buildProductObject($product)
    {
        $this->_obj = new stdClass();
        $sku = $product->getSku();
        $this->_obj->ProductCode = $sku;

        $productsLookup = $this->_cache['productsLookup'];
        $sfProductId = is_array($productsLookup) && isset($productsLookup[$sku]) && is_object($productsLookup[$sku])
            ? $productsLookup[$sku]->Id : null;
        if ($sfProductId) {
            $product->setSalesforceId($sfProductId);
        }

        //if ($product->getSalesforceId()) {
        //    $this->_obj->Id = $product->getSalesforceId();
        //}

        $this->_obj->IsActive = true;

        //Process mapping
        Mage::getModel('tnw_salesforce/sync_mapping_product_product')
            ->setSync($this)
            ->processMapping($product);

        // if "Synchronize product attributes" is set to "yes" we replace sf description with product attributes
        if (intval($this->getHelper()->getProductAttributesSync()) == 1) {
            $this->_obj->Description = $this->_formatProductAttributesForSalesforce($product, false);
        }

        if (property_exists($this->_obj, 'IsActive')) {
            $this->_obj->IsActive = ($this->_obj->IsActive == "Enabled") ? 1 : 0;
        }

        if ($this->getHelper()->getType() == 'PRO') {
            $disableSyncField = Mage::helper('tnw_salesforce/config')->getDisableSyncField();
            $this->_obj->$disableSyncField = true;
        }


        if ($product->getId()) {
            $magentoIdField = Mage::helper('tnw_salesforce/config')->getMagentoIdField();
            $this->_obj->{$magentoIdField} = $product->getId();

            //get name from salesforce or from magento
            if (!property_exists($this->_obj, 'Name')) {
                if (empty($productsLookup)
                    || !array_key_exists($sku, $productsLookup)
                    || !property_exists($productsLookup[$sku], 'Name')
                ) {
                    $this->_obj->Name = $product->getName();
                } else {
                    $this->_obj->Name = $productsLookup[$sku]->Name;
                }
            }

            $syncId = property_exists($this->_obj, 'Id') ? 'Id' : $magentoIdField;
            $this->_cache['productsToSync'][$syncId][$product->getId()] = $this->_obj;
        } else {
            if ($this->canDisplayErrors()) {
                Mage::getSingleton('adminhtml/session')->addError('WARNING: Could not synchronize product (sku: '
                    . $sku . '), product ID is missing!');
            }
            $this->getHelper()->log("ERROR: Magento product ID is undefined, skipping!", 1, "sf-errors");
        }
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

        return $serialize ? serialize($sfDescriptionSerializeFormat) : $sfDescriptionCustomFormat;
    }

    protected function _pushProductsSegment($chunk = array(), $_upsertOn = 'Id')
    {
        if (empty($chunk)) {
            return false;
        }

        $_productIds = array_keys($chunk);

        try {
            Mage::dispatchEvent("tnw_salesforce_product_send_before", array("data" => $chunk));
            $_responses = $this->_mySforceConnection->upsert($_upsertOn, array_values($chunk), 'Product2');
            Mage::dispatchEvent("tnw_salesforce_product_send_after", array("data" => $chunk, "result" => $_responses));
        } catch (Exception $e) {
            $_response = $this->_buildErrorResponse($e->getMessage());
            foreach ($_productIds as $_id) {
                $this->_cache['responses']['products'][$_id] = $_response;
            }
            $_responses = array();
            $this->getHelper()->log('CRITICAL: Push of products to Salesforce failed' . $e->getMessage());
        }

        $_success = false;

        foreach ($_responses as $_key => $_response) {
            $_magentoId = $_productIds[$_key];
            //Report Transaction
            $this->_cache['responses']['products'][$_magentoId] = $_response;

            if (property_exists($_response, 'success') && $_response->success) {
                $_success = true;

                $this->getHelper()->log('PRODUCT: magentoID (' . $_magentoId . ') : salesforceID (' . $_response->id . ')');
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
        $standardPricebookId = $this->_standardPricebookId;
        $_standardPbeId = $_defaultPbeId = NULL;
        $currentStore = Mage::app()->getStore($this->getHelper()->getStoreId());
        $currentCurrencyCode = $currentStore->getDefaultCurrencyCode();

        $_prod = null;
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
            if (!$this->getHelper()->isMultiCurrency()) {
                // Check if Pricebooks Entry exist already
                foreach ($_prod->PriceBooks as $_key => $_pbeObject) {
                    if ($_pbeObject->Pricebook2Id == $standardPricebookId) {
                        $_standardPbeId = $_pbeObject->Id;
                    }
                }
            } else {
                $_standardKey = $this->_doesPricebookEntryExist($_prod, $standardPricebookId, $currentCurrencyCode);
                if ($_standardKey !== false) {
                    $_standardPbeId = $_prod->PriceBooks[$_standardKey]->Id;
                }
            }
        }

        // Is Multi Site Sync?
        if (!$this->isMultiSync()) {
            /* Do I need to create Standard Pricebook Entry or update existing one? */
            if (!$_prod || !$_standardPbeId) {
                $this->addPriceBookEntryToSync($currentStore, $_magentoId, $_prod, $_sfProductId, $this->_standardPricebookId);
            }
            /* Do I need to create Default Pricebook Entry or update existing one? */
            if ($this->_standardPricebookId != $this->_defaultPriceBook) {
                $this->addPriceBookEntryToSync($currentStore, $_magentoId, $_prod, $_sfProductId, $this->_defaultPriceBook);
                if ($currentStore->getId() == 0) {
                    foreach (Mage::app()->getStores() as $_storeId => $_store) {
                        $_storePriceBookId = $this->getHelper()->getPricebookId($_storeId) ?: $this->_defaultPriceBook;
                        $this->addPriceBookEntryToSync($_store, $_magentoId, $_prod, $_sfProductId, $_storePriceBookId);
                    }
                }
            }
        } else {
            // Load entire product to figure out what stores and currencies need to be created / updated
            // TODO: place this into cache? in massAdd and read from it?
            $_magentoProduct = Mage::getModel('catalog/product')->load($_magentoId);

            // If $_flag == true - create a standard Pricebook as well
            $_flag = !$_prod || !$_standardPbeId;

            if ($_flag) {
                $this->addPriceBookEntryToSync(0, $_magentoId, $_prod, $_sfProductId, $this->_standardPricebookId);
            }

            foreach ($this->getCurrencies() as $_storeId => $_code) {
                // Skip store if product is not assigned to it
                if (!in_array($_storeId, $_magentoProduct->getStoreIds())) {
                    continue;
                }
                $this->addPriceBookEntryToSync($_storeId, $_magentoId, $_prod, $_sfProductId, $this->_standardPricebookId);
            }

            // Sync remaining Stores
            foreach ($_magentoProduct->getStoreIds() as $_storeId) {
                $_storePriceBookId = $this->getHelper()->getPricebookId($_storeId) ?: $this->_defaultPriceBook;
                $this->addPriceBookEntryToSync($_storeId, $_magentoId, $_prod, $_sfProductId, $_storePriceBookId);
            }
        }
    }

    protected function addPriceBookEntryToSync($store, $magentoProductId, $sfProduct, $sfProductId, $priceBookId)
    {
        if (!($store instanceof Mage_Core_Model_Store)) {
            $store = Mage::app()->getStore($store);
        }

        if ($this->getHelper()->isMultiCurrency()) {
            $currencyCodes = $store->getAvailableCurrencyCodes();
        } else {
            $currencyCodes = $store->getDefaultCurrencyCode();
        }

        foreach ($currencyCodes as $currencyCode) {
            $cacheCode = $priceBookId . ':::' . $magentoProductId . ':::' . $currencyCode;
            $this->_cache['pricebookEntryKeyToStore'][$cacheCode][] = $store->getId();

            if (isset($this->_cache['pricebookEntryToSync']) && isset($this->_cache['pricebookEntryToSync'][$cacheCode])) {
                return;
            }

            $price = is_array($this->_cache['productPrices'][$magentoProductId])
            && array_key_exists($store->getId(), $this->_cache['productPrices'][$magentoProductId])
                ? $this->_cache['productPrices'][$magentoProductId][$store->getId()] : 0;

            $this->_cache['pricebookEntryToSync'][$cacheCode]
                = $this->_addEntryToQueue($price, $priceBookId, $sfProductId, $sfProduct, $store->getId(), $currencyCode);
        }
    }

    protected function _doesPricebookEntryExist($sfProduct, $priceBookId, $currencyCode)
    {
        $_return = false;

        if (is_object($sfProduct) && property_exists($sfProduct, 'PriceBooks') && !empty($sfProduct->PriceBooks)) {
            foreach ($sfProduct->PriceBooks as $_key => $_pricebookEntry) {
                if (property_exists($_pricebookEntry, 'Pricebook2Id')
                    && $_pricebookEntry->Pricebook2Id == $priceBookId
                ) {
                    if ($this->getHelper()->isMultiCurrency()) {
                        if (property_exists($_pricebookEntry, 'CurrencyIsoCode')
                            && $_pricebookEntry->CurrencyIsoCode == $currencyCode
                        ) {
                            // Multi-currency enabled and a match is found
                            $_return = $_key;
                            break;
                        }
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

    protected function _addEntryToQueue($price, $priceBookId, $sfProductId, $sfProduct, $storeId = 0, $currencyCode)
    {
        // Create Default Pricebook Entry
        $object = new stdClass();
        $object->UseStandardPrice = 0;
        $object->UnitPrice = $this->numberFormat($price);
        $object->IsActive = true;

        $_key = $this->_doesPricebookEntryExist($sfProduct, $priceBookId, $currencyCode);
        if ($_key !== false) {
            $object->Id = $sfProduct->PriceBooks[$_key]->Id;
        } else {
            $object->Pricebook2Id = $priceBookId;
            $object->Product2Id = $sfProductId;


            if ($this->getHelper()->isMultiCurrency()) {
                $object->CurrencyIsoCode = $currencyCode;
            }
        }

        if ($this->getHelper()->isMultiCurrency()) {
            $object->UnitPrice = Mage::app()->getStore($storeId)->getBaseCurrency()->convert($price, $this->getCurrency($currencyCode));
            $object->UnitPrice = $this->numberFormat($object->UnitPrice);
        }

        return $object;
    }

    public function getCurrency($currencyCode)
    {
        if (!$this->_currencies[$currencyCode]) {
            $this->_currencies[$currencyCode] = Mage::getModel('directory/currency')->load($currencyCode);
        }

        return $this->_currencies[$currencyCode];
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
                if ($this->canDisplayErrors()) {
                    Mage::getSingleton('adminhtml/session')
                        ->addError('WARNING: Product upserts failed, skipping PriceBook synchronization');
                }
                $this->getHelper()->log('ERROR: Product upsert failed, skipping PriceBook upserts', 1, "sf-errors");
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

        $this->getHelper()->log('----------' . strtoupper($type) . ' PRODUCTS: Start----------');

        $this->_pushProductChunked($this->_cache['productsToSync']['Id']);
        $this->_pushProductChunked($this->_cache['productsToSync'][$this->_magentoId], $this->_magentoId);

        $this->getHelper()->log('----------' . strtoupper($type) . ' PRODUCTS: End----------');
        $this->getHelper()->log('----------UPSERTING Pricebook: Start----------');

        foreach ($this->_cache['pricebookEntryToSync'] as $_key => $tmpObject) {
            // Dump products that will be synced
            if (!$this->isFromCLI()) {
                foreach ($tmpObject as $key => $value) {
                    $this->getHelper()->log("Pricebook Object: " . $key . " = '" . $value . "'");
                }

                $this->getHelper()->log("---------------------------------------");
            }
        }

        $this->_pushPricebookChunked($this->_cache['pricebookEntryToSync']);

        $this->getHelper()->log('----------UPSERTING Pricebook: End----------');
    }

    protected function _pushPriceBookSegment($chunk = array())
    {
        if (empty($chunk)) {
            return;
        }

        $_keys = array_keys($chunk);
        try {
            $_responses = $this->_mySforceConnection->upsert('Id', array_values($chunk), 'PricebookEntry');
        } catch (Exception $e) {
            $_response = $this->_buildErrorResponse($e->getMessage());
            foreach ($_keys as $_id) {
                $this->_cache['responses']['products'][$_id] = $_response;
            }
            $_responses = array();
            $this->getHelper()->log('CRITICAL: Push of products to Salesforce failed' . $e->getMessage());
        }


        foreach ($_responses as $_key => $_response) {

            $_tmp = explode(':::', $_keys[$_key]);
            $_magentoId = $_tmp[1];
            $storeIds = array_unique($this->_cache['pricebookEntryKeyToStore'][$_keys[$_key]]);

            //Report Transaction
            $this->_cache['responses']['pricebooks'][$_keys[$_key]] = $_response;

            if (property_exists($_response, 'success') && $_response->success) {
                $this->getHelper()->log('PRICEBOOK ENTRY: magentoID (' . $_magentoId
                    . ') : salesforceID (' . $_response->id . ')');
                if (!property_exists($this->_cache['toSaveInMagento'][$_magentoId], 'pricebookEntryIds')) {
                    $this->_cache['toSaveInMagento'][$_magentoId]->pricebookEntryIds = array();
                }
                foreach ($storeIds as $_storeId) {
                    $this->_cache['toSaveInMagento'][$_magentoId]->pricebookEntryIds[$_storeId] = $_response->id;
                }
            } else {
                $this->_cache['toSaveInMagento'][$_magentoId]->SfInSync = 0;
                $this->_processErrors($_response, 'pricebook', $chunk[$_keys[$_key]]);
            }
        }
        if (count($_responses) == 0 && isset($_response)) {
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

        $this->getHelper()->log("================ MASS SYNC: START ================");

        // Reset cache (need to conver to magento cache
        $this->_cache = array(
            'productsLookup' => array(),
            'productsToSync' => array(
                'Id' => array(),
                $this->_magentoId => array()
            ),
            'pricebookEntryToSync' => array(),
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
        $this->_defaultPriceBook = $this->getHelper()->getDefaultPricebook() ?: $this->_standardPricebookId;

        $resource = Mage::getResourceModel('eav/entity_attribute');
        $this->_attributes['salesforce_id'] = $resource->getIdByCode('catalog_product', 'salesforce_id');
        $this->_attributes['salesforce_pricebook_id']
            = $resource->getIdByCode('catalog_product', 'salesforce_pricebook_id');
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