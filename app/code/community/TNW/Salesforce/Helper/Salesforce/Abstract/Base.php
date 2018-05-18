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
     * @var string
     */
    protected $_mappingEntityName = '';

    /**
     * @var string
     */
    protected $_mappingEntityItemName = '';

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
     * @comment cache keys
     * @var string
     */
    protected $_ucParentEntityType = '';
    protected $_manyParentEntityType = '';
    protected $_itemsField = '';

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
            $cachePrefix = $this->_generateKeyPrefixEntityCache($_entity);
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

    /**
     * @param $_entity
     * @return mixed
     * @throws Exception
     */
    protected function _generateKeyPrefixEntityCache($_entity)
    {
        return $this->_getEntityNumber($_entity);
    }

    /**
     * @param $cachePrefix
     * @return string
     */
    protected function _generateKeyEntityCache($cachePrefix)
    {
        return sprintf('%s_cached_%s', $this->_magentoEntityName, (string)$cachePrefix);
    }

    /**
     * @param $cachePrefix
     * @return $this
     */
    public function unsetEntityCache($cachePrefix)
    {
        $entityRegistryKey = $this->_generateKeyEntityCache($cachePrefix);
        Mage::unregister($entityRegistryKey);

        return $this;
    }

    /**
     * @param $_entity
     * @return $this
     */
    public function setEntityCache($_entity)
    {
        $cachePrefix       = $this->_generateKeyPrefixEntityCache($_entity);
        $entityRegistryKey = $this->_generateKeyEntityCache($cachePrefix);

        Mage::unregister($entityRegistryKey);
        Mage::register($entityRegistryKey, $_entity);

        return $this;
    }

    /**
     * @param $cachePrefix
     * @return mixed
     */
    public function getEntityCache($cachePrefix)
    {
        $entityRegistryKey = $this->_generateKeyEntityCache($cachePrefix);
        return Mage::registry($entityRegistryKey);
    }

    /**
     * @param $cachePrefix
     * @return bool
     */
    public function issetEntityCache($cachePrefix)
    {
        return (bool)$this->getEntityCache($cachePrefix);
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
     * @param array $_ids
     * @param bool|false $_isCron
     * @return bool
     */
    public function massAdd($_ids = array(), $_isCron = false)
    {
        $_ids           = !is_array($_ids) ? array($_ids) : $_ids;
        $this->_isCron  = $_isCron;

        if (empty($_ids)) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Entity Id is not specified, don't know what to synchronize!");
            return false;
        }

        $this->_skippedEntity = array();
        try {
            $this->_massAddBefore($_ids);

            foreach ($_ids as $_id) {

                if (!empty($this->_skippedEntity[$_id])) {
                    continue;
                }

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

            $this->resetSkippedEntity($this->_skippedEntity);

            if (empty($this->_cache[self::CACHE_KEY_ENTITIES_UPDATING])) {
                return false;
            }

            $this->_massAddAfter();
            $this->resetEntity(array_diff($_ids, $this->_skippedEntity));

            return !empty($this->_cache[self::CACHE_KEY_ENTITIES_UPDATING]);
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("CRITICAL: " . $e->getMessage());
            return false;
        }
    }

    /**
     * @param array $_ids
     */
    protected function _massAddBefore($_ids)
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
        throw new Exception(sprintf('Method "%s" must be overridden before use', __METHOD__));
    }

    /**
     *
     */
    protected function _massAddAfter()
    {
        return;
    }

    /**
     * @param array $skippedIds
     */
    protected function resetSkippedEntity(array $skippedIds)
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

        $resource    = $this->_modelEntity()->getResource();
        $mainTable   = $resource->getMainTable();
        $idFieldName = $resource->getIdFieldName();
        $sql = "UPDATE `$mainTable` SET salesforce_id = NULL, sf_insync = 0 WHERE $idFieldName IN (" . join(',', $ids) . ");";
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
            $this->_process($type);
            $this->_afterProcess();

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace(sprintf("================= %s SYNC: END =================", $_syncType));
            return true;
        }
        catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("CRITICAL: " . $e->getMessage());
            return false;
        }
    }

    /**
     * @return bool
     */
    public function reset()
    {
        parent::reset();

        // Clean order cache
        if (!empty($this->_cache[self::CACHE_KEY_ENTITIES_UPDATING])) {
            foreach ($this->_cache[self::CACHE_KEY_ENTITIES_UPDATING] as $_orderNumber) {
                $this->unsetEntityCache($_orderNumber);
            }
        }

        $this->_cache = array(
            self::CACHE_KEY_ENTITIES_UPDATING => array(),
        );

        return $this->check();
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
        set_time_limit(1000);

        if ($type == 'full') {
            $this->_prepareRemaining();

            $this->_pushRemainingEntityData();

            $this->clearMemory();
        }

        $this->_onComplete();
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

        if ($this->isNotesEnabled()) {
            $this->_prepareNotes();
        }
    }

    /**
     * @return bool
     */
    protected function isNotesEnabled()
    {
        return Mage::helper('tnw_salesforce')->isOrderNotesEnabled();
    }

    protected function _prepareEntity()
    {
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace(sprintf('----------%s Preparation: Start----------', $this->getUcParentEntityType()));
        foreach ($this->_cache[self::CACHE_KEY_ENTITIES_UPDATING] as $_key => $_entityNumber)
        {
            if (!$this->_checkPrepareEntityBefore($_key)) {
                continue;
            }

            $this->_obj = new stdClass();
            $this->_setEntityInfo($this->getEntityCache($_entityNumber), $_key);

            if (!$this->_checkPrepareEntityAfter($_key)) {
                continue;
            }

            $this->_cache[sprintf('%sToUpsert', strtolower($this->getManyParentEntityType()))][$_entityNumber] = $this->_obj;
        }

        $this->_prepareEntityAfter();
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

    protected function _prepareEntityAfter()
    {
        return;
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
     * @deprecated
     */
    public function prepareNotes()
    {
        $this->_prepareNotes();
    }

    /**
     * @param $_entity
     * @throws Exception
     * @return array
     */
    protected function _getEntityNotesCollection($_entity)
    {
        throw new Exception(sprintf('Method "%s" must be overridden before use', __METHOD__));
    }

    /**
     * @param Mage_Sales_Model_Order_Status_History[] $notes
     * @return $this
     * @deprecated
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

            $parentId = $this->_getNotesParentSalesforceId($_note);
            if (empty($parentId)) {
                continue;
            }

            $comment      = utf8_encode($_note->getData('comment'));

            $_obj = new stdClass();
            $_obj->ParentId   = $parentId;
            $_obj->IsPrivate  = 0;
            $_obj->Body       = $comment;
            $_obj->Title      = (strlen($comment) > 75)
                ? sprintf('%s...', mb_substr($comment, 0, 75))
                : $comment;

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace(sprintf("Note Object:\n%s", print_r($_obj, true)));

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
        return !$_note->getData($this->_notesTableFieldName()) && $_note->getData('comment');
    }

    protected function _getNotesParentSalesforceId($notes)
    {
        throw new Exception(sprintf('Method "%s" must be overridden before use', __METHOD__));
    }

    /**
     * @param $_entity
     * @return mixed
     */
    public function getEntityNumber($_entity)
    {
        return $this->_getEntityNumber($_entity);
    }

    /**
     * @param $_entity
     * @return mixed
     * @throws Exception
     */
    protected function _getEntityNumber($_entity)
    {
        throw new Exception(sprintf('Method "%s" must be overridden before use', __METHOD__));
    }

    /**
     * @param $_entity
     * @return mixed
     * @throws Exception
     */
    protected function _setEntityInfo($_entity, $key = null)
    {
        $_entityId = $this->_getEntitySalesforceId($_entity);
        if (!empty($_entityId)) {
            $this->_obj->Id = $_entityId;
        }

        /** @var tnw_salesforce_model_mysql4_mapping_collection $_mappingCollection */
        $_mappingCollection = Mage::getResourceModel('tnw_salesforce/mapping_collection')
            ->addObjectToFilter($this->_mappingEntityName)
            ->addFilterTypeMS(property_exists($this->_obj, 'Id') && $this->_obj->Id)
            ->firstSystem();

        $_objectMappings = array();
        foreach (array_unique($_mappingCollection->walk('getLocalFieldType')) as $_type) {
            $_objectMappings[$_type] = $this->_getObjectByEntityType($_entity, $_type);
        }

        /** @var tnw_salesforce_model_mapping $_mapping */
        foreach ($_mappingCollection as $_mapping) {
            $this->_obj->{$_mapping->getSfField()} = $_mapping->getValue(array_filter($_objectMappings), $this->_obj);
        }

        // Unset attribute
        foreach ($this->_obj as $_key => $_value) {
            if (null !== $_value) {
                continue;
            }

            unset($this->_obj->{$_key});
        }

        $this->_prepareEntityObjCustom($_entity, $key);
    }

    /**
     * @param $_entity
     * @return mixed
     * @throws Exception
     */
    protected function _getEntitySalesforceId($_entity)
    {
        $_entityNumber = $this->_getEntityNumber($_entity);
        $_lookupKey    = sprintf('%sLookup', $this->_salesforceEntityName);

        if (!isset($this->_cache[$_lookupKey][$_entityNumber])) {
            return null;
        }

        return $this->_cache[$_lookupKey][$_entityNumber]->Id;
    }

    /**
     * @param $_entity
     * @param $key
     */
    protected function _prepareEntityObjCustom($_entity, $key)
    {
        return;
    }

    /**
     * @param $_entity
     * @param $type string
     * @return mixed
     */
    protected function _getObjectByEntityType($_entity, $type)
    {
        return null;
    }

    /**
     * @param $_entity
     * @param $type string
     * @return mixed
     */
    public function getObjectByEntityType($_entity, $type)
    {
        return $this->_getObjectByEntityType($_entity, $type);
    }

    /**
     * @param array $chunk
     * @deprecated
     */
    protected function _pushEntityItems($chunk = array())
    {
        throw new Exception(sprintf('Method "_pushItems" must be overridden before use'));
    }

    /**
     * @return $this
     */
    protected function _pushItems()
    {
        $itemKey = sprintf('%sToUpsert', lcfirst($this->getItemsField()));
        if (empty($this->_cache[$itemKey])) {
            return $this;
        }

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Push Items: Start----------');

        Mage::dispatchEvent(sprintf('tnw_salesforce_%s_products_send_before', $this->_magentoEntityName), array('data' => $this->_cache[$itemKey]));
        foreach (array_chunk($this->_cache[$itemKey], TNW_Salesforce_Helper_Data::BASE_UPDATE_LIMIT, true) as $_itemsToPush) {
            $this->_pushItemsChunk($_itemsToPush);
        }

        Mage::dispatchEvent(sprintf('tnw_salesforce_%s_products_send_after', $this->_magentoEntityName), array(
            'data' => $this->_cache[$itemKey],
            'result' => $this->_cache['responses'][lcfirst($this->getItemsField())]
        ));

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Push Items: End----------');
        return $this;
    }

    /**
     * @param array $chunk
     */
    protected function _pushItemsChunk(array $chunk)
    {
        $this->_pushEntityItems($chunk);
    }

    /**
     * @throws Exception
     */
    protected function _pushEntity()
    {
        throw new Exception(sprintf('Method "%s" must be overridden before use', __METHOD__));
    }

    /**
     * Prepare Order items object(s) for upsert
     */
    protected function _prepareEntityItems()
    {
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace(sprintf('----------Prepare %s items: Start----------', $this->_magentoEntityName));
        foreach ($this->_cache[self::CACHE_KEY_ENTITIES_UPDATING] as $_key => $_entityNumber) {
            if (in_array($_entityNumber, $this->_cache[sprintf('failed%s', $this->getManyParentEntityType())])) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace(sprintf('%s (%s): Skipping, issues with upserting an %s!',
                        strtoupper($this->_magentoEntityName), $_entityNumber, $this->_salesforceEntityName));

                continue;
            }

            if (!$this->_checkPrepareEntityItem($_key)) {
                continue;
            }

            $_entity = $this->_loadEntityByCache($_key, $_entityNumber);
            foreach ($this->getItems($_entity) as $_entityItem) {
                $this->_prepareEntityItemObj($_entity, $_entityItem);
            }
        }

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace(sprintf('----------Prepare %s items: End----------', $this->_magentoEntityName));
    }

    protected function _prepareEntityItemsBefore()
    {
        return;
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
     * @comment return entity items
     * @param $_entity Mage_Core_Model_Abstract
     * @return mixed
     * @throws Exception
     */
    public function getItems($_entity)
    {
        throw new Exception(sprintf('Method "%s" must be overridden before use', __METHOD__));
    }

    /**
     * @param $_item
     * @return mixed
     * @throws Exception
     */
    public function getEntityByItem($_item)
    {
        return call_user_func(array($_item, sprintf('get%s', ucfirst($this->_magentoEntityName))));
    }

    /**
     * @param $_entity
     * @param $_entityItem
     * @return mixed
     * @throws Exception
     */
    protected function _prepareEntityItemObj($_entity, $_entityItem)
    {
        $this->_obj = new stdClass();
        $_entityItemId = $this->_getEntityItemSalesforceId($_entityItem);
        if (!empty($_entityItemId)) {
            $this->_obj->Id = $_entityItemId;
        }

        /** @var tnw_salesforce_model_mysql4_mapping_collection $_mappingCollection */
        $_mappingCollection = Mage::getResourceModel('tnw_salesforce/mapping_collection')
            ->addObjectToFilter($this->_mappingEntityItemName)
            ->addFilterTypeMS(property_exists($this->_obj, 'Id') && $this->_obj->Id)
            ->firstSystem();

        $_objectMappings = array();
        foreach (array_unique($_mappingCollection->walk('getLocalFieldType')) as $_type) {
            $_objectMappings[$_type] = $this->_getObjectByEntityItemType($_entityItem, $_type);
        }

        /** @var tnw_salesforce_model_mapping $_mapping */
        foreach ($_mappingCollection as $_mapping) {
            $this->_obj->{$_mapping->getSfField()} = $_mapping->getValue(array_filter($_objectMappings), $this->_obj);
        }

        // Unset attribute
        foreach ($this->_obj as $_key => $_value) {
            if (null !== $_value) {
                continue;
            }

            unset($this->_obj->{$_key});
        }

        $this->_prepareEntityItemObjCustom($_entityItem);
    }

    /**
     * @param $_entityItem
     * @throws Exception
     */
    protected function _getEntityItemSalesforceId($_entityItem)
    {
        throw new Exception(sprintf('Method "%s" must be overridden before use', __METHOD__));
    }

    /**
     * @param $_entityItem
     * @param $_type
     * @return null
     */
    protected function _getObjectByEntityItemType($_entityItem, $_type)
    {
        return null;
    }

    /**
     * @param $_entityItem
     * @param $_type
     * @return null
     */
    public function getObjectByEntityItemType($_entityItem, $_type)
    {
        return $this->_getObjectByEntityItemType($_entityItem, $_type);
    }

    /**
     * @param $_entityItem
     */
    protected function _prepareEntityItemObjCustom($_entityItem)
    {
        return;
    }

    /**
     *
     */
    protected function _pushRemainingEntityData()
    {
        // Push Items Data
        $this->_pushItems();

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
            $results = $this->getClient()->upsert("Id", array_values($chunk), 'Note');
        } catch (Exception $e) {
            $results = array_fill(0, count($chunk),
                $this->_buildErrorResponse($e->getMessage()));

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

                $sql = sprintf('UPDATE `%s` SET %s = "%s" WHERE entity_id = "%s";',
                    $this->_notesTableName(), $this->_notesTableFieldName(), $_result->id, $_noteId);
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SQL: ' . $sql);
                Mage::helper('tnw_salesforce')->getDbConnection()->query($sql);
            }
        }
    }

    /**
     * @return string
     */
    protected function _notesTableFieldName()
    {
        return 'salesforce_id';
    }

    /**
     * @return string
     */
    protected function _notesTableName()
    {
        throw new Exception(sprintf('Method "%s" must be overridden before use', __METHOD__));
    }

    /**
     *
     */
    public function countSuccessEntityUpsert()
    {
        $upsertStatus = array();
        foreach ($this->getSyncResults() as $_responses) {
            foreach ($_responses as $entityNumber => $_tmpResponse) {
                $__response = array_key_exists('subObj', $_tmpResponse)
                    ? $_tmpResponse['subObj'] : array($_tmpResponse);

                foreach ($__response as $_response) {
                    if (!isset($upsertStatus[$entityNumber])) {
                        $upsertStatus[$entityNumber] = true;
                    }

                    $_response = (array)$_response;
                    $upsertStatus[$entityNumber] = $upsertStatus[$entityNumber] && isset($_response['success']) &&
                        (strcasecmp($_response['success'], 'true') === 0 || $_response['success'] === true);
                }
            }
        }

        return count(array_filter($upsertStatus));
    }
}
