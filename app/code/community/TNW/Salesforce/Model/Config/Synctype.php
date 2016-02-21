<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Config_Synctype
{
    /**
     * @var array
     */
    protected $_syncType = array();

    /**
     * @var array
     */
    protected $_types = array();

    /**
     * drop down list method
     *
     * @return mixed
     */
    public function toOptionArray()
    {
        $this->_syncType['sync_type_realtime'] = 'Realtime';

        if (Mage::helper('tnw_salesforce')->getType() == "PRO") {
            $this->_syncType['sync_type_queue_interval'] = 'Queue Interval';
            $this->_syncType['sync_type_spectime'] = 'Specific Time';
        }

        return $this->_getOptions();
    }

    /**
     * @return array
     */
    protected function _getOptions() {
        foreach ($this->_syncType as $key => $value) {
            $this->_types[] = array(
                'value' => $key,
                'label' => $value,
            );
        }

        return $this->_types;
    }
}