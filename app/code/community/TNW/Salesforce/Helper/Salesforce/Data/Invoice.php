<?php

class TNW_Salesforce_Helper_Salesforce_Data_Invoice extends TNW_Salesforce_Helper_Salesforce_Data
{
    /**
     * @param array $ids
     * @return array|bool
     */
    public function lookup($ids = array())
    {
        $ids = !is_array($ids)
            ? array($ids) : $ids;

        try {
            if (!is_object($this->getClient())) {
                return false;
            }

            $_magentoId = TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE . 'Magento_ID__c';
            $oiiTable   = TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE . 'InvoiceItem__r';

            $_results = array();
            foreach (array_chunk($ids, self::UPDATE_LIMIT) as $_ids) {
                $result = $this->_queryInvoice($_magentoId, $oiiTable, $_ids);
                if (empty($result) || $result->size < 1) {
                    continue;
                }

                $_results[] = $result;
            }

            if (empty($_results)) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Invoice lookup returned: no results...");
                return false;
            }

            $returnArray = array();
            foreach ($_results as $result) {
                foreach ($result->records as $_item) {
                    $tmp = new stdClass();
                    $tmp->Id = $_item->Id;
                    $tmp->Notes = (property_exists($_item, 'Notes')) ? $_item->Notes : NULL;
                    $tmp->Items = (property_exists($_item, $oiiTable)) ? $_item->$oiiTable : NULL;
                    $tmp->MagentoId = $_item->$_magentoId;

                    $returnArray[$tmp->MagentoId] = $tmp;
                }
            }

            return $returnArray;
        }
        catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Could not find any existing orders in Salesforce matching these IDs (" . implode(",", $ids) . ")");
            return false;
        }
    }

    protected function _queryInvoice($_magentoId, $oiiTable, $ids)
    {
        $_fields    = array(
            'Id', $_magentoId,
            sprintf('(SELECT Id, Name, %s, %s, %s, %s FROM %s)',
                TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE . 'Order_Item__c',
                TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE . 'Opportunity_Product__c',
                TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE . 'Product_Code__c',
                TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE . 'Quantity__c',
                $oiiTable
            ),
            '(SELECT Id, Title, Body FROM Notes)'
        );

        $query = sprintf('SELECT %s FROM %s WHERE %s IN (\'%s\')',
            implode(', ', $_fields), TNW_Salesforce_Model_Config_Objects::ORDER_INVOICE_OBJECT, $_magentoId, implode('\',\'', $ids));

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("QUERY: " . $query);
        try {
            $_result = $this->getClient()->query($query);
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());
            $_result = array();
        }

        return $_result;
    }
}