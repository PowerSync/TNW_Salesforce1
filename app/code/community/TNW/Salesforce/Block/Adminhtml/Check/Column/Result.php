<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Block_Adminhtml_Check_Column_Result extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{


    public function _getValue(Varien_Object $row)
    {
        $data = parent::_getValue($row);
        $styles = array(
            'background-color:transparent',
            'background-position:3px 1px',
            'border:0px solid', 'display:block',
            'width:23px',
        );

        return '<span class="' . $data . '" style="' . implode(' !important;', $styles) . '">&nbsp</span>';
    }


}