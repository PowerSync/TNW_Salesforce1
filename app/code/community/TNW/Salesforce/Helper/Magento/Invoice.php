<?php

class TNW_Salesforce_Helper_Magento_Invoice extends TNW_Salesforce_Helper_Magento_Abstract
{
    /**
     * @param null $object
     * @return mixed
     */
    public function syncFromSalesforce($object = null)
    {
        $this->_prepare();

        $_isNew = false;

        $_salesforceId = (property_exists($object, "Id") && $object->Id)
            ? $object->Id : null;

        if (!$_salesforceId) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR upserting invoice into Magento: tnw_fulfilment__OrderShipmentItem__c ID is missing");
            $this->_addError('Could not upsert Invoice into Magento, salesforce ID is missing', 'SALESFORCE_ID_IS_MISSING');
            return false;
        }

        $_magentoId = (property_exists($object, TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_FULFILMENT . "Magento_ID__c") && $object->{TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_FULFILMENT . 'Magento_ID__c'})
            ? $object->{TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_FULFILMENT . 'Magento_ID__c'} : null;

        // Lookup product by Magento Id
        if ($_magentoId) {
            //Test if user exists
            $sql = "SELECT entity_id  FROM `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_invoice') . "` WHERE entity_id = '" . $_magentoId . "'";
            $row = $this->_write->query($sql)->fetch();
            if (!$row) {
                // Magento ID exists in Salesforce, user must have been deleted. Will re-create with the same ID
                $_isNew = true;
            }
        }

        if ($_magentoId && !$_isNew) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Product loaded using Magento ID: " . $_magentoId);
        } else {
            // No Magento ID
            if ($_salesforceId) {
                // Try to find the user by SF Id
                $sql = "SELECT entity_id FROM `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_invoice') . "` WHERE salesforce_id = '" . $_salesforceId . "'";
                $row = $this->_write->query($sql)->fetch();
                $_magentoId = ($row) ? $row['entity_id'] : null;
            }

            if ($_magentoId) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Invoice #" . $_magentoId . " Loaded by using Salesforce ID: " . $_salesforceId);
            } else {
                //Brand new invoice
                $_isNew = true;
            }
        }

        return $this->_updateMagento($object, $_magentoId, $_salesforceId, $_isNew);
    }

    protected function _updateMagento($object, $_magentoId, $_salesforceId, $_isNew)
    {

    }
}