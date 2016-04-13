<?php

class TNW_Salesforce_Model_Config_Campaign_Type
{
    public function toOptionArray()
    {
        return array(
            array('value' => 'Conference', 'label' => 'Conference'),
            array('value' => 'Webinar', 'label' => 'Webinar'),
            array('value' => 'Trade Show', 'label' => 'Trade Show'),
            array('value' => 'Public Relations', 'label' => 'Public Relations'),
            array('value' => 'Partners', 'label' => 'Partners'),
            array('value' => 'Referral Program', 'label' => 'Referral Program'),
            array('value' => 'Advertisement', 'label' => 'Advertisement'),
            array('value' => 'Banner Ads', 'label' => 'Banner Ads'),
            array('value' => 'Direct Mail', 'label' => 'Direct Mail'),
            array('value' => 'Direct Mail', 'label' => 'Direct Mail'),
            array('value' => 'Email', 'label' => 'Email'),
            array('value' => 'Telemarketing', 'label' => 'Telemarketing'),
            array('value' => 'Other', 'label' => 'Other'),
        );
    }
}