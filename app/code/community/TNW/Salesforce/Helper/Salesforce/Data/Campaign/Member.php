<?php

class TNW_Salesforce_Helper_Salesforce_Data_Campaign_Member extends TNW_Salesforce_Helper_Salesforce_Data
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

            $_magentoId = TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_FULFILMENT . 'Magento_ID__c';
            $oiiTable   = TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_FULFILMENT . 'OrderInvoiceItem__r';
            $_fields    = array(
                'Id', $_magentoId,
                sprintf('(SELECT Id, Name, %s, %s, %s FROM %s)',
                    TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_FULFILMENT . 'Order_Item__c',
                    TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_FULFILMENT . 'Product_Code__c',
                    TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_FULFILMENT . 'Quantity__c',
                    $oiiTable
                ),
                '(SELECT Id, Title, Body FROM Notes)'
            );

            $query = sprintf('SELECT %s FROM %s WHERE %s IN (\'%s\')',
                implode(', ', $_fields), TNW_Salesforce_Model_Config_Objects::ORDER_INVOICE_OBJECT, $_magentoId, implode('\',\'', $ids));

            $result = $this->getClient()->query($query);
            if (!$result || $result->size < 1) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace(sprintf("Order lookup returned: %s results...", $result->size));
                return false;
            }

            $returnArray = array();
            foreach ($result->records as $_item) {
                $tmp = new stdClass();
                $tmp->Id = $_item->Id;
                $tmp->Notes = (property_exists($_item, 'Notes')) ? $_item->Notes : NULL;
                $tmp->Items = (property_exists($_item, $oiiTable)) ? $_item->$oiiTable : NULL;
                $tmp->MagentoId = $_item->$_magentoId;
                $returnArray[$tmp->MagentoId] = $tmp;
            }

            return $returnArray;
        }
        catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Could not find any existing orders in Salesforce matching these IDs (" . implode(",", $ids) . ")");
            return false;
        }
    }
}