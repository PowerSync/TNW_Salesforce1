<?php

class TNW_Salesforce_Model_Imports_Bulk
{
    /**
     * @var TNW_Salesforce_Model_Imports
     */
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

    /**
     * @param string $message
     *
     * @return $this
     */
    protected function log($message)
    {
        Mage::helper('tnw_salesforce')->log($message);

        return $this;
    }

    public function process()
    {
        $this->preStart();

        $collection = $this->_queue->getCollection()->getOnlyPending();

        $queueCount = count($collection);
        if ($queueCount > 0) {
            $this->log("---- Start Magento Upsert ----");
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
                    $this->log("Queue unserialize Error: " . $e->getMessage());
                    $objects = NULL;
                    unset($e);
                }

                if (!is_array($objects)) {
                    $this->log("Error: Failed to unserialize data from the queue (data is corrupted)")
                        ->log("Deleting queue #" . $mId);
                    $queue->delete();
                    continue;
                }
                $queueStatus = true;

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
                                    $this->log("Synchronizing: " . $object->attributes->type);
                                    Mage::helper('tnw_salesforce/magento_customers')->process($object);
                                } else {
                                    $this->log("SKIPPING: Email is missing in Salesforce!");
                                }
                            } elseif ($object->attributes->type == Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject()) {
                                if (
                                    property_exists($object, Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . 'Website_ID__c')
                                    && !empty($object->{Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . 'Website_ID__c'})
                                    && property_exists($object, Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . 'Code__c')
                                    && !empty($object->{Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . 'Code__c'})
                                ) {
                                    Mage::helper('tnw_salesforce/magento_websites')->process($object);
                                } else {
                                    $this->log("SKIPPING: Website ID and/or Code is missing in Salesforce!");
                                }
                            } elseif ($object->attributes->type == "Product2") {
                                if ($object->ProductCode) {
                                    Mage::helper('tnw_salesforce/magento_products')->process($object);
                                } else {
                                    $this->log("SKIPPING: ProductCode is missing in Salesforce!");
                                }
                            }
                            /* Reset session for further insertion */
                            Mage::getSingleton('core/session')->setFromSalesForce(false);
                        } catch (Exception $e) {
                            $this->log("Error: " . $e->getMessage());
                            $this->log("Failed to upsert a " . $object->attributes->type . " #" . $object->Id . ", please re-save or re-import it manually");
                            $queueStatus = false;
                            unset($e);
                        }
                    } else {
                        // 'attributes' or 'type' was not available in the object, hack?
                        $this->log("Invalid Salesforce object format, or type is not supported");
                    }
                    unset($object);
                }
                if ($queueStatus) {
                    $queue->delete();
                }
                unset($queue);
                set_time_limit(30); //Reset Script execution time limit
            }

            $this->log("---- End Magento Upsert ----");
        } elseif ($queueCount > 1) {
            $this->log("Too many items in the queue, need manual processing");
        } else {
            // Nothing in the queue
        }
        return true;
    }
}