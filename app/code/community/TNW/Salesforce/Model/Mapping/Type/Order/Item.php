<?php

class TNW_Salesforce_Model_Mapping_Type_Order_Item extends TNW_Salesforce_Model_Mapping_Type_Abstract
{
    const TYPE = 'Order Item';

    /**
     * @param $_entity Mage_Sales_Model_Order_Item
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
     * @param $_entity Mage_Sales_Model_Order_Item
     * @return float|mixed
     */
    public function convertNumber($_entity)
    {
        return $_entity->getId();
    }

    /**
     * @param $_entity Mage_Sales_Model_Order_Item
     * @param bool $includeTax
     * @param bool $includeDiscount
     * @return float|mixed
     */
    public function convertUnitPrice($_entity, $includeTax, $includeDiscount)
    {
        $netTotal = $this->_calculateItemPrice($_entity, $_entity->getQtyOrdered(), $includeTax, $includeDiscount);
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

        if (
            is_array($options)
            && $_entity->getData('product_type') == 'downloadable'
            && array_key_exists('links', $options)
        ) {
            $purchasedItem = Mage::getModel('downloadable/link_purchased_item')->getCollection()
                ->addFieldToFilter('order_item_id', $_entity->getId());

            $opt[] = '<table><thead><tr><th align="left">Links</th></tr><tbody>';
            /** @var Mage_Downloadable_Model_Link_Purchased_Item $item */
            foreach ($purchasedItem as $item) {
                $opt[] = '<tr><td align="left">' . sprintf('%s (%s / %s)', $item->getLinkTitle(), $item->getNumberOfDownloadsUsed(), $item->getNumberOfDownloadsBought()?$item->getNumberOfDownloadsBought():Mage::helper('downloadable')->__('U'))  . '</td></tr>';
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

        if (
            is_array($options)
            && $_entity->getData('product_type') == 'downloadable'
            && array_key_exists('links', $options)
        ) {
            $purchasedItem = Mage::getModel('downloadable/link_purchased_item')->getCollection()
                ->addFieldToFilter('order_item_id', $_entity->getId());

            /** @var Mage_Downloadable_Model_Link_Purchased_Item $item */
            foreach ($purchasedItem as $item) {
                $_summary[] = sprintf('%s (%s / %s)', $item->getLinkTitle(), $item->getNumberOfDownloadsUsed(), $item->getNumberOfDownloadsBought()?$item->getNumberOfDownloadsBought():Mage::helper('downloadable')->__('U'));
            }
        }


        /**
         * add parent SKU to Description for Bundle items
         */
        if (empty($_summary) && $_entity->getBundleItemToSync()) {
            $_summary[] = $_entity->getBundleItemToSync();
        }

        return join(", ", $_summary);
    }
}