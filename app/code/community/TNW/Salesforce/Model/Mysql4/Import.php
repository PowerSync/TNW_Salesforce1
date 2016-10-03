<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Mysql4_Import extends Mage_Core_Model_Mysql4_Abstract
{
    /**
     * Primery key auto increment flag
     *
     * @var bool
     */
    protected $_isPkAutoIncrement    = false;

    public function _construct()
    {
        $this->_init('tnw_salesforce/import', 'import_id');
    }

    /**
     * @param Mage_Core_Model_Abstract $object
     * @return $this
     */
    protected function _beforeSave(Mage_Core_Model_Abstract $object)
    {
        //generate primary key
        if (!$object->getId()) {
            $object->setId(self::generateId());
        }

        return parent::_beforeSave($object);
    }

    /**
     * @return string
     */
    static public function generateId()
    {
        return uniqid("ctmr_", true);
    }
}
