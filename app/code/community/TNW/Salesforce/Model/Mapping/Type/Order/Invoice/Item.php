<?php

class TNW_Salesforce_Model_Mapping_Type_Order_Invoice_Item extends TNW_Salesforce_Model_Mapping_Type_Order_Item
{
    const TYPE = 'Billing Item';

    /**
     * @param $_entity Mage_Sales_Model_Order_Invoice_Item
     * @return string
     */
    protected function _prepareValue($_entity)
    {
        $attribute = $this->_mapping->getLocalFieldAttributeCode();
        switch ($attribute) {
            case 'number':
                return $this->convertNumber($_entity);

            case 'unit_price':
                return $this->convertUnitPrice($_entity);

            case 'sf_product_options_html':
                return $this->convertSfProductOptionsHtml($_entity->getOrderItem());

            case 'sf_product_options_text':
                return $this->convertSfProductOptionsText($_entity->getOrderItem());
        }

        return TNW_Salesforce_Model_Mapping_Type_Abstract::_prepareValue($_entity);
    }

    /**
     * @param $_entity Mage_Sales_Model_Order_Invoice_Item
     * @return float|mixed
     */
    public function convertNumber($_entity)
    {
        if ($_entity instanceof Mage_Sales_Model_Order_Invoice_Item) {
            return $_entity->getId();
        }

        return '';
    }

    /**
     * @param $_entity Mage_Sales_Model_Order_Invoice_Item
     * @return float|mixed
     */
    public function convertUnitPrice($_entity)
    {
        $netTotal = $this->_calculateItemPrice($_entity, $_entity->getQty());
        return $this->numberFormat($netTotal);
    }
}