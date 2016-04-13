<?php

class TNW_Salesforce_Helper_Salesforce_Data_Campaign extends TNW_Salesforce_Helper_Salesforce_Data
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

            $_magentoId = TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL . 'Magento_ID__c';
            $_fields    = array(
                'Id', $_magentoId,
            );

            $query = sprintf('SELECT %s FROM Campaign WHERE %s IN (\'%s\')',
                implode(', ', $_fields), $_magentoId, implode('\',\'', $ids));

            $result = $this->getClient()->query($query);
            if (!$result || $result->size < 1) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace(sprintf("Campaign lookup returned: %s results...", $result->size));
                return false;
            }

            $returnArray = array();
            foreach ($result->records as $_item) {
                $tmp = new stdClass();
                $tmp->Id = $_item->Id;
                $tmp->MagentoId = $_item->$_magentoId;
                $returnArray[$tmp->MagentoId] = $tmp;
            }

            return $returnArray;
        }
        catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Could not find any existing Campaigns in Salesforce matching these IDs (" . implode(",", $ids) . ")");
            return false;
        }
    }
}