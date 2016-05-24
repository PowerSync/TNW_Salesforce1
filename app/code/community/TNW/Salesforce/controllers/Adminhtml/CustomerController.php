<?php

include_once "Mage/Adminhtml/controllers/CustomerController.php";
class TNW_Salesforce_Adminhtml_CustomerController extends Mage_Adminhtml_CustomerController
{
    /**
     * Customer orders grid
     *
     */
    public function opportunitiesAction() {
        $this->_initCustomer();
        $this->loadLayout();
        $this->renderLayout();
    }
}