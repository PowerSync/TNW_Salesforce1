<?php

class TNW_Salesforce_Model_Config_Order_Status
{

    public function toOptionArray()
    {
        $_statusArray = array();
        foreach(Mage::getModel('sales/order_status')->getResourceCollection()->getData() as $_status) {
            $_statusArray[] = array(
                'label' => $_status['label'],
                'value' => $_status['status']
            );
        }

        return $_statusArray;
    }

}
