<?php

/**
 * Author: Evgeniy Ermolaev
 * Email: eermolaev@yandex.ru
 * Date: 09.03.15
 * Time: 22:22
 */
class TNW_Salesforce_Model_Sync_Mapping_Order_Order extends TNW_Salesforce_Model_Sync_Mapping_Order_Base
{

    protected $_type = 'Order';

    protected function _processMapping($_order = null)
    {
        parent::_processMapping($_order);
        $this->getObj()->Description = $this->_getDescriptionCart($_order);

    }


}