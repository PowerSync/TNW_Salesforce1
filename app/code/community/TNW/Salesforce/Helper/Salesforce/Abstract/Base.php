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
     * @comment cache keys
     * @var string
     */
    protected $_ucParentEntityType = '';
    protected $_manyParentEntityType = '';
    protected $_itemsField = '';

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
        if (is_null($cachePrefix)) {
            $_entity = $this->_loadEntity($_key);
            $cachePrefix = $this->_getEntityNumber($_entity);
        }

        $entityRegistryKey = sprintf('%s_cached_%s', $this->_magentoEntityName, (string)$cachePrefix);
        if (!is_null($_entity)) {
            Mage::unregister($entityRegistryKey);
        }

        // Generate cache
        if (!Mage::registry($entityRegistryKey)) {
            $_entity = is_null($_entity) ? $this->_loadEntity($_key) : $_entity;
            Mage::register($entityRegistryKey, $_entity);
        }

        // Get entity
        return Mage::registry($entityRegistryKey);
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

    public function massAdd($_ids = array(), $_isCron = false)
    {
        $_ids           = !is_array($_ids) ? array($_ids) : $_ids;
        $this->_isCron  = $_isCron;

        if (!$_ids) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Order Id is not specified, don't know what to synchronize!");
            return false;
        }

        // test sf api connection
        /** @var TNW_Salesforce_Model_Connection $_client */
        $_client = Mage::getSingleton('tnw_salesforce/connection');
        if (!$_client->tryWsdl() || !$_client->tryToConnect() || !$_client->tryToLogin()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR on sync orders, sf api connection failed");

            return true;
        }

        $_skippedEntity = array();
        try {
            // Clear Order ID
            $this->resetEntity($_ids);
            $this->_massAddBefore();

            foreach ($_ids as $_id) {
                $_entity        = $this->_loadEntityByCache($_id);
                $_entityNumber  = $this->_getEntityNumber($_entity);

                if (!$_entity->getId()) {
                    Mage::getSingleton('tnw_salesforce/tool_log')
                        ->saveError(sprintf('WARNING: Sync for %s #%s, %s could not be loaded!',
                            $this->_magentoEntityName, $_id, $this->_magentoEntityName));

                    $_skippedEntity[$_id] = $_id;
                    continue;
                }

                if (!$this->_checkMassAddEntity($_entity)) {
                    $_skippedEntity[$_entity->getId()] = $_entity->getId();
                    continue;
                }

                $this->_prepareMassAddEntity($_entity);

                // Associate order ID with order Number
                $this->_cache[self::CACHE_KEY_ENTITIES_UPDATING][$_id] = $_entityNumber;
            }

            $this->_massAddAfter();

            /**
             * all orders fails - return false otherwise return true
             */
            return (count($_skippedEntity) != count($_ids));
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
     */
    protected function _checkMassAddEntity($_entity)
    {
        return true;
    }

    /**
     * @param $_entity
     */
    abstract protected function _prepareMassAddEntity($_entity);

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

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("================ MASS SYNC: START ================");

            if (!is_array($this->_cache) || empty($this->_cache['entitiesUpdating'])) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError(sprintf("WARNING: Sync %s, cache is empty!", $this->getManyParentEntityType()));
                $this->_dumpObjectToLog($this->_cache, "Cache", true);
                return false;
            }

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

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("================= MASS SYNC: END =================");
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
            // Only sync notes for the order
            if (!(!$_note->getData('salesforce_id') && $_note->getData('comment'))) {
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

    protected function _getNotesParentSalesforceId($notes)
    {
        throw new Exception(sprintf('Method "%s::%s" must be overridden before use', __CLASS__, __METHOD__));
    }

    /**
     * @param $_entity
     * @return mixed
     */
    abstract protected function _getEntityNumber($_entity);

    /**
     * @param $order
     * @return mixed
     */
    abstract protected function _setEntityInfo($order);

    /**
     * @param array $chunk
     * @return mixed
     */
    abstract protected function _pushEntityItems($chunk = array());

    /**
     * @return mixed
     */
    abstract protected function _pushEntity();

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

            $this->_prepareEntityItem($_entityNumber);
        }

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace(sprintf('----------Prepare %s items: End----------', $this->_magentoEntityName));
    }

    protected function _prepareEntityItemsBefore()
    {
        return;
    }

    /**
     * @param $_entityNumber
     * @return mixed
     */
    abstract protected function _prepareEntityItem($_entityNumber);

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
                $this->_cache['responses']['notes'][] = $_response;
            }
            $results = array();
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError('CRITICAL: Push of Notes to SalesForce failed' . $e->getMessage());
        }

        $_noteIds = array_keys($chunk);
        foreach ($results as $_key => $_result) {
            $_noteId = $_noteIds[$_key];

            //Report Transaction
            $this->_cache['responses']['notes'][$_noteId] = $_result;

            if (!$_result->success) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError('ERROR: Note (id: ' . $_noteId . ') failed to upsert');
                $this->_processErrors($_result, 'orderNote', $chunk[$_noteId]);

            } else {
                $_orderSalesforceId = $this->_cache['notesToUpsert'][$_noteId]->ParentId;
                $_orderId = array_search($_orderSalesforceId, $this->_cache  ['upserted' . $this->getManyParentEntityType()]);

                $sql = "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order_status_history') . "` SET salesforce_id = '" . $_result->id . "' WHERE entity_id = '" . $_noteId . "';";
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Note (id: ' . $_noteId . ') upserted for order #' . $_orderId . ')');
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SQL: ' . $sql);
                Mage::helper('tnw_salesforce')->getDbConnection()->query($sql);
            }
        }
    }

    /**
     * @depricated Exists compatibility for
     * @comment call leads convertation method
     */
    protected function _convertLeads()
    {
        return Mage::helper('tnw_salesforce/salesforce_data_lead')
            ->setParent($this)->convertLeads($this->_magentoEntityName);
    }
}