<?php

class TNW_Salesforce_Model_Mapping_Type_Wishlist_Item extends TNW_Salesforce_Model_Mapping_Type_Abstract
{
    const TYPE = 'Wishlist Item';

    /**
     * @param $_entity Mage_Wishlist_Model_Item
     * @return string
     */
    protected function _prepareValue($_entity)
    {
        $attribute = $this->_mapping->getLocalFieldAttributeCode();
        switch ($attribute) {
            case 'number':
                return $this->convertNumber($_entity);

            case 'price_book':
                return $this->convertPriceBook($_entity);

            case 'sf_product_options_html':
                return $this->convertSfProductOptionsHtml($_entity);

            case 'sf_product_options_text':
                return $this->convertSfProductOptionsText($_entity);
        }

        return parent::_prepareValue($_entity);
    }

    /**
     * @param $_entity Mage_Wishlist_Model_Item
     * @return float|mixed
     */
    public function convertNumber($_entity)
    {
        return $_entity->getId();
    }

    /**
     * @param $_entity Mage_Wishlist_Model_Item
     * @return string
     * @throws Mage_Core_Exception
     * @throws Mage_Core_Model_Store_Exception
     */
    public function convertPriceBook($_entity)
    {
        $_currencyCode = $_entity->getProduct()->getStore()->getCurrentCurrencyCode();
        $pricebookEntryId = $_entity->getProduct()->getSalesforcePricebookId();
        foreach (explode("\n", $pricebookEntryId) as $value) {
            if (strpos($value, ':') === false) {
                continue;
            }

            list($_currency, $_priceBook) = explode(':', $value, 2);
            if (!empty($_currency) && ($_currency == $_currencyCode || empty($_currencyCode))) {
                $pricebookEntryId = $_priceBook;
            }
        }

        return $pricebookEntryId;
    }

    /**
     * @param $_entity Mage_Wishlist_Model_Item
     * @return string
     * @throws Mage_Core_Exception
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
     * @param $_entity Mage_Wishlist_Model_Item
     * @return string
     * @throws Mage_Core_Exception
     */
    public function convertSfProductOptionsText($_entity)
    {
        $typeId = $_entity->getProduct()->getTypeId();
        switch($typeId) {
            case 'bundle':
                /** @var Mage_Bundle_Helper_Catalog_Product_Configuration $configuration */
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

        return implode(', ', $_summary);
    }
}