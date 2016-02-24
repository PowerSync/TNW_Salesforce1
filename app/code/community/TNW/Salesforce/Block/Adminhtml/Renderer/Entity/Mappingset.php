<?php

/**
 * Class TNW_Salesforce_Block_Adminhtml_Renderer_Entity_Status
 */
class TNW_Salesforce_Block_Adminhtml_Renderer_Entity_Mappingset extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    /**
     * Direction type
     */
    const SYNC_DIRECTION_SF_MAGENTO = 'magento_sf';
    const SYNC_DIRECTION_MAGENTO_SF = 'sf_magento';

    /**
     * @var string
     */
    protected $_sync_direction = self::SYNC_DIRECTION_MAGENTO_SF;

    /**
     * @param $_direction
     * @return $this
     */
    public function setDirection($_direction)
    {
        $this->_sync_direction = $_direction;
        return $this;
    }

    /**
     * @param Varien_Object $row
     * @return string
     */
    public function render(Varien_Object $row)
    {
        if (!$row instanceof TNW_Salesforce_Model_Mapping) {
            return '';
        }

        $_enableField = $this->_sync_direction == self::SYNC_DIRECTION_MAGENTO_SF
            ? 'magento_sf_enable' : 'sf_magento_enable';

        $_setTypeField = $this->_sync_direction == self::SYNC_DIRECTION_MAGENTO_SF
            ? 'magento_sf_type' : 'sf_magento_type';

        $inSync = $row->getData($_enableField);
        $_imageUrl = ($inSync)
            ? Mage::getDesign()->getSkinUrl('images/success_msg_icon.gif')
            : Mage::getDesign()->getSkinUrl('images/error_msg_icon.gif');

        $_imageTitle = ($inSync)
            ? 'Enable' : 'Disable';

        $_setType = $row->getNameBySetType($row->getData($_setTypeField));
        $_setTypeHtml = ($inSync) ? "<span style=\"vertical-align: inherit\">$_setType</span>" : '';
        return "<img src=\"$_imageUrl\" title=\"$_imageTitle\"/>$_setTypeHtml";
    }
}