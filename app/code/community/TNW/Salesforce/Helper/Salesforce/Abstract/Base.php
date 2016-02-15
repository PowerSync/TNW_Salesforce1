<?php

abstract class TNW_Salesforce_Helper_Salesforce_Abstract_Base extends TNW_Salesforce_Helper_Salesforce_Abstract
{
    const CACHE_KEY_ENTITIES_UPDATING = 'entitiesUpdating';

    /**
     * @comment magento entity alias "convert from"
     * @var string
     */
    protected $_magentoEntityName = '';

    /**
     * @comment salesforce entity alias "convert to"
     * @var string
     */
    protected $_salesforceEntityName = '';

    /**
     * @comment magento entity model alias
     * @var array
     */
    protected $_magentoEntityModel = '';

    /**
     * @comment salesforce field name to assign parent entity
     * @var string
     */
    protected $_salesforceParentIdField = '';

    /**
     * @comment magento entity item qty field name
     * @var array
     */
    protected $_itemQtyField = 'qty';

    /**
     * @comment cache keys
     * @var string
     */
    protected $_ucParentEntityType = '';
    protected $_manyParentEntityType = '';
    protected $_itemsField = '';

    /**
     * @var array
     */
    protected $_availableFees = array();

    /**
     * @var array
     */
    protected $_skippedEntity = array();

    /**
     * @var array
     */
    protected $_alternativeKeys = array();

    /**
     * @return string
     */
    public function getSalesforceEntityName()
    {
        return $this->_salesforceEntityName;
    }

    /**
     * @param string $salesforceEntityName
     * @return $this
     */
    public function setSalesforceEntityName($salesforceEntityName)
    {
        $this->_salesforceEntityName = $salesforceEntityName;
        return $this;
    }

    /**
     * @return array
     */
    public function getSkippedEntity()
    {
        return $this->_skippedEntity;
    }

    //public function

    public function getMagentoEntityName()
    {
        return $this->_magentoEntityName;
    }

    /**
     * @param array $magentoEntityName
     * @return $this
     */
    public function setMagentoEntityName($magentoEntityName)
    {
        $this->_magentoEntityName = $magentoEntityName;
        return $this;
    }

    /**
     * @return array
     */
    public function getMagentoEntityModel()
    {
        return $this->_magentoEntityModel;
    }

    /**
     * @param array $magentoEntityModel
     * @return $this
     */
    public function setMagentoEntityModel($magentoEntityModel)
    {
        $this->_magentoEntityModel = $magentoEntityModel;
        return $this;
    }

    /**
     * @return array
     */
    public function getAvailableFees()
    {
        return $this->_availableFees;
    }

    /**
     * @param array $availableFees
     * @return $this
     */
    public function setAvailableFees($availableFees)
    {
        $this->_availableFees = $availableFees;
        return $this;
    }

    /**
     * @return array
     */
    public function getAlternativeKeys()
    {
        return $this->_alternativeKeys;
    }

    /**
     * @return false|Mage_Core_Model_Abstract
     */
    protected function _modelEntity()
    {
        return Mage::getModel($this->_magentoEntityModel);
    }

    /**
     * @param $_key
     * @return Mage_Core_Model_Abstract
     */
    protected function _loadEntity($_key)
    {
        return $this->_modelEntity()
            ->load($_key);
    }

    /**
     * @param $_key
     * @param $cachePrefix
     * @return Mage_Core_Model_Abstract
     */
    protected function _loadEntityByCache($_key, $cachePrefix = null)
    {
        $_entity = null;

        // Generate cache key
        if (is_null($cachePrefix) && !empty($_key)) {
            $_entity = $this->_loadEntity($_key);
            $cachePrefix = $this->_getEntityNumber($_entity);
        }

        $entityRegistryKey = $this->_generateKeyEntityCache($cachePrefix);
        if (!is_null($_entity)) {
            Mage::unregister($entityRegistryKey);
        }

        // Generate cache
        if (!Mage::registry($entityRegistryKey) && !empty($_key)) {
            $_entity = is_null($_entity) ? $this->_loadEntity($_key) : $_entity;
            Mage::register($entityRegistryKey, $_entity);
        }

        // Get entity
        return Mage::registry($entityRegistryKey);
    }

    protected function _generateKeyEntityCache($cachePrefix)
    {
        return sprintf('%s_cached_%s', $this->_magentoEntityName, (string)$cachePrefix);
    }

    /**
     * @param $cachePrefix
     */
    protected function _unsetEntityCache($cachePrefix)
    {
        $entityRegistryKey = $this->_generateKeyEntityCache($cachePrefix);
        Mage::unregister($entityRegistryKey);
    }

