<?php

abstract class TNW_Salesforce_Model_Sync_Mapping_Invoice_Base extends TNW_Salesforce_Model_Sync_Mapping_Abstract_Base
{
    /**
     * @comment Gets field mapping from Magento and creates Shipment object
     * @param Mage_Sales_Model_Order_Invoice $entity
     * @param null $additionalObject
     */
    protected function _processMapping($entity = null, $additionalObject = null)
    {
        /** @var TNW_Salesforce_Model_Mapping $_map */
        foreach ($this->getMappingCollection() as $_map) {
            $value         = false;
            $mappingType   = $_map->getLocalFieldType();
            $attributeCode = $_map->getLocalFieldAttributeCode();

            if (!$this->_mappingTypeAllowed($mappingType)) {
                continue;
            }

            $value         = $this->_fieldMappingBefore($entity, $mappingType, $attributeCode, $value);
            if (!$this->isBreak()) {
                switch ($mappingType) {
                    case 'Billing':
                        break;

                    case 'Shipping':
                        break;
                }
            }

            $sf_field      = $_map->getSfField();
            $value         = $this->_fieldMappingAfter($entity, $mappingType, $attributeCode, $value);
            if (!$value) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace($this->_type . ' MAPPING: attribute ' . $sf_field . ' does not have a value in Magento, SKIPPING!');
                continue;
            }

            $this->getObj()->$sf_field = trim($value);
            $this->setBreak(false);
        }
    }
}
