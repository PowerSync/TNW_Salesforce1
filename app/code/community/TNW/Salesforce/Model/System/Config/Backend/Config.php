<?php

class TNW_Salesforce_Model_System_Config_Backend_Config extends Mage_Core_Model_Config_Data
{
    protected function _beforeSave()
    {
        if (empty($_FILES['groups']['tmp_name'][$this->getGroupId()]['fields'][$this->getField()]['value'])) {
            return $this;
        }

        /** @var string $tmpPath */
        $tmpPath = $_FILES['groups']['tmp_name'][$this->getGroupId()]['fields'][$this->getField()]['value'];

        if (!file_exists($tmpPath)) {
            return $this;
        }

        $uploadfile = sys_get_temp_dir(). DS . $_FILES["groups"]["name"][$this->getGroupId()]['fields'][$this->getField()]['value'];
        if (!move_uploaded_file($tmpPath, $uploadfile)) {
            return $this;
        }

        $csv = fopen("phar://$uploadfile/admin/config.csv", 'r');
        while (($data = fgetcsv($csv, 1000, ',')) !== false) {
            list($path, $value) = $data;
            if ('' === $value) {
                continue;
            }

            Mage::app()->getConfig()
                ->saveConfig($path, $value);
        }

        fclose($csv);
        $this->setValue('');
        return $this;
    }
}