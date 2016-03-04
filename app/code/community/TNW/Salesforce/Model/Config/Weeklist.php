<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Config_Weeklist
{
    /**
     * drop down week list
     *
     * @return mixed
     */
    public function toOptionArray()
    {
        return Mage::helper('tnw_salesforce')->_syncFrequencyWeekList();
    }
}