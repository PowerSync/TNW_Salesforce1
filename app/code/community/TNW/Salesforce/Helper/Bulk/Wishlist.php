<?php

class TNW_Salesforce_Helper_Bulk_Wishlist extends TNW_Salesforce_Helper_Salesforce_Wishlist
{
    /**
     * @param string $type
     * @return bool
     */
    public function process($type = 'soft')
    {
        /**
         * @comment apply bulk server settings
         */
        $this->getServerHelper()->apply(TNW_Salesforce_Helper_Config_Server::BULK);

        $result = parent::process($type);

        /**
         * @comment restore server settings
         */
        $this->getServerHelper()->apply();

        return $result;
    }

    /**
     * @throws Exception
     */
    protected function _pushEntity()
    {

    }

    /**
     * @param array $chunk
     * @throws Exception
     */
    protected function _pushEntityItems($chunk = array())
    {

    }

    /**
     * @return bool
     */
    public function reset()
    {
        parent::reset();

        $this->_cache['bulkJobs'] = array(
            $this->_magentoEntityName       => array('Id' => NULL),
            lcfirst($this->getItemsField()) => array('Id' => NULL),
        );

        $this->_cache['batch'] = array();
        $this->_cache['batchCache'] = array();

        return $this->check();
    }

    /**
     *
     */
    protected function _onComplete()
    {
        // Close Jobs
        if ($this->_cache['bulkJobs'][$this->_magentoEntityName]['Id']) {
            $this->_closeJob($this->_cache['bulkJobs'][$this->_magentoEntityName]['Id']);

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace("Closing job: {$this->_cache['bulkJobs'][$this->_magentoEntityName]['Id']}");
        }

        if ($this->_cache['bulkJobs'][lcfirst($this->getItemsField())]['Id']) {
            $this->_closeJob($this->_cache['bulkJobs'][lcfirst($this->getItemsField())]['Id']);

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace("Closing job: {$this->_cache['bulkJobs'][lcfirst($this->getItemsField())]['Id']}");
        }

        parent::_onComplete();
    }
}