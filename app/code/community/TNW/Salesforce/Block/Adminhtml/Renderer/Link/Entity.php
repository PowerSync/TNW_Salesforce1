<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Block_Adminhtml_Renderer_Link_Entity extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Action
{

    /**
     * Prepares action data for html render
     *
     * @param array $action
     * @param string $actionCaption
     * @param Varien_Object $row
     * @return Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Action
     */
    protected function _transformActionData(&$action, &$actionCaption, Varien_Object $row)
    {
        $this->getColumn()->setFormat(null);
        $actionCaption = $this->_getValue($row);

        if ($action['url']) {
            if (is_array($action['url'])) {

                $value = $this->_getValue($row);
                if ($getter = $action['getter']) {
                    $value = $row->$getter();
                }

                $params = array($action['field'] => $value);

                if (isset($action['url']['params'])) {
                    $params = array_merge($action['url']['params'], $params);
                }
                $action['href'] = $this->getUrl($action['url']['base'], $params);
                unset($action['field']);
            } else {
                $action['href'] = $action['url'];
            }
            unset($action['url']);
        }

        parent::_transformActionData($action, $actionCaption, $row);

        return $this;
    }
}