<?php

class TNW_Salesforce_Model_System_Config_Backend_RecordType extends Mage_Core_Model_Config_Data
{
    const TYPE_DEFAULT = 'default';

    /**
     * Decrypt value after loading
     *
     */
    protected function _afterLoad()
    {
        if (self::TYPE_DEFAULT == (string)$this->getValue()) {
            $this->setValue(null);
        }
    }
}