<?php

class TNW_Salesforce_Helper_Salesforce_Data_Shipment extends TNW_Salesforce_Helper_Salesforce_Data
{
    /**
     * @param array $ids
     * @return array|bool
     * @throws Exception
     */
    public function lookup(array $ids)
    {
        $_magentoId = TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_SHIPMENT . 'Magento_ID__c';
        $osiTable   = TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_SHIPMENT . 'ShipmentItem__r';
        $ostTable   = TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_SHIPMENT . 'ShipmentTracking__r';

        $_results = array();
        foreach (array_chunk($ids, self::UPDATE_LIMIT) as $_ids) {
            $_results[] = $this->_queryShipment($_magentoId, $osiTable, $ostTable, $_ids);
        }

        $records = $this->mergeRecords($_results);
        if (empty($records)) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Shipment lookup returned: no results...');
            return false;
        }

        $returnArray = array();
        foreach ($records as $_item) {
            $tmp = new stdClass();
            $tmp->Id = $_item->Id;
            $tmp->Notes = $this->getProperty($_item, 'Notes');
            $tmp->Items = $this->getProperty($_item, $osiTable);
            $tmp->Tracks = $this->getProperty($_item, $ostTable);
            $tmp->MagentoId = $_item->$_magentoId;

            $returnArray[$tmp->MagentoId] = $tmp;
        }

        return $returnArray;
    }

    /**
     * @param $_magentoId
     * @param $osiTable
     * @param $ostTable
     * @param $ids
     * @return array|stdClass
     */
    protected function _queryShipment($_magentoId, $osiTable, $ostTable, $ids)
    {
        $_fields    = array(
            'Id', $_magentoId,
            sprintf('(SELECT Id, Name, %s, %s, %s, %s, %s FROM %s)',
                TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_SHIPMENT . 'Order_Item__c',
                TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_SHIPMENT . 'Opportunity_Product__c',
                TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_SHIPMENT . 'Product_Code__c',
                TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_SHIPMENT . 'Quantity__c',
                TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_SHIPMENT . 'Magento_ID__c',
                $osiTable
            ),
            sprintf('(SELECT Id, Name, %s, %s FROM %s)',
                TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_SHIPMENT . 'Carrier__c',
                TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_SHIPMENT . 'Number__c',
                $ostTable
            ),
            '(SELECT Id, Title, Body FROM Notes)'
        );

        $query = sprintf('SELECT %s FROM %s WHERE %s IN (\'%s\')',
            implode(', ', $_fields), TNW_Salesforce_Model_Config_Objects::ORDER_SHIPMENT_OBJECT, $_magentoId, implode('\',\'', $ids));

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Shipment QUERY:\n$query");
        return $this->getClient()->query($query);
    }
}