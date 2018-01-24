<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Config_Newslettersync
{
    /**
     * @var array
     */
    protected $_cache = array();

    /**
     * @return array
     */
    public function toOptionArray()
    {
        return $this->getOptions();
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        if (!$this->_cache) {
            $this->_cache[] = array(
                'label' => 'No',
                'value' => '0'
            );
            $this->_cache[] = array(
                'label' => 'Yes',
                'value' => '1'
            );
        }

        return $this->_cache;
    }
}