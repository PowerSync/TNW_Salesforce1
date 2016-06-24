<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Config_Lead_Notification
{

    public function toOptionArray()
    {
        return array(
            array('value' => 1, 'label'=>Mage::helper('tnw_salesforce')->__('Send to the record owner')),
            array('value' => 0, 'label'=>Mage::helper('tnw_salesforce')->__('Default Setting')),
        );
    }

}
