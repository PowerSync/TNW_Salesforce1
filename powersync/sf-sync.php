<?php
/* Instantiate Magento */
$mageFilename = '../app/Mage.php';
require_once $mageFilename;
unset($mageFilename);
Mage::app();
Mage::app()->setCurrentStore(0);

// set locale
$locale = Mage::app()->getLocale()->getLocaleCode();
Mage::app()->getTranslator()->setLocale($locale)->init('frontend', true);

/* Look for passed name/value pair */
$fromSf = ($_REQUEST['sf']) ? strip_tags($_REQUEST['sf'], true) : NULL;
Mage::helper('tnw_salesforce')->log("========== Sync from Salesforce Start ==========");
#Mage::helper('tnw_salesforce')->log("JSON: ". urlencode($fromSf));

if (!function_exists('apache_request_headers')) {
    function apache_request_headers()
    {
        $arh = array();
        $rx_http = '/\AHTTP_/';
        foreach ($_SERVER as $key => $val) {
            if (preg_match($rx_http, $key)) {
                $arh_key = preg_replace($rx_http, '', $key);
                $rx_matches = array();
                // do some nasty string manipulations to restore the original letter case
                // this should work in most cases
                $rx_matches = explode('_', $arh_key);
                if (count($rx_matches) > 0 and strlen($arh_key) > 2) {
                    foreach ($rx_matches as $ak_key => $ak_val) $rx_matches[$ak_key] = ucfirst($ak_val);
                    $arh_key = implode('-', $rx_matches);
                }
                $arh[uc_words(strtolower($arh_key))] = $val;
            }
        }
        return ($arh);
    }
}

$headers = apache_request_headers();
if (
    !array_key_exists('Authorization', $headers)
) {
    if (isset($_SERVER['REDIRECT_REMOTE_USER'])) {
        $headers['Authorization'] = ($_SERVER['REDIRECT_REMOTE_USER']) ? $_SERVER['REDIRECT_REMOTE_USER'] : NULL;
    }
}

/* Setting default values */
$response = new stdClass();

