<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

/**
 * @deprecated
 */
class TNW_Salesforce_Helper_Report extends TNW_Salesforce_Helper_Abstract
{
    /**
     * @param string $_target
     * @param string $_type
     * @param array $_records
     * @param array $_responses
     * @deprecated
     * @return null
     */
    public function add($_target = 'Salesforce', $_type = 'Product2', $_records = array(), $_responses = array())
    {
        return null;
    }

    /**
     * @deprecated
     * @return bool
     */
    public function send()
    {
        return true;
    }

    /**
     * @deprecated
     */
    public function reset()
    {

    }
}