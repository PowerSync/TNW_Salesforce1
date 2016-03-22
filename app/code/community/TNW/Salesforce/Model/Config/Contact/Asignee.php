<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Config_Contact_Asignee
{
    const CONTACT_ASIGNEE_DEFAULT = 0;
    const CONTACT_ASIGNEE_ACCOUNT = 1;

    protected $_contactAsignee = array();
    protected $_contactAsigneeOptions = array();

    public function __construct()
    {
        $this->_contactAsignee = array(
            self::CONTACT_ASIGNEE_DEFAULT => Mage::helper('tnw_salesforce')->__('Use default owner'),
            self::CONTACT_ASIGNEE_ACCOUNT => Mage::helper('tnw_salesforce')->__('Retain owner from existing Account')
        );
    }

    /**
     * @return array|bool
     */
    public function getContactAsignee()
    {
        return $this->_contactAsignee;
    }

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {

        if (!$this->_contactAsigneeOptions) {
            foreach ($this->getContactAsignee() as $key => $value) {
                $this->_contactAsigneeOptions[] = array(
                    'value' => $key,
                    'label' => $value
                );
            }
        }

        return $this->_contactAsigneeOptions;
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return $this->getContactAsignee();
    }


}
