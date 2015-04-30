<?php

class TNW_Salesforce_Model_Import extends Mage_Core_Model_Abstract
{
    protected function _construct()
    {
        parent::_construct();
        $this->_init('tnw_salesforce/import');
    }

    public function forceInsert()
    {
        $this->getResource()->setForceInsertMode();
        $this->save();
        $this->getResource()->setForceInsertMode(false);
        return $this;
    }
}
