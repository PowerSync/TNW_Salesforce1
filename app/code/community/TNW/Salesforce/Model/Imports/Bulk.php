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
                    try {
                        $this->importObject($object);
                    } catch (Exception $e) {
                        $this->log("Error: " . $e->getMessage());
                        $this->log("Failed to upsert a " . $this->getObjectType($object)
                            . " #" . $object->Id . ", please re-save or re-import it manually");
                        $queueStatus = false;
                    }
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
        }
        return true;
    }

    /**
     * @param stdClass $object
     *
     * @return bool Queue status
     */
    protected function importObject($object)
    {
        if (!$this->getObjectType($object)) {
            $this->log("Invalid Salesforce object format, or type is not supported");
            return;
        }

        /* Safer to set the session at this level */
        Mage::getSingleton('core/session')->setFromSalesForce(true);
        // Call proper Magento upsert method
        switch ($this->getObjectType($object)) {
            case 'Account': //for account if personal account is enabled
                if ($object->IsPersonAccount != 1) {
                    break;
                }
            case 'Contact': //or for contact
                if ($object->Email || (property_exists($object, 'IsPersonAccount')
                        && $object->IsPersonAccount == 1 && $object->PersonEmail)
                ) {
                    $this->log('Synchronizing: ' . $this->getObjectType($object));
                    Mage::helper('tnw_salesforce/magento_customers')->process($object);
                } else {
                    $this->log('SKIPPING: Email is missing in Salesforce!');
                }
                break;
            case Mage::helper('tnw_salesforce/config')->getMagentoWebsiteField():
                $prefix = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix();
                if (property_exists($object, $prefix . 'Website_ID__c')
                    && !empty($object->{$prefix . 'Website_ID__c'})
                    && property_exists($object, $prefix . 'Code__c')
                    && !empty($object->{$prefix . 'Code__c'})
                ) {
                    Mage::helper('tnw_salesforce/magento_websites')->process($object);
                } else {
                    $this->log('SKIPPING: Website ID and/or Code is missing in Salesforce!');
                }
                break;
            case 'Product2':
                if ($object->ProductCode) {
                    Mage::helper('tnw_salesforce/magento_products')->process($object);
                } else {
                    $this->log('SKIPPING: ProductCode is missing in Salesforce!');
                }
                break;
        }
        /* Reset session for further insertion */
        Mage::getSingleton('core/session')->setFromSalesForce(false);
    }

    /**
     * @param stdClass $object
     * @return string
     */
    protected function getObjectType($object)
    {
        return property_exists($object, "attributes") && property_exists($object->attributes, "type")
            ? (string)$object->attributes->type : '';
    }
}