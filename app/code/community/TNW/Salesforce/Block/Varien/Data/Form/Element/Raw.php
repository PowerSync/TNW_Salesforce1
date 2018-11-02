<?php
/**
 * Author: Tech-N-Web, LLC (dba PowerSync)
 * Email: support@powersync.biz
 * Developer: Evgeniy Ermolaev
 * Date: 17.11.15
 * Time: 11:54
 */

class TNW_Salesforce_Block_Varien_Data_Form_Element_Raw extends Varien_Data_Form_Element_Abstract
{
    public function getElementHtml()
    {
        $id = !$this->getData('id') ? $this->getId() : $this->getData('id');
        return sprintf('<pre id="%s">%s</pre>', $id, htmlspecialchars($this->getValue()));
    }
}