<?php

/**
 * Class TNW_Salesforce_Helper_Salesforce_Product
 */
class TNW_Salesforce_Helper_Salesforce_Product extends TNW_Salesforce_Helper_Salesforce_Abstract_Base
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
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace(
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
                        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("------ Product ID: " . $_mId . " ------");
                        foreach ($_obj as $key => $value) {
                            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Product Object: " . $key . " = '" . $value . "'");
                        }
                        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("---------------------------------------");
                    }
                }
            }

            $this->_pushProducts();
            $this->clearMemory();

            $this->_updateMagento();
            $this->clearMemory();

            $this->_onComplete();

            // Logout
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("================= MASS SYNC: END =================");
            return true;
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("CRITICAL: " . $e->getMessage());
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
            /** @var Mage_Catalog_Model_Product[] $productsCollection */
            $productsCollection = Mage::getModel('catalog/product')
                ->getCollection()
                ->addIdFilter($ids)
                ->addAttributeToSelect('salesforce_disable_sync');
            Mage::register('product_sync_collection', $productsCollection);

            $this->_skippedEntity = $skuArray = array();
            foreach ($productsCollection as $product) {
                // we check product type and skip synchronization if needed
                if (intval($product->getData('salesforce_disable_sync')) == 1) {
                    $message = 'SKIPPING: Product (ID: ' . $product->getId() . ') is excluded from synchronization';
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace($message);
                    if ($this->canDisplayErrors()) {
                        Mage::getSingleton('adminhtml/session')->addNotice($message);
                    }

                    $this->_skippedEntity[] = $product->getId();
                    continue;
                }

                if (!$product->getSku()) {
                    $message = 'SKIPPING: Product #' . $product->getId() . ', product sku is missing!';
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace($message);
                    if ($this->canDisplayErrors()) {
                        Mage::getSingleton('adminhtml/session')->addNotice($message);
                    }

                    $this->_skippedEntity[] = $product->getId();
                    continue;
                }

                $sku = trim($product->getSku());
                $this->_cache['productIdToSku'][$product->getId()] = $sku;
                $skuArray[$product->getId()] = $sku;
            }

            if (empty($skuArray)) {
                return false;
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

            return true;
        }
        catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("CRITICAL: " . $e->getMessage());
            return false;
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
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Exception: " . $e->getMessage());
            }
        }
    }

    protected function _updateMagento()
    {
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("---------- Start: Magento Update ----------");
        $this->_sqlToRun = array();
        $ids = array();

        foreach ($this->_cache['toSaveInMagento'] as $_magentoId => $_product) {
            /**
             * skip order fee products, these products don't exist in magento and use sku instead Id
             */
            if (!is_numeric($_magentoId)) {
                continue;
            }

            $_product->salesforceId = (isset($_product->salesforceId)) ? $_product->salesforceId : NULL;
            $_product->pricebookEntryIds = (isset($_product->pricebookEntryIds)) ? $_product->pricebookEntryIds : array();
            $_product->SfInSync = isset($_product->SfInSync) ? $_product->SfInSync : 0;

            /* Add product and sync flag for display in Admin */
            $this->updateMagentoEntityValue($_magentoId, $_product->SfInSync, 'sf_insync', 'catalog_product_entity_int', 0);
            $this->updateMagentoEntityValue($_magentoId, $_product->salesforceId, 'salesforce_id','catalog_product_entity_varchar', 0);

            /* Update for each store */
            foreach (Mage::app()->getStores() as $_storeId => $_store) {
                if (
                    array_key_exists($_storeId, $this->_cache['skipMagentoUpdate'])
                    && in_array($_magentoId, $this->_cache['skipMagentoUpdate'][$_storeId])
                ) {
                    continue;
                }
                $this->updateMagentoEntityValue($_magentoId, $_product->SfInSync, 'sf_insync', 'catalog_product_entity_int', $_storeId);
                $this->updateMagentoEntityValue($_magentoId, $_product->salesforceId, 'salesforce_id','catalog_product_entity_varchar', $_storeId);
            }

            // Remove Standard Pricebook ID from being written into Magento, only store value for Store 0
            if (
                array_key_exists('Standard', $_product->pricebookEntryIds)
                && array_key_exists(0, $_product->pricebookEntryIds)
            ) {
                unset($_product->pricebookEntryIds['Standard']);
            }
            foreach ($_product->pricebookEntryIds as $_key => $_pbeId) {
                $this->updateMagentoEntityValue($_magentoId, $_pbeId, 'salesforce_pricebook_id', 'catalog_product_entity_text', $_key);
            }
            $ids[] = $_magentoId;
        }

        $this->processSql();

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Updated: " . count($this->_cache['toSaveInMagento']) . " products!");
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("---------- End: Magento Update ----------");
    }

    public function updateMagentoEntityValue($_entityId = NULL, $_value = 0, $_attributeName = NULL, $_tableName = 'catalog_product_entity_varchar', $_storeId = NULL)
    {
        $_table = $this->getHelper()->getTable($_tableName);
        $_storeId = ($_storeId == NULL || $_storeId == 'Standard') ? $this->getHelper()->getStoreId() : $_storeId;
        $storeIdQuery = ($_storeId !== NULL) ? " store_id = '" . $_storeId . "' AND" : NULL;
        if (!$_attributeName) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError('Could not update Magento product values: attribute name is not specified');
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
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("SQL: " . $sql);
        }
    }

    /**
     * @param Mage_Catalog_Model_Product $product
     */
    protected function _buildProductObject($product)
    {
        $this->_obj = new stdClass();
        if (isset($this->_cache['productsLookup'][$product->getSku()])) {
            $this->_obj->Id = $this->_cache['productsLookup'][$product->getSku()]->Id;
        }

        /** @var tnw_salesforce_model_mysql4_mapping_collection $_mappingCollection */
        $_mappingCollection = Mage::getResourceModel('tnw_salesforce/mapping_collection')
            ->addObjectToFilter('Product2')
            ->addFilterTypeMS(property_exists($this->_obj, 'Id') && $this->_obj->Id);

        $_objectMappings = array();
        foreach (array_unique($_mappingCollection->walk('getLocalFieldType')) as $_type) {
            $_objectMappings[$_type] = $this->_getObjectByEntityType($product, $_type);
        }

        /** @var tnw_salesforce_model_mapping $_mapping */
        foreach ($_mappingCollection as $_mapping) {
            $this->_obj->{$_mapping->getSfField()} = $_mapping->getValue(array_filter($_objectMappings));
        }

        // Unset attribute
        foreach ($this->_obj as $_key => $_value) {
            if (null !== $_value) {
                continue;
            }

            unset($this->_obj->{$_key});
        }

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

        $syncId = property_exists($this->_obj, 'Id')
            ? 'Id'
            : Mage::helper('tnw_salesforce/config')->getMagentoIdField();

        $this->_cache['productsToSync'][$syncId][$product->getId()] = $this->_obj;
    }

    /**
     * @param Mage_Catalog_Model_Product $_entity
     * @param string $_type
     * @return null
     */
    protected function _getObjectByEntityType($_entity, $_type)
    {
        switch($_type)
        {
            case 'Product':
                $_object = $_entity;
                break;

            case 'Product Inventory':
                $_object = Mage::getModel('cataloginventory/stock_item')
                    ->loadByProduct($_entity);
                break;

            case 'Custom':
                $_object = $_entity->getStore();
                break;

            default:
                $_object = null;
                break;
        }

        return $_object;
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
            $_responses = array_fill(0, count($_productIds),
                $this->_buildErrorResponse($e->getMessage()));

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveError('CRITICAL: Push of products to Salesforce failed' . $e->getMessage());
        }

        $_success = false;

        foreach ($_responses as $_key => $_response) {
            $_magentoId = $_productIds[$_key];
            //Report Transaction
            $this->_cache['responses']['products'][$_magentoId] = $_response;

            if (property_exists($_response, 'success') && $_response->success) {
                $_success = true;

                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('PRODUCT: magentoID (' . $_magentoId . ') : salesforceID (' . $_response->id . ')');
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

        $currentStore = Mage::app()->getStore($this->getHelper()->getStoreId());

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

        // Is Multi Site Sync?
        if (!$this->isMultiSync()) {
            $this->addPriceBookEntryToSync($currentStore, $_magentoId, $_prod, $_sfProductId, $this->_standardPricebookId);

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

            $this->addPriceBookEntryToSync(0, $_magentoId, $_prod, $_sfProductId, $this->_standardPricebookId);

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
            /**
             * Add all possible currencies to standard pricebook
             */
            if ($priceBookId == $this->_standardPricebookId) {
                $currencyCodes = array();
                foreach(Mage::app()->getStores(true) as $storeItem) {
                    $storeCurrencyCodes = $storeItem->getAvailableCurrencyCodes();
                    if (!empty($storeCurrencyCodes)) {
                        $currencyCodes = array_merge($currencyCodes, $storeCurrencyCodes);
                    }
                    $currencyCodes = array_unique($currencyCodes);
                }

            } else {
                $currencyCodes = $store->getAvailableCurrencyCodes();
            }

        } else {
            $currencyCodes = array($store->getBaseCurrencyCode());
        }

        foreach ($currencyCodes as $currencyCode) {
            $cacheCode = $priceBookId . ':::' . $magentoProductId . ':::' . $currencyCode;
            $this->_cache['pricebookEntryKeyToStore'][$cacheCode][] = $store->getId();

            if (isset($this->_cache['pricebookEntryToSync']) && isset($this->_cache['pricebookEntryToSync'][$cacheCode])) {
                continue;
            }

            $price = is_array($this->_cache['productPrices'][$magentoProductId])
            && array_key_exists($store->getId(), $this->_cache['productPrices'][$magentoProductId])
                ? $this->_cache['productPrices'][$magentoProductId][$store->getId()] : 0;

            $this->_cache['pricebookEntryToSync'][$cacheCode]
                = $this->_addEntryToQueue($price, $priceBookId, $sfProductId, $sfProduct, $store->getId(), $currencyCode);
        }

        /**
         * try sync pricebooks for order fee products
         */
        $_helper = Mage::helper('tnw_salesforce');

        $availableFees = Mage::helper('tnw_salesforce/salesforce_order')->getAvailableFees();
        foreach ($availableFees as $feeName) {
            $ucFee = ucfirst($feeName);

            $configMethod = 'use' . $ucFee . 'FeeProduct';
            if ($_helper->$configMethod()) {
                $getProductMethod = 'get' . $ucFee . 'Product';

                if ($_helper->$getProductMethod()) {
                    /**
                     * Give fee product data from the config, it's serialized array
                     */
                    $feeData = Mage::app()->getStore($store->getId())->getConfig($_helper->getFeeProduct($feeName));
                    if ($feeData) {
                        $feeData = unserialize($feeData);
                    } else {
                        continue;
                    }
                    $sfProductId = $feeData['Id'];

                    foreach ($currencyCodes as $currencyCode) {
                        $cacheCode = $priceBookId . ':::' . $sfProductId . ':::' . $currencyCode;
                        $this->_cache['pricebookEntryKeyToStore'][$cacheCode][] = $store->getId();

                        $pricebookEntry = Mage::helper('tnw_salesforce/salesforce_data_product')->getProductPricebookEntry($sfProductId, $priceBookId, $currencyCode);
                        /**
                         * check if pricebook entries already added for sync
                         * don't add if pricebook entry already exists
                         */
                        if ((isset($this->_cache['pricebookEntryToSync'])
                                && isset($this->_cache['pricebookEntryToSync'][$cacheCode]))
                            || !empty($pricebookEntry)

                        ) {
                            continue;
                        }
                        $this->_cache['pricebookEntryToSync'][$cacheCode]
                            = $this->_addEntryToQueue(0, $priceBookId, $sfProductId, null, $store->getId(), $currencyCode);
                        /**
                         * reset cache data
                         */
                        $cache = Mage::app()->getCache();
                        $useCache = Mage::app()->useCache('tnw_salesforce');

                        if ($useCache) {
                            $cache->remove('tnw_salesforce_products_pricebook_entry');
                        }
                    }
                }
            }
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
        if (!isset($this->_currencies[$currencyCode])) {
            $this->_currencies[$currencyCode] = Mage::getModel('directory/currency')->load($currencyCode);
        }

        return $this->_currencies[$currencyCode];
    }


    protected function _pushProductChunked($_entities = array(), $_upsertOn = 'Id')
    {
        //Push On ID
        if (!empty($_entities)) {

            $_success = true;
            $_entitiesChunk = array_chunk($_entities, TNW_Salesforce_Helper_Data::BASE_UPDATE_LIMIT, true);
            foreach ($_entitiesChunk as $_itemsToPush) {
                $_success = $this->_pushProductsSegment($_itemsToPush, $_upsertOn);
            }

            if (!$_success) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError('ERROR: Product upsert failed, skipping PriceBook upserts');
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

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------' . strtoupper($type) . ' PRODUCTS: Start----------');

        $this->_pushProductChunked($this->_cache['productsToSync']['Id']);
        $this->_pushProductChunked($this->_cache['productsToSync'][$this->_magentoId], $this->_magentoId);

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------' . strtoupper($type) . ' PRODUCTS: End----------');
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------UPSERTING Pricebook: Start----------');

        foreach ($this->_cache['pricebookEntryToSync'] as $_key => $tmpObject) {
            // Dump products that will be synced
            if (!$this->isFromCLI()) {
                foreach ($tmpObject as $key => $value) {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Pricebook Object: " . $key . " = '" . $value . "'");
                }

                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("---------------------------------------");
            }
        }

        $this->_pushPricebookChunked($this->_cache['pricebookEntryToSync']);

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------UPSERTING Pricebook: End----------');
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
            $_responses = array_fill(0, count($_keys),
                $this->_buildErrorResponse($e->getMessage()));

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveError('CRITICAL: Push of products to Salesforce failed' . $e->getMessage());
        }

        foreach ($_responses as $_key => $_response) {

            $_tmp = explode(':::', $_keys[$_key]);
            $_magentoId = $_tmp[1];
            $currencyCode = $_tmp[2];
            $storeIds = array_unique($this->_cache['pricebookEntryKeyToStore'][$_keys[$_key]]);

            //Report Transaction
            $this->_cache['responses']['pricebooks'][$_keys[$_key]] = $_response;

            if (property_exists($_response, 'success') && $_response->success) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('PRICEBOOK ENTRY: magentoID (' . $_magentoId
                    . ') : salesforceID (' . $_response->id . ')');
                if (!property_exists($this->_cache['toSaveInMagento'][$_magentoId], 'pricebookEntryIds')) {
                    $this->_cache['toSaveInMagento'][$_magentoId]->pricebookEntryIds = array();
                }
                foreach ($storeIds as $_storeId) {
                    $value = $currencyCode . ':' . (string)$_response->id . "\n";
                    if (array_key_exists($_storeId, $this->_cache['toSaveInMagento'][$_magentoId]->pricebookEntryIds)) {
                        $this->_cache['toSaveInMagento'][$_magentoId]->pricebookEntryIds[$_storeId] .= $value;
                    } else {
                        $this->_cache['toSaveInMagento'][$_magentoId]->pricebookEntryIds[$_storeId] = $value;
                    }
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
            $_entitiesChunk = array_chunk($_entities, TNW_Salesforce_Helper_Data::BASE_UPDATE_LIMIT, true);
            foreach ($_entitiesChunk as $_itemsToPush) {
                $this->_pushPriceBookSegment($_itemsToPush);
            }
        }

        return true;
    }

    public function reset()
    {
        parent::reset();

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("================ MASS SYNC: START ================");

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