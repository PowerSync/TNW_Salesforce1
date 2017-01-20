<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

/**
 * @method int getObjectId()
 * @method string getSfObjectType()
 * @method string getMageObjectType()
 */
class TNW_Salesforce_Model_Queue_Storage extends Mage_Core_Model_Abstract
{
    protected function _construct()
    {
        parent::_construct();
        $this->_init('tnw_salesforce/queue_storage');
    }

    /**
     * @return Mage_Core_Model_Website
     */
    public function getWebsite()
    {
        return Mage::app()->getWebsite($this->getData('website_id'));
    }
}