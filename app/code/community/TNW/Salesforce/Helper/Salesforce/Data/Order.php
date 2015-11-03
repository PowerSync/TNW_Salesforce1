<?php
class TNW_Salesforce_Helper_Salesforce_Data_Order extends TNW_Salesforce_Helper_Salesforce_Data
{
    const DISABLED_STATUS_CODE = 'D';
    const ACTIVATED_STATUS_CODE = 'A';
    const ACTIVATED_STATUS = 'Activated';
    const DRAFT_STATUS = 'Draft';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @param array $ids
     * @return array|bool
     */
    public function lookup($ids = array())
    {
        try {
            if (!is_object($this->getClient())) {

                return false;
            }
            $_magentoId = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . "Magento_ID__c";

            $orderItemFieldsToSelect = 'Id, Quantity, UnitPrice, PricebookEntry.ProductCode, PricebookEntryId, Description, PricebookEntry.UnitPrice, PricebookEntry.Name';

            if (!Mage::helper('tnw_salesforce/data')->isProfessionalSalesforceVersionType()) {
                $orderItemFieldsToSelect .=   ' , AvailableQuantity';
            }

            $_selectFields = array(
                "ID",
                "AccountId",
                "Pricebook2Id",
                "StatusCode",
                "Status",
                $_magentoId,
                "(SELECT $orderItemFieldsToSelect FROM OrderItems)",
                "(SELECT Id, Title, Body FROM Notes)"
            );
            if (is_array($ids)) {
                $query = "SELECT " . implode(',', $_selectFields) . " FROM Order WHERE " . $_magentoId . " IN ('" . implode("','", $ids) . "')";
            } else {
                $query = "SELECT " . implode(',', $_selectFields) . " FROM Order WHERE " . $_magentoId . "='" . $ids . "'";
            }

            $result = $this->getClient()->query(($query));

            unset($query);
            if (!$result || $result->size < 1) {
                Mage::getModel('tnw_salesforce/tool_log')->saveTrace("Order lookup returned: " . $result->size . " results...");
                return false;
            }
            $returnArray = array();
            foreach ($result->records as $_item) {
                $tmp = new stdClass();
                $tmp->Id = $_item->Id;
                $tmp->AccountId = (property_exists($_item, "AccountId")) ? $_item->AccountId : NULL;
                $tmp->Pricebook2Id = (property_exists($_item, "Pricebook2Id")) ? $_item->Pricebook2Id : NULL;
                $tmp->Status = (property_exists($_item, "Status")) ? $_item->Status : NULL;
                $tmp->StatusCode = (property_exists($_item, "StatusCode")) ? $_item->StatusCode : self::DISABLED_STATUS_CODE;
                $tmp->MagentoId = $_item->$_magentoId;
                $tmp->OrderItems = (property_exists($_item, "OrderItems")) ? $_item->OrderItems : NULL;
                $tmp->Notes = (property_exists($_item, "Notes")) ? $_item->Notes : NULL;
                $returnArray[$tmp->MagentoId] = $tmp;
            }

            return $returnArray;
        } catch (Exception $e) {
            Mage::getModel('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());
            Mage::getModel('tnw_salesforce/tool_log')->saveTrace("Could not find any existing orders in Salesforce matching these IDs (" . implode(",", $ids) . ")");
            unset($email);
            return false;
        }
    }
}