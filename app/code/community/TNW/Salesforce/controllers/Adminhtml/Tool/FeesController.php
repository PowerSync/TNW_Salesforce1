<?php

class TNW_Salesforce_Adminhtml_Tool_FeesController extends Mage_Adminhtml_Controller_Action
{
    public function createAction()
    {
        $success    = true;
        $_responses = array();

        try {
            $_responses = Mage::getSingleton('tnw_salesforce/connection')->getClient()
                ->upsert('Id', array(
                    (object)array('Name'=>'Tax Fee',      'ProductCode'=>'fee_tax'),
                    (object)array('Name'=>'Shipping Fee', 'ProductCode'=>'fee_shipping'),
                    (object)array('Name'=>'Discount Fee', 'ProductCode'=>'fee_discount'),
                ), 'Product2');
        } catch (Exception $e) {
            $success = false;

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveError("ERROR: " . $e->getMessage());
        }

        foreach ($_responses as $_key => $_response) {
            if (property_exists($_response, 'success') && $_response->success) {
                continue;
            }

            foreach ($_response->errors as $_error) {
                $success = false;

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError("ERROR: " . $_error->message);
            }
        }

        if ($success) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveNotice('Products successfully created');
        }

        $this->_redirect('adminhtml/system_config/edit/section/salesforce_order');
    }
}
