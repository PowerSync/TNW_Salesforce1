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

        /** @var TNW_Salesforce_Model_Api_Entity_Resource_Account_Collection $collection */
        $collection = Mage::getResourceModel('tnw_salesforce_api_entity/account_collection')
            ->addFieldToFilter('Name', array('like' => "%$query%"))
            ->setPageSize(TNW_Salesforce_Model_Api_Entity_Resource_Account_Collection::PAGE_SIZE)
            ->setCurPage($curPage);

        if (Mage::helper('tnw_salesforce')->usePersonAccount()) {
            $collection->getSelect()
                ->where('IsPersonAccount = false');
        }

        $result = array();
        /** @var TNW_Salesforce_Model_Api_Entity_Account $item */
        foreach ($collection as $item) {
            $result[] = array(
                'id'    => $item->getId(),
                'text'  => $item->getData('Name'),
            );
        }

        $this->_sendJson(array(
            'totalRecords' => $collection->getSize(),
            'items' => $result
        ));
    }

    public function campaignAction()
    {
        $query = $this->getRequest()->getQuery('q');
        if (empty($query)) {
            $this->_sendJson(array());
            return;
        }

        $curPage = $this->getRequest()->getQuery('page', 1);

        /** @var TNW_Salesforce_Model_Api_Entity_Resource_Campaign_Collection $collection */
        $collection = Mage::getResourceModel('tnw_salesforce_api_entity/campaign_collection')
            ->addFieldToFilter('Name', array('like' => "%$query%"))
            ->setPageSize(TNW_Salesforce_Model_Api_Entity_Resource_Account_Collection::PAGE_SIZE)
            ->setCurPage($curPage);

        $filterType = $this->getRequest()->getParam('filter');
        switch ($filterType) {
            case 'rules':
                $collection->addFieldToFilter(TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL . 'Magento_ID__c', array('eq'=>null));
                break;
        }

        $result = array();
        /** @var TNW_Salesforce_Model_Api_Entity_Account $item */
        foreach ($collection as $item) {
            $result[] = array(
                'id'    => $item->getId(),
                'text'  => $item->getData('Name'),
            );
        }

        $this->_sendJson(array(
            'totalRecords' => $collection->getSize(),
            'items' => $result
        ));
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