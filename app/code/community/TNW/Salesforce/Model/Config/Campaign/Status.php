<?php

class TNW_Salesforce_Model_Config_Campaign_Status
{
    public function toOptionArray()
    {
        return array(
            array('value' => 'Planned', 'label' => 'Planned'),
            array('value' => 'In Progress', 'label' => 'In Progress'),
            array('value' => 'In Progress', 'label' => 'In Progress'),
            array('value' => 'Completed', 'label' => 'Completed'),
            array('value' => 'Aborted', 'label' => 'Aborted'),
        );
    }
}