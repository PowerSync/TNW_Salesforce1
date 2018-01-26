<?php

class TNW_Salesforce_Model_Api2_Import extends Mage_Api2_Model_Resource
{
    /**
     * @param array $filteredData
     * @return string|void
     * @throws Mage_Api2_Exception
     */
    public function _create(array $filteredData)
    {
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("========== Sync from Salesforce ==========");
        if (!Mage::helper('tnw_salesforce')->isEnabled()) {
            $this->_critical("Extension is disabled or not working", Mage_Api2_Model_Server::HTTP_METHOD_NOT_ALLOWED);
        }

        try {
            $filteredData['sf'] = urldecode($filteredData['sf']);
            $objects = Zend_Json::decode($filteredData['sf'], Zend_Json::TYPE_OBJECT);

            $formatJson = defined('JSON_PRETTY_PRINT')
                ? json_encode($objects, JSON_PRETTY_PRINT)
                : $filteredData['sf'];

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace("JSON: \n{$formatJson}");
        } catch (Zend_Json_Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace("JSON: \n{$filteredData['sf']}");

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveError("Error: {$e->getMessage()}");

            $this->_critical($e->getMessage(), Mage_Api2_Model_Server::HTTP_BAD_REQUEST);
        }

        // Check if integration is enabled in Magento
        if (count($objects) == 1) {
            // Process Realtime
            $object = reset($objects);
            try {
                /** @var TNW_Salesforce_Model_Import $import */
                $import = Mage::getModel('tnw_salesforce/import');
                $_association = $import->setObject($object)
                    ->process();

                $import->sendMagentoIdToSalesforce($_association);
            } catch (Exception $e) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError("Error: {$e->getMessage()}");

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace("Failed to upsert a {$object->attributes->type} #{$object->Id}, please re-save or re-import it manually");

                $this->_critical($e->getMessage(), Mage_Api2_Model_Server::HTTP_INTERNAL_ERROR);
            }
        }
        else {
            // Add to Queue
            /* Save into a db */
            try {
                foreach ($objects as $object) {
                    Mage::getModel('tnw_salesforce/import')
                        ->importObject($object)
                        ->save();
                }

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace("Import JSON accepted, pending Import");
            } catch (Exception $e) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError("Error: {$e->getMessage()}");

                $this->_critical("Could not process JSON, Error: {$e->getMessage()}", Mage_Api2_Model_Server::HTTP_INTERNAL_ERROR);
            }
        }

        $this->_successMessage('Resource created successful.', Mage_Api2_Model_Server::HTTP_OK);
    }
}