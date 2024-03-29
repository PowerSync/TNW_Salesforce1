<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Block_Adminhtml_Renderer_Mapping_Magento extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    public function render(Varien_Object $row)
    {
        $original = explode(":", $row->getData('local_field'));
        $data = "<strong>" . $original[0] . " :</strong> <i>" . $original[1] . "</i>";
        return $data;
    }
}