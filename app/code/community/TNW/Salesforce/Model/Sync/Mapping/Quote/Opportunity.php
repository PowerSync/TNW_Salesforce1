<?php

/**
 * Author: Evgeniy Ermolaev
 * Email: eermolaev@yandex.ru
 * Date: 09.03.15
 * Time: 22:22
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