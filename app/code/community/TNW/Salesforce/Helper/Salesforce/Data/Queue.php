<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Helper_Salesforce_Data_Queue extends TNW_Salesforce_Helper_Salesforce_Data
{
    /**
     * @return array
     */
    public function getAllQueues()
    {
        try {
            $_data = array();
            if (Mage::helper('tnw_salesforce')->isWorking()) {
                $query = "SELECT Id, Name, OwnerId, Type FROM Group WHERE type = 'Queue'";

                try {
                    $this->getClient();
                } catch (Exception $e) {
                    return $this->_noConnectionArray;
                }

                $result = $this->getClient()->query(($query));
                if ($result && is_object($result)) {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Extracted queues from Salesforce!");
                    if (!empty($result->records)) {
                        foreach ($result->records as $_queue) {
                            $_data[] = $_queue->Id;
                        }
                    }
                }
            }

            return $_data;
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveError("ERROR: " . $e->getMessage());

            return array();
        }
    }
}