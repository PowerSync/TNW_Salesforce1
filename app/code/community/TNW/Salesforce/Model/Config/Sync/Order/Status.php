<?php
/**
 * Author: Tech-N-Web, LLC (dba PowerSync)
 * Email: support@powersync.biz
 * Developer: Evgeniy Ermolaev
 *
 * Class TNW_Salesforce_Model_Config_Sync_Customer_Address
 */

class TNW_Salesforce_Model_Config_Sync_Order_Status
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return $this->getOptions();
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        $_options = array();
        $states = Mage::helper('tnw_salesforce/salesforce_data')
            ->getPicklistValues('Order', 'Status');
        if (!is_array($states)) {
            $states = array();
        }
        foreach ($states as $key => $field) {
            $_options[] = array(
                'value' => $field->value,
                'label' => $field->label
            );
        }

        return $_options;
    }

}
