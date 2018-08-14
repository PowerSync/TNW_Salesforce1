<?php

class TNW_Salesforce_Helper_Salesforce_Data_Invoice extends TNW_Salesforce_Helper_Salesforce_Data
{
    /**
     * @param array $ids
     * @return array|bool
     * @throws Exception
     */
    public function lookup(array $ids)
    {
        $_magentoId = TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE . 'Magento_ID__c';
        $oiiTable   = TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE . 'InvoiceItem__r';

        $_results = array();
        foreach (array_chunk($ids, self::UPDATE_LIMIT) as $_ids) {
            $_results[]  = $this->_queryInvoice($_magentoId, $oiiTable, $_ids);
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
            $tmp->Items = $this->getProperty($_item, $oiiTable);
            $tmp->MagentoId = $_item->$_magentoId;

            $returnArray[$tmp->MagentoId] = $tmp;
        }

        Mage::getSingleton('tnw_salesforce/tool_log')
            ->saveTrace(sprintf("Invoice lookup returned:\n%s", print_r($returnArray, true)));

        return $returnArray;
    }

    protected function _queryInvoice($_magentoId, $oiiTable, $ids)
    {
        $_fields    = array(
            'Id', $_magentoId,
            sprintf('(SELECT Id, Name, %s, %s, %s, %s, %s FROM %s)',
                TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE . 'Order_Item__c',
                TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE . 'Opportunity_Product__c',
                TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE . 'Product_Code__c',
                TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE . 'Quantity__c',
                TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE . 'Magento_ID__c',
                $oiiTable
            ),
            '(SELECT Id, Title, Body FROM Notes)'
        );

        $query = sprintf('SELECT %s FROM %s WHERE %s IN (\'%s\')',
            implode(', ', $_fields), TNW_Salesforce_Model_Config_Objects::ORDER_INVOICE_OBJECT, $_magentoId, implode('\',\'', $ids));

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Invoice QUERY:\n$query");
        return $this->getClient()->query($query);
    }
}