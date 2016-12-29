<?php

class TNW_Salesforce_Model_Config_Products_Backend_Fees extends Mage_Core_Model_Config_Data
{
    /**
     * Processing object before save data
     *
     * @return Mage_Core_Model_Abstract
     */
    protected function _beforeSave()
    {
        $fees = $this->getValue();
        if ($fees) {
            return parent::_beforeSave();
        }

        switch ($this->getData('field_config')->product_type) {
            case 'tax':
                $enablePath = 'groups/opportunity_cart/fields/use_tax_product/value';
                $createProduct = array((object)array('Name'=>'Tax Fee', 'ProductCode'=>'fee_tax'));
                break;

            case 'shipping':
                $enablePath = 'groups/opportunity_cart/fields/use_shipping_product/value';
                $createProduct = array((object)array('Name'=>'Shipping Fee', 'ProductCode'=>'fee_shipping'));
                break;

            case 'discount':
                $enablePath = 'groups/opportunity_cart/fields/use_discount_product/value';
                $createProduct = array((object)array('Name'=>'Discount Fee', 'ProductCode'=>'fee_discount'));
                break;

            default:
                $this->_dataSaveAllowed = false;
                return $this;
        }

        if (!$this->getData($enablePath)) {
            return parent::_beforeSave();
        }

        try {
            $_responses = TNW_Salesforce_Model_Connection::createConnection()->getClient()
                ->upsert('Id', $createProduct, 'Product2');
        } catch (Exception $e) {
            $_responses = array();

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveError("ERROR: " . $e->getMessage());
        }

        if (isset($_responses[0]->success) && $_responses[0]->success) {
            $this->setValue(serialize(array(
                'Id'=>$_responses[0]->id,
                'Name'=>$createProduct[0]->Name,
                'ProductCode'=>$createProduct[0]->ProductCode
            )));
        }

        return parent::_beforeSave();
    }
}