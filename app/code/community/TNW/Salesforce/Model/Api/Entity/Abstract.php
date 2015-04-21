<?php

class TNW_Salesforce_Model_Api_Entity_Abstract extends Mage_Core_Model_Abstract
{

    /**
     * Load object data
     *
     * @param   integer $id
     * @return  Mage_Core_Model_Abstract
     */
    public function loadAll($id, $field=null)
    {
        $this->_beforeLoad($id, $field);
        $this->_getResource()->loadAll($this, $id, $field);
        $this->_afterLoad();
        $this->setOrigData();
        $this->_hasDataChanges = false;
        return $this;
    }


}