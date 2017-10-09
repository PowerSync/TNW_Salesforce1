<?php

/**
 * Author: Tech-N-Web, LLC (dba PowerSync)
 * Email: support@powersync.biz
 * Developer: Evgeniy Ermolaev
 *
 * Class TNW_Salesforce_Model_Config_Lead_Source
 */
class TNW_Salesforce_Model_Config_Lead_Source
{

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        $optionArray = $this->toOptionArray();
        $array = array();
        foreach ($optionArray as $data) {
            $array[$data['value']] = $data['label'];
        }

        return $array;
    }

    /**
     * @return array|mixed
     */
    public function toOptionArray()
    {
        $leadSource = Mage::helper('tnw_salesforce')->getStorage("tnw_salesforce_lead_source");
        if (empty($leadSource)) {
            $leadSource = array(
                array(
                    'label' => Mage::helper('tnw_salesforce')->__('None'),
                    'value' => '',
                )
            );

            try {
                $client = Mage::helper('tnw_salesforce/salesforce_data')->getClient();
                if ($client) {
                    $leadDescription = $client->describeSObject('Lead');
                    foreach ($leadDescription->fields as $field) {
                        if ($field->name == 'LeadSource') {
                            foreach ($field->picklistValues as $data) {
                                if (!$data->active) {
                                    continue;
                                }
                                $leadSource[] = array(
                                    'value' => $data->value,
                                    'label' => $data->label
                                );

                            }
                            break;
                        }
                    }

                    Mage::helper('tnw_salesforce')->setStorage($leadSource, 'tnw_salesforce_lead_source');
                }
            } catch (Exception $e) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Salesforce connect failed: " . $e->getMessage());
            }
        }

        return $leadSource;
    }
}
