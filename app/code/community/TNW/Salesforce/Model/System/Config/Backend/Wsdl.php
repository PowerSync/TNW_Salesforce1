<?php

class TNW_Salesforce_Model_System_Config_Backend_Wsdl extends Mage_Adminhtml_Model_System_Config_Backend_File
{
    /**
     * Save uploaded file before saving config value
     *
     * @return $this
     */
    protected function _beforeSave()
    {
        if (!empty($_FILES['groups']['tmp_name'][$this->getGroupId()]['fields'][$this->getField()]['value'])){

            $uploadDir = Mage::getBaseDir('base') . DS . 'var/salesforce';
            if ($this->_addWhetherScopeInfo()) {
                $uploadDir = $this->_appendScopeInfo($uploadDir);
            }

            // Create directory if does not exist
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0750, true);
            }

            try {
                $file = array();
                $tmpName = $_FILES['groups']['tmp_name'];
                $file['tmp_name'] = $tmpName[$this->getGroupId()]['fields'][$this->getField()]['value'];
                $name = $_FILES['groups']['name'];
                $file['name'] = $name[$this->getGroupId()]['fields'][$this->getField()]['value'];
                $uploader = new Mage_Core_Model_File_Uploader($file);
                $uploader->setAllowedExtensions($this->_getAllowedExtensions());
                $uploader->setAllowRenameFiles(false);
                $uploader->addValidateCallback('size', $this, 'validateMaxSize');
                $result = $uploader->save($uploadDir);

            } catch (Exception $e) {
                Mage::throwException($e->getMessage());
            }

            // Clear Cache
            Mage::app()->getCacheInstance()
                ->cleanType('tnw_salesforce');

            $filename = $result['file'];
            if ($filename) {
                if ($this->_addWhetherScopeInfo()) {
                    $filename = $this->_prependScopeInfo($filename);
                }

                //$fieldConfig = $this->getFieldConfig();
                /* @var $fieldConfig Varien_Simplexml_Element */
                //$uploadDir = (string)$fieldConfig->upload_dir;
                $this->setValue('var/salesforce/'.$filename);
            }
        }

        return $this;
    }

    /**
     * Return the root part of directory path for uploading
     *
     * @var string
     * @return string
     */
    protected function _getUploadRoot($token)
    {
        return Mage::getBaseDir('var');
    }
}