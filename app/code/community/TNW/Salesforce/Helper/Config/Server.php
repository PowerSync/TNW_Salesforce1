<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Helper_Config_Server extends Mage_Core_Helper_Abstract
{

    const BULK = 'bulk';

    /**
     * @comment PHP configuration settings path
     */
    const BULK_SERVER_CONFIGURATION = 'salesforce/development_and_debugging/bulk';

    /**
     * @comment Contains old server settings
     * @var array
     */
    protected $_originSettings = array();

    /**
     * @comment Contains setting parameter names.
     * @var array
     */
    protected $_trackedSettings = array(
        /**
         * @comment change settings via "ini_set" function
         */
        'max_execution_time' => 'max_execution_time',
        'mysql.connect_timeout' => 'mysql.connect_timeout',
        /**
         * @comment change settings via specific functions
         */
        'set_time_limit' => array(
            'set' => 'set_time_limit',
            'get' => 'max_execution_time'
        ),
        'zend.enable_gc' => 'zend.enable_gc'
    );

    /**
     * @param null $name
     * @return array
     */
    public function getOriginSetting($name)
    {
        return $this->getOriginSettings($name);
    }

    /**
     * @param $name
     * @return bool
     */
    public function hasOriginSetting($name)
    {
        return isset($this->_originSettings[$name]);
    }

    /**
     * @param null $name
     * @return array
     */
    public function getOriginSettings($name = null)
    {
        if (!empty($name)) {
            return $this->_originSettings[$name];
        }
        return $this->_originSettings;
    }

    /**
     * @param $originSettings
     * @return $this
     */
    public function setOriginSettings($originSettings)
    {
        $this->_originSettings = $originSettings;

        return $this;
    }

    /**
     * @param $name
     * @param $value
     * @return $this
     */
    public function setOriginSetting($name, $value)
    {
        $this->_originSettings[$name] = $value;

        return $this;
    }

    /**
     * @param $name
     * @param $value
     * @return $this
     */
    public function addOriginSetting($name, $value)
    {
        $this->_originSettings[$name] = $value;

        return $this;
    }

    public function getTrackedSetting($name)
    {
        return $this->getTrackedSettings($name);
    }
    /**
     * @return array
     */
    public function getTrackedSettings($name = null)
    {
        if (!empty($name)) {
            return $this->_trackedSettings[$name];
        }
        return $this->_trackedSettings;
    }

    /**
     * @param $trackedSettings
     * @return $this
     */
    public function setTrackedSettings($trackedSettings)
    {
        $this->_trackedSettings = $trackedSettings;

        return $this;
    }

    public function getSettingValue($name)
    {
        $value = null;
        $_settingData = $this->getTrackedSetting($name);

        if (is_array($_settingData) && isset($_settingData['get'])) {

            $_getMethod = $_settingData['get'];

            if (function_exists($_getMethod)) {
                $value = $_getMethod();
            } else {
                $value = ini_get($_getMethod);
            }
        } else {
            $value = ini_get($name);
        }

        return $value;
    }

    /**
     * @comment Save default server settings
     */
    public function __construct()
    {
        foreach ($this->getTrackedSettings() as $name => $_settingData) {

            $value = null;

            if (is_array($_settingData)) {

                if (!isset($_settingData['get'])) {
                    continue;
                }

                $_getMethod = $_settingData['get'];

                if (function_exists($_getMethod)) {
                    $value = $_getMethod();
                } else {
                    $value = ini_get($_getMethod);
                }
            } else {
                $value = ini_get($name);
            }

            $this->addOriginSetting($name, $value);
        }
    }

    /**
     * @comment apply server settings
     * @param $settings
     * @return $this
     */
    public function applySettings($settings)
    {

        foreach ($settings as $name => $value) {
            $_settingData = $this->getTrackedSetting($name);

            /**
             * @comment save origin value
             */
            if (!$this->hasOriginSetting($name)) {
                $this->addOriginSetting($name, $this->getSettingValue($name));
            }

            if (is_array($_settingData)) {
                if (!isset($_settingData['set'])) {
                    continue;
                }

                $_setMethod = $_settingData['set'];

                if (function_exists($_setMethod)) {
                    $_setMethod($value);
                } else {
                    ini_set($_setMethod, $value);
                }

            } else {
                ini_set($name, $value);
            }

        }

        return $this;
    }

    /**
     * @comment set server settings for the bulk synchronization
     * @return $this
     */
    public function apply($code = '')
    {
        switch ($code) {
            case self::BULK:
                $settings = Mage::getStoreConfig(self::BULK_SERVER_CONFIGURATION);
                break;
            default:
                $settings = $this->getOriginSettings();
                break;
        }

        if ($settings) {
            $this->applySettings($settings);
        }

        return $this;
    }

    /**
     * @comment Restore original settings
     * @return $this
     */
    public function resetSettings()
    {
        $this->applySettings($this->getOriginSettings());

        return $this;
    }

    /**
     * @comment alias of the "resetSettings" method
     * @return $this
     */
    public function reset()
    {
        return $this->resetSettings();
    }
}