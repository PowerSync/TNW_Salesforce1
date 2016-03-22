<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Helper_Test_Openssl extends TNW_Salesforce_Helper_Test_Abstract
{
    /**
     * @var string
     */
    protected $_title = 'PHP OpenSSL extension test';

    /**
     * @var string
     */
    protected $_message = 'Please install and enable OpenSSL extension in PHP.';

    /**
     * @var
     */
    protected $_redirect;

    /**
     * @return bool|mixed
     */
    protected function _performTest()
    {
        return (!extension_loaded('openssl')) ? false : true;
    }
}