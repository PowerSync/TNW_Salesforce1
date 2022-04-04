<?php

class TNW_Salesforce_Model_System_Config_Source_Payment_Offlinemethods
{
    public function toOptionArray()
    {
        $offlineMethods = [];
        $methods = Mage::helper('payment')->getPaymentMethodList(true, true, true);

        if (isset($methods['offline']['value'])) {
            $offlineMethods = $methods['offline']['value'];
        }

        return $offlineMethods;
    }
}
