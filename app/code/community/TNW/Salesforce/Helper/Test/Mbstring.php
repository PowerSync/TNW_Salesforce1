<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Helper_Test_Mbstring extends TNW_Salesforce_Helper_Test_Abstract
{
    /**
     * @var string
     */
    protected $_title = 'mbstring extension enabled';

    /**
     * @var string
     */
    protected $_message = 'Please enable mbstring extension in PHP.';

    /**
     * @var
     */
    protected $_redirect;

    /**
     * @return mixed
     */
    protected function _performTest()
    {
        return Mage::helper('tnw_salesforce')->isMbstringEnabled();
    }
}
