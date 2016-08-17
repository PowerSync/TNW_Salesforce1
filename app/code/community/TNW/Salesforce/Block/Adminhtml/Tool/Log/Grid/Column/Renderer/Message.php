<?php

class TNW_Salesforce_Block_Adminhtml_Tool_Log_Grid_Column_Renderer_Message
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
        return sprintf('<pre style="white-space: pre-wrap">%s</pre>', $this->escapeHtml(parent::_getValue($row)));
    }
}
