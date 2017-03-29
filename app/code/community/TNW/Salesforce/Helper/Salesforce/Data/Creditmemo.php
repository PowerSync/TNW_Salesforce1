<?php

class TNW_Salesforce_Helper_Salesforce_Data_Creditmemo extends TNW_Salesforce_Helper_Salesforce_Data
{
    /**
     * @param array $ids
     * @return array|bool
     * @throws Exception
     */
    public function lookup(array $ids)
    {
        $_magentoId = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . 'Magento_ID__c';

        $_results = array();
        foreach (array_chunk($ids, self::UPDATE_LIMIT) as $_ids) {
            $_results[] = $this->_queryCreditmemo($_magentoId, $_ids);
        }

        $records = $this->mergeRecords($_results);
        if (empty($records)) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Invoice lookup returned: no results...');
            return false;
        }

        $returnArray = array();
        foreach ($records as $_item) {
            $tmp = new stdClass();
            $tmp->Id = $_item->Id;
            $tmp->Notes = $this->getProperty($_item, 'Notes');
            $tmp->Items = $this->getProperty($_item, 'OrderItems');
            $tmp->MagentoId = $_item->$_magentoId;

            $returnArray[$tmp->MagentoId] = $tmp;
        }

        return $returnArray;
    }

    /**
     * @param $_magentoId
     * @param $ids
     * @return array|stdClass
     * @throws Exception
     */
    protected function _queryCreditmemo($_magentoId, $ids)
    {
        $_fields = array(
            "ID",
            "AccountId",
            "Pricebook2Id",
            "StatusCode",
            "Status",
            "OriginalOrderId",
            $_magentoId,
            "(SELECT Id, Quantity, UnitPrice, OriginalOrderItemId, PricebookEntry.ProductCode FROM OrderItems)",
            "(SELECT Id, Title, Body FROM Notes)"
        );

        $query = sprintf('SELECT %s FROM Order WHERE %s IN (\'%s\') AND IsReductionOrder = true',
            implode(', ', $_fields), $_magentoId, implode('\',\'', $ids));

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Creditmemo QUERY:\n$query");
        return $this->getClient()->query($query);
    }
}