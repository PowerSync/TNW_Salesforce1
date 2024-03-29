<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Config_Opportunity_State
{
    protected $_statuses = array();
    protected $_statusOptions = array();

    public function __construct()
    {
        $this->_statuses = Mage::helper('tnw_salesforce/salesforce_data')->getStatus('Opportunity');
    }

    /**
     * @return array|bool
     */
    public function getStatuses()
    {
        return $this->_statuses;
    }

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {

        if (!$this->_statusOptions) {

            $this->_statusOptions[] = array(
                'value' => '',
                'label' => Mage::helper('tnw_salesforce')->__('No status')
            );

            foreach ($this->getStatuses() as $key => $field) {
                $this->_statusOptions[] = array(
                    'value' => $field->MasterLabel,
                    'label' => $field->MasterLabel
                );
            }
        }

        return $this->_statusOptions;
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return $this->getStatuses();
    }

}