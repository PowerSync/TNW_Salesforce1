<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_System_Config_Source_Order_Integration_Option
{
    const OPPORTUNITY = 'opportunity';
    const ORDER = 'order';
    const ORDER_AND_OPPORTUNITY = 'order_and_opportunity';

    /**
     * @var array
     */
    protected $_data = array(
        self::OPPORTUNITY => 'Opportunity (only)',
        self::ORDER => 'Order (only)',
        self::ORDER_AND_OPPORTUNITY => 'Opportunity & Order',
    );

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
