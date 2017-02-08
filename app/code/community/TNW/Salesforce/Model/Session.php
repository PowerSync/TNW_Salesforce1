<?php

/**
 * TNW_Salesforce session model
 */
class TNW_Salesforce_Model_Session extends Mage_Core_Model_Session_Abstract
{
    public function __construct($data = array())
    {
        $this->init('tnw_salesforce');
    }
}
