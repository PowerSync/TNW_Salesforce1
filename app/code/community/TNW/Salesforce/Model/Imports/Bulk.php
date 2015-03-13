<?php

class TNW_Salesforce_Model_Imports_Bulk
{
    protected $_queue;

    protected function preStart()
    {
        if (!$this->_queue) {
            $this->_queue = Mage::getModel('tnw_salesforce/imports');
        }
    }

    public function __destruct()
    {
        foreach ($this as $index => $value) unset($this->$index);
    }

    public function process()
    {
        $this->preStart();

        $collection = $this->_queue->getCollection()->getOnlyPending();

        $queueCount = count($collection);
        #if ($queueCount < 0) {
        if ($queueCount > 0) {
            Mage::helper('tnw_salesforce')->log("---- Start Magento Upsert ----");
            //set_time_limit(60); //Reset Script execution time limit
            $count = 0;
            foreach ($collection as $_map) {
                $count++;
                if ($count == 5) {
                    break;
                } //Don't hog up the DB, do 4 queued items at a time = up to 1000 records.
                $mId = $_map->getId();
                $queue = $this->_queue->load($mId);
                // Check if already in progress
                if (!is_null($queue->getIsProcessing())) {
                    continue;
                }
                $queue
                    ->setForceInsertMode(false)
                    ->setIsProcessing(1)
                    ->save(); //Update status to prevent duplication
                try {
                    $json = unserialize($_map->getJson());
                    $objects = json_decode($json);;
                } catch (Exception $e) {
                    Mage::helper('tnw_salesforce')->log("Queue unserialize Error: " . $e->getMessage());
                    $objects = NULL;
                    unset($e);
                }

                if (!is_array($objects)) {
                    Mage::helper('tnw_salesforce')->log("Error: Failed to unserialize data from the queue (data is corrupted)");
                    Mage::helper('tnw_salesforce')->log("Deleting queue #" . $mId);
                    $queue->delete();
                    continue;
                }
                $queueStatus = true;
                //$customers = array();

                foreach ($objects as $object) {
                    // Each object should have 'attributes' property and 'type' inside 'attributes'
                    if (property_exists($object, "attributes") && property_exists($object->attributes, "type")) {
                        try {
                            /* Safer to set the session at this level */
                            Mage::getSingleton('core/session')->setFromSalesForce(true);
                            // Call proper Magento upsert method
                            if (
                                $object->attributes->type == "Contact"
                                || ($object->IsPersonAccount == 1 && $object->attributes->type == "Account")
                            ) {
                                if ($object->Email || (property_exists($object, 'IsPersonAccount') && $object->IsPersonAccount == 1 && $object->PersonEmail)) {
                                    Mage::helper('tnw_salesforce')->log("Synchronizing: " . $object->attributes->type);
                                    //$entity[] = Mage::helper('tnw_salesforce/customer')->contactProcess($object);
                                    Mage::helper('tnw_salesforce/magento_customers')->process($object);
                                } else {
                                    Mage::helper('tnw_salesforce')->log("SKIPPING: Email is missing in Salesforce!");
                                }
                            } elseif ($object->attributes->type == TNW_Salesforce_Helper_Salesforce::CONNECTOR_ENTERPRISE_PERFIX . "Website__c") {
                                if (
                                    property_exists($object, TNW_Salesforce_Helper_Salesforce::CONNECTOR_ENTERPRISE_PERFIX . 'Website_ID__c')
                                    && !empty($object->{TNW_Salesforce_Helper_Salesforce::CONNECTOR_ENTERPRISE_PERFIX . 'Website_ID__c'})
                                    && property_exists($object, TNW_Salesforce_Helper_Salesforce::CONNECTOR_ENTERPRISE_PERFIX . 'Code__c')
                                    && !empty($object->{TNW_Salesforce_Helper_Salesforce::CONNECTOR_ENTERPRISE_PERFIX . 'Code__c'})
                                ) {
                                    Mage::helper('tnw_salesforce/magento_websites')->process($object);
                                } else {
                                    Mage::helper('tnw_salesforce')->log("SKIPPING: Website ID and/or Code is missing in Salesforce!");
                                }
                            } elseif ($object->attributes->type == "Product2") {
                                if ($object->ProductCode) {
                                    Mage::helper('tnw_salesforce/magento_products')->process($object);
                                } else {
                                    Mage::helper('tnw_salesforce')->log("SKIPPING: ProductCode is missing in Salesforce!");
                                }
                            }
                            /* Reset session for further insertion */
                            Mage::getSingleton('core/session')->setFromSalesForce(false);
                        } catch (Exception $e) {
                            Mage::helper('tnw_salesforce')->log("Error: " . $e->getMessage());
                            Mage::helper('tnw_salesforce')->log("Failed to upsert a " . $object->attributes->type . " #" . $object->Id . ", please re-save or re-import it manually");
                            $queueStatus = false;
                            unset($e);
                        }
                    } else {
                        // 'attributes' or 'type' was not available in the object, hack?
                        Mage::helper('tnw_salesforce')->log("Invalid Salesforce object format, or type is not supported");
                    }
                    unset($object);
                }
                if ($queueStatus) {
                    //if (!empty($customers)) {
                    //    $queueStatus = Mage::helper('tnw_salesforce/customer')->updateContacts($customers);
                    //}
                    //if ($queueStatus) {
                        /* Only delete the queue if all records from the queue were accepted */
                        /* If PHP execution time is reached only fully processed queues will be removed */
                        $queue->delete();
                    //}

                    unset($customers, $queue);
                }
                set_time_limit(30); //Reset Script execution time limit
            }
            unset($collection);

            Mage::helper('tnw_salesforce')->log("---- End Magento Upsert ----");
        } elseif ($queueCount > 1) {
            Mage::helper('tnw_salesforce')->log("Too many items in the queue, need manual processing");
        } else {
            // Nothing in the queue
        }
        return true;
    }
}