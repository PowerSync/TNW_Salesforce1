<?php

class TNW_Salesforce_Helper_Salesforce_Data_Creditmemo extends TNW_Salesforce_Helper_Salesforce_Data
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

            $_magentoId = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . "Magento_ID__c";

            $_results = array();
            foreach (array_chunk($ids, self::UPDATE_LIMIT) as $_ids) {
                $result = $this->_queryCreditmemo($_magentoId, $_ids);
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
                    $tmp->Items = (property_exists($_item, 'OrderItems')) ? $_item->OrderItems : NULL;
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

    /**
     * @param $_magentoId
     * @param $ids
     * @return array|stdClass
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
            "(SELECT Id, Quantity, UnitPrice, OriginalOrderItemId FROM OrderItems)",
            "(SELECT Id, Title, Body FROM Notes)"
        );

        $query = sprintf('SELECT %s FROM Order WHERE %s IN (\'%s\') AND IsReductionOrder = true',
            implode(', ', $_fields), $_magentoId, implode('\',\'', $ids));

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