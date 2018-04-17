<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_System_Config_Source_Order_Integration_Create
{
    const ALWAYS = 'always';
    const PAID = 'paid';

    /**
     * @var array
     */
    protected $_data = array(
        self::ALWAYS => 'Always',
        self::PAID => 'PAID Order',
    );

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $_result = array();
        foreach ($this->_data as $key => $value) {
            $_result[] = array(
                'value' => $key,
                'label' => Mage::helper('tnw_salesforce')->__($value),
            );
        }

        return $_result;
    }
}
