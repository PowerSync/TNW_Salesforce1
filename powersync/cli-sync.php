<?php
    ini_set('display_errors', 1);
    error_reporting(E_ALL);

    // ini_set("memory_limit","2048M");
    set_time_limit(1000);

    $options = getopt("t:a:s:e:b:f:");
    $_salesforceSessionId = NULL;
    $_salesforceServerDomain = NULL;

    if (
        !array_key_exists('t', $options)
        && !array_key_exists('s', $options)
        && !array_key_exists('e', $options)
        && !array_key_exists('b', $options)
        && !array_key_exists('a', $options)
        && !array_key_exists('f', $options)
    ) {
        echo "Please run bulk-sync.php instead!\r\n";
        die();
    }

    /* Instantiate Magento */
    $mageFilename = '../app/Mage.php';
    require_once $mageFilename;
    unset($mageFilename);

    Mage::app();
    Mage::app()->setCurrentStore(0);

    $session = Mage::getModel('core/session');

    if (Mage::helper('tnw_salesforce')->getType() != "PRO") {
        echo "You will need to upgrade to Enterprise version of the connector to use this feature.\r\n";
        die();
    }

    $_type = (array_key_exists('t', $options)) ? $options['t'] : NULL;
    if (!$_type || ($_type != 'product' && $_type != 'customer' && $_type != 'order')) {
        echo "Invalid entity type specified! Please run 'php cli-sync.php' for more information.\r\n";
        die();
    }
    $_totalRecords = 0;
    $_ids = array();
    if (
        array_key_exists('a', $options) && $options['a'] == "ll"
    ) {
        switch ($_type) {
            case 'product':
                $collection = Mage::getModel('catalog/product')->getCollection();
                if (
                    !array_key_exists('f', $options) || $options['f'] != "orce"
                ) {
                    $attribute = Mage::getModel('eav/entity_attribute')->loadByCode('catalog_product', 'sf_insync');
                    $collection->getSelect()->joinLeft(
                        array('at_sf_insync' => Mage::helper('tnw_salesforce')->getTable('catalog_product_entity_int')),
                        '`at_sf_insync`.`entity_id` = `e`.`entity_id` AND `at_sf_insync`.`attribute_id` = "'. $attribute->getId() .'" AND `at_sf_insync`.`store_id` = 0',
                        array('sf_insync' => 'value')
                    );
                    $collection->getSelect()->where('`at_sf_insync`.`value` != 1 OR `at_sf_insync`.`value` IS NULL');
                }
                break;
            case 'customer':
                $collection = Mage::getModel('customer/customer')->getCollection();
                if (
                    !array_key_exists('f', $options) || $options['f'] != "orce"
                ) {
                    $attribute = Mage::getModel('eav/entity_attribute')->loadByCode('customer', 'sf_insync');
                    $collection->getSelect()->joinLeft(
                        array('at_sf_insync' => Mage::helper('tnw_salesforce')->getTable('customer_entity_int')),
                        '`at_sf_insync`.`entity_id` = `e`.`entity_id` AND `at_sf_insync`.`attribute_id` = "'. $attribute->getId() .'"',
                        array('sf_insync' => 'value')
                    );
                    $collection->getSelect()->where('`at_sf_insync`.`value` != 1 OR `at_sf_insync`.`value` IS NULL');
                }
                break;
            case 'order':
                $collection = Mage::getModel('sales/order')->getCollection();
                if (
                    !array_key_exists('f', $options) || $options['f'] != "orce"
                ) {
                    $collection->addAttributeToFilter('sf_insync', array('neq' => 1));
                }
                break;
        }

        $_perPage = (int)$options['e'];
        $_page = (int)$options['b'];
        $collection->setPageSize($_perPage);
        $collection->setCurPage($_page);
        $collection->load();

        if ($collection->count() == 0) {
            echo "All " . $_type . "s are in sync!\r\n";
            die();
        }
        foreach($collection as $_entity)
        {
            $_ids[] = $_entity->getId();
            $_totalRecords++;
        }
        $collection = $_entity = NULL;
        unset($collection, $_entity);
    } elseif (array_key_exists('p', $options)) {
        $_ids = json_decode($options['p']);
        $_totalRecords = count($_ids);
        if (!$_ids) {
            echo "Invalid list of product Ids provided! Please run 'php cli-sync.php' for more information.\r\n";
            die();
        }
    } else {
        echo "Need to specify " . $_type . " IDs to synchronize! Please run 'php cli-sync.php' for more information.\r\n";
        die();
    }

    $_urlArray = explode('/', Mage::app()->getStore(Mage::helper('tnw_salesforce')->getStoreId())->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB));
    $_server = (array_key_exists('2', $_urlArray)) ? $_urlArray[2] : NULL;

    // this is old license check which I've replaced by calling methods mageSfAuthenticate() and sfApiDummyCall()
    /*
    $_license = Mage::helper('tnw_salesforce/test_license')->forceTest($_server);
    if ($_license) {
        $_client = Mage::helper('tnw_salesforce/salesforce');

        // try to connect
        if (
            !$_client->tryWsdl()
            || !$_client->tryToConnect()
            || !$_client->tryToLogin()
        ) {
            Mage::getModel('tnw_salesforce/tool_log')->saveError("ERROR: logging to salesforce api failed, sync process skipped");
            die();
        } else {
            Mage::getModel('tnw_salesforce/tool_log')->saveTrace("INFO: logging to salesforce api - OK");
        }
    } else {
        echo "License validation has failed!\r\n";
        die();
    }

    if (!$_client->getServerUrl()) {
        echo "Failed to capture Salesforce server URL.\r\n";
        die();
    }

    if (!$_client->getSessionId()) {
        echo "Failed to capture Salesforce session ID.\r\n";
        die();
    }
    */

    // these lines required to get sf domain value
    $_client = Mage::helper('tnw_salesforce/salesforce');
    $_client->tryWsdl();
    $_client->tryToConnect();
    $_client->tryToLogin();

    $instance_url = explode('/', $_client->getServerUrl());
    $_salesforceServerDomain = 'https://' . $instance_url[2];
    $_sessionId = $_client->getSessionId();
    $_client = NULL;
    unset($_client);

    // here we try several times to auth in sf, if no success - just stop the script
    $sfAuthAttemptLimit = 2;
    for ($i = 1; $i <= $sfAuthAttemptLimit; $i++) {
        // sf license / login checks
        $sfAuth = Mage::helper('tnw_salesforce/test_authentication')->mageSfAuthenticate(true);
        if ($sfAuth) {
            // dummy api call
            $apiDummyCall = Mage::helper('tnw_salesforce/test_authentication')->sfApiDummyCall();
            if ($apiDummyCall) {
                break;
            }
        }
        // stop script
        if ($i == $sfAuthAttemptLimit) {
            echo 'error: sf auth attempt limit reached, cannot connect to sf';
            die;
        }
    }

    if ($_ids && !empty($_ids) && $_salesforceServerDomain && $_sessionId) {
        try {
            // Config
            $manualSync = Mage::helper('tnw_salesforce/bulk_' . $_type);
            $manualSync->clearMemory();
            if ($manualSync->reset()) {

                $manualSync->setIsFromCLI(true);
                $manualSync->setSalesforceServerDomain($_salesforceServerDomain);
                $manualSync->setSalesforceSessionId($_sessionId);

                $manualSync->massAdd($_ids);

                Mage::getModel('tnw_salesforce/tool_log')->saveTrace("Starting to process the data ...");
                if ($_type == 'order') {
                    $manualSync->process('full');
                } else {
                    $manualSync->process();
                }

                $manualSync->setIsFromCLI(false);
                //$_cached = $manualSync->getResults();
                $manualSync->clearMemory();
                $manualSync = NULL;
                unset($manualSync);
            }
            set_time_limit(90);
        } catch (Exception $e) {
            echo $e->getMessage() . "\r\n";
        }

        echo $_totalRecords . " " . $_type . " records processed...\r\n";
        echo "See Logs folder in Magento for more details\r\n";
    }
die();