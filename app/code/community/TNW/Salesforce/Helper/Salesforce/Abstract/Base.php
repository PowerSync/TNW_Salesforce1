<?php

abstract class TNW_Salesforce_Helper_Salesforce_Abstract_Base extends TNW_Salesforce_Helper_Salesforce_Abstract
{
    /**
     * @var array
     */
    protected $_skippedEntity = array();

    /**
     * @return array
     */
    public function getSkippedEntity()
    {
        return $this->_skippedEntity;
    }

    //public function

    abstract function massAdd();
    abstract function process();
}