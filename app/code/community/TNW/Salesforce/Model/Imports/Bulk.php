<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Imports_Bulk
{
    /**
     * @var TNW_Salesforce_Model_Import
     */
    protected $_queue;

    /**
     * @return TNW_Salesforce_Model_Import
     */
    protected function getModel()
    {
        return Mage::getModel('tnw_salesforce/import');
    }

    protected function preStart()
    {
        if (!$this->_queue) {
            $this->_queue = $this->getModel();
        }
    }

    /**
     * @param string $message
     *
     * @return $this
     */
    protected function log($message)
    {
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace($message);

        return $this;
    }

    public function process()
    {
        $this->preStart();

        $collection = $this->_queue->getCollection()->getOnlyPending();

        $queueCount = count($collection);
        if ($queueCount > 0) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("---- Start Magento Upsert ----");
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
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Queue unserialize Error: " . $e->getMessage());
                    $objects = NULL;
                    unset($e);
                }

                if (!is_array($objects)) {
                    Mage::getModel('tnw_salesforce/tool_log')
                        ->saveError("Error: Failed to unserialize data from the queue (data is corrupted)");
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Deleting queue #" . $mId);
                    $queue->delete();
                    continue;
                }
                $queueStatus = true;

                foreach ($objects as $object) {
                    try {
                        $this->getModel()->setObject($object)->process();
                    } catch (Exception $e) {
                        Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Error: " . $e->getMessage());
                        $objectType = property_exists($object, "attributes")
                            && property_exists($object->attributes, "type")
                            ? (string)$object->attributes->type : '';
                        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Failed to upsert a " . $objectType
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

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("---- End Magento Upsert ----");
        } elseif ($queueCount > 1) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Too many items in the queue, need manual processing");
        }
        return true;
    }
}