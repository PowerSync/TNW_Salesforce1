<?php

class TNW_Salesforce_Model_Mapping_Type_Cart_Item extends TNW_Salesforce_Model_Mapping_Type_Abstract
{
    const TYPE = 'Cart Item';

    /**
     * @param $_entity Mage_Sales_Model_Quote_Item
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
                return $this->convertSfProductOptionsHtml($_entity);

            case 'sf_product_options_text':
                return $this->convertSfProductOptionsText($_entity);
        }

        return parent::_prepareValue($_entity);
    }

    /**
     * @param $_entity Mage_Sales_Model_Quote_Item
     * @return float|mixed
     */
    public function convertNumber($_entity)
    {
        return $_entity->getId();
    }

    /**
     * @param $_entity Mage_Sales_Model_Quote_Item
     * @param bool $includeTax
     * @param bool $includeDiscount
     * @return float|mixed
     */
    public function convertUnitPrice($_entity, $includeTax = true, $includeDiscount = true)
    {
        $netTotal = $this->_calculateItemPrice($_entity, $_entity->getQty(), $includeTax, $includeDiscount);
        return $this->numberFormat($netTotal);
    }

    /**
     * @param $_entity Mage_Sales_Model_Quote_Item
     * @return string
     */
    public function convertSfProductOptionsHtml($_entity)
    {
        $typeId = $_entity->getProduct()->getTypeId();
        switch($typeId) {
            case 'bundle':
                /** @var Mage_Bundle_Helper_Catalog_Product_configuration $configuration */
                $configuration = Mage::helper('bundle/catalog_product_configuration');
                $options = $configuration->getOptions($_entity);
                break;
            case 'downloadable':
                /** @var Mage_Downloadable_Helper_Catalog_Product_Configuration $configuration */
                $configuration = Mage::helper('downloadable/catalog_product_configuration');
                $options = $configuration->getOptions($_entity);
                break;
            default:
                /** @var Mage_Catalog_Helper_Product_Configuration $configuration */
                $configuration = Mage::helper('catalog/product_configuration');
                $options = $configuration->getOptions($_entity);
                break;
        }

        if (empty($options)) {
            return '';
        }

        $opt = array();
        $opt[] = '<table><thead><tr><th align="left">Option Name</th><th align="left">Title</th></tr></thead><tbody>';
        foreach ($options as $_option) {
            $optionValue = '';
            if(isset($_option['print_value'])) {
                $optionValue = $_option['print_value'];
            } elseif (isset($_option['value'])) {
                $optionValue = $_option['value'];
            }

            if (is_array($optionValue)) {
                $optionValue = implode(', ', $optionValue);
            }

            $opt[] = '<tr><td align="left">' . $_option['label'] . '</td><td align="left">' . $optionValue . '</td></tr>';
        }
        $opt[] = '</tbody></table>';

        return implode('', $opt);
    }

    /**
     * @param $_entity Mage_Sales_Model_Quote_Item
     * @return string
     */
    public function convertSfProductOptionsText($_entity)
    {
        $typeId = $_entity->getProduct()->getTypeId();
        switch($typeId) {
            case 'bundle':
                /** @var Mage_Bundle_Helper_Catalog_Product_configuration $configuration */
                $configuration = Mage::helper('bundle/catalog_product_configuration');
                $options = $configuration->getOptions($_entity);
                break;
            case 'downloadable':
                /** @var Mage_Downloadable_Helper_Catalog_Product_Configuration $configuration */
                $configuration = Mage::helper('downloadable/catalog_product_configuration');
                $options = $configuration->getOptions($_entity);
                break;
            default:
                /** @var Mage_Catalog_Helper_Product_Configuration $configuration */
                $configuration = Mage::helper('catalog/product_configuration');
                $options = $configuration->getOptions($_entity);
                break;
        }

        if (empty($options)) {
            return '';
        }

        $_summary = array();
        foreach ($options as $_option) {
            $optionValue = '';
            if(isset($_option['print_value'])) {
                $optionValue = $_option['print_value'];
            } elseif (isset($_option['value'])) {
                $optionValue = $_option['value'];
            }

            if (is_array($optionValue)) {
                $optionValue = implode(', ', $optionValue);
            }

            $_summary[] = strip_tags($optionValue);
        }

        $_description = join(", ", $_summary);

        return $_description;
    }
}