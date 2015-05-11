<?php
class TNW_Salesforce_Helper_Salesforce_Data_Order_Status extends TNW_Salesforce_Helper_Salesforce_Data_Order
{
    /**
     * @return array|bool
     */
    public function getAll()
    {
        try {
            if (!is_object($this->getClient())) {

                return false;
            }
            // Not implemented by Salesforce yet
            //TODO: change when they do

            $_dummyInactiveObject = new stdClass();
            $_dummyActiveObject = new stdClass();
            $_dummyActiveObject->MasterLabel = 'Activated';
            $_dummyInactiveObject->MasterLabel = 'Draft';

            $allRules = new stdClass();
            $allRules->records = array(
                $_dummyActiveObject,
                $_dummyInactiveObject
            );

            return $allRules->records;
        } catch (Exception $e) {
            Mage::helper('tnw_salesforce')->log("Error: " . $e->getMessage());
            Mage::helper('tnw_salesforce')->log("Could not extract order statuses from Salesforce");
            unset($email);
            return false;
        }
    }
}