<?php

/**
 * Class TNW_Salesforce_Helper_Salesforce_Abstract
 */
class TNW_Salesforce_Helper_Salesforce_Abstract
{
    protected $_salesforceApiVersion = '34.0';
    /**
     * @var null
     */
    protected $_mageCache = NULL;

    /**
     * @var bool
     */
    protected $_useCache = false;

    /**
     * reference to salesforce connection
     *
     * @var null
     */
    public $_mySforceConnection = NULL;

    /**
     * @var null
     */
    protected $_obj = NULL;

    /**
     * @var null
     */
    protected $_write = NULL;

    /**
     * @var null
     */
    protected $_magentoId = NULL;

    /**
     * @var array
     */
    protected $_currencies = array();

    /**
     * cached data
     * @comment public access for Passing by Reference
     *
     * @var null
     */
    public $_cache = NULL;

    /**
     * @var array
     */
    protected $_attributes = array();

    /**
     * @var null
     */
    protected $_customerEntityTypeCode = NULL;

    /**
     * @var null
     */
    protected $_productEntityTypeCode = NULL;

    /**
     * @var int
     */
    protected $_maxBatchLimit = 0;

    /**
     * @var Zend_Http_Client
     */
    protected $_client = NULL;

    /**
     * @var array
     */
    protected $_allResults = array();

    /**
     * @var bool
     */
    protected $_isFromCLI = false;

    /**
     * @var bool
     */
    protected $_isCron = false;

    /**
     * @var bool
     */
    protected $_stopFurtherProcessing = false;

    /**
     * @var null
     */
    protected $_salesforceSessionId = NULL;

    /**
     * @var null
     */
    protected $_salesforceServerDomain = NULL;

    /**
     * @var null
     */
    protected $_prefix = NULL;

    /**
     * @var array
     */
    protected $_websiteSfIds = array();

    /**
     * Store sync results before we clear cache
     * @var array
     */
    protected $_syncedResults = array();

    /**
     * @comment Contains Server configuration helper
     * @var TNW_Salesforce_Helper_Config_Server
     */
    protected $_serverHelper;

    /**
     * Initialize cache
     */
    public function _initCache()
    {
        $this->_mageCache = Mage::app()->getCache();
        $this->_useCache = Mage::app()->useCache('tnw_salesforce');
    }

    /**
     * test for product integration flag,
     * try to extract the salesforce connection from the helper, if not available
     * we instantiate another salesforce connection
     */
    protected function checkConnection()
    {
        if (!$this->_mySforceConnection) {
            $this->_mySforceConnection = Mage::getSingleton('tnw_salesforce/connection')->getClient();
        }
    }

    public function setSalesforceServerDomain($_value = NULL)
    {
        $this->_salesforceServerDomain = $_value;
    }

    /**
     * @return null
     */
    public function getSalesforceServerDomain()
    {
        return $this->_salesforceServerDomain;
    }

    public function setSalesforceSessionId($_value = NULL)
    {
        $this->_salesforceSessionId = $_value;
    }

    /**
     * @return null
     */
    public function getSalesforceSessionId()
    {
        return $this->_salesforceSessionId;
    }

    public function setIsFromCLI($_value = false)
    {
        $this->_isFromCLI = $_value;
    }

    /**
     * @return bool
     */
    public function isFromCLI()
    {
        return $this->_isFromCLI;
    }

    public function setIsCron($_value = false)
    {
        $this->_isCron = $_value;
    }

    /**
     * @return bool
     */
    public function isCron()
    {
        return $this->_isCron;
    }

    /**
     * @return array
     */
    public function getResults()
    {
        return $this->_allResults;
    }

    /**
     * @return bool
     */
    protected function check()
    {
        if (
            !Mage::helper('tnw_salesforce')->isWorking()
            || Mage::getSingleton('core/session')->getFromSalesForce()
        ) {
            return false;
        }

        $this->checkConnection();
        if (!$this->_mySforceConnection) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveNotice("SKIPPING: Salesforce connection failed!");
            return false;
        }

        if ($this->_stopFurtherProcessing) {
            return false;
        }

