<?php

class TNW_Salesforce_Model_Mapping_Type_Order_Creditmemo_Item extends TNW_Salesforce_Model_Mapping_Type_Order_Item
{
    const TYPE = 'Credit Memo Item';

    /**
     * @param $_entity Mage_Sales_Model_Order_Creditmemo_Item
     * @return string
     */
    public function getValue($_entity, $additional = null)
    {
        $attribute = $this->_mapping->getLocalFieldAttributeCode();
        switch ($attribute) {
            case 'number':
                return $this->convertNumber($_entity);

            case 'unit_price':
                return $this->convertUnitPrice($_entity);

            case 'qty':
                return $this->convertSfQty($_entity);

            case 'sf_product_options_html':
                return $this->convertSfProductOptionsHtml($_entity->getOrderItem());

            case 'sf_product_options_text':
                return $this->convertSfProductOptionsText($_entity->getOrderItem());
        }

        return TNW_Salesforce_Model_Mapping_Type_Abstract::getValue($_entity);
    }

    /**
     * @param $_entity Mage_Sales_Model_Order_Creditmemo_Item
     * @return float|mixed
     */
    public function convertNumber($_entity)
    {
        if ($_entity instanceof Mage_Sales_Model_Order_Creditmemo_Item) {
            return $_entity->getId();
        }

        return '';
    }

    /**
     * @param $_entity Mage_Sales_Model_Order_Creditmemo_Item
     * @return float|mixed
     */
    public function convertUnitPrice($_entity)
    {
        $netTotal = $this->_calculateItemPrice($_entity, $_entity->getQty());
        return $this->numberFormat($netTotal);
    }

    /**
     * @param $_entity Mage_Sales_Model_Order_Creditmemo_Item
     * @return float|mixed
     */
    public function convertSfQty($_entity)
    {
        return 0 - $_entity->getQty();
    }
}