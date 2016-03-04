<?php

abstract class TNW_Salesforce_Model_Mapping_Type_Abstract
{
    /**
     * @var TNW_Salesforce_Model_Mapping
     */
    protected $_mapping = null;

    /**
     * @param $_entity Mage_Core_Model_Abstract
     * @return string
     */
    public function getValue($_entity)
    {
        $attributeCode = $this->_mapping->getLocalFieldAttributeCode();

        $method = 'get' . str_replace(" ", "", ucwords(str_replace("_", " ", $attributeCode)));
        $value = call_user_func(array($_entity, $method));

        if (is_array($value)) {
            $value = implode(' ', $value);
        } elseif ($this->_mapping->getBackendType() == 'datetime' || $this->_mapping->getBackendType() == 'timestamp' || $attributeCode == 'created_at') {
            $value = gmdate(DATE_ATOM, Mage::getModel('core/date')->timestamp(strtotime($value)));
        } else {
            //check if get option text required
            if (is_object($_entity->getResource()) && method_exists($_entity->getResource(), 'getAttribute')
                && is_object($_entity->getResource()->getAttribute($attributeCode))
                && $_entity->getResource()->getAttribute($attributeCode)->getFrontendInput() == 'select'
            ) {
                $value = $_entity->getResource()->getAttribute($attributeCode)->getSource()->getOptionText($value);
            }
        }

        return $value;
    }

    /**
     * @param $_mapping TNW_Salesforce_Model_Mapping
     * @return $this
     */
    public function setMapping($_mapping)
    {
        $this->_mapping = $_mapping;
        return $this;
    }

    /**
     * @param $item
     * @param int $qty
     * @return float
     */
    protected function _calculateItemPrice($item, $qty = 1)
    {
        if (!Mage::helper('tnw_salesforce')->useTaxFeeProduct()) {
            $netTotal = $this->getEntityPrice($item, 'RowTotalInclTax');
        } else {
            $netTotal = $this->getEntityPrice($item, 'RowTotal');
        }

        if (!Mage::helper('tnw_salesforce')->useDiscountFeeProduct()) {
            $netTotal = ($netTotal - $this->getEntityPrice($item, 'DiscountAmount'));
            $netTotal = $netTotal / $qty;
        } else {
            $netTotal = $netTotal / $qty;
        }

        return $netTotal;
    }

    /**
     * @param $value
     * @return mixed
     */
    public function numberFormat($value)
    {
        return Mage::helper('tnw_salesforce/salesforce_data')->numberFormat($value);
    }

    /**
     * @param $_entity
     * @param $priceField
     * @return mixed
     */
    protected function getEntityPrice($_entity, $priceField)
    {
        $origPriceField = $priceField;
        /**
         * use base price if it's selected in config and multicurrency disabled
         */
        if (Mage::helper('tnw_salesforce/config_sales')->useBaseCurrency() && !Mage::helper('tnw_salesforce/config_sales')->isMultiCurrency()) {
            $priceField = 'Base' . $priceField;
        }

        $result = call_user_func(array($_entity, 'get' . $priceField));
        if (!$result) {
            $result = call_user_func(array($_entity, 'get' . $origPriceField));
        }

        return $result;
    }
}