    /**
     * @return string
     */
    public function getUcParentEntityType()
    {
        if (!$this->_ucParentEntityType) {
            /** @comment first letter in upper case */
            $this->_ucParentEntityType = ucfirst($this->_salesforceEntityName);
        }

        return $this->_ucParentEntityType;
    }

    /**
     * @param string $ucParentEntityType
     * @return $this
     */
    public function setUcParentEntityType($ucParentEntityType)
    {
        $this->_ucParentEntityType = $ucParentEntityType;
        return $this;
    }

    /**
     * @return string
     */
    public function getManyParentEntityType()
    {
        if (!$this->_manyParentEntityType) {
            $this->_manyParentEntityType = $this->getUcParentEntityType();
            $this->_manyParentEntityType .= 's';
        }

        return $this->_manyParentEntityType;
    }

    /**
     * @param string $manyParentEntityType
     * @return $this
     */
    public function setManyParentEntityType($manyParentEntityType)
    {
        $this->_manyParentEntityType = $manyParentEntityType;
        return $this;
    }

    /**
     * @return string
     */
    public function getItemsField()
    {
        if (!$this->_itemsField) {
            $this->_itemsField = $this->getUcParentEntityType();
            $this->_itemsField .= 'Items';
        }

        return $this->_itemsField;
    }

    /**
     * @param string $itemsField
     * @return $this
     */
    public function setItemsField($itemsField)
    {
        $this->_itemsField = $itemsField;
        return $this;
    }

    /**
     * @return string
     */
    public function getSalesforceParentIdField()
    {
        return $this->_salesforceParentIdField;
    }

    /**
     * @param string $salesforceParentIdField
     * @return $this
     */
    public function setSalesforceParentIdField($salesforceParentIdField)
    {
        $this->_salesforceParentIdField = $salesforceParentIdField;
        return $this;
    }

    /**
     * @comment assign Opportynity/Order Id
     */
    protected function _getParentEntityId($_entityNumber)
    {
        if (!$this->getSalesforceParentIdField()) {
            $this->setSalesforceParentIdField($this->getUcParentEntityType() . 'Id');
        }

        $upsertedKey = sprintf('upserted%s', $this->getManyParentEntityType());
        return (array_key_exists($_entityNumber, $this->_cache[$upsertedKey])) ? $this->_cache[$upsertedKey][$_entityNumber] :  NULL;
    }

    /**
     * @return string
     */
    public function getItemQtyField()
    {
        return $this->_itemQtyField;
    }

    /**
     * @param array $itemQtyField
     * @return $this
     */
    public function setItemQtyField($itemQtyField)
    {
        $this->_itemQtyField = $itemQtyField;
        return $this;
    }

    /**
     * @param array $_ids
     * @param bool|false $_isCron
     * @return bool
     */
    public function massAdd($_ids = array(), $_isCron = false)
    {
        $_ids           = !is_array($_ids) ? array($_ids) : $_ids;
        $this->_isCron  = $_isCron;

        if (!$_ids) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Entity Id is not specified, don't know what to synchronize!");
            return false;
        }

        // test sf api connection
        /** @var TNW_Salesforce_Model_Connection $_client */
        $_client = Mage::getSingleton('tnw_salesforce/connection');
        if (!$_client->initConnection()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR on sync entity, sf api connection failed");

            return true;
        }

