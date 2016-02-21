<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Config_Products_Tax extends TNW_Salesforce_Model_Config_Products
{
    public function toOptionArray()
    {
        return $this->buildDropDown('Tax');
    }
}
