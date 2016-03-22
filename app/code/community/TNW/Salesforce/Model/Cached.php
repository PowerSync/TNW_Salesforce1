<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

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