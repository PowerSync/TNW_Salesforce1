<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_System_Config_Source_Order_Integration_Type
{
    const ORDER = 'order';

    /**
     * @var array
     */
    protected $_data = array();

    /**
     * TNW_Salesforce_Model_System_Config_Source_Order_Integration_Type constructor.
     */
    public function __construct()
    {
        $this->_data[self::ORDER] = 'Order';
    }

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $_result = array();
        foreach ($this->_data as $key => $value) {
            $_result[] = array(
                'value' => $key,
                'label' => $value,
            );
        }

        return $_result;
    }
}
