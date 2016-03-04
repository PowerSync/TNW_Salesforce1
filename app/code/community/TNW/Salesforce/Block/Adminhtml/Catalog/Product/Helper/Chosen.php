<?php

/**
 * Author: Tech-N-Web, LLC (dba PowerSync)
 * Email: support@powersync.biz
 * Developer: Evgeniy Ermolaev
 * Date: 18.09.15
 * Time: 15:01
 */

class TNW_Salesforce_Block_Adminhtml_Catalog_Product_Helper_Chosen extends Varien_Data_Form_Element_Select
{
    /**
     * add chosen css class
     *
     * @param array $attributes
     */
    public function __construct($attributes = array())
    {
        parent::__construct($attributes);

        if (!isset($this->_data['class'])) {
            $this->_data['class'] = '';
        }
        $this->_data['class'] .= ' chosen-select ';

    }

    /**
     * hide salesforce_campaign_id for non-Enterprise version
     * @return mixed|string
     */
    public function getAfterElementHtml()
    {
        $html = parent::getAfterElementHtml();
        if ($this->getName() == 'salesforce_campaign_id' && Mage::helper('tnw_salesforce')->getType() != 'PRO') {
            $html .= "  <script>
        				$('" . $this->getHtmlId() . "').up().up().hide();
        				</script>";
        }
        return $html;
    }

}
