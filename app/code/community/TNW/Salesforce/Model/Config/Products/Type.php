<?php

class TNW_Salesforce_Model_Config_Products_Type
{
    const TYPE_UNKNOWN = 'unknown';

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        $options = Mage::getSingleton('catalog/product_type')->getOptions();
        array_unshift($options, array('value'=>self::TYPE_UNKNOWN, 'label'=>'Unknown Product'));

        return $options;
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return array_merge(
            array(self::TYPE_UNKNOWN => 'Unknown Product'),
            Mage::getSingleton('catalog/product_type')->getOptionArray()
        );
    }
}