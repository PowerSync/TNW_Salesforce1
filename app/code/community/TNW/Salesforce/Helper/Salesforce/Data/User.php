<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Helper_Salesforce_Data_User extends TNW_Salesforce_Helper_Salesforce_Data
{

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
    protected function _isUserActive($sfUserId = NULL) {
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
            foreach($this->_sfUsers as $user) {
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

}