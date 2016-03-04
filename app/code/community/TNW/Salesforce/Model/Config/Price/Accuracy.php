<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */


/**
 * drop down list
 *
 * Class TNW_Salesforce_Model_Config_Price_Accuracy
 */
class TNW_Salesforce_Model_Config_Price_Accuracy
{

    /**
     * @var array
     */
    protected $_options = array();

    /**
     * drop down list method
     *
     * @return mixed
     */
    public function toOptionArray()
    {
        $optionArray = array();

        foreach($this->_getOptions() as $option) {
            $optionArray[$option['value']] = $option['label'];
        }

        return $optionArray;
    }

    /**
     * @return array
     */
    protected function _getOptions()
    {
        if (!$this->_options) {
            for ($i = 2; $i <= 4; $i++) {
                $this->_options[] = array(
                    'value' => $i,
                    'label'=> Mage::helper('tnw_salesforce')->__('%d', $i)
                );
            }

        }
        return $this->_options;
    }
}