        return true;
    }

    /**
     * @param string $_obj
     * @param string $_operation
     * @param string $_externalId
     *
     * @throws Exception
     *
     * @return null|string
     */
    public function _createJob($_obj = NULL, $_operation = 'upsert', $_externalId = NULL)
    {
        if (!$this->getSalesforceSessionId()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: Salesforce connection failed, bulk API session ID is invalid!");
            return NULL;
        }
        if (!$this->getSalesforceServerDomain()) {
            $this->_getSalesforceDomainFromSession();

            if (!$this->getSalesforceServerDomain()) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: Salesforce connection failed, bulk API domain is not set!");
                return NULL;
            }
        }

        $this->_client->setUri($this->getSalesforceServerDomain() . '/services/async/' . $this->_salesforceApiVersion . '/job');
        $this->_client->setMethod('POST');
        $this->_client->setHeaders('Content-Type: application/xml');
        $this->_client->setHeaders('X-SFDC-Session', $this->getSalesforceSessionId());

        $_data = '<?xml version="1.0" encoding="UTF-8"?>
            <jobInfo xmlns="http://www.force.com/2009/06/asyncapi/dataload">
                <operation>' . $_operation . '</operation>
                <object>' . $_obj . '</object>';
        if ($_externalId) {
            $_data .= '<externalIdFieldName>' . $_externalId . '</externalIdFieldName>';
        } else {
            $_data .= '<concurrencyMode>Parallel</concurrencyMode>';

        }
        $_data .= '<contentType>XML</contentType>
            </jobInfo>';


        $this->_client->setRawData($_data);

        $response = $this->_client->request()->getBody();
        $_jobInfo = simplexml_load_string($response);

        if (isset($_jobInfo->exceptionMessage)) {
            throw new Exception('Cannot find job id:' . $_jobInfo->exceptionMessage);
        } elseif (!isset($_jobInfo->id)) {
            throw new Exception('Cannot find job id');
        }

        return substr($_jobInfo->id, 0, -3);
    }

    /**
     * divide data to butches and send to sf
     *
     * @param null $_jobId
     * @param $_batchType
     * @param array $_entities
     * @param string $_on
     * @return bool
     */
    protected function _pushChunked($_jobId = NULL, $_batchType, $_entities = array(), $_on = 'Id')
    {
        if (!empty($_entities) && $_jobId) {
            if (!array_key_exists($_batchType, $this->_cache['batch'])) {
                $this->_cache['batch'][$_batchType] = array();
            }
            if (!array_key_exists($_on, $this->_cache['batch'][$_batchType])) {
                $this->_cache['batch'][$_batchType][$_on] = array();
            }
            $_ttl = count($_entities); // 33
            $_success = true;
            if ($_ttl > $this->_maxBatchLimit) {
                $_steps = ceil($_ttl / $this->_maxBatchLimit);
                if ($_steps == 0) {
                    $_steps = 1;
                }
                for ($_i = 0; $_i < $_steps; $_i++) {
                    $_start = $_i * $this->_maxBatchLimit;
                    $_itemsToPush = array_slice($_entities, $_start, $this->_maxBatchLimit, true);
                    if (!array_key_exists($_i, $this->_cache['batch'][$_batchType][$_on])) {
                        $this->_cache['batch'][$_batchType][$_on][$_i] = array();
                    }
                    // send data to sf
                    $_success = $this->_pushSegment($_jobId, $_batchType, $_itemsToPush, $_i, $_on);
                }
            } else {
                if (!array_key_exists(0, $this->_cache['batch'][$_batchType][$_on])) {
                    $this->_cache['batch'][$_batchType][$_on][0] = array();
                }
                // send data to sf
                $_success = $this->_pushSegment($_jobId, $_batchType, $_entities, 0, $_on);
            }
            if (!$_success) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError('ERROR: ' . uc_words($_batchType) . ' upsert failed');
                return false;
            }
        }

        return true;
    }

    /**
     * @param null $_jobId
     * @return bool
     */
    protected function _closeJob($_jobId = NULL)
    {
        $this->_client->setUri($this->getSalesforceServerDomain() . '/services/async/' . $this->_salesforceApiVersion . '/job/' . $_jobId);
        $this->_client->setMethod('POST');
        $this->_client->setHeaders('Content-Type: application/xml');
        $this->_client->setHeaders('X-SFDC-Session', $this->getSalesforceSessionId());

        $_data = '<?xml version="1.0" encoding="UTF-8"?>
            <jobInfo xmlns="http://www.force.com/2009/06/asyncapi/dataload">
                <state>Closed</state>
            </jobInfo>';

        $this->_client->setRawData($_data);

        $_state = false;
        try {
            $response = simplexml_load_string($this->_client->request()->getBody());

            if ((string)$response->state == "Closed") {
                $_state = true;
            }
        } catch (Exception $e) {
            // TODO:  Log error, quit
        }
        return $_state;
    }

    /**
     * creating a batch and send it to sf
     *
     * @param $_jobId
     * @param $_batchType
     * @param array $chunk
     * @param int $_batchNum
     * @param string $_on
     * @return bool
     */
    protected function _pushSegment($_jobId, $_batchType, $chunk = array(), $_batchNum = 0, $_on = 'Id')
    {
        if (empty($chunk)) {
            return false;
        }
        if (
            array_key_exists($_batchType, $this->_cache['batchCache'])
            && array_key_exists($_batchNum, $this->_cache['batchCache'][$_batchType])
            && !empty($this->_cache['batchCache'][$_batchType][$_batchNum])
        ) {
            return true; // Already processed
        }
        $_batchId = NULL;

        $this->_client->setUri($this->getSalesforceServerDomain() . '/services/async/' . $this->_salesforceApiVersion . '/job/' . $_jobId . '/batch');
        $this->_client->setMethod('POST');
        $this->_client->setHeaders('Content-Type: application/xml');
        $this->_client->setHeaders('X-SFDC-Session', $this->getSalesforceSessionId());

        $_data = '<?xml version="1.0" encoding="UTF-8"?>
            <sObjects xmlns="http://www.force.com/2009/06/asyncapi/dataload">';

        foreach ($chunk as $_item) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("+++++ Start " . ucwords($_batchType) . " Object +++++");
            $_data .= '<sObject>';
            foreach ($_item as $_tag => $_value) {
                $_data .= '<' . $_tag . '><![CDATA[' . $_value . ']]></' . $_tag . '>';
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace(ucwords($_batchType) . " - " . $_tag . " : " . $_value);
            }
            $_data .= '</sObject>';
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("+++++ End " . ucwords($_batchType) . " Object +++++");
        }

        $_data .= '</sObjects>';
        $this->_client->setRawData($_data);

        try {
            $response = $this->_client->request()->getBody();
            $_batchInfo = simplexml_load_string($response);

            $_batchId = substr($_batchInfo->id, 0, -3);
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace(ucwords($_batchType) . ' batch was created, batch number: ' . $_batchId);
            // Update Batches cache
            if (!array_key_exists($_batchType, $this->_cache['batchCache'])) {
                $this->_cache['batchCache'][$_batchType] = array();
            }
            if (!array_key_exists($_on, $this->_cache['batchCache'][$_batchType])) {
                $this->_cache['batchCache'][$_batchType][$_on] = array();
            }
            $this->_cache['batchCache'][$_batchType][$_on][$_batchNum] = $_batchId;

            $this->_cache['batch'][$_batchType][$_on][$_batchNum] = $chunk;
            return true;
        } catch (Exception $e) {
            // TODO:  Log error, quit
            return false;
        }
    }

    /**
     * check batch info
     *
     * @param null $jobId
     * @param null $batchId
     * @return SimpleXMLElement|string
     */
    public function getBatch($jobId = NULL, $batchId = NULL)
    {
        // Check for update on all batches
        $this->_client->setUri($this->getSalesforceServerDomain() . '/services/async/' . $this->_salesforceApiVersion . '/job/' . $jobId . '/batch/' . $batchId . '/result');
        $this->_client->setMethod('GET');
        $this->_client->setHeaders('Content-Type: application/xml');
        $this->_client->setHeaders('X-SFDC-Session', $this->getSalesforceSessionId());
        try {
            $response = simplexml_load_string($this->_client->request()->getBody());
        } catch (Exception $e) {
            // TODO:  Log error, quit
            $response = $e->getMessage();
        }
        return $response;
    }

    /**
     * check batch info
     *
     * @param null $jobId
     * @param null $batchId
     * @param null $_resultId
     * @return SimpleXMLElement|string
     */
    public function getBatchResult($jobId = NULL, $batchId = NULL, $_resultId = NULL)
    {
        // Check for update on all batches
        $this->_client->setUri($this->getSalesforceServerDomain() . '/services/async/' . $this->_salesforceApiVersion . '/job/' . $jobId . '/batch/' . $batchId . '/result/' . $_resultId);
        $this->_client->setMethod('GET');
        $this->_client->setHeaders('Content-Type: application/xml');
        $this->_client->setHeaders('X-SFDC-Session', $this->getSalesforceSessionId());
        try {
            $response = new SimpleXMLElement($this->_client->request()->getBody());
        } catch (Exception $e) {
            // TODO:  Log error, quit
            $response = $e->getMessage();
        }
        return $response;
    }

    /**
     * check if all batches are complete
     * if all batches completed - we return true
     * if not all batches completed - we return false
     * if exception occurs - we return 'exception'
     *
     * @param string $jobId
     *
     * @return bool|string
     */
    protected function _checkBatchCompletion($jobId)
    {
        $completed = true;
        $logHelper = Mage::helper('tnw_salesforce');
        $client = $this->_client;

        // check for update on all batches
        try {
            $client->setUri(sprintf('%s/services/async/%s/job/%s/batch',
                $this->getSalesforceServerDomain(), $this->_salesforceApiVersion, $jobId));
            $client->setMethod('GET');
            $client->setHeaders('Content-Type: text/csv');
            $client->setHeaders('X-SFDC-Session', $this->getSalesforceSessionId());

            $response = simplexml_load_string($client->request()->getBody());
            foreach ($response as $_responseRow) {
                if (property_exists($_responseRow, 'state')) {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('INFO: State: ' . $_responseRow->state);
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('INFO: Batch ID: ' . $_responseRow->id);
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('INFO: RecordsProcessed: ' . $_responseRow->numberRecordsProcessed);
                    if ('Failed' == $_responseRow->state) {
                        $completed = 'exception';
                        break;
                        // completed but failed
                    } elseif ('Completed' != $_responseRow->state) {
                        $completed = false;
                        break;
                        // try later
                    }
                } else {
                    $completed = false;
                    break;
                }
            }
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('_checkBatchCompletion function has an error: ' . $e->getMessage());
            $completed = 'exception';
        }

        return $completed;
    }

    /**
     * @param $_response
     * @param string $type
     */
    protected function _processErrors($_response, $type = 'order', $_object = NULL)
    {
        $objectStr = var_export($_object, true);
        if (is_array($_response->errors)) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError('Failed to upsert ' . $type . '! ');
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace($objectStr);
            foreach ($_response->errors as $_error) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $_error->message);
            }
        } else {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError('CRITICAL ERROR: Failed to upsert ' . $type . ': ' . $_response->errors->message . '. Object dump: ' . $objectStr);
        }
    }

    public function getCurrencies()
    {
        if (empty($this->_currencies)) {
            // Set all currencies
            foreach (Mage::app()->getStores() as $storeId => $store) {
                $this->_currencies[$storeId] = $store->getCurrentCurrencyCode();
            }
        }
        return $this->_currencies;
    }

    public function clearMemory()
    {
        set_time_limit(1000);
        Mage::helper('tnw_salesforce')->clearMemory();
    }

    protected function _getSalesforceDomainFromSession()
    {
        if (Mage::getSingleton('core/session')->getSalesforceServerUrl()) {
            $instance_url = explode('/', Mage::getSingleton('core/session')->getSalesforceServerUrl());
            Mage::getSingleton('core/session')->setSalesforceServerDomain('https://' . $instance_url[2]);
            $this->setSalesforceServerDomain('https://' . $instance_url[2]);
        }
    }

    protected function reset()
    {
        $this->_initCache();

        if (!$this->_magentoId) {
            $this->_magentoId = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . "Magento_ID__c";
        }

        if (!$this->_write) {
            $this->_write = Mage::getSingleton('core/resource')->getConnection('core_write');
        }
        Mage::getSingleton('core/session')->setFromSalesForce(false);

        $this->_maxBatchLimit = 10000;

        if (!$this->getSalesforceServerDomain()) {
            $this->_getSalesforceDomainFromSession();
        } else {
            Mage::getSingleton('core/session')->setSalesforceServerDomain($this->getSalesforceServerDomain());
        }

        $sql = "SELECT * FROM `" . Mage::helper('tnw_salesforce')->getTable('eav_entity_type') . "` WHERE entity_type_code = 'customer'";
        $row = $this->_write->query($sql)->fetch();
        $this->_customerEntityTypeCode = ($row) ? (int)$row['entity_type_id'] : NULL;

        $sql = "SELECT * FROM `" . Mage::helper('tnw_salesforce')->getTable('eav_entity_type') . "` WHERE entity_type_code = 'catalog_product'";
        $row = $this->_write->query($sql)->fetch();
        $this->_productEntityTypeCode = ($row) ? (int)$row['entity_type_id'] : NULL;

        $this->_client = $this->getHttpClient();
        $this->_client->setConfig(
            array(
                'maxredirects' => 0,
                'timeout' => 10,
                'keepalive' => true,
                'storeresponse' => true,
            )
        );

        $this->_fillWebsiteSfIds();

        Mage::getSingleton('tnw_salesforce/tool_log_mail')->send();
    }

    public function getHttpClient()
    {
        $client = new Zend_Http_Client();
        return $client;

    }

    protected function _fillWebsiteSfIds()
    {
        $websiteHelper = Mage::helper('tnw_salesforce/magento_websites');
        $website = Mage::getModel('core/website')->load(0);
        $this->_websiteSfIds[0] = $websiteHelper->getWebsiteSfId($website);
        foreach (Mage::app()->getWebsites() as $website) {
            Mage::helper('tnw_salesforce/salesforce_website');
            $websiteSfId = $websiteHelper->getWebsiteSfId($website);
            $this->_websiteSfIds[$website->getData('website_id')] = $websiteSfId;
            if (empty($websiteSfId)) {
                $this->_stopFurtherProcessing = true;
            }
        }
    }

    /**
     * @param $array
     * @param $type
     * @param null $isError
     */
    protected function _dumpObjectToLog($array, $type, $isError = NULL)
    {
        if (is_object($array)) {
            $array = (array)$array;
        }
        if (empty($array)) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Could not dump object: " . $type . " - it's empty");
            return;
        }
        if ($isError) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("~~~~~~~~~~~~ Dumping Object: ~~~~~~~~~~~~~");
        }
        /* Dump object into the log */
        foreach ($array as $k => $_obj) {
            if ($isError) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Entity Key: " . $k);
            } else {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Entity Key: " . $k);
            }
            if (empty($_obj) || (!is_array($_obj) && !is_object($_obj))) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError($type . " Object is empty!");
            } else {
                foreach ($_obj as $_key => $_value) {
                    if (is_object($_value) || is_array($_value)) {
                        foreach ($_value as $k1 => $v1) {
                            if ($isError) {
                                Mage::getSingleton('tnw_salesforce/tool_log')->saveError($type . " Object: " . $k1 . " = '" . $v1 . "'");
                            } else {
                                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace($type . " Object: " . $k1 . " = '" . $v1 . "'");
                            }
                        }
                    } else {
                        if ($isError) {
                            Mage::getSingleton('tnw_salesforce/tool_log')->saveError($type . " Object: " . $_key . " = '" . $_value . "'");
                        } else {
                            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace($type . " Object: " . $_key . " = '" . $_value . "'");
                        }
                    }
                }
            }
            if ($isError) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError("=====================");
            } else {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("=====================");
            }
        }
        if ($isError) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~");
        }
    }

    /**
     * @param null $_query
     * @param null $jobId
     * @return null|string
     */
    public function _query($_query = NULL, $jobId = NULL)
    {
        if (!$this->getSalesforceSessionId()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: Salesforce connection failed, bulk API session ID is invalid!");
            return NULL;
        }
        $this->_client->setUri($this->getSalesforceServerDomain() . '/services/async/' . $this->_salesforceApiVersion . '/job/' . $jobId . '/batch');
        $this->_client->setMethod('POST');
        $this->_client->setHeaders('Content-Type: application/xml');
        $this->_client->setHeaders('X-SFDC-Session', $this->getSalesforceSessionId());

        $this->_client->setRawData($_query);

        try {
            $response = $this->_client->request()->getBody();
            $_batchInfo = simplexml_load_string($response);

            $_batchId = substr($_batchInfo->id, 0, -3);
            return $_batchId;
        } catch (Exception $e) {
            // TODO:  Log error, quit
        }
    }

    /**
     * @param null $_obj
     * @return null|string
     */
    public function _createJobQuery($_obj = NULL)
    {
        if (!$this->getSalesforceSessionId()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: Salesforce connection failed, bulk API session ID is invalid!");
            return NULL;
        }
        $this->_client->setUri($this->getSalesforceServerDomain() . '/services/async/' . $this->_salesforceApiVersion . '/job');
        $this->_client->setMethod('POST');
        $this->_client->setHeaders('Content-Type: application/xml');
        $this->_client->setHeaders('X-SFDC-Session', $this->getSalesforceSessionId());

        $_data = '<?xml version="1.0" encoding="UTF-8"?>
                    <jobInfo xmlns="http://www.force.com/2009/06/asyncapi/dataload">
                        <operation>query</operation>
                        <object>' . $_obj . '</object>
                        <concurrencyMode>Parallel</concurrencyMode>
                        <contentType>XML</contentType>
                    </jobInfo>
        ';

        $this->_client->setRawData($_data);

        try {
            $response = $this->_client->request()->getBody();
            $_jobInfo = simplexml_load_string($response);
            return substr($_jobInfo->id, 0, -3);
        } catch (Exception $e) {
            // TODO:  Log error, quit
        }
    }

    /**
     * @param null $_text
     * @param string $_statusCode
     * @return stdClass
     */
    protected function _buildErrorResponse($_text = NULL, $_statusCode = 'POWERSYNC_EXCEPTION')
    {
        if ($this->_mageCache === NULL) {
            $this->_initCache();
        }

        $_orgId = NULL;
        if ($this->_useCache) {
            $_orgId = $this->_mageCache->load("tnw_salesforce_org");
        } elseif (Mage::getSingleton('core/session')->getSalesForceOrg()) {
            $_orgId = Mage::getSingleton('core/session')->getSalesForceOrg();
        } else {
            $_orgId = 'Unknown';
        }

        $_errorItem = new stdClass();
        $_errorItem->statusCode = $_statusCode;
        $_errorItem->message = $_text;

        $_error = new stdClass();
        $_error->success = false;
        $_error->orgId = $_orgId;
        $_error->errors = array($_errorItem);

        return $_error;
    }

    /**
     * @comment realize public access for _isUserActive
     * @param null $_sfUserId
     * @return bool
     */
    public function isUserActive($_sfUserId = NULL)
    {
        return $this->_isUserActive($_sfUserId);
    }

    /**
     * Read from cache or pull from Salesforce Active users
     * Accept $_sfUserId parameter and check if its in the array of active users
     * @param null $_sfUserId
     * @return bool
     */
    protected function _isUserActive($_sfUserId = NULL)
    {

        return Mage::helper('tnw_salesforce/salesforce_data_user')->isUserActive($_sfUserId);
    }

    protected function _getStoreIdByCurrency($_currenctCurrencyCode)
    {
        foreach (Mage::app()->getStores() as $_store) {
            $_currency = Mage::app()->getStore($_store->getId())->getDefaultCurrencyCode();
            if ($_currenctCurrencyCode == $_currency) {
                return $_store->getId();
            }
        }
        return false;
    }

    /**
     * input paremeter: salesforceId or string type1:salesforceId1;type2:salesforceId2;
     * @param $_field
     * @return string
     */
    public function generateLinkToSalesforce($_field)
    {
        $_data = null;

        if ($_field) {
            $valuesArray = explode("\n", $_field);

            foreach ($valuesArray as $value) {
                $currency = '';
                if (strpos($value, ':') !== false) {
                    $tmp = explode(':', $value);
                    $currency = $tmp[0] . ': ';
                    $_field = $tmp[1];
                    $value = $tmp[1];
                }

                if (empty($value)) {
                    continue;
                }

                $_url = Mage::helper('tnw_salesforce/test_authentication')->getStorage('salesforce_url') . '/' . $value;
                if (Mage::helper('tnw_salesforce/test_authentication')->getStorage('salesforce_url')) {
                    $_data .=  '<strong>' . $currency . '<a target="_blank" href="' . $_url . '">' . $_field . "</a></strong><br />";
                } else {
                    $_data .= '<strong>' . $value . "</strong><br />";
                }
            }
        }

        if (!$_data) {
            $_data = 'N/A';
        }

        return $_data;
    }

    protected function _whenToStopWaiting($_result = NULL, $_attempt = 50, $_jobRecords = NULL)
    {
        // Break infinite loop after 50 attempts.
        if (
            (
                !$_result
                && $_attempt == 50
            ) || (
                !$_jobRecords
                || empty($_jobRecords)
            )
        ) {
            $_result = 'exception';
        }

        return $_result;
    }

    /**
     * Get results from SF sync for all objects
     * @return array
     */
    public function getSyncResults()
    {
        return $this->_syncedResults;
    }

    /**
     *
     */
    protected function _onComplete()
    {
        // Store results
        if ($this->_cache &&
            array_key_exists('responses', $this->_cache)
            && is_array($this->_cache['responses'])
        ) {
            $this->_syncedResults = $this->_cache['responses'];
        }
    }

    /**
     * @return null|string
     */
    public function getMagentoId()
    {
        return $this->_magentoId;
    }

    /**
     * @param $magentoId
     * @return $this
     */
    public function setMagentoId($magentoId)
    {
        $this->_magentoId = $magentoId;

        return $this;
    }

    /**
     * @return null
     */
    public function getCache()
    {
        return $this->_cache;
    }

    /**
     * @param null $cache
     * @return $this
     */
    public function setCache($cache)
    {
        $this->_cache = $cache;

        return $this;
    }

    /**
     * @param string $key
     * @return array|null|string
     */
    public function getWebsiteSfIds($key = null)
    {
        if (!is_null($key)) {
            return isset($this->_websiteSfIds[$key]) ? (string)$this->_websiteSfIds[$key] : null;
        }
        return $this->_websiteSfIds;
    }

    /**
     * @param array $websiteSfIds
     * @return $this
     */
    public function setWebsiteSfIds($websiteSfIds)
    {
        $this->_websiteSfIds = $websiteSfIds;

        return $this;
    }

    /**
     * @return null
     */
    public function getObj()
    {
        if (!$this->_obj) {
            $this->_obj = new stdClass();
        }
        return $this->_obj;
    }

    /**
     * @param null $obj
     * @return $this
     */
    public function setObj($obj)
    {
        $this->_obj = $obj;

        return $this;
    }

    /**
     * @param Mage_Sales_Model_Order|Mage_Sales_Model_Quote $_entity
     * @return null|Mage_Customer_Model_Customer
     */
    public function getCustomer($_entity)
    {
        if (method_exists($this, '_getCustomer')) {
            return $this->_getCustomer($_entity);
        }

        return null;
    }

    /**
     * @return TNW_Salesforce_Helper_Config_Server
     */
    public function getServerHelper()
    {
        if (!$this->_serverHelper) {
            $this->_serverHelper = Mage::helper('tnw_salesforce/config_server');
        }
        return $this->_serverHelper;
    }

    public function getEntityPrice($entity, $priceField)
    {
        $origPriceField = $priceField;
        /**
         * use base price if it's selected in config and multicurrency disabled
         */
        if (Mage::helper('tnw_salesforce/config_sales')->useBaseCurrency() && !Mage::helper('tnw_salesforce/config_sales')->isMultiCurrency()) {
            $priceField = 'Base' . $priceField;
        }

        $priceGetter = 'get' . $priceField;

        $result = $entity->$priceGetter();

        if (!$result) {
            $origPriceGetter = 'get' . $origPriceField;
            $result = $entity->$origPriceGetter();
        }

        return $result;
    }

    public function numberFormat($value)
    {
        return Mage::helper('tnw_salesforce/salesforce_data')->numberFormat($value);
    }

    /**
     * remove some technical data from Id, in fact first 15 symbols important only, last 3 - it's technical data for SF
     * @param $id
     * @return string
     */
    public function prepareId($id)
    {
        return Mage::helper('tnw_salesforce')->prepareId($id);
    }

    /**
     * Add message to output
     * @param $message
     * @return TNW_Salesforce_Helper_Salesforce_Abstract
     */
    public function logNotice($message)
    {
        return Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace($message);
    }

    /**
     * Add message to output
     * @param $message
     * @return TNW_Salesforce_Helper_Salesforce_Abstract
     */
    public function logError($message)
    {
        return Mage::getSingleton('tnw_salesforce/tool_log')->saveError($message);
    }

    /**
     * @deprecated
     * Add message to output
     * @param $message
     * @param $level
     * @return TNW_Salesforce_Helper_Salesforce_Abstract
     */
    public function logMessage($message, $level)
    {

        $fileName = null;

        switch ($level) {
            case 'error':
                $fileName = 'sf-errors';
                break;
            default:
                break;
        }

        if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
            $method = 'add' . uc_words($level);
            Mage::getSingleton('adminhtml/session')->$method($message);
        }

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace($message);

        return $this;
    }

}