<?php

/**
 * Author: Evgeniy Ermolaev
 * Email: eermolaev@yandex.ru
 * Date: 07.04.15
 * Time: 18:16
 */
class TNW_Salesforce_Helper_Salesforce_Data_User extends TNW_Salesforce_Helper_Salesforce_Data
{
    /**
     * @see https://developer.salesforce.com/docs/atlas.en-us.api.meta/api/sforce_api_calls_merge.htm
     * Limit defined by SF
     */
    const MERGE_LIMIT = 3;

    /**
     * @var null
     */
    protected $_sfUsers = NULL;

    /**
     * @var null
     */
    protected $_queueList = NULL;

    /**
     * Read from cache or pull from Salesforce Active users
     * Accept $sfUserId parameter and check if its in the array of active users
     * @param null $sfUserId
     * @return bool
     */
    protected function _isUserActive($sfUserId = NULL)
    {
        if ($this->_mageCache === NULL) {
            $this->_initCache();
        }
        $activeUsers = array();
        if (!$this->_sfUsers) {
            if ($this->_useCache) {
                $this->_sfUsers = unserialize($this->_mageCache->load("tnw_salesforce_users"));
            }
            if (!$this->_sfUsers) {
                $this->_sfUsers = Mage::helper('tnw_salesforce/salesforce_data')->getUsers();
            }
        }

        if (is_array($this->_sfUsers)) {
            foreach ($this->_sfUsers as $user) {
                $activeUsers[] = $user['value'];
            }
        }

        $result = (!empty($activeUsers)) ? in_array($sfUserId, $activeUsers) : false;

        if (!$result) {
            $result = $this->_isQueue($sfUserId);
        }

        return $result;

    }

    /**
     * @comment Check is this object Salesforce Queue entity
     * @param null $sfUserId
     * @return bool
     */
    protected function _isQueue($sfUserId = NULL)
    {
        if (is_null($this->_queueList)) {
            $this->_queueList = Mage::helper('tnw_salesforce/salesforce_data_queue')->getAllQueues();
        }

        return in_array($sfUserId, $this->_queueList);
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
     * Find customer duplicates in SF
     */
    public function getDuplicates()
    {
        foreach (array('lead', 'account', 'contact') as $sfEntityType) {
            /**
             * @var $helper TNW_Salesforce_Helper_Salesforce_Data_Lead|TNW_Salesforce_Helper_Salesforce_Data_Account|TNW_Salesforce_Helper_Salesforce_Data_Contact
             */
            $helper = Mage::helper('tnw_salesforce/salesforce_data_' . $sfEntityType);
            $duplicates = $helper->getDuplicates();

            foreach ($duplicates as $duplicate) {
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
            $mergeRequest = new stdclass();

            Mage::helper('tnw_salesforce')->log("INFO: $type to merge: " . print_r($objects, 1));

            $masterObject = array_shift($objects);

            $mergeRequest->masterRecord = $masterObject;

            $mergeRequest->comments = Mage::helper('tnw_salesforce')->__('Automate merge by Magento');

            $mergeRequest->recordToMergeIds = array();
            foreach ($objects as $object) {
                $mergeRequest->recordToMergeIds[] = $object->Id;
            }

            $result = $this->getClient()->merge($mergeRequest, $type);

            $result = array_shift($result);
            if (!property_exists($result, 'success') || $result->success != true) {
                throw new Exception("$type merging error: " . print_r($result));
            }
            Mage::helper('tnw_salesforce')->log("INFO: $type merging result: " . print_r($result, 1));

        } catch (Exception $e) {
            Mage::helper('tnw_salesforce')->log("ERROR: $type merging error: " . $e->getMessage());
            throw $e;
        }

        return $result;
    }

}