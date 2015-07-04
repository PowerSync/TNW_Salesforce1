<?php

class TNW_Salesforce_Model_Config_Products_Tax extends TNW_Salesforce_Model_Config_Products
{
    public function toOptionArray()
    {
        return $this->buildDropDown('Tax');
    }
}