        $this->_skippedEntity = array();
        try {
            $this->_massAddBefore();

            foreach ($_ids as $_id) {
                $_entity        = $this->_loadEntityByCache($_id);
                $_entityNumber  = $this->_getEntityNumber($_entity);

                if (!$_entity->getId() || !$_entityNumber) {
                    Mage::getSingleton('tnw_salesforce/tool_log')
                        ->saveError(sprintf('WARNING: Sync for %s #%s, %s could not be loaded!',
                            $this->_magentoEntityName, $_id, $this->_magentoEntityName));

                    $this->_skippedEntity[$_id] = $_id;
                    continue;
                }

                if (!$this->_checkMassAddEntity($_entity)) {
                    $this->_skippedEntity[$_entity->getId()] = $_entity->getId();
                    continue;
                }

                // Associate order ID with order Number
                $this->_cache[self::CACHE_KEY_ENTITIES_UPDATING][$_id] = $_entityNumber;
            }

            if (empty($this->_cache[self::CACHE_KEY_ENTITIES_UPDATING])) {
                return false;
            }

            $this->resetEntity(array_diff($_ids, $this->_skippedEntity));
            $this->_massAddAfter();

            return !empty($this->_cache[self::CACHE_KEY_ENTITIES_UPDATING]);
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("CRITICAL: " . $e->getMessage());
            return false;
        }
    }

    /**
     *
     */
    protected function _massAddBefore()
    {
        return;
    }

    /**
     * @param $_entity
     * @return bool
     * @throws Exception
     */
    protected function _checkMassAddEntity($_entity)
    {
        throw new Exception(sprintf('Method "%s::%s" must be overridden before use', __CLASS__, __METHOD__));
    }

    /**
     *
     */
    protected function _massAddAfter()
    {
        return;
    }

    /**
     * @param $ids
     * Reset Salesforce ID in Magento for the order
     */
    public function resetEntity($ids)
    {
        if (empty($ids)) {
            return;
        }

        $ids = !is_array($ids)
            ? array($ids) : $ids;

        $mainTable = $this->_modelEntity()->getResource()->getMainTable();
        $sql = "UPDATE `" . $mainTable . "` SET salesforce_id = NULL, sf_insync = 0 WHERE entity_id IN (" . join(',', $ids) . ");";
        Mage::helper('tnw_salesforce')->getDbConnection()->query($sql);

        Mage::getSingleton('tnw_salesforce/tool_log')
            ->saveTrace(sprintf("%s ID and Sync Status for %s (#%s) were reset.",
                $this->_magentoEntityName, $this->_magentoEntityName, join(',', $ids)));
    }

    /**
     * @param string $type
     * @return bool
     */
    public function process($type = 'soft')
    {
        try {
            if (!Mage::helper('tnw_salesforce/salesforce_data')->isLoggedIn()) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError("CRITICAL: Connection to Salesforce could not be established! Check API limits and/or login info.");
                if (!$this->isFromCLI() && Mage::helper('tnw_salesforce')->displayErrors()) {
                    Mage::getSingleton('adminhtml/session')->addWarning('WARNING: SKIPPING synchronization, could not establish Salesforce connection.');
                }
                return false;
            }

            $_syncType = stripos(get_class($this), '_bulk_') !== false ? 'MASS' : 'REALTIME';
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace(sprintf("================ %s SYNC: START ================", $_syncType));

            if (!is_array($this->_cache) || empty($this->_cache[self::CACHE_KEY_ENTITIES_UPDATING])) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError(sprintf("WARNING: Sync %s, cache is empty!", $this->getManyParentEntityType()));
                $this->_dumpObjectToLog($this->_cache, "Cache", true);
                return false;
            }

            $this->_alternativeKeys = $this->_cache[self::CACHE_KEY_ENTITIES_UPDATING];
            $this->_beforeProcess();

            $this->_prepareEntity();
            $this->_pushEntity();

            $this->clearMemory();
            set_time_limit(1000);

            if ($type == 'full') {
                $this->_prepareRemaining();
                $this->_pushRemainingEntityData();

                $this->clearMemory();
            }

            $this->_onComplete();
            $this->_afterProcess();

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace(sprintf("================= %s SYNC: END =================", $_syncType));
            return true;
        }
        catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("CRITICAL: " . $e->getMessage());
            return false;
        }
    }

    protected function _beforeProcess()
    {
        return;
    }

    protected function _afterProcess()
    {
        return;
    }

    /**
     * Remaining Data
     */
    protected function _prepareRemaining()
    {
        if (Mage::helper('tnw_salesforce')->doPushShoppingCart()) {
            $this->_prepareEntityItems();
        }
        if (Mage::helper('tnw_salesforce')->isOrderNotesEnabled()) {
            $this->_prepareNotes();
        }
    }

    protected function _prepareEntity()
    {
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace(sprintf('----------%s Preparation: Start----------', $this->getUcParentEntityType()));
        foreach ($this->_cache[self::CACHE_KEY_ENTITIES_UPDATING] as $_key => $_orderNumber)
        {
            if (!$this->_checkPrepareEntityBefore($_key)) {
                continue;
            }

            $this->_obj = new stdClass();
            $this->_setEntityInfo($this->_loadEntityByCache($_key, $_orderNumber));

            if (!$this->_checkPrepareEntityAfter($_key)) {
                continue;
            }

            $this->_cache[sprintf('%sToUpsert', strtolower($this->getManyParentEntityType()))][$_orderNumber] = $this->_obj;
        }

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace(sprintf('----------%s Preparation: End----------', $this->getUcParentEntityType()));
    }

    protected function _checkPrepareEntityBefore($_key)
    {
        return true;
    }

    protected function _checkPrepareEntityAfter($_key)
    {
        return true;
    }

    /**
     * Prepare order history notes for syncronization
     */
    protected function _prepareNotes()
    {
        $failedKey = sprintf('failed%s', $this->getManyParentEntityType());

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Prepare Notes: Start----------');

        // Get all products from each order and decide if all needs to me synced prior to inserting them
        foreach ($this->_cache[self::CACHE_KEY_ENTITIES_UPDATING] as $_key => $_number) {
            if (in_array($_number, $this->_cache[$failedKey])) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace(sprintf('%s (%s): Skipping, issues with upserting an %s!',
                    strtoupper($this->getMagentoEntityName()), $_number, $this->getSalesforceEntityName()));

                continue;
            }

            $_entity = $this->_loadEntityByCache($_key, $_number);
            $_notes  = $this->_getEntityNotesCollection($_entity);
            $this->createObjNones($_notes);
        }

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Prepare Notes: End----------');
    }

    /**
     * @param $_entity
     * @throws Exception
     * @return array
     */
    protected function _getEntityNotesCollection($_entity)
    {
        throw new Exception(sprintf('Method "%s::%s" must be overridden before use', __CLASS__, __METHOD__));
    }

    /**
     * @param Mage_Sales_Model_Order_Status_History[] $notes
     * @return $this
     */
    public function createObjNones($notes)
    {
        if (!$notes instanceof Varien_Data_Collection && !is_array($notes)) {
            $notes = array($notes);
        }

        foreach ($notes as $_note) {
            if (!$this->_checkNotesItem($_note)){
                continue;
            }

            $comment      = utf8_encode($_note->getData('comment'));

            $_obj = new stdClass();
            $_obj->ParentId   = $this->_getNotesParentSalesforceId($_note);
            $_obj->IsPrivate  = 0;
            $_obj->Body       = $comment;
            $_obj->Title      = (strlen($comment) > 75)
                ? sprintf('%s...', mb_substr($comment, 0, 75))
                : $comment;

            foreach ($_obj as $key => $_value) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace(sprintf('Note Object: %s = "%s"', $key, $_value));
            }

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('+++++++++++++++++++++++++++++');
            $this->_cache['notesToUpsert'][$_note->getData('entity_id')] = $_obj;
        }

        return $this;
    }

    /**
     * @param $_note Varien_Object
     * @return bool
     */
    protected function _checkNotesItem($_note)
    {
        return !$_note->getData('salesforce_id') && $_note->getData('comment');
    }

    protected function _getNotesParentSalesforceId($notes)
    {
        throw new Exception(sprintf('Method "%s::%s" must be overridden before use', __CLASS__, __METHOD__));
    }

    /**
     * @param $_entity
     * @return mixed
     * @throws Exception
     */
    protected function _getEntityNumber($_entity)
    {
        throw new Exception(sprintf('Method "%s::%s" must be overridden before use', __CLASS__, __METHOD__));
    }

    /**
     * @param $_entity
     * @return mixed
     * @throws Exception
     */
    protected function _setEntityInfo($_entity)
    {
        throw new Exception(sprintf('Method "%s::%s" must be overridden before use', __CLASS__, __METHOD__));
    }

    /**
     * @param array $chunk
     * @return mixed
     * @throws Exception
     */
    protected function _pushEntityItems($chunk = array())
    {
        throw new Exception(sprintf('Method "%s::%s" must be overridden before use', __CLASS__, __METHOD__));
    }

    /**
     * @return mixed
     * @throws Exception
     */
    protected function _pushEntity()
    {
        throw new Exception(sprintf('Method "%s::%s" must be overridden before use', __CLASS__, __METHOD__));
    }

    /**
     * Prepare Order items object(s) for upsert
     */
    protected function _prepareEntityItems()
    {
        $failedKey = sprintf('failed%s', $this->getManyParentEntityType());

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace(sprintf('----------Prepare %s items: Start----------', $this->_magentoEntityName));
        $this->_prepareEntityItemsBefore();

        foreach ($this->_cache[self::CACHE_KEY_ENTITIES_UPDATING] as $_key => $_entityNumber) {
            if (in_array($_entityNumber, $this->_cache[$failedKey])) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace(sprintf('%s (%s): Skipping, issues with upserting an %s!',
                        strtoupper($this->_magentoEntityName), $_entityNumber, $this->_salesforceEntityName));

                continue;
            }

            $_entity = $this->_loadEntityByCache($_key, $_entityNumber);
            foreach ($this->getItems($_entity) as $_entityItem) {
                try {
                    $this->_prepareEntityItemObj($_entity, $_entityItem);
                } catch (Exception $e) {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveError($e->getMessage());
                    continue;
                }
            }

            $this->_prepareEntityItemAfter($_entity);
        }

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace(sprintf('----------Prepare %s items: End----------', $this->_magentoEntityName));
    }

    protected function _prepareEntityItemsBefore()
    {
        return;
    }

    /**
     * @comment return entity items
     * @param $_entity
     * @return mixed
     * @throws Exception
     */
    public function getItems($_entity)
    {
        throw new Exception(sprintf('Method "%s::%s" must be overridden before use', __CLASS__, __METHOD__));
    }

    /**
     * @param $_item
     * @throws Exception
     */
    public function getEntityByItem($_item)
    {
        throw new Exception(sprintf('Method "%s::%s" must be overridden before use', __CLASS__, __METHOD__));
    }

    /**
     * @param $_entity
     * @param $_entityItem
     * @return mixed
     * @throws Exception
     */
    protected function _prepareEntityItemObj($_entity, $_entityItem)
    {
        throw new Exception(sprintf('Method "%s::%s" must be overridden before use', __CLASS__, __METHOD__));
    }

    /**
     * @param $_entity
     */
    protected function _prepareEntityItemAfter($_entity)
    {
        return;
    }

    /**
     * @comment add Tax/Shipping/Discount to the order as different product
     * @param $_entity Varien_Object
     */
    protected function _applyAdditionalFees($_entity)
    {
        /** @var TNW_Salesforce_Helper_Data $_helper */
        $_helper = Mage::helper('tnw_salesforce');
        foreach ($this->getAvailableFees() as $feeName) {
            $ucFee = ucfirst($feeName);

            // Push Fee As Product
            if (!call_user_func(array($_helper, sprintf('use%sFeeProduct', $ucFee))) || $_entity->getData($feeName . '_amount') == 0) {
                continue;
            }

            if (! call_user_func(array($_helper, sprintf('get%sProduct', $ucFee)))) {
                continue;
            }

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Add $feeName");
            $feeData = Mage::getStoreConfig($_helper->getFeeProduct($feeName), $_entity->getStoreId());
            if (!$feeData) {
                continue;
            }

            $feeData = @unserialize($feeData);
            $item = new Varien_Object();
            $item->setData($feeData);

            /**
             * add data in lower case too compatibility for
             */
            foreach ($feeData as $key => $value) {
                $item->setData(strtolower($key), $value);
            }

            $item->addData(array(
                $this->getItemQtyField() => 1,
                'description'            => $_helper->__($ucFee),
                'row_total_incl_tax'     => $this->getEntityPrice($_entity, $ucFee . 'Amount'),
                'row_total'              => $this->getEntityPrice($_entity, $ucFee . 'Amount')
            ));

            $this->_prepareEntityItemObj($_entity, $item);
        }
    }

    /**
     * @param $_entity
     * @return string
     */
    public function getCurrencyCode($_entity)
    {
        $currencyCodeField = $this->getMagentoEntityName() . '_currency_code';
        $_currencyCode = '';

        if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {

            /**
             * this condition used for invoice sync
             */
            if (!$_entity->hasData($currencyCodeField)) {
                $currencyCodeField = 'order_currency_code';
            }
            $_currencyCode = $_entity->getData($currencyCodeField);
        }

        return $_currencyCode;
    }

    /**
     * @param $_entity
     * @return string
     */
    protected function _getDescriptionByEntity($_entity)
    {
        /** @var TNW_Salesforce_Helper_Data $helper */
        $helper = Mage::helper('tnw_salesforce');

        $baseCurrency = Mage::helper('tnw_salesforce/config_sales')->useBaseCurrency();
        $currency = $baseCurrency ? $_entity->getBaseCurrencyCode() : $_entity->getOrderCurrencyCode();
        /**
         * use custome currency if Multicurrency enabled
         */
        if ($helper->isMultiCurrency()) {
            $currency = $_entity->getOrderCurrencyCode();
            $baseCurrency = false;
        }

        ## Put Products into Single field
        $delimiter = '=======================================';
        $lines = array();
        $lines[] = sprintf('Items %s:', $this->_magentoEntityName);
        $lines[] = $delimiter;
        $lines[] = 'SKU, Qty, Name, Price, Tax, Subtotal, Net Total';
        $lines[] = $delimiter;

        foreach ($this->getItems($_entity) as $itemId => $item) {
            $rowTotalInclTax = $baseCurrency ? $item->getBaseRowTotalInclTax() : $item->getRowTotalInclTax();
            $discount = $baseCurrency ? $item->getBaseDiscountAmount() : $item->getDiscountAmount();

            $lines[] = implode(', ', array(
                $item->getSku(),
                $helper->numberFormat($item->getQtyOrdered()),
                $item->getName(),
                $currency . $helper->numberFormat($baseCurrency ? $item->getBasePrice() : $item->getPrice()),
                $currency . $helper->numberFormat($baseCurrency ? $item->getBaseTaxAmount() : $item->getTaxAmount()),
                $currency . $helper->numberFormat($rowTotalInclTax),
                $currency . $helper->numberFormat($rowTotalInclTax - $discount),
            ));
        }
        $lines[] = $delimiter;

        $subtotal = $baseCurrency ? $_entity->getBaseSubtotal() : $_entity->getSubtotal();
        $tax = $baseCurrency ? $_entity->getBaseTaxAmount() : $_entity->getTaxAmount();
        $shipping = $baseCurrency ? $_entity->getBaseShippingAmount() : $_entity->getShippingAmount();
        $grandTotal = $baseCurrency ? $_entity->getBaseGrandTotal() : $_entity->getGrandTotal();
        foreach (array(
                     'Sub Total' => $subtotal,
                     'Tax' => $tax,
                     'Shipping' => $shipping,
                     'Discount Amount' => $grandTotal - ($shipping + $tax + $subtotal),
                     'Total' => $grandTotal,
                 ) as $label => $totalValue) {
            $lines[] = sprintf('%s: %s%s', $label, $currency, $helper->numberFormat($totalValue));
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param $_entity
     * @param $_entityItem
     * @param $text
     * @param null $html
     * @return string
     */
    protected function _getDescriptionByEntityItem($_entity, $_entityItem, &$text, &$html = null)
    {
        $html = $text = '';

        $baseCurrency = Mage::helper('tnw_salesforce/config_sales')->useBaseCurrency();
        $_currencyCode = $baseCurrency ? $_entity->getBaseCurrencyCode() : $_entity->getOrderCurrencyCode();

        /**
         * use custome currency if Multicurrency enabled
         */
        if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {
            $_currencyCode = $_entity->getOrderCurrencyCode();
        }

        $opt = array();
        $options = (is_array($_entityItem->getData('product_options')))
            ? $_entityItem->getData('product_options')
            : @unserialize($_entityItem->getData('product_options'));

        $_summary = array();

        if (
            is_array($options)
            && array_key_exists('options', $options)
        ) {
            $_prefix = '<table><thead><tr><th align="left">Option Name</th><th align="left">Title</th></tr></thead><tbody>';
            foreach ($options['options'] as $_option) {
                $optionValue = '';
                if(isset($_option['print_value'])) {
                    $optionValue = $_option['print_value'];
                } elseif (isset($_option['value'])) {
                    $optionValue = $_option['value'];
                }

                $opt[] = '<tr><td align="left">' . $_option['label'] . '</td><td align="left">' . $optionValue . '</td></tr>';
                $_summary[] = $optionValue;
            }
        }

        if (
            is_array($options)
            && $_entityItem->getData('product_type') == 'bundle'
            && array_key_exists('bundle_options', $options)
        ) {
            $_prefix = '<table><thead><tr><th align="left">Option Name</th><th align="left">Title</th><th>Qty</th><th align="left">Fee<th></tr><tbody>';
            foreach ($options['bundle_options'] as $_option) {
                $_string = '<td align="left">' . $_option['label'] . '</td>';
                if (is_array($_option['value'])) {
                    $_tmp = array();
                    foreach ($_option['value'] as $_value) {
                        $_tmp[] = '<td align="left">' . $_value['title'] . '</td><td align="center">' . $_value['qty'] . '</td><td align="left">' . $_currencyCode . ' ' . $this->numberFormat($_value['price']) . '</td>';
                        $_summary[] = $_value['title'];
                    }
                    if (count($_tmp) > 0) {
                        $_string .= join(", ", $_tmp);
                    }
                }

                $opt[] = '<tr>' . $_string . '</tr>';
            }
        }

        if (
            is_array($options)
            && $_entityItem->getData('product_type') == 'configurable'
            && array_key_exists('attributes_info', $options)
        ) {
            $_prefix = '<table><thead><tr><th align="left">Option Name</th><th align="left">Title</th></tr><tbody>';
            foreach ($options['attributes_info'] as $_option) {
                $_string = '<td align="left">' . $_option['label'] . '</td>';
                $_string .= '<td align="left">' . $_option['value'] . '</td>';
                $_summary[] = $_option['value'];

                $opt[] = '<tr>' . $_string . '</tr>';
            }
        }

        if (count($opt) > 0) {
            $html = $_prefix . join("", $opt) . '</tbody></table>';
            $text = join(", ", $_summary);
        }
    }

    /**
     * @param $item Mage_Sales_Model_Order_Item
     * @return array
     */
    protected function _getItemDescription($item)
    {
        $opt = array();
        $options = (is_array($item->getData('product_options')))
            ? $item->getData('product_options')
            : @unserialize($item->getData('product_options'));

        $_summary = array();
        if (
            is_array($options)
            && array_key_exists('options', $options)
        ) {
            $_prefix = '<table><thead><tr><th align="left">Option Name</th><th align="left">Title</th></tr></thead><tbody>';
            foreach ($options['options'] as $_option) {
                $optionValue = '';
                if(isset($_option['print_value'])) {
                    $optionValue = $_option['print_value'];
                } elseif (isset($_option['value'])) {
                    $optionValue = $_option['value'];
                }

                $opt[] = '<tr><td align="left">' . $_option['label'] . '</td><td align="left">' . $optionValue . '</td></tr>';
                $_summary[] = $optionValue;
            }
        }

        if (
            is_array($options)
            && $item->getData('product_type') == 'bundle'
            && array_key_exists('bundle_options', $options)
        ) {
            $_entity       = $this->getEntityByItem($item);
            $_currencyCode = $this->getCurrencyCode($_entity);

            $_prefix = '<table><thead><tr><th align="left">Option Name</th><th align="left">Title</th><th>Qty</th><th align="left">Fee<th></tr><tbody>';
            foreach ($options['bundle_options'] as $_option) {
                $_string = '<td align="left">' . $_option['label'] . '</td>';
                if (is_array($_option['value'])) {
                    $_tmp = array();
                    foreach ($_option['value'] as $_value) {
                        $_tmp[] = '<td align="left">' . $_value['title'] . '</td><td align="center">' . $_value['qty'] . '</td><td align="left">' . $_currencyCode . ' ' . $this->numberFormat($_value['price']) . '</td>';
                        $_summary[] = $_value['title'];
                    }
                    if (count($_tmp) > 0) {
                        $_string .= join(", ", $_tmp);
                    }
                }

                $opt[] = '<tr>' . $_string . '</tr>';
            }
        }

        if (
            is_array($options)
            && $item->getData('product_type') == 'configurable'
            && array_key_exists('attributes_info', $options)
        ) {
            $_prefix = '<table><thead><tr><th align="left">Option Name</th><th align="left">Title</th></tr><tbody>';
            foreach ($options['attributes_info'] as $_option) {
                $_string = '<td align="left">' . $_option['label'] . '</td>';
                $_string .= '<td align="left">' . $_option['value'] . '</td>';
                $_summary[] = $_option['value'];
                $opt[] = '<tr>' . $_string . '</tr>';
            }
        }

        if (count($opt) > 0) {
            $_description = join(", ", $_summary);
            if (strlen($_description) > 200) {
                $_description = substr($_description, 0, 200) . '...';
            }

            return array($_prefix . join("", $opt) . '</tbody></table>', $_description);
        }

        return array('', '');
    }

    /**
     * @param $item
     * @param int $qty
     * @return float
     */
    protected function _prepareItemPrice($item, $qty = 1)
    {
        $netTotal = $this->_calculateItemPrice($item, $qty);

        /**
         * @comment prepare formatted price
         */
        return $this->numberFormat($netTotal);
    }

    /**
     * @param $item
     * @param int $qty
     * @return float
     */
    protected function _calculateItemPrice($item, $qty = 1)
    {
        if (!Mage::helper('tnw_salesforce')->useTaxFeeProduct()) {
            $netTotal = $this->getEntityPrice($item, 'RowTotalInclTax');
        } else {
            $netTotal = $this->getEntityPrice($item, 'RowTotal');
        }

        if (!Mage::helper('tnw_salesforce')->useDiscountFeeProduct()) {
            $netTotal = ($netTotal - $this->getEntityPrice($item, 'DiscountAmount'));
            $netTotal = $netTotal / $qty;
        } else {
            $netTotal = $netTotal / $qty;
        }

        return $netTotal;
    }

    /**
     * @comment returns item qty
     * @param $item Varien_Object
     * @return mixed
     */
    public function getItemQty($item)
    {
        return $item->getData($this->getItemQtyField());
    }

    /**
     * @param $_item
     * @return int
     * Get product Id from the cart
     */
    public function getProductIdFromCart($_item)
    {
        if (
            $_item->getData('product_type') == 'bundle'
            || (is_array($_options = unserialize($_item->getData('product_options'))) && array_key_exists('options', $_options))
        ) {
            $id = $_item->getData('product_id');
        } else {
            $id = (int)Mage::getModel('catalog/product')->getIdBySku($_item->getSku());
        }

        return $id;
    }

    /**
     * @param $_entity
     * @return null|string
     */
    protected function _getPricebookIdToEntity($_entity)
    {
        /** @var TNW_Salesforce_Helper_Data $_helper */
        $_helper = Mage::helper('tnw_salesforce');
        return Mage::getStoreConfig($_helper::PRODUCT_PRICEBOOK, $_entity->getStoreId());
    }

    /**
     *
     */
    protected function _pushRemainingEntityData()
    {
        $itemKey = sprintf('%sToUpsert', lcfirst($this->getItemsField()));

        // Push Order Products
        if (!empty($this->_cache[$itemKey])) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Push Cart Items: Start----------');

            Mage::dispatchEvent(sprintf('tnw_salesforce_%s_products_send_before', $this->_magentoEntityName), array("data" => $this->_cache[$itemKey]));

            $orderItemsToUpsert = array_chunk($this->_cache[$itemKey], TNW_Salesforce_Helper_Data::BASE_UPDATE_LIMIT, true);
            foreach ($orderItemsToUpsert as $_itemsToPush) {
                $this->_pushEntityItems($_itemsToPush);
            }

            Mage::dispatchEvent(sprintf('tnw_salesforce_%s_products_send_after', $this->_magentoEntityName), array(
                "data" => $this->_cache[$itemKey],
                "result" => $this->_cache['responses'][lcfirst($this->getItemsField())]
            ));

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Push Cart Items: End----------');
        }

        // Push Custom Data
        $this->_pushRemainingCustomEntityData();

        // Kick off the event to allow additional data to be pushed into salesforce
        Mage::dispatchEvent(sprintf('tnw_salesforce_%s_sync_after_final', $this->_magentoEntityName),array(
            "all" => $this->_cache['entitiesUpdating'],
            "failed" => $this->_cache[sprintf('failed%s', $this->getManyParentEntityType())]
        ));
    }

    protected function _pushRemainingCustomEntityData()
    {
        // Push Notes
        $this->pushDataNotes();
    }

    /**
     * @return $this
     */
    public function pushDataNotes()
    {
        if (empty($this->_cache['notesToUpsert'])) {
            return $this;
        }

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Push Notes: Start----------');
        Mage::dispatchEvent(sprintf('tnw_salesforce_%s_notes_send_before', $this->_magentoEntityName), array("data" => $this->_cache['notesToUpsert']));

        // Push Cart
        $notesToUpsert = array_chunk($this->_cache['notesToUpsert'], TNW_Salesforce_Helper_Data::BASE_UPDATE_LIMIT, true);
        foreach ($notesToUpsert as $_itemsToPush) {
            $this->_pushNotes($_itemsToPush);
        }

        Mage::dispatchEvent(sprintf('tnw_salesforce_%s_notes_send_after', $this->_magentoEntityName), array(
            "data" => $this->_cache['notesToUpsert'],
            "result" => $this->_cache['responses']['notes']
        ));

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Push Notes: End----------');
        return $this;
    }

    /**
     * @param array $chunk
     * push Notes chunk into Salesforce
     */
    protected function _pushNotes($chunk = array())
    {
        try {
            $results = $this->_mySforceConnection->upsert("Id", array_values($chunk), 'Note');
        } catch (Exception $e) {
            $_response = $this->_buildErrorResponse($e->getMessage());
            foreach($chunk as $_object) {
                $_orderSalesforceId = $_object->ParentId;
                $_entityNum = array_search($_orderSalesforceId, $this->_cache['upserted'.$this->getManyParentEntityType()]);

                $this->_cache['responses']['notes'][$_entityNum]['subObj'][] = $_response;
            }

            $results = array();
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError('CRITICAL: Push of Notes to SalesForce failed' . $e->getMessage());
        }

        $_noteIds = array_keys($chunk);
        foreach ($results as $_key => $_result) {
            $_noteId = $_noteIds[$_key];
            $_orderSalesforceId = $this->_cache['notesToUpsert'][$_noteId]->ParentId;
            $_entityNum = array_search($_orderSalesforceId, $this->_cache['upserted' . $this->getManyParentEntityType()]);

            //Report Transaction
            $this->_cache['responses']['notes'][$_entityNum]['subObj'][] = $_result;

            if (!$_result->success) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError('ERROR: Note (id: ' . $_noteId . ') failed to upsert');
                $this->_processErrors($_result, sprintf('%sNote', $this->_magentoEntityName), $chunk[$_noteId]);
            }
            else {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Note (id: ' . $_noteId . ') upserted for '.$this->_magentoEntityName.' #' . $_entityNum . ')');

                $sql = sprintf('UPDATE `%s` SET salesforce_id = "%s" WHERE entity_id = "%s";',
                    $this->_notesTableName(), $_result->id, $_noteId);
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SQL: ' . $sql);
                Mage::helper('tnw_salesforce')->getDbConnection()->query($sql);
            }
        }
    }

    /**
     * @throws Exception
     * @return mixed
     */
    protected function _notesTableName()
    {
        throw new Exception(sprintf('Method "%s::%s" must be overridden before use', __CLASS__, __METHOD__));
    }
}