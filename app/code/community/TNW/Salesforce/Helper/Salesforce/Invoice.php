<?php

class TNW_Salesforce_Helper_Salesforce_Invoice extends TNW_Salesforce_Helper_Salesforce_Abstract_Base
{
    /**
     * @comment magento entity alias "convert from"
     * @var string
     */
    protected $_magentoEntityName = 'invoice';

    /**
     * @comment salesforce entity alias "convert to"
     * @var string
     */
    protected $_salesforceEntityName = 'OrderInvoice';

    /**
     * @comment magento entity model alias
     * @var array
     */
    protected $_magentoEntityModel = 'sales/order_invoice';

    /**
     * @param $_entity Mage_Sales_Model_Order_Invoice
     * @return mixed
     */
    protected function _getEntityNumber($_entity)
    {
        return $_entity->getIncrementId();
    }

    /**
     * @param $_entity
     */
    protected function _prepareMassAddEntity($_entity)
    {
        // TODO: Implement _prepareAdditionalEntityData() method.
    }

    /**
     * @param $order
     * @return mixed
     */
    protected function _setEntityInfo($order)
    {
        // TODO: Implement _setEntityInfo() method.
    }

    /**
     * @return mixed
     */
    protected function _pushEntity()
    {
        $entityToUpsertKey = sprintf('%sToUpsert', strtolower($this->getManyParentEntityType()));
        if (empty($this->_cache[$entityToUpsertKey])) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('No Orders found queued for the synchronization!');
            return;
        }

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Order Push: Start----------');
        foreach ($this->_cache[$entityToUpsertKey] as $_opp) {
            foreach ($_opp as $_key => $_value) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace(sprintf('%s Object: %s = "%s"', $this->_salesforceEntityName, $_key, $_value));
            }

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("--------------------------");
        }

        $_keys = array_keys($this->_cache[$entityToUpsertKey]);

        try {
            Mage::dispatchEvent(sprintf('tnw_salesforce_%s_send_before', $this->_magentoEntityName),
                array("data" => $this->_cache[$entityToUpsertKey]));

            $results = $this->_mySforceConnection->upsert(
                'Id', array_values($this->_cache[$entityToUpsertKey]), TNW_Salesforce_Model_Config_Objects::ORDER_INVOICE_OBJECT);

            Mage::dispatchEvent(sprintf('tnw_salesforce_%s_send_after', $this->_magentoEntityName), array(
                "data" => $this->_cache[$entityToUpsertKey],
                "result" => $results
            ));
        }
        catch (Exception $e) {
            $_response = $this->_buildErrorResponse($e->getMessage());
            foreach ($_keys as $_id) {
                $this->_cache['responses'][strtolower($this->getManyParentEntityType())][$_id] = $_response;
            }

            $results = array();
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveError('CRITICAL: Push of an order to Salesforce failed' . $e->getMessage());
        }

        $_entityArray = array_flip($this->_cache['entitiesUpdating']);
        $_undeleteIds = array();
        if (!$results) {
            $results = array();
        }

        foreach ($results as $_key => $_result) {
            $_entityNum = $_keys[$_key];

            //Report Transaction
            $this->_cache['responses'][strtolower($this->getManyParentEntityType())][$_entityNum] = $_result;

            if (!$_result->success) {
                if ($_result->errors[0]->statusCode == "ENTITY_IS_DELETED") {
                    $_undeleteIds[] = $_entityNum;
                }

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError(sprintf('%s Failed: (%s: ' . $_entityNum . ')', $this->_salesforceEntityName, $this->_magentoEntityName));

                $this->_processErrors($_result, $this->_salesforceEntityName, $this->_cache[$entityToUpsertKey][$_entityNum]);
                $this->_cache[sprintf('failed%s', $this->getManyParentEntityType())][] = $_entityNum;
            }
            else {
                $sql = sprintf('UPDATE `%s` SET sf_insync = 1, salesforce_id = "%s" WHERE entity_id = %d;',
                    $this->_modelEntity()->getResource()->getMainTable(), $_result->id, $_entityArray[$_entityNum]);
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SQL: ' . $sql);
                Mage::helper('tnw_salesforce')->getDbConnection()->query($sql);

                $this->_cache[sprintf('upserted%s', $this->getManyParentEntityType())][$_entityNum] = $_result->id;
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace(sprintf('%s Upserted: %s' , $this->_salesforceEntityName, $_result->id));
            }
        }

        if (!empty($_undeleteIds)) {
            $_deleted = Mage::helper('tnw_salesforce/salesforce_data_invoice')
                ->lookup($_undeleteIds);

            $_toUndelete = array();
            foreach ($_deleted as $_object) {
                $_toUndelete[] = $_object->Id;
            }

            if (!empty($_toUndelete)) {
                $this->_mySforceConnection->undelete($_toUndelete);
            }
        }

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Order Push: End----------');
    }

    /**
     * @param $_entityNumber
     * @return mixed
     */
    protected function _prepareEntityItem($_entityNumber)
    {
        // TODO: Implement _prepareEntityItem() method.
    }

    /**
     * @param array $chunk
     * @return mixed
     */
    protected function _pushEntityItems($chunk = array())
    {
        $_orderNumbers = array_flip($this->_cache['upserted'.$this->getManyParentEntityType()]);
        $_chunkKeys    = array_keys($chunk);

        try {
            $results = $this->_mySforceConnection->upsert(
                'Id', array_values($chunk), TNW_Salesforce_Model_Config_Objects::ORDER_INVOICE_ITEM_OBJECT);
        } catch (Exception $e) {
            $_response = $this->_buildErrorResponse($e->getMessage());
            foreach ($chunk as $_object) {
                $this->_cache['responses']['invoiceItems'][] = $_response;
            }
            $results = array();
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError('CRITICAL: Push of Order Items to SalesForce failed' . $e->getMessage());
        }

        foreach ($results as $_key => $_result) {
            $_orderNum = $_orderNumbers[$this->_cache[sprintf('%sToUpsert', $this->getItemsField())][$_chunkKeys[$_key]]->InvoiceId];

            //Report Transaction
            $this->_cache['responses']['invoiceItems'][] = $_result;
            if (!$_result->success) {
                // Reset sync status
                $sql = sprintf('UPDATE `%s` SET sf_insync = 0 WHERE salesforce_id = "%s";',
                    $this->_modelEntity()->getResource()->getMainTable(), $this->_cache[sprintf('%sToUpsert', $this->getItemsField())][$_chunkKeys[$_key]]->InvoiceId);
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SQL: ' . $sql);
                Mage::helper('tnw_salesforce')->getDbConnection()->query($sql);

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError(sprintf('ERROR: One of the Cart Item for (%s: %s) failed to upsert.', $this->_magentoEntityName, $_orderNum));
                $this->_processErrors($_result, 'invoiceCart', $chunk[$_chunkKeys[$_key]]);
            }
            else {
                /*$_cartItemId = $_chunkKeys[$_key];
                if ($_cartItemId && strrpos($_cartItemId, 'cart_', -strlen($_cartItemId)) !== FALSE) {
                    $_sql = "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order_item') . "` SET salesforce_id = '" . $_result->id . "' WHERE item_id = '" . str_replace('cart_', '', $_cartItemId) . "';";
                    Mage::helper('tnw_salesforce')->getDbConnection()->query($_sql);
                }*/
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace(sprintf('Cart Item (id: %s) for (%s: %s) upserted.', $_result->id, $this->_magentoEntityName, $_orderNum));
            }
        }
    }
}