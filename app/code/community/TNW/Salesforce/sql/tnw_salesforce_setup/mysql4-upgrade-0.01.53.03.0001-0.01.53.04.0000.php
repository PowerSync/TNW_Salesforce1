<?php

/* @var $installer Mage_Customer_Model_Entity_Setup */
$installer = Mage::getResourceModel('customer/setup', 'customer_setup');

$installer->startSetup();

$newAttributes = array(
    'last_purchase' => array(
        'type' => Varien_Db_Ddl_Table::TYPE_TIMESTAMP
    ),
    'last_login' => array(
        'type' => Varien_Db_Ddl_Table::TYPE_TIMESTAMP
    ),
    'last_transaction_id' => array(
        'type' => Varien_Db_Ddl_Table::TYPE_TEXT
    ),
    'total_order_count' => array(
        'type' => Varien_Db_Ddl_Table::TYPE_INTEGER
    ),
    'total_order_amount' => array(
        'type' => Varien_Db_Ddl_Table::TYPE_DECIMAL,
        'length' => '12,4'
    )
);

foreach ($newAttributes as $code => $newAttributeData) {

    $installer->getConnection()->addColumn(
        $installer->getTable('customer/entity'),
        $code,
        array(
            'comment' => isset($newAttributeData['label'])? isset($newAttributeData['length']): $code,
            'type'    => $newAttributeData['type'],
            'length' => isset($newAttributeData['length'])? $newAttributeData['length']: null,
        )
    );

    $installer->addAttribute(
        'customer',
        $code,
        array(
            'label' => $code,
            'type' => 'static',
            'input' => '',
        )
    );

}

$installer->endSetup();