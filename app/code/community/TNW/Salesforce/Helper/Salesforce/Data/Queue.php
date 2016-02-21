<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Helper_Salesforce_Data_Queue extends TNW_Salesforce_Helper_Salesforce_Data
{
    public function __construct()
    {
        parent::__construct();
    }

    public function getAllQueues() {
        try {
            $_useCache = Mage::app()->useCache('tnw_salesforce');
            $cache = Mage::app()->getCache();

            if (
                $_useCache
                && $cache->load("tnw_salesforce_queue_list")
            ) {
                return unserialize($cache->load("tnw_salesforce_queue_list"));
            }

            $_data = NULL;
            if (Mage::helper('tnw_salesforce')->isWorking()) {
                $query = "SELECT Id, Name, OwnerId, Type FROM Group WHERE type = 'Queue'";
                if (!is_object($this->getClient())) {
                    return $this->_noConnectionArray;
                }
                $result = $this->getClient()->query(($query));
                unset($query);

                if ($result && is_object($result)) {
                    Mage::helper('tnw_salesforce')->log("Extracted queues from Salesforce!");
                    $_data = array();
                    if (!empty($result->records)) {
                        foreach ($result->records as $_queue) {
                            $_data[] = $_queue->Id;
                        }
                    }
                    if ($_useCache) {
                        $cache->save(serialize($_data), "tnw_salesforce_queue_list", array("TNW_SALESFORCE"));
                    }
                }
            }
            return $_data;
        } catch (Exception $e) {
            Mage::helper('tnw_salesforce')->log("Error: " . $e->getMessage(), 1, "sf-errors");
            unset($e);
            return false;
        }
    }
}