<?php
/**
 * Author: Evgeniy Ermolaev
 * Email: eermolaev@yandex.ru
 * Date: 30.04.15
 * Time: 12:28
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