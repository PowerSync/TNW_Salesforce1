<?php

class TNW_Salesforce_Block_Adminhtml_Widget_Grid_Column_Renderer_Json
    extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    /**
     * Renders grid column
     *
     * @param Varien_Object $row
     * @return mixed
     */
    public function _getValue(Varien_Object $row)
    {
        $formatJson = defined('JSON_PRETTY_PRINT') ? json_encode(json_decode(parent::_getValue($row)), JSON_PRETTY_PRINT) : parent::_getValue($row);
        return sprintf('<pre style="white-space: pre-wrap">%s</pre>', $this->escapeHtml($formatJson));
    }
}