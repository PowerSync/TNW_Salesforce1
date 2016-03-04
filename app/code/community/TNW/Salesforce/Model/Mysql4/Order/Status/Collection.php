<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Mysql4_Order_Status_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract
{
    protected function _construct()
    {
        parent::_construct();
        $this->_init('tnw_salesforce/order_status');
    }

    public function addStatusToFilter($id)
    {
        $this->getSelect()
            ->where('main_table.status = ?', $id);
        return $this;
    }
}
