<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Sync_Mapping_Quote_Opportunity extends TNW_Salesforce_Model_Sync_Mapping_Quote_Base
{

    protected $_type = 'Quote';

    /**
     * @param Mage_Sales_Model_Quote $quote
     */
    protected function _processMapping($quote = null)
    {
        parent::_processMapping($quote);
        $this->getObj()->Description = self::getQuoteDescription($quote);
    }
}