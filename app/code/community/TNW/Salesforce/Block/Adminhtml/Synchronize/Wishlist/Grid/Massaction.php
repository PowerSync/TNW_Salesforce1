<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Block_Adminhtml_Synchronize_Wishlist_Grid_Massaction extends Mage_Adminhtml_Block_Widget_Grid_Massaction_Abstract
{
    /**
     * @return string
     * @throws Varien_Exception
     */
    public function getGridIdsJson()
    {
        if (!$this->getUseSelectAll()) {
            return '';
        }

        /** @var Mage_Customer_Model_Resource_Customer_Collection $collection */
        $collection = $this->getParentBlock()->getCollection();

        $gridIds = $collection->getConnection()->fetchCol($this->_getAllIdsSelect(), $this->_bindParams);

        if (!empty($gridIds)) {
            return join(",", $gridIds);
        }
        return '';
    }

    /**
     * Clone and reset collection
     *
     * @return Mage_Eav_Model_Entity_Collection_Abstract
     */
    protected function _getAllIdsSelect($limit = null, $offset = null)
    {
        $collection = $this->getParentBlock()->getCollection();

        $idsSelect = clone $collection->getSelect();
        $idsSelect->reset(Zend_Db_Select::ORDER);
        $idsSelect->reset(Zend_Db_Select::LIMIT_COUNT);
        $idsSelect->reset(Zend_Db_Select::LIMIT_OFFSET);
        $idsSelect->reset(Zend_Db_Select::COLUMNS);
        $idsSelect->columns(new Zend_Db_Expr($this->getParentBlock()->getMassactionIdField()));
        $idsSelect->limit($limit, $offset);

        return $idsSelect;
    }
}