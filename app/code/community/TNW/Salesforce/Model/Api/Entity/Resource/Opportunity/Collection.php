<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Api_Entity_Resource_Opportunity_Collection
    extends TNW_Salesforce_Model_Api_Entity_Resource_Collection_Abstract
{
    protected function _construct()
    {
        parent::_construct();
        $this->_init('tnw_salesforce_api_entity/opportunity');
    }

}