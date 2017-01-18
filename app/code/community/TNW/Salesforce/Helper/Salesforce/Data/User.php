<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Helper_Salesforce_Data_User extends TNW_Salesforce_Helper_Salesforce_Data
{
    /**
     * @see https://developer.salesforce.com/docs/atlas.en-us.api.meta/api/sforce_api_calls_merge.htm
     * Limit defined by SF
     */
    const MERGE_LIMIT = 3;

    /**
     * Read from cache or pull from Salesforce Active users
     * Accept $sfUserId parameter and check if its in the array of active users
     * @param null $sfUserId
     * @return bool
     */
    protected function _isUserActive($sfUserId = NULL)
    {
        $activeUsers = $this->getActiveUsers();
        $result = (!empty($activeUsers)) ? in_array($sfUserId, $activeUsers) : false;
        if (!$result) {
            $result = $this->_isQueue($sfUserId);
        }

        return $result;
    }

    /**
     * @return array
     */
    public function getActiveUsers()
    {
        $activeUsers = $this->getStorage('tnw_salesforce_users');
        if (empty($activeUsers)) {
            $activeUsers = array_map(function ($user) {
                return $user['value'];
            }, Mage::helper('tnw_salesforce/salesforce_data')->getUsers());

            $this->setStorage($activeUsers, 'tnw_salesforce_users');
        }

        return $activeUsers;
    }

    /**
     * @comment Check is this object Salesforce Queue entity
     * @param null $sfUserId
     * @return bool
     */
    protected function _isQueue($sfUserId = NULL)
    {
        $queueList = $this->getStorage('tnw_salesforce_queue_list');
        if (empty($queueList)) {
            $queueList = Mage::helper('tnw_salesforce/salesforce_data_queue')->getAllQueues();
            $this->setStorage($queueList, 'tnw_salesforce_queue_list');
        }

        return in_array($sfUserId, $queueList);
    }

    /**
     * @comment Check is this object Salesforce Queue entity
     * @param null $sfUserId
     * @return bool
     */
    public function isQueue($sfUserId = NULL)
    {
        return $this->_isQueue($sfUserId);
    }

    /**
     * @comment check, is this user active
     * @param null $sfUserId
     * @return bool
     */
    public function isUserActive($sfUserId = NULL)
    {
        return $this->_isUserActive($sfUserId);
    }

    /**
     * set global cache data
     *
     * @param $cache
     * @return $this
     */
    public function setCache(&$cache)
    {
        $this->_cache = &$cache;

        return $this;
    }

    /**
     * Find customer and merge duplicates in SF
     * @param $customers Mage_Customer_Model_Customer[]
     */
    public function processDuplicates($customers)
    {
        $duplicatesEntity = array();
        if (Mage::helper('tnw_salesforce/config_customer')->mergeAccountDuplicates()) {
            $duplicatesEntity[] = 'account';
        }

        if (Mage::helper('tnw_salesforce/config_customer')->mergeContactDuplicates()) {
            $duplicatesEntity[] = 'contact';
        }

        if (Mage::helper('tnw_salesforce/config_customer')->mergeLeadDuplicates()) {
            $duplicatesEntity[] = 'lead';
        }

        foreach ($duplicatesEntity as $sfEntityType) {

            $duplicates = array();
            /**
             * @var $helper TNW_Salesforce_Helper_Salesforce_Data_Lead|TNW_Salesforce_Helper_Salesforce_Data_Account|TNW_Salesforce_Helper_Salesforce_Data_Contact
             */
            $helper = Mage::helper('tnw_salesforce/salesforce_data_' . $sfEntityType);
            switch ($sfEntityType) {
                case 'lead':
                    $leadSource = (Mage::helper('tnw_salesforce/data')->useLeadSourceFilter())
                        ? Mage::helper('tnw_salesforce/data')->getLeadSource() : null;

                    $duplicates = $helper->getDuplicates($customers, $leadSource);
                    break;
                case 'account':
                    $duplicates = $helper->getDuplicates($customers);
                    if (Mage::helper('tnw_salesforce')->usePersonAccount()) {
                        $duplicatesPersonAccount = $helper->getDuplicates($customers, true);
                        $duplicates = array_merge($duplicates, $duplicatesPersonAccount);
                    }
                    break;
                case 'contact':
                    $duplicates = $helper->getDuplicates($customers);
                    break;

            }

            foreach ($duplicates as $duplicate) {
                if ($sfEntityType == 'lead') {
                    $leadSource = (Mage::helper('tnw_salesforce/data')->useLeadSourceFilter())
                        ? Mage::helper('tnw_salesforce/data')->getLeadSource() : null;

                    $helper->mergeDuplicates($duplicate, $leadSource);
                    continue;
                }

                $helper->mergeDuplicates($duplicate);
            }
        }
    }

    /**
     * Send merge request to Salesforce
     * @param array $objects
     * @param string $type
     * @return $this
     * @throws Exception
     */
    public function sendMergeRequest($objects, $type = 'Lead')
    {
        try {
            $mergeRequest = new stdClass();

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("INFO: $type to merge: " . print_r($objects, 1));

            if (count($objects) < 2 || count($objects) > 3) {
                throw new Exception('Incorrect objects count for merge request');
            }

            /**
             * use last item as master record
             */
            $masterObject = array_pop($objects);

            $mergeRequest->masterRecord = $masterObject;

            $mergeRequest->comments = Mage::helper('tnw_salesforce')->__('Automate merge by Magento');

            $mergeRequest->recordToMergeIds = array();
            foreach ($objects as $object) {
                $mergeRequest->recordToMergeIds[] = property_exists($object, 'Id')? $object->Id: $object->id;
            }

            $result = $this->getClient()->merge($mergeRequest, $type);

            $result = array_shift($result);
            if (!property_exists($result, 'success') || $result->success != true) {
                throw new Exception("$type merging error: " . print_r($result, 1));
            }
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("INFO: $type merging result: " . print_r($result, 1));

            /**
             * replace Ids of duplicates to merge result Id
             */
            if (isset($this->_cache['toSaveInMagento'])) {
                foreach ($this->_cache['toSaveInMagento'] as $websiteId => $websiteCustomers) {
                    foreach ($websiteCustomers as $customer) {

                        if ($type == 'Contact') {
                            $sfEntryIdField = 'SalesforceId';
                        } else {
                            $sfEntryIdField = $type . 'Id';
                        }

                        if (!property_exists($customer, $sfEntryIdField)) {
                            continue;
                        }

                        /**
                         * replace duplicate Id to merge result Id
                         */
                        if (in_array($customer->{$sfEntryIdField}, $result->mergedRecordIds)) {
                            $customer->{$sfEntryIdField} = $result->id;
                        }
                    }
                }
            }

        } catch (Exception $e) {
            throw new Exception("ERROR: $type merging error: " . $e->getMessage());
        }

        return $result;
    }

}