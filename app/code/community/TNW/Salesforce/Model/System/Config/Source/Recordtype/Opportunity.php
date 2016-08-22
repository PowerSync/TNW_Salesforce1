<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_System_Config_Source_Recordtype_Opportunity
{

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $_recordTypes = array(
            array(
                'label' => 'Use Default',
                'value' => ''
            )
        );

        $records = Mage::helper('tnw_salesforce/salesforce_data')->getRecordTypeByEntity('Opportunity');
        foreach ($records as $record) {
            $_recordTypes[] = array(
                'label' => $record->Name,
                'value' => $record->Id
            );
        }

        return $_recordTypes;
    }
}