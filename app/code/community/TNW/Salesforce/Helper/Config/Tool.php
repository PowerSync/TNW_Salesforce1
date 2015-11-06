<?php

/**
 * Author: Tech-N-Web, LLC (dba PowerSync)
 * Email: support@powersync.biz
 * Developer: Evgeniy Ermolaev
 *
 * Class TNW_Salesforce_Helper_Config_Tool
 */
class TNW_Salesforce_Helper_Config_Tool extends TNW_Salesforce_Helper_Config
{
    /**
     * @comment module tool configuration settings path
     */

    /**
     * total DB log records limit
     */
    const DB_LOG_LIMIT = 'salesforce/development_and_debugging/db_log_limit';

    /**
     * remove this records count if limit exceedede
     */
    const DB_LOG_REMOVE_COUNT = 'salesforce/development_and_debugging/db_log_remove_count';

    /**
     * enable SOQL console
     */
    const ENABLE_SOQL = 'salesforce/development_and_debugging/enable_soql';

    /**
     * total DB log records limit
     */
    public function getDbLogLimit()
    {
        return $this->getStoreConfig(self::DB_LOG_LIMIT);
    }

    /**
     * remove this records count if limit exceedede
     */
    public function getDbLogRemoveCount()
    {
        return $this->getStoreConfig(self::DB_LOG_REMOVE_COUNT);
    }

    /**
     * enable SOQL console
     */
    public function getEnableSoql()
    {
        return $this->getStoreConfig(self::ENABLE_SOQL);
    }

}