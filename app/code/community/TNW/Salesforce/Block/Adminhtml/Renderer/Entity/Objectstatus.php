<?php

/**
 * Class TNW_Salesforce_Block_Adminhtml_Renderer_Entity_Objectstatus
 */
class TNW_Salesforce_Block_Adminhtml_Renderer_Entity_Objectstatus extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    /**
     * return icon html
     *
     * @param Varien_Object $row
     * @return string
     */
    public function render(Varien_Object $row)
    {
        $status = $row->getData('status');
        switch ($status) {
            case "new":
                $html = '';
                break;
            case "sync_error":
                $imageUrl = Mage::getDesign()->getSkinUrl('images/error_msg_icon.gif');
                $imageTitle = "Object failed synchronization";
                $html = "<img src = '$imageUrl' title='$imageTitle' />";
                break;
            case "success":
                $imageUrl = Mage::getDesign()->getSkinUrl('images/success_msg_icon.gif');
                $imageTitle = "Object was synchronized successfully";
                $html = "<img src = '$imageUrl' title='$imageTitle' />";
                break;
            case "sync_running":
                $imageUrl = Mage::getDesign()->getSkinUrl('images/preloader-sm.gif');
                $imageTitle = "Object is synchronizing currently";
                $html = "<img src = '$imageUrl' title='$imageTitle' />";
                break;
        }

        return $html;
    }
}