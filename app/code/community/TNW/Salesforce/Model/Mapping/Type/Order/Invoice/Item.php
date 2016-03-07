<?php

class TNW_Salesforce_Model_Mapping_Type_Order_Invoice_Item extends TNW_Salesforce_Model_Mapping_Type_Abstract
{
    const TYPE = 'Billing Item';

    /**
     * @param $_entity Mage_Sales_Model_Order_Invoice_Item
     * @return string
     */
    public function getValue($_entity)
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

        return parent::getValue($_entity);
    }

    /**
     * @param $_entity Mage_Sales_Model_Order_Invoice_Item
     * @return float|mixed
     */
    public function convertNumber($_entity)
    {
        return $_entity->getId();
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

    /**
     * @param $_entity Mage_Sales_Model_Order_Item
     * @return string
     */
    public function convertSfProductOptionsHtml($_entity)
    {
        if (!$_entity instanceof Mage_Sales_Model_Order_Item) {
            return '';
        }

        $opt = array();
        $options = (is_array($_entity->getData('product_options')))
            ? $_entity->getData('product_options')
            : @unserialize($_entity->getData('product_options'));

        if (
            is_array($options)
            && array_key_exists('options', $options)
        ) {
            $opt[] = '<table><thead><tr><th align="left">Option Name</th><th align="left">Title</th></tr></thead><tbody>';
            foreach ($options['options'] as $_option) {
                $optionValue = '';
                if(isset($_option['print_value'])) {
                    $optionValue = $_option['print_value'];
                } elseif (isset($_option['value'])) {
                    $optionValue = $_option['value'];
                }

                $opt[] = '<tr><td align="left">' . $_option['label'] . '</td><td align="left">' . $optionValue . '</td></tr>';
            }
            $opt[] = '</tbody></table>';
        }

        if (
            is_array($options)
            && $_entity->getData('product_type') == 'bundle'
            && array_key_exists('bundle_options', $options)
        ) {
            $_currencyCode = $_entity->getOrder()->getOrderCurrencyCode();
            $opt[] = '<table><thead><tr><th align="left">Option Name</th><th align="left">Title</th><th>Qty</th><th align="left">Fee<th></tr><tbody>';
            foreach ($options['bundle_options'] as $_option) {
                $_string = '<td align="left">' . $_option['label'] . '</td>';
                if (is_array($_option['value'])) {
                    $_tmp = array();
                    foreach ($_option['value'] as $_value) {
                        $_tmp[] = '<td align="left">'
                            . $_value['title'] . '</td><td align="center">'
                            . $_value['qty'] . '</td><td align="left">'
                            . $_currencyCode . ' ' . Mage::helper('tnw_salesforce/salesforce_data')->numberFormat($_value['price']) . '</td>';
                    }

                    if (count($_tmp) > 0) {
                        $_string .= implode(', ', $_tmp);
                    }
                }

                $opt[] = '<tr>' . $_string . '</tr>';
            }

            $opt[] = '</tbody></table>';
        }

        if (
            is_array($options)
            && $_entity->getData('product_type') == 'configurable'
            && array_key_exists('attributes_info', $options)
        ) {
            $opt[] = '<table><thead><tr><th align="left">Option Name</th><th align="left">Title</th></tr><tbody>';
            foreach ($options['attributes_info'] as $_option) {
                $opt[] = '<tr><td align="left">' . $_option['label'] . '</td><td align="left">' . $_option['value'] . '</td></tr>';
            }
            $opt[] = '</tbody></table>';
        }

        return implode('', $opt);
    }

    /**
     * @param $_entity Mage_Sales_Model_Order_Item
     * @return string
     */
    public function convertSfProductOptionsText($_entity)
    {
        if (!$_entity instanceof Mage_Sales_Model_Order_Item) {
            return '';
        }

        $options = (is_array($_entity->getData('product_options')))
            ? $_entity->getData('product_options')
            : @unserialize($_entity->getData('product_options'));

        $_summary = array();
        if (
            is_array($options)
            && array_key_exists('options', $options)
        ) {
            foreach ($options['options'] as $_option) {
                $optionValue = '';
                if(isset($_option['print_value'])) {
                    $optionValue = $_option['print_value'];
                } elseif (isset($_option['value'])) {
                    $optionValue = $_option['value'];
                }

                $_summary[] = $optionValue;
            }
        }

        if (
            is_array($options)
            && $_entity->getData('product_type') == 'bundle'
            && array_key_exists('bundle_options', $options)
        ) {
            foreach ($options['bundle_options'] as $_option) {
                if (is_array($_option['value'])) {
                    foreach ($_option['value'] as $_value) {
                        $_summary[] = $_value['title'];
                    }
                }
            }
        }

        if (
            is_array($options)
            && $_entity->getData('product_type') == 'configurable'
            && array_key_exists('attributes_info', $options)
        ) {
            foreach ($options['attributes_info'] as $_option) {
                $_summary[] = $_option['value'];
            }
        }

        $_description = join(", ", $_summary);
        if (strlen($_description) > 200) {
            $_description = substr($_description, 0, 200) . '...';
        }

        return $_description;
    }
}