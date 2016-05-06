<?php

class TNW_Salesforce_Helper_Salesforce_Data_Currency extends TNW_Salesforce_Helper_Salesforce_Data
{
    public function lookupAll()
    {
        try {
            if (!is_object($this->getClient())) {
                return false;
            }

            $_fields    = array(
                'Id', 'IsoCode', 'IsCorporate', 'ConversionRate', 'DecimalPlaces',
            );

            $query = sprintf('SELECT %s FROM CurrencyType', implode(', ', $_fields));

            /** @var stdClass $result */
            $result = $this->getClient()->query($query);
            if (!$result || $result->size < 1) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace(sprintf("Currency lookup returned: %s results...", $result->size));

                return false;
            }

            $returnArray = array();
            foreach ($result->records as $_item) {
                $tmp = new stdClass();
                $tmp->Id = $_item->Id;
                $tmp->IsoCode = $_item->IsoCode;
                $tmp->IsCorporate = $_item->IsCorporate;
                $tmp->ConversionRate = $_item->ConversionRate;
                $tmp->DecimalPlaces = $_item->DecimalPlaces;

                $returnArray[$_item->IsoCode] = $tmp;
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