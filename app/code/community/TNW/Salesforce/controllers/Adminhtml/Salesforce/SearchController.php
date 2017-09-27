<?php

class TNW_Salesforce_Adminhtml_Salesforce_SearchController extends Mage_Adminhtml_Controller_Action
{

    /**
     * @return bool
     */
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')
            ->isAllowed('tnw_salesforce');
    }

    public function accountAction()
    {
        $website = $this->getRequest()->getParam('website');
        $curPage = $this->getRequest()->getQuery('page', 1);
        $query = $this->getRequest()->getQuery('q');

        if (empty($query)) {
            $this->_sendJson(array());
            return;
        }

        $result = Mage::helper('tnw_salesforce/config')->wrapEmulationWebsiteDifferentConfig($website, function () use($query, $curPage) {
            return Mage::getSingleton('tnw_salesforce/sforce_entity_cache')
                ->searchByName($query, TNW_Salesforce_Model_Sforce_Entity_Cache::CACHE_TYPE_ACCOUNT, $curPage);
        });

        $this->_sendJson($result);
    }

    public function campaignAction()
    {
        $website = $this->getRequest()->getParam('website');
        $curPage = $this->getRequest()->getQuery('page', 1);
        $query = $this->getRequest()->getQuery('q');

        if (empty($query)) {
            $this->_sendJson(array());
            return;
        }

        $result = Mage::helper('tnw_salesforce/config')->wrapEmulationWebsiteDifferentConfig($website, function () use($query, $curPage) {
            return Mage::getSingleton('tnw_salesforce/sforce_entity_cache')
                ->searchByName($query, TNW_Salesforce_Model_Sforce_Entity_Cache::CACHE_TYPE_CAMPAIGN, $curPage);
        });

        $this->_sendJson($result);
    }

    public function userAction()
    {
        $website = $this->getRequest()->getParam('website');
        $curPage = $this->getRequest()->getQuery('page', 1);
        $query = $this->getRequest()->getQuery('q');

        if (empty($query)) {
            $this->_sendJson(array());
            return;
        }

        $result  = Mage::helper('tnw_salesforce/config')->wrapEmulationWebsiteDifferentConfig($website, function () use($query, $curPage) {
            return Mage::getSingleton('tnw_salesforce/sforce_entity_cache')
                ->searchByName($query, TNW_Salesforce_Model_Sforce_Entity_Cache::CACHE_TYPE_USER, $curPage);
        });

        $this->_sendJson($result);
    }

    /**
     * @param $json
     */
    private function _sendJson($json)
    {
        $jsonData = Zend_Json::encode($json);
        $this->getResponse()
            ->setHeader('Content-type', 'application/json')
            ->setBody($jsonData);
    }
}