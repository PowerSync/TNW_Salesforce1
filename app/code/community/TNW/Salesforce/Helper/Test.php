<?php

/**
 * Class TNW_Salesforce_Helper_Test
 */
class TNW_Salesforce_Helper_Test extends TNW_Salesforce_Helper_Abstract
{
    /**
     * cache for the test results collection
     *
     * @var null|varien_data_collection
     */
    protected $_resultCollection = null;

    /**
     * gets the test results and stores them in the cache
     *
     * @return varien_data_collection
     */
    public function performIntegrationTests()
    {
        $_required = array('license', 'soap', 'wsdl', 'openssl', 'connection');
        if (!$this->hasResults()) {
            $results = $this->_helper('test_resultCollection');
            $breakFlag = false;
            foreach ($this->getAvailableTestClasses() as $test) {
                foreach ($results->getItems() as $_item) {
                    if (in_array($test, $_required) && $_item && $_item->response != "Success!") {
                        // if previous step fails, stop testing further
                        $breakFlag = true;
                    }
                }
                unset($_item);
                if (!$breakFlag) {
                    $results->addItem($this->_helper('test_' . $test)->performTest());
                }
            }
            $this->_resultCollection = $results;
            unset($results);
        }
        return $this->getResultCollection();
    }

    /**
     * returns true if the results collection contains any results
     *
     * @return bool
     */
    public function hasResults()
    {
        return ($this->_resultCollection != null);
    }

    /**
     * returns the results collection
     *
     * @return null|varien_data_collection
     */
    public function getResultCollection()
    {
        return $this->_resultCollection;
    }

    /**
     * returns the class names used to perform the tests
     *
     * @return array
     */
    public function getAvailableTestClasses()
    {
        $myTests = array('version', 'soap', 'wsdl', 'openssl', 'connection', 'login', 'license', 'website');
        #$myTests = array('version','wsdl','connection','login','website');
        if (Mage::helper('tnw_salesforce')->isLogEnabled()) {
            $myTests[] = 'log';
        }
        return $myTests;
    }
}