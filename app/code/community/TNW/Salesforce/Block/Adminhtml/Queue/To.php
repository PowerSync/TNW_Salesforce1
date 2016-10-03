<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Block_Adminhtml_Queue_To extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    const TYPE_OUTGOING = 'outgoing';
    const TYPE_BULK     = 'bulk';

    /**
     * TNW_Salesforce_Block_Adminhtml_Queue_To constructor.
     */
    public function __construct()
    {
        $this->_blockGroup = 'tnw_salesforce';
        $this->_controller = 'adminhtml_queue_to';
        parent::__construct();
        $this->removeButton('add');
    }

    /**
     * @return string
     */
    protected function getType()
    {
        $type = $this->getData('type');
        switch ($type) {
            case self::TYPE_BULK:
                $this->setData('type', self::TYPE_BULK);
                break;

            case self::TYPE_OUTGOING:
            default:
                $this->setData('type', self::TYPE_OUTGOING);
                break;
        }

        return $this->getData('type');
    }

    /**
     * @return $this
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();

        $collection = Mage::getResourceModel('tnw_salesforce/queue_storage_collection');
        if ($this->getType() == self::TYPE_BULK) {
            $collection->addFieldToFilter('sync_type', array('eq' => TNW_Salesforce_Model_Cron::SYNC_TYPE_BULK));
        }
        else {
            $collection->addFieldToFilter('sync_type', array('eq' => TNW_Salesforce_Model_Cron::SYNC_TYPE_OUTGOING));
        }

        /** @var TNW_Salesforce_Block_Adminhtml_Queue_To_Grid $block */
        $block = $this->getChild('grid');
        $block->setCollection($collection);
        return $this;
    }

    /**
     * Get header text
     *
     * @return string
     */
    public function getHeaderText()
    {
        if ($this->getType() == self::TYPE_BULK) {
            return Mage::helper('tnw_salesforce')->__('TO Salesforce (background)');
        }

        return Mage::helper('tnw_salesforce')->__('TO Salesforce');
    }
}