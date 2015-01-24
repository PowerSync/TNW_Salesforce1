<?php

/**
 * Class TNW_Salesforce_Block_Adminhtml_Renderer_Entity_Status
 */
class TNW_Salesforce_Block_Adminhtml_Renderer_Entity_Status extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    /**
     * @param Varien_Object $row
     * @return string
     */
    public function render(Varien_Object $row)
    {
        $inSync = $row->getData('sf_insync');
        $_imageUrl = Mage::getDesign()->getSkinUrl('images/error_msg_icon.gif');
        $_imageTitle = 'Entity is out of sync! Manual synchronization required.';
        if ($inSync) {
            $_imageUrl = Mage::getDesign()->getSkinUrl('images/success_msg_icon.gif');
            $_imageTitle = 'Entity is synchronized.';
        }
        return '<img src ="' . $_imageUrl . '" title="' . $_imageTitle . '"/>';
    }
}