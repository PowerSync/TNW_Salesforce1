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
        return sprintf('<pre style="white-space: pre-wrap"><script type="application/javascript">document.write(JSON.stringify(%s, null, 4));</script></pre>', parent::_getValue($row));
    }
}