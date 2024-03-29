<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Helper_Salesforce_Abstract
{

    static public $usedHelpers = array();

    /**
     * @var string
     */
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
     * @var stdClass
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
     * @return TNW_Salesforce_Model_Sforce_Client
     */
    public function getClient()
    {
        return TNW_Salesforce_Model_Connection::createConnection()->getClient();
    }

    /**
     * @return null
     */
    public function getSalesforceServerDomain()
    {
        $serverDomain = Mage::helper('tnw_salesforce/test_authentication')->getStorage('salesforce_url');

        return $serverDomain;
    }

    /**
     * @return null
     */
    public function getSalesforceSessionId()
    {
        return TNW_Salesforce_Model_Connection::createConnection()->getSalesforceSessionId();
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

        try {
            $this->getClient();
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveNotice("SKIPPING: Salesforce connection failed!");
            return false;
        }

        if ($this->_stopFurtherProcessing) {
            return false;
        }

        self::$usedHelpers[get_class($this)] = $this;

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

        $url = "{$this->getSalesforceServerDomain()}/services/async/{$this->_salesforceApiVersion}/job";
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Create Job use url: {$url}");

        $_client = $this->getHttpClient()
            ->setUri($url)
            ->setMethod('POST')
            ->setHeaders('Content-Type: application/xml')
            ->setHeaders('X-SFDC-Session', $this->getSalesforceSessionId())
            ->setRawData($_data);

        $response = $_client->request()->getBody();
        $_jobInfo = $this->parseXml($response);
        if (isset($_jobInfo->exceptionMessage)) {
            throw new Exception('Cannot find job id:' . $_jobInfo->exceptionMessage);
        }

        if (!isset($_jobInfo->id)) {
            throw new Exception('Cannot find job id');
        }

        return substr($_jobInfo->id, 0, -3);
    }

    /**
     * @param $xml
     *
     * @return SimpleXMLElement
     * @throws Exception
     */
    protected function parseXml($xml)
    {
        $use_errors = libxml_use_internal_errors(true);

        try {
            $object = simplexml_load_string($xml);
            if (false === $object) {
                /** @var LibXMLError $error */
                foreach (libxml_get_errors() as $error) {
                    throw new Exception("XML parse error message: \"{$error->message}\"");
                }
            }
        } catch (Exception $e) {
            libxml_clear_errors();
            libxml_use_internal_errors($use_errors);
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError($e->getMessage());
            throw $e;
        }

        libxml_clear_errors();
        libxml_use_internal_errors($use_errors);
        return $object;
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

            $_success = true;
            $batchLimit = $this->_maxBatchLimit == 0
                ? TNW_Salesforce_Helper_Data::BASE_UPDATE_LIMIT
                : $this->_maxBatchLimit;

            $_entitiesChunk = array_chunk($_entities, $batchLimit, true);
            foreach ($_entitiesChunk as $_batchNum => $_itemsToPush) {
                if (!array_key_exists($_batchNum, $this->_cache['batch'][$_batchType][$_on])) {
                    $this->_cache['batch'][$_batchType][$_on][$_batchNum] = array();
                }

                $_success = $this->_pushSegment($_jobId, $_batchType, $_itemsToPush, $_batchNum, $_on);
            }

            if (!$_success) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError('ERROR: ' . uc_words($_batchType) . ' upsert failed!');
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
        $_data = '<?xml version="1.0" encoding="UTF-8"?>
            <jobInfo xmlns="http://www.force.com/2009/06/asyncapi/dataload">
                <state>Closed</state>
            </jobInfo>';

        $_client = $this->getHttpClient()
            ->setUri($this->getSalesforceServerDomain() . '/services/async/' . $this->_salesforceApiVersion . '/job/' . $_jobId)
            ->setMethod('POST')
            ->setHeaders('Content-Type: application/xml')
            ->setHeaders('X-SFDC-Session', $this->getSalesforceSessionId())
            ->setRawData($_data);

        $_state = false;
        try {
            $response = $this->parseXml($_client->request()->getBody());

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

        if (!empty($this->_cache['batchCache'][$_batchType][$_batchNum])) {
            return true; // Already processed
        }

        $_data = '<?xml version="1.0" encoding="UTF-8"?>
<sObjects xmlns="http://www.force.com/2009/06/asyncapi/dataload">'."\n";

        foreach ($chunk as $_item) {
            $_data .= "\t<sObject>";
            foreach ($_item as $_tag => $_value) {
                if (is_array($_value)) {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Valid value for the '$_tag' property is string. An array defined. Skip property.");
                    continue;
                }
                $_data .= '<' . $_tag . '><![CDATA[' . str_replace( array('<![CDATA[', ']]>'), '', $_value) . ']]></' . $_tag . '>';
            }
            $_data .= "</sObject>\n";
        }

        $_data .= '</sObjects>';

        $_client = $this->getHttpClient()
            ->setUri($this->getSalesforceServerDomain() . '/services/async/' . $this->_salesforceApiVersion . '/job/' . $_jobId . '/batch')
            ->setMethod('POST')
            ->setHeaders('Content-Type: application/xml')
            ->setHeaders('X-SFDC-Session', $this->getSalesforceSessionId())
            ->setRawData($_data);

        Mage::getSingleton('tnw_salesforce/tool_log')
            ->saveTrace(sprintf("Bulk. Object: '%s' . Sent a request to url: %s \nData: %s", $_batchType, $_client->getUri(true), $_data));

        try {
            $response = $_client->request()->getBody();
            $_batchInfo = $this->parseXml($response);

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
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError('ERROR: '. $e->getMessage());
            return false;
        }
    }

    /**
     * check batch info
     *
     * @param string $jobId
     * @param string $batchId
     * @return SimpleXMLElement|string
     */
    public function getBatch($jobId, $batchId)
    {
        // Check for update on all batches
        $_client = $this->getHttpClient()
            ->setUri($this->getSalesforceServerDomain() . '/services/async/' . $this->_salesforceApiVersion . '/job/' . $jobId . '/batch/' . $batchId . '/result')
            ->setMethod('GET')
            ->setHeaders('Content-Type: application/xml')
            ->setHeaders('X-SFDC-Session', $this->getSalesforceSessionId());

        $response = $_client->request()->getBody();
        Mage::getSingleton('tnw_salesforce/tool_log')
            ->saveTrace(sprintf("Bulk. Processing result. Reply received on url: %s \nData: %s", $_client->getUri(true), $response));

        return $this->parseXml($response);
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
        $_client = $this->getHttpClient()
            ->setUri($this->getSalesforceServerDomain() . '/services/async/' . $this->_salesforceApiVersion . '/job/' . $jobId . '/batch/' . $batchId . '/result/' . $_resultId)
            ->setMethod('GET')
            ->setHeaders('Content-Type: application/xml')
            ->setHeaders('X-SFDC-Session', $this->getSalesforceSessionId());

        $response = $_client->request()->getBody();
        Mage::getSingleton('tnw_salesforce/tool_log')
            ->saveTrace(sprintf("Bulk. Processing result. Reply received on url: %s \nData: %s", $_client->getUri(true), $response));

        return $this->parseXml($response);
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

        // check for update on all batches
        try {
            $client = $this->getHttpClient()
                ->setUri(sprintf('%s/services/async/%s/job/%s/batch', $this->getSalesforceServerDomain(), $this->_salesforceApiVersion, $jobId))
                ->setMethod('GET')
                ->setHeaders('Content-Type: text/csv')
                ->setHeaders('X-SFDC-Session', $this->getSalesforceSessionId());

            $response = $client->request()->getBody();
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace(sprintf("Bulk. Reply received on url: %s \nData: %s", $client->getUri(true), $response));

            $response = $this->parseXml($response);
            foreach ($response as $_responseRow) {
                if (property_exists($_responseRow, 'state')) {
                    if ('Failed' == $_responseRow->state) {
                        $completed = 'exception';
                        Mage::getSingleton('tnw_salesforce/tool_log')
                            ->saveError(sprintf('Batch failed: %s', @$_responseRow->stateMessage));

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
     * @param $jobId
     * @return bool
     */
    protected function waitingSuccessStatusBatch($jobId)
    {
        $result = $this->_checkBatchCompletion($jobId);

        $attempt = 1;
        while (strval($result) != 'exception' && !$result) {
            set_time_limit(1800);
            sleep(5);
            $result = $this->_checkBatchCompletion($jobId);
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace('Still checking (job: ' . $jobId . ')...');

            $result = $this->_whenToStopWaiting($result, ++$attempt, $jobId);
        }

        if (strval($result) == 'exception') {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveError('Check batch is failed! Stopping...');

            return false;
        }

        return true;
    }

    /**
     * @param $_response
     * @param string $type
     * @param null $_object
     */
    protected function _processErrors($_response, $type = 'order', $_object = NULL)
    {
        Mage::getSingleton('tnw_salesforce/tool_log')->saveError('Failed to upsert ' . $type . '! ');
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace(print_r($_object, true));

        if (is_array($_response->errors)) {
            foreach ($_response->errors as $_error) {
                $fields = '';
                if (!empty($_error->fields)) {
                    $fields = sprintf('. Fields: %s', implode(', ', $_error->fields));
                }

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError("ERROR: {$_error->message}{$fields}");
            }
        } else {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveError("ERROR: {$_response->errors->message}");
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

        $sql = "SELECT * FROM `" . Mage::helper('tnw_salesforce')->getTable('eav_entity_type') . "` WHERE entity_type_code = 'customer'";
        $row = $this->_write->query($sql)->fetch();
        $this->_customerEntityTypeCode = ($row) ? (int)$row['entity_type_id'] : NULL;

        $sql = "SELECT * FROM `" . Mage::helper('tnw_salesforce')->getTable('eav_entity_type') . "` WHERE entity_type_code = 'catalog_product'";
        $row = $this->_write->query($sql)->fetch();
        $this->_productEntityTypeCode = ($row) ? (int)$row['entity_type_id'] : NULL;

        $this->_client = $this->getHttpClient();

        $this->_fillWebsiteSfIds();

        Mage::getSingleton('tnw_salesforce/tool_log_mail')->send();
    }

    public function getHttpClient()
    {
        static $lastCreateTime = 0;
        if (time() - $lastCreateTime > 30) {
            $lastCreateTime = time();
            $this->_client = new Zend_Http_Client();
            $this->_client->setConfig(array(
                'maxredirects' => 0,
                'timeout' => 10,
                'keepalive' => false,
                'storeresponse' => true,
            ));
        }
        $this->_client->resetParameters();

        return $this->_client;
    }

    protected function _fillWebsiteSfIds()
    {
        $websiteHelper = Mage::helper('tnw_salesforce/magento_websites');
        /** @var Mage_Core_Model_Website $website */
        foreach (Mage::app()->getWebsites(true) as $website) {
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
            if (empty($_obj) || (!is_array($_obj) && !is_object($_obj))) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError("$type Object (Entity Key: $k) is empty!");
                continue;
            }

            if ($isError) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError("$type Object (Entity Key: $k): \n" . print_r($_obj, true));
            } else {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("$type Object (Entity Key: $k): \n" . print_r($_obj, true));
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
        $client = $this->getHttpClient()
            ->setUri($this->getSalesforceServerDomain() . '/services/async/' . $this->_salesforceApiVersion . '/job/' . $jobId . '/batch')
            ->setMethod('POST')
            ->setHeaders('Content-Type: application/xml')
            ->setHeaders('X-SFDC-Session', $this->getSalesforceSessionId())
            ->setRawData($_query);

        try {
            $response = $client->request()->getBody();
            $_batchInfo = $this->parseXml($response);

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
        $_data = '<?xml version="1.0" encoding="UTF-8"?>
                    <jobInfo xmlns="http://www.force.com/2009/06/asyncapi/dataload">
                        <operation>query</operation>
                        <object>' . $_obj . '</object>
                        <concurrencyMode>Parallel</concurrencyMode>
                        <contentType>XML</contentType>
                    </jobInfo>
        ';

        $client = $this->getHttpClient()
            ->setUri($this->getSalesforceServerDomain() . '/services/async/' . $this->_salesforceApiVersion . '/job')
            ->setMethod('POST')
            ->setHeaders('Content-Type: application/xml')
            ->setHeaders('X-SFDC-Session', $this->getSalesforceSessionId())
            ->setRawData($_data);

        try {
            $response = $client->request()->getBody();
            $_jobInfo = $this->parseXml($response);
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
        $_orgId = Mage::helper('tnw_salesforce/test_authentication')->getStorage('salesforce_org_id');
        if (empty($_orgId)) {
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

    /**
     * input paremeter: salesforceId or string type1:salesforceId1;type2:salesforceId2;
     * @param $_field
     * @return string
     */
    public function generateLinkToSalesforce($_field)
    {
        $_data = array();
        foreach (explode("\n", $_field) as $value) {
            $currency = '';
            if (strpos($value, ':') !== false) {
                list($currency, $value) = explode(':', $value, 2);
                $currency = "$currency: ";
            }

            if (empty($value)) {
                continue;
            }

            $salesforceUrl = Mage::helper('tnw_salesforce/test_authentication')
                ->getStorage('salesforce_url');

            if (!empty($salesforceUrl)) {
                $value = sprintf('%1$s<a target="_blank" href="%2$s/%3$s">%3$s</a>', $currency, $salesforceUrl, $value);
            }

            $_data[] = sprintf('<strong>%s</strong>', $value);
        }

        if (empty($_data)) {
            return 'N/A';
        }

        return implode('<br />', $_data);
    }

    protected function _whenToStopWaiting($_result = NULL, $_attempt = 50, $_jobRecords = NULL)
    {
        // Break infinite loop after 50 attempts.
        if (
            (
                !$_result
                && $_attempt == Mage::helper('tnw_salesforce')->getBulkResultMaxAttentions()
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
     * @return string|null
     */
    public function getWebsiteSfIds($key)
    {
        return isset($this->_websiteSfIds[$key]) ? (string)$this->_websiteSfIds[$key] : null;
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
     * @deprecated
     */
    public function logNotice($message)
    {
        return Mage::getSingleton('tnw_salesforce/tool_log')->saveNotice($message);
    }

    /**
     * Add message to output
     * @param $message
     * @return TNW_Salesforce_Helper_Salesforce_Abstract
     * @deprecated
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