<?php

/**
 * Author: Tech-N-Web, LLC (dba PowerSync)
 * Email: support@powersync.biz
 * Developer: Evgeniy Ermolaev
 *
 * Class TNW_Salesforce_Adminhtml_Salesforce_AccountController
 */
class TNW_Salesforce_Adminhtml_Salesforce_AccountController extends TNW_Salesforce_Controller_Base_Mapping
{

    /**
     * name of  Salesforce object in case sensitive
     * @var string
     */
    protected $_sfEntity = 'Account';

    /**
     * @var string
     */
    protected $_blockPath = 'mapping_customer_account';

    /**
     * @return bool
     */
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')
            ->isAllowed('tnw_salesforce/mappings/customer_mapping/account_mapping');
    }
}
