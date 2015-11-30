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

    public function getElementHtml() {

        $html = $this->getValue();
        if ($this->getData('nl2br') == true) {
            $html = nl2br($html);
        }

        return $html;
    }

}