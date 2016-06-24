<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Mysql4_Order_Creditmemo_Status_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract
{
    protected function _construct()
    {
        parent::_construct();
        $this->_init('tnw_salesforce/order_creditmemo_status');
    }

    public function toStatusHash()
    {
        return $this->_toOptionHash('magento_stage', 'salesforce_status');
    }

    public function toReverseStatusHash()
    {
        return $this->_toOptionHash('salesforce_status', 'magento_stage');
    }
}
