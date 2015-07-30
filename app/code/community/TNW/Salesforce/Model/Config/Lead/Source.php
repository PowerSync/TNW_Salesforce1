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
        $_useCache = Mage::app()->useCache('tnw_salesforce');
        $cache = Mage::app()->getCache();

        if ($_useCache && $cache->load("tnw_salesforce_lead_source")) {
            $leadSource = unserialize($cache->load("tnw_salesforce_lead_source"));
        } else {

            $leadSource = array(
                array(
                    'label' => Mage::helper('tnw_salesforce')->__('None'),
                    'value' => '',
                )
            );
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

                if ($_useCache) {
                    $cache->save(serialize($leadSource), 'tnw_salesforce_lead_source', array("TNW_SALESFORCE"));
                }
            }
        }

        return $leadSource;
    }
}
