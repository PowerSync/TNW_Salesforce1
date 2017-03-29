<?php

/**
 * Class TNW_Salesforce_Helper_Salesforce_Product
 *
 * @method Mage_Catalog_Model_Product getEntityCache($cachePrefix)
 * @method Mage_Catalog_Model_Product _modelEntity()
 */
class TNW_Salesforce_Helper_Salesforce_Product extends TNW_Salesforce_Helper_Salesforce_Abstract_Base
{
    const ENTITY_FEE_CHECK = '__tnw_fee';

    /**
     * @comment magento entity alias "convert from"
     * @var string
     */
    protected $_magentoEntityName = 'product';

    /**
     * @comment salesforce entity alias "convert to"
     * @var string
     */
    protected $_salesforceEntityName = 'Product2';

    /**
     * @comment magento entity model alias
     * @var array
     */
    protected $_magentoEntityModel = 'catalog/product';

    /**
     * @var string
     */
    protected $_mappingEntityName = 'Product2';

    /* Salesforce ID for default Pricebook set in Magento */
    protected $_defaultPriceBook = NULL;

    /* Salesforce ID for default Pricebook set in Salesforce */
    protected $_standardPricebookId = NULL;

    protected $_isMultiSync = null;
    protected $_orderStoreId = NULL;

    /**
     * currency models array
     * @var array
     */
    protected $_currencies = array();

    /**
     * @var array
     */
    protected $_forceProducts = array();

    /**
     * @return TNW_Salesforce_Helper_Data
     */
    protected function getHelper()
    {
        return Mage::helper('tnw_salesforce');
    }

    /**
     * @param $type
     * @throws Exception
     */
    protected function _process($type)
    {
        $this->_prepareEntity();
        $this->_pushEntity();
        $this->clearMemory();

        $this->_prepareEntityPriceBook();
        $this->_pushEntityPriceBook();
        $this->clearMemory();

        $this->_updateMagento();
        $this->_onComplete();
    }

