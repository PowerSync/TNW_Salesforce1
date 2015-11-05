<?php

class TNW_Salesforce_Helper_Order_Roles extends TNW_Salesforce_Helper_Order
{
    protected $_mySforceConnection = NULL;

    public function assignRole($opportunityId = NULL, $contactId = NULL)
    {
        $this->_mySforceConnection = Mage::helper('tnw_salesforce/salesforce_data')->getClient();
        if (!$this->_mySforceConnection) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveNotice("SKIPPING: Salesforce connection failed!");
            return;
        }
        if (!$opportunityId) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Cannot update Opportunity Contact Role - Undefined Opportunity ID");
            return false;
        }
        if (!$contactId) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Cannot update Opportunity Contact Role - Undefined Contact ID");
            return false;
        }
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("------------------- OpportunityContactRole Start -------------------");

        $opportunityContactRoleIds = Mage::helper('tnw_salesforce/salesforce_data')->roleLookup($opportunityId, $contactId);
        $ocr_id = NULL;
        if (is_array($opportunityContactRoleIds) && count($opportunityContactRoleIds) > 0) {
            // Should only return one
            $ocr_id = $opportunityContactRoleIds[0]->Id;
        }
        unset($opportunityContactRoleIds);
        $ocr = new stdClass();
        $ocr->Id = $ocr_id;
        $ocr->IsPrimary = true;
        $ocr->OpportunityId = $opportunityId;
        $ocr->ContactId = $contactId;
        $ocr->Role = Mage::helper('tnw_salesforce')->getDefaultCustomerRole();

        unset($opportunityId, $contactId);
        /* Dump to Logs */
        foreach ($ocr as $key => $_value) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("OpportunityContactRole Object: " . $key . " = '" . $_value . "'");
        }

        Mage::dispatchEvent("tnw_salesforce_opportunitycontactrole_send_before",array("data" => array($ocr)));
        $response = $this->_mySforceConnection->upsert('Id', array($ocr), 'OpportunityContactRole');
        Mage::dispatchEvent("tnw_salesforce_opportunitycontactrole_send_after",array(
            "data" => array($ocr),
            "result" => $response
        ));

        unset($ocr);
        if (!$response[0]->success) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Failed to upsert OpportunityContactRole on Id: " . $ocr_id);
            if (is_array($response[0]->errors)) {
                foreach ($response[0]->errors as $_error) {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $_error->message);
                }
            } else {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $response[0]->errors->message);
            }
        } else {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("OpportunityContactRole #" . $response[0]->id . " upserted...");
        }
        unset($response);
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("------------------- OpportunityContactRole End -------------------");
    }
}