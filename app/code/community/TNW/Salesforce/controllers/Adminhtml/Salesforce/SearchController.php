<?php

class TNW_Salesforce_Adminhtml_Salesforce_SearchController extends Mage_Adminhtml_Controller_Action
{
    public function accountAction()
    {
        $query = $this->getRequest()->getQuery('q');
        if (empty($query)) {
            $this->_sendJson(array());
            return;
        }

        $curPage = $this->getRequest()->getQuery('page', 1);
        $result  = Mage::getSingleton('tnw_salesforce/sforce_entity_cache')
            ->searchByName($query, TNW_Salesforce_Model_Sforce_Entity_Cache::CACHE_TYPE_ACCOUNT, $curPage);

        $this->_sendJson($result);
    }

    public function campaignAction()
    {
        $query = $this->getRequest()->getQuery('q');
        if (empty($query)) {
            $this->_sendJson(array());
            return;
        }

        $curPage = $this->getRequest()->getQuery('page', 1);
        $result  = Mage::getSingleton('tnw_salesforce/sforce_entity_cache')
            ->searchByName($query, TNW_Salesforce_Model_Sforce_Entity_Cache::CACHE_TYPE_CAMPAIGN, $curPage);

        $this->_sendJson($result);
    }

    public function userAction()
    {
        $query = $this->getRequest()->getQuery('q');
        if (empty($query)) {
            $this->_sendJson(array());
            return;
        }

        $curPage = $this->getRequest()->getQuery('page', 1);
        $result  = Mage::getSingleton('tnw_salesforce/sforce_entity_cache')
            ->searchByName($query, TNW_Salesforce_Model_Sforce_Entity_Cache::CACHE_TYPE_USER, $curPage);

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