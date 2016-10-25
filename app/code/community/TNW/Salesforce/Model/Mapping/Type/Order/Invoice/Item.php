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

            case 'unit_price_excluding_tax_and_discounts':
                return $this->convertUnitPrice($_entity, false, false);

            case 'unit_price_including_tax_excluding_discounts':
                return $this->convertUnitPrice($_entity, true, false);

            case 'unit_price_including_discounts_excluding_tax':
                return $this->convertUnitPrice($_entity, false, true);

            case 'unit_price_including_tax_and_discounts':
                return $this->convertUnitPrice($_entity, true, true);

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
     * @param bool $includeTax
     * @param bool $includeDiscount
     * @return float|mixed
     */
    public function convertUnitPrice($_entity, $includeTax, $includeDiscount)
    {
        $netTotal = $this->_calculateItemPrice($_entity, $_entity->getQty(), $includeTax, $includeDiscount);
        return $this->numberFormat($netTotal);
    }
}