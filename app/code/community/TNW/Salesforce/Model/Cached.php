<?php

class TNW_Salesforce_Model_Cached
{
    protected $_cached = array();

    /**
     * @param string $modelAlias
     * @param string $id
     * @return Mage_Core_Model_Abstract
     */
    public function load($modelAlias, $id)
    {
        if (!isset($this->_cached[$modelAlias][$id])) {
            $this->_cached[$modelAlias][$id] = Mage::getModel($modelAlias)->load($id);
        }

        return $this->_cached[$modelAlias][$id];
    }
}