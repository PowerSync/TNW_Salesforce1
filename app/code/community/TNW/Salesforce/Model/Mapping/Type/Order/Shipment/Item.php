<?php

class TNW_Salesforce_Model_Mapping_Type_Order_Shipment_Item extends TNW_Salesforce_Model_Mapping_Type_Order_Item
{
    const TYPE = 'Shipment Item';

    /**
     * @param $_entity Mage_Sales_Model_Order_Shipment_Item
     * @return string
     */
    protected function _prepareValue($_entity)
    {
        $attribute = $this->_mapping->getLocalFieldAttributeCode();
        switch ($attribute) {
            case 'number':
                return $this->convertNumber($_entity);

            case 'sf_product_options_html':
                return $this->convertSfProductOptionsHtml($_entity->getOrderItem());

            case 'sf_product_options_text':
                return $this->convertSfProductOptionsText($_entity->getOrderItem());
        }

        return TNW_Salesforce_Model_Mapping_Type_Abstract::_prepareValue($_entity);
    }

    /**
     * @param $_entity Mage_Sales_Model_Order_Shipment_Item
     * @return float|mixed
     */
    public function convertNumber($_entity)
    {
        return $_entity->getId();
    }
}