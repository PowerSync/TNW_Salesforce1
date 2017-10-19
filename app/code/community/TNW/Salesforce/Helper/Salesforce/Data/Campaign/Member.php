<?php

class TNW_Salesforce_Helper_Salesforce_Data_Campaign_Member extends TNW_Salesforce_Helper_Salesforce_Data
{
    /**
     * @param array $campaignCustomers
     * @return array|bool
     */
    public function lookup($campaignCustomers = array())
    {
        try {
            try {
                $this->getClient();
            } catch (Exception $e) {
                return false;
            }

            $returnArray = array();
            /**
             * @var string $campaignId
             * @var Mage_Customer_Model_Customer[] $customers
             */
            foreach ($campaignCustomers as $campaignId => $customers) {
                $_results = array();
                foreach (array_chunk($customers, self::UPDATE_LIMIT) as $_customers) {
                    $result = $this->_queryCampaignMember($campaignId, $_customers);
                    if (empty($result) || $result->size < 1) {
                        continue;
                    }

                    $_results[] = $result;
                }

                if (empty($_results)) {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Campaign Member lookup returned: no results...");
                    continue;
                }

                foreach ($_results as $result) {
                    foreach ($result->records as $_item) {
                        $tmp = new stdClass();
                        $tmp->Id = (property_exists($_item, "Id")) ? $_item->Id : null;
                        $tmp->ContactId = (property_exists($_item, "ContactId")) ? $_item->ContactId : null;
                        $tmp->LeadId = (property_exists($_item, "LeadId")) ? $_item->LeadId : null;
                        $tmp->CampaignId = (property_exists($_item, "CampaignId")) ? $_item->CampaignId : null;

                        $returnArray[Mage::helper('tnw_salesforce')->prepareId($_item->CampaignId)][] = $tmp;
                    }
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
     * @param $campaignId
     * @param $customers
     * @return array|stdClass
     */
    protected function _queryCampaignMember($campaignId, $customers)
    {
        $_fields    = array(
            'Id', 'ContactId', 'LeadId', 'CampaignId'
        );

        $query = sprintf('SELECT %s FROM CampaignMember WHERE CampaignId = \'%s\' AND',
            implode(', ', $_fields), $campaignId);

        $sfIds = array_filter(array_map(function(Mage_Customer_Model_Customer $customer) {
            return $customer->getData('salesforce_id');
        }, $customers));

        $where = array();
        if (!empty($sfIds)) {
            $where[] = sprintf('ContactId IN (\'%s\')', implode('\', \'', $sfIds));
        }

        $sfLeadIds = array_filter(array_map(function(Mage_Customer_Model_Customer $customer) {
            return $customer->getData('salesforce_lead_id');
        }, $customers));

        if (!empty($sfLeadIds)) {
            $where[] = sprintf('LeadId IN (\'%s\')', implode('\', \'', $sfLeadIds));
        }

        $query .= ' (' . implode(' OR ', $where) . ')';

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