    protected function _onComplete()
    {
        parent::_onComplete();

        if ($this->getHelper()->isRemoteLogEnabled()) {
            $logger = Mage::helper('tnw_salesforce/report');
            $logger->reset();
            $logger->add('Salesforce', 'Product2', $this->_cache[sprintf('%sToUpsert', strtolower($this->getManyParentEntityType()))], $this->_cache['responses']['products']);
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
     * @param $_entity Mage_Catalog_Model_Product
     * @return mixed
     * @throws Exception
     */
    protected function _getEntityNumber($_entity)
    {
        return strtolower(trim($_entity->getSku()));
    }

    /**
     * @param Mage_Catalog_Model_Product[] $products
     * @return bool
     */
    public function forceAdd($products)
    {
        $this->_forceProducts = $products;
        $this->_skippedEntity = array();

        //Product filter duplicate
        $_products = array();
        foreach ($products as $product) {
            $_products[$this->_getEntityNumber($product)] = $product;
        }

        try {
            $_existIds = array_filter(array_map(function(Mage_Catalog_Model_Product $product){
                return is_numeric($product->getId())? $product->getId(): null;
            }, $_products));

            $this->_massAddBefore($_existIds);
            foreach ($_products as $product) {
                $this->setEntityCache($product);
                $entityId = $this->_getEntityId($product);

                if (!$this->_checkMassAddEntity($product)) {
                    $this->_skippedEntity[$entityId] = $entityId;
                    continue;
                }

                // Associate order ID with order Number
                $this->_cache[self::CACHE_KEY_ENTITIES_UPDATING][$entityId] = $this->_getEntityNumber($product);
            }

            if (empty($this->_cache[self::CACHE_KEY_ENTITIES_UPDATING])) {
                return false;
            }

            $this->_massAddAfter();
            $this->resetEntity(array_diff($_existIds, $this->_skippedEntity));

            return !empty($this->_cache[self::CACHE_KEY_ENTITIES_UPDATING]);
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("CRITICAL: " . $e->getMessage());
            return false;
        }
    }

    /**
     *
     */
    protected function _massAddAfter()
    {
        $products = array();
        foreach ((array)$this->_cache[self::CACHE_KEY_ENTITIES_UPDATING] as $entityNumber) {
            $products[] = $this->getEntityCache($entityNumber);
        }

        $this->_cache[sprintf('%sLookup', $this->_salesforceEntityName)] = Mage::helper('tnw_salesforce/salesforce_data_product')
            ->lookup($products);
    }

    /**
     * @param $ids
     */
    public function resetEntity($ids)
    {
        if (empty($ids)) {
            return;
        }

        $ids = !is_array($ids)
            ? array($ids) : $ids;

        foreach ($this->storesAvailable() as $storeId) {
            Mage::getSingleton('catalog/product_action')->updateAttributes($ids, array(
                'salesforce_id' => null,
                'salesforce_pricebook_id' => null,
                'sf_insync' => null
            ), $storeId);
        }

        Mage::getSingleton('tnw_salesforce/tool_log')
            ->saveTrace(sprintf("%s ID and Sync Status for %s (#%s) were reset.",
                $this->_magentoEntityName, $this->_magentoEntityName, join(',', $ids)));
    }

    /**
     * @return array
     */
    protected function storesAvailable()
    {
        $adminWebsiteId = Mage::app()->getWebsite('admin')->getId();
        $currentDiffWebsite = Mage::helper('tnw_salesforce/config')->getWebsiteDifferentConfig();

        $websites = ($currentDiffWebsite->getId() == $adminWebsiteId)
            ? array_diff_key(Mage::app()->getWebsites(true), Mage::helper('tnw_salesforce/config')->getWebsitesDifferentConfig(false))
            : array($currentDiffWebsite->getId() => $currentDiffWebsite);

        return call_user_func_array('array_merge', array_map(function (Mage_Core_Model_Website $website) {
            return $website->getStoreIds();
        }, $websites));
    }

    /**
     * @param $_entity Mage_Catalog_Model_Product
     * @return string
     */
    protected function _getEntityId($_entity)
    {
        if (!$_entity->getId()) {
            return 'fake_' . $this->_getEntityNumber($_entity);
        }

        return $_entity->getId();
    }

    /**
     * @param $_entity Mage_Catalog_Model_Product
     * @return bool
     */
    protected function _checkMassAddEntity($_entity)
    {
        if (intval($_entity->getData('salesforce_disable_sync')) == 1) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveNotice('SKIPPING: Product (ID: ' . $_entity->getId() . ') is excluded from synchronization');

            return false;
        }

        if (!$_entity->getSku()) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveNotice('SKIPPING: Product #' . $_entity->getId() . ', product sku is missing!');

            return false;
        }

        $typesAllow = Mage::helper('tnw_salesforce/config_product')->getSyncTypesAllow();
        if (!$this->isFeeEntity($_entity) && !in_array($_entity->getTypeId(), $typesAllow)) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveNotice('SKIPPING: Sync for product type "' . $_entity->getTypeId() . '" is disabled!');

            return false;
        }

        $this->_cache['productIdToSku'][$this->_getEntityId($_entity)] = $this->_getEntityNumber($_entity);
        return true;
    }

    /**
     * @param $_entity Mage_Catalog_Model_Product
     * @return bool
     */
    protected function isFeeEntity($_entity)
    {
        return (bool)$_entity->getData(self::ENTITY_FEE_CHECK);
    }

    protected function _updateMagento()
    {
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("---------- Start: Magento Update ----------");

        foreach ($this->_cache['toSaveInMagento'] as $_magentoSku => $_product) {
            $entity  = $this->getEntityCache($_magentoSku);
            $originalStoreId = $entity->getStoreId();

            $resetAttribute = array(
                'salesforce_id' => isset($_product->salesforceId)
                    ? $_product->salesforceId : null,

                'salesforce_pricebook_id' => !empty($_product->pricebookEntryIds[$originalStoreId])
                    ? implode("\n", $_product->pricebookEntryIds[$originalStoreId]) : null,

                'sf_insync' => isset($_product->SfInSync)
                    ? $_product->SfInSync : 0
            );

            // all force field product
            foreach ($this->_forceProducts as $forceProduct) {
                if ($this->_getEntityNumber($forceProduct) != $this->_getEntityNumber($entity)) {
                    continue;
                }

                $forceProduct->addData($resetAttribute);
            }

            // Set attribute
            $entity->addData($resetAttribute);

            /**
             * skip order fee products, these products don't exist in magento and use sku instead Id
             */
            $_magentoId = $this->_getEntityId($entity);
            if (!is_numeric($_magentoId)) {
                continue;
            }

            // Save Attribute
            $message = '';
            foreach ($this->storesAvailable() as $_storeId) {
                $entity->setData('store_id', $_storeId);

                if (!empty($_product->pricebookEntryIds[$_storeId])) {
                    $entity->setData('salesforce_pricebook_id', implode("\n", $_product->pricebookEntryIds[$_storeId]));
                }

                foreach (array_keys($resetAttribute) as $code) {
                    $entity->getResource()->saveAttribute($entity, $code);
                }

                $message .= sprintf("\nfor Store \"%s\": %s",
                    Mage::app()->getStore($_storeId)->getCode(),
                    print_r(array_intersect_key($entity->getData(), $resetAttribute), true)
                );
            }

            // Overload attribute
            $entity->setData('store_id', $originalStoreId);
            $entity->addData($resetAttribute);

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace("Save attribute (product: $_magentoSku)$message");
        }

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Updated: " . count($this->_cache['toSaveInMagento']) . " products!");
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("---------- End: Magento Update ----------");
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

    protected function _addPriceBookEntry($sku, $_sfProductId)
    {
        $entity     = $this->getEntityCache($sku);
        $_magentoId = $this->_getEntityId($entity);
        $_prod      = isset($this->_cache[sprintf('%sLookup', $this->_salesforceEntityName)][$sku])
            ? $this->_cache[sprintf('%sLookup', $this->_salesforceEntityName)][$sku]
            : null;

        // Sync remaining Stores
        foreach ($this->storesAvailable() as $_storeId) {
            $_storePriceBookId = $this->getHelper()->getPricebookId($_storeId) ?: $this->_defaultPriceBook;
            $this->addPriceBookEntryToSync($_storeId, $_magentoId, $_prod, $_sfProductId, $this->_standardPricebookId);
            $this->addPriceBookEntryToSync($_storeId, $_magentoId, $_prod, $_sfProductId, $_storePriceBookId);
        }
    }

    protected function addPriceBookEntryToSync($store, $magentoProductId, $sfProduct, $sfProductId, $priceBookId)
    {
        $store = Mage::app()->getStore($store);

        if ($this->getHelper()->isMultiCurrency()) {
            $currencyCodes = $store->getAvailableCurrencyCodes();
        } else {
            $currencyCodes = array($store->getBaseCurrencyCode());
        }

        foreach ($currencyCodes as $currencyCode) {

            $cacheCode = "$priceBookId:::$magentoProductId";
            if ($this->getHelper()->isMultiCurrency()) {
                $cacheCode .= ":::$currencyCode";
            }

            if ($priceBookId != $this->_standardPricebookId || $this->_defaultPriceBook == $this->_standardPricebookId) {
                $this->_cache['pricebookEntryKeyToStore'][$cacheCode][] = "{$currencyCode}:::{$store->getId()}";
            }

            if (isset($this->_cache['pricebookEntryToSync'][$cacheCode])) {
                continue;
            }

            $price = !empty($this->_cache['productPrices'][$magentoProductId][$store->getId()])
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
        if (!isset($this->_currencies[$currencyCode])) {
            $this->_currencies[$currencyCode] = Mage::getModel('directory/currency')->load($currencyCode);
        }

        return $this->_currencies[$currencyCode];
    }

    /**
     * @param $_entity Mage_Catalog_Model_Product
     * @param $key
     */
    protected function _prepareEntityObjCustom($_entity, $key)
    {
        if (property_exists($this->_obj, 'IsActive')) {
            $this->_obj->IsActive = ($this->_obj->IsActive == "Enabled") ? 1 : 0;
        }

        if ($this->getHelper()->getType() == 'PRO') {
            $disableSyncField = Mage::helper('tnw_salesforce/config')->getDisableSyncField();
            $this->_obj->$disableSyncField = true;
        }
    }

    /**
     *
     */
    protected function _pushEntity()
    {
        $toUpsertKey = sprintf('%sToUpsert', strtolower($this->getManyParentEntityType()));
        if (empty($this->_cache[$toUpsertKey])) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace('No Product found queued for the synchronization!');

            return;
        }

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------PRODUCTS: Start----------');

        $_keys = array_keys($this->_cache[$toUpsertKey]);
        try {
            Mage::dispatchEvent("tnw_salesforce_product_send_before", array(
                "data" => $this->_cache[$toUpsertKey]
            ));

            $_responses = $this->getClient()->upsert('Id', array_values($this->_cache[$toUpsertKey]), 'Product2');
            Mage::dispatchEvent("tnw_salesforce_product_send_after", array(
                "data" => $this->_cache[$toUpsertKey],
                "result" => $_responses
            ));
        } catch (Exception $e) {
            $_responses = array_fill(0, count($_keys),
                $this->_buildErrorResponse($e->getMessage()));

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveError('CRITICAL: Push of products to Salesforce failed' . $e->getMessage());
        }

        foreach ($_responses as $_key => $_response) {
            $_sku = $_keys[$_key];

            //Report Transaction
            $this->_cache['responses']['products'][$_sku] = $_response;

            if (!$_response->success) {
                $this->_processErrors($_response, 'product', $this->_cache[$toUpsertKey][$_sku]);
                continue;
            }

            $this->_cache[sprintf('upserted%s', $this->getManyParentEntityType())][$_sku] = $_response->id;
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('PRODUCT SKU (' . $_sku . '): salesforceID (' . $_response->id . ')');

            $_product = new stdClass();
            $_product->salesforceId = $_response->id;
            $_product->SfInSync = 1;
            $this->_cache['toSaveInMagento'][$_sku] = $_product;
        }

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------PRODUCTS: End----------');
    }

    protected function _prepareEntityPriceBook()
    {
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------UPSERTING Pricebook: Start----------');

        foreach ($this->_cache[sprintf('upserted%s', $this->getManyParentEntityType())] as $sku => $salesforceId) {
            $entity = $this->getEntityCache($sku);
            foreach ($this->storesAvailable() as $storeId) {
                $productId = $this->_getEntityId($entity);
                $price = is_numeric($productId)
                    ? $entity->getResource()->getAttributeRawValue($productId, 'price', $storeId)
                    : $entity->getPrice();

                $this->_cache['productPrices'][$this->_getEntityId($entity)][$storeId]
                    = $this->numberFormat($price);
            }

            $this->_addPriceBookEntry($sku, $salesforceId);
        }

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------UPSERTING Pricebook: End----------');
    }

    protected function _pushEntityPriceBook()
    {
        if (empty($this->_cache['pricebookEntryToSync'])) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace('No PriceBook found queued for the synchronization!');

            return;
        }

        $_keys = array_keys($this->_cache['pricebookEntryToSync']);
        try {
            $_responses = $this->getClient()->upsert('Id', array_values($this->_cache['pricebookEntryToSync']), 'PricebookEntry');
        } catch (Exception $e) {
            $_responses = array_fill(0, count($_keys),
                $this->_buildErrorResponse($e->getMessage()));

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveError('CRITICAL: Push of products to Salesforce failed' . $e->getMessage());
        }

        foreach ($_responses as $_key => $_response) {
            $cacheKey = $_keys[$_key];

            list($priceBookId, $_magentoId) = explode(':::', $cacheKey, 3);
            $sku = $this->_cache['productIdToSku'][$_magentoId];

            //Report Transaction
            $this->_cache['responses']['pricebooks'][$cacheKey] = $_response;
            if (!$_response->success) {
                $this->_cache['toSaveInMagento'][$sku]->SfInSync = 0;
                $this->_processErrors($_response, 'pricebook', $this->_cache['pricebookEntryToSync'][$cacheKey]);

                continue;
            }

            $standard = ($priceBookId == $this->_standardPricebookId) ? ' of standard pricebook' : '';
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace("PRICEBOOK ENTRY: Product SKU ({$sku}) : salesforceID ({$_response->id}){$standard}");

            if (!empty($this->_cache['pricebookEntryKeyToStore'][$cacheKey])) {
                foreach (array_unique((array)$this->_cache['pricebookEntryKeyToStore'][$cacheKey]) as $store) {
                    list($currencyCode, $storeId) = explode(':::', $store, 2);
                    $this->_cache['toSaveInMagento'][$sku]->pricebookEntryIds[$storeId][] = "{$currencyCode}:{$_response->id}";
                }
            }
        }
    }

    public function reset()
    {
        parent::reset();

        // Reset cache (need to conver to magento cache
        $this->_cache = array(
            sprintf('%sLookup', $this->_salesforceEntityName) => array(),
            sprintf('%sToUpsert', strtolower($this->getManyParentEntityType())) => array(),
            sprintf('upserted%s', $this->getManyParentEntityType()) => array(),
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

        return $this->check();
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