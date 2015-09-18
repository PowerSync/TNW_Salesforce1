<?php
/**
 * Author: Tech-N-Web, LLC (dba PowerSync)
 * Email: support@powersync.biz
 * Developer: Evgeniy Ermolaev
 * Date: 18.09.15
 * Time: 15:01
 */

class TNW_Salesforce_Block_Adminhtml_Catalog_Product_Helper_Chosen extends Varien_Data_Form_Element_Select
{
    public function __construct($attributes = array())
    {
        parent::__construct($attributes);

        if (!isset($this->_data['class'])) {
            $this->_data['class'] = '';
        }
        $this->_data['class'] .= ' chosen-select ';
    }

}
