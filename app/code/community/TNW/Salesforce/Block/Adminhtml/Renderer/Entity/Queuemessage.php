<?php

/**
 * Class TNW_Salesforce_Block_Adminhtml_Renderer_Entity_Queuemessage
 */
class TNW_Salesforce_Block_Adminhtml_Renderer_Entity_Queuemessage extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    /**
     * return icon html
     *
     * @param Varien_Object $row
     * @return string
     */
    public function render(Varien_Object $row)
    {
        $_errors = unserialize(urldecode($row->getData('message')));
        if (!empty($_errors)) {
            $_html = '<ul><li>' . join('</li><li>', $_errors) . '</li></ul>';
        } else {
            $_html = 'N/A';
        }

        return $_html;
    }
}