/* Set Default */
$status = false;
$errorString = NULL;
if (!Mage::helper('tnw_salesforce')->isWorking()) {
    $errorString = "Extension is disabled or not working";
} else {
    /* Validate Request */
    try {
        if (
            !array_key_exists('Authorization', $headers)
        ) {
            throw new Exception("Missing authorization, possible attack!");
        }

        $auth = explode(" ", $headers['Authorization']);
        $myAuth = base64_decode($auth[1], true);
        $credentials = explode(":", $myAuth);
        if (count($auth) != 2 || $auth[0] !== "BASIC" || !$myAuth || !is_array($credentials) || count($credentials) != 2) {
            Mage::helper("tnw_salesforce")->log('ERROR: Trying to hack, eh?');
            throw new Exception("Authorization invalid, possible attack!");
        }
        // Final check to see if all is good
        if (Mage::helper("tnw_salesforce")->getLicenseInvoice() != $credentials[0] || Mage::helper("tnw_salesforce")->getLicenseEmail() != $credentials[1]) {
            Mage::helper("tnw_salesforce")->log('ERROR: Authentication fialed!');
            throw new Exception("Authorization invalid, possible attack!");
        }
    } catch (Exception $e) {
        Mage::helper("tnw_salesforce")->log('ERROR: ' . $e->getMessage());
        die();
    }

    if (Mage::helper('tnw_salesforce')->getType() != "PRO") {
        /* 2 way sync is disabled */
        $errorString = "Please upgrade PowerSync to support two way synchronization";
    } else if (!$fromSf) {
        /* Could not find required variable 'sf' */
        $errorString = "Salesforce object is not available!";
    } else {
        /* Decode JSON into PHP object */
        $objects = json_decode($fromSf);
        if (!$objects || !is_array($objects)) {
            /* JSON is invalid, hack? */
            $errorString = "Invalid JSON format!";
            Mage::helper('tnw_salesforce')->log($errorString);
        } else {
            if (!Mage::helper('tnw_salesforce')->isEnabledCustomerSync()) {
                Mage::helper("tnw_salesforce")->log('SKIPING: Customer synchronization disabled');
            } else {
                if (count($objects) == 1) {
                    // Process Realtime
                    //$entity = array();
                    //$queueStatus = true;
                    $object = $objects[0];
                    // Each object should have 'attributes' property and 'type' inside 'attributes'
                    if (property_exists($object, "attributes") && property_exists($object->attributes, "type")) {
                        try {
                            $_prefix = Mage::helper('tnw_salesforce/salesforce')->getSfPrefix();
                            /* Safer to set the session at this level */
                            Mage::getSingleton('core/session')->setFromSalesForce(true);
                            // Call proper Magento upsert method
                            if (
                                $object->attributes->type == "Contact"
                                || ($object->IsPersonAccount == 1 && $object->attributes->type == "Account")
                            ) {
                                if ($object->Email || (property_exists($object, 'IsPersonAccount') && $object->IsPersonAccount == 1 && $object->PersonEmail)) {
                                    Mage::helper('tnw_salesforce')->log("Synchronizing: " . $object->attributes->type);
                                    //$entity[] = Mage::helper('tnw_salesforce/customer')->contactProcess($object);
                                    Mage::helper('tnw_salesforce/magento_customers')->process($object);
                                } else {
                                    Mage::helper('tnw_salesforce')->log("SKIPPING: Email is missing in Salesforce!");
                                }
                            } elseif ($object->attributes->type == $_prefix . "Website__c") {
                                if (
                                    property_exists($object, $_prefix . 'Website_ID__c')
                                    && !empty($object->{$_prefix . 'Website_ID__c'})
                                    && property_exists($object, $_prefix . 'Code__c')
                                    && !empty($object->{$_prefix . 'Code__c'})
                                ) {
                                    Mage::helper('tnw_salesforce/magento_websites')->process($object);
                                } else {
                                    Mage::helper('tnw_salesforce')->log("SKIPPING: Website ID and/or Code is missing in Salesforce!");
                                }
                            } elseif ($object->attributes->type == "Product2") {
                                if ($object->ProductCode) {
                                    Mage::helper('tnw_salesforce/magento_products')->process($object);
                                } else {
                                    Mage::helper('tnw_salesforce')->log("SKIPPING: ProductCode is missing in Salesforce!");
                                }
                            } else {
                                Mage::helper('tnw_salesforce')->log("Synchronization SKIPPED for: " . $object->attributes->type);
                            }
                            /* Reset session for further insertion */
                            Mage::getSingleton('core/session')->setFromSalesForce(false);
                        } catch (Exception $e) {
                            Mage::helper('tnw_salesforce')->log("Error: " . $e->getMessage());
                            Mage::helper('tnw_salesforce')->log("Failed to upsert a " . $object->attributes->type . " #" . $object->Id . ", please re-save or re-import it manually");
                            //$queueStatus = false;
                            unset($e);
                        }
                    }
                    /*
                    if ($queueStatus) {
                        if (
                            $object->attributes->type == "Contact"
                            || ($object->IsPersonAccount == 1 && $object->attributes->type == "Account")
                        ) {
                            Mage::helper('tnw_salesforce/customer')->updateContacts($entity, $object->attributes->type);
                        }
                    }
                    */
                } else {
                    // Add to Queue
                    /* Save into a db */
                    try {
                        $uid = uniqid("ctmr_", true);
                        $model = Mage::getModel('tnw_salesforce/imports');
                        $model->setId($uid);
                        $model->setJson(serialize($fromSf));
                        $model->setIsProcessing(NULL);
                        unset($objects, $formSf);
                        $model->forceInsert();
                        Mage::helper('tnw_salesforce')->log("Import JSON accepted, pending Import");
                        $status = true;
                        unset($uid, $model);
                    } catch (Exception $e) {
                        $errorString = "Could not process JSON, Error: " . $e->getMessage();
                        Mage::helper('tnw_salesforce')->log($errorString);
                        unset($e);
                    }
                }
            }
        }
    }
}
$response->error = $errorString;
/* Create JSON to send back to Salesforce */
$return = json_encode($response);

Mage::helper('tnw_salesforce')->log("Return JSON to Salesforce: " . $return);
Mage::helper('tnw_salesforce')->log("========== Sync from Salesforce End ==========");

echo $return; // display JSON for Salesforce
unset($return);