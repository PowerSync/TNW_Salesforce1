<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Block_Adminhtml_Check_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    /**
     * @param string $html
     * @return string
     */
    protected function _afterToHtml($html)
    {
        if (Mage::helper('tnw_salesforce/test')->hasResults()) {
            return $html;
        }
    }

    /**
     * prepare the collection of posts to display
     *
     * @return mage_adminhtml_block_widget_grid
     */
    protected function _prepareCollection()
    {
        $collection = $this->_getResultCollection();
        $this->setCollection($collection);

        return parent::_prepareCollection();
    }

    /**
     * @return mixed
     */
    protected function _getResultCollection()
    {
        return Mage::helper('tnw_salesforce/test')->performIntegrationTests();
    }

    /**
     * prepares the columns of the grid
     *
     * @return $this
     */
    protected function _prepareColumns()
    {
        $this->addResultColumn();

        $this->addColumn('title', array(
            'header' => 'Test Title',
            'index' => 'title',
            'width' => '260px',
        ));

        $this->addColumn('response', array(
            'header' => 'Server Response',
            'index' => 'response',
            'format' => '$response'
        ));

        return parent::_prepareColumns();
    }

    /**
     * @return $this
     */
    protected function addResultColumn()
    {
        $column = $this->addColumn('result', array('header' => '&nbsp;', 'index' => 'result', 'width' => '23px'))->getColumn('result');
        $render = $this->getLayout()->createBlock('tnw_salesforce/adminhtml_check_column_result')->setColumn($column);
        $column->setRenderer($render);

        return $this;
    }

    /**
     * @return Mage_Core_Block_Abstract
     */
    protected function _prepareLayout()
    {
        $result = parent::_prepareLayout();

        $this->unsetChild('reset_filter_button');
        $this->unsetChild('search_button');
        $this->unsetChild('export_button');

        $this->_pagerVisibility = false;
        $this->_filterVisibility = false;

        return $result;
    }

    /**
     * @param $row
     * @return bool|string
     */
    public function getRowUrl($row)
    {
        if ($row->redirect) {
            return $row->redirect;
        }

        return false;
    }
}