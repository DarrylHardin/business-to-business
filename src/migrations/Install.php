<?php
/**
 * Business To Business plugin for Craft CMS 3.x
 *
 * Allow businesses to create vouchers for employees that allows the purchasing of products to be charged to the company at a later date.
 *
 * @link      http://importantcoding.com
 * @copyright Copyright (c) 2019 Darryl Hardin
 */

namespace importantcoding\businesstobusiness\migrations;

use importantcoding\businesstobusiness\BusinessToBusiness;

use Craft;
use craft\config\DbConfig;
use craft\db\Migration;
use craft\helpers\MigrationHelper;
/**
 * Business To Business Install Migration
 *
 * @author    Darryl Hardin
 * @package   BusinessToBusiness
 * @since     1.0.0
 */
class Install extends Migration
{
    // Public Properties
    // =========================================================================

    /**
     * @var string The database driver to use
     */
    public $driver;

    // Public Methods
    // =========================================================================

    /**
     *
     * @return boolean return a false value to indicate the migration fails
     * and should not proceed further. All other return values mean the migration succeeds.
     */
    public function safeUp()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        if ($this->createTables()) {
            $this->createIndexes();
            $this->addForeignKeys();
            // Refresh the db schema caches
            Craft::$app->db->schema->refresh();
            $this->insertDefaultData();
        }

        return true;
    }

    /**
     *
     * @return boolean return a false value to indicate the migration fails
     * and should not proceed further. All other return values mean the migration succeeds.
     */
    public function safeDown()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        $this->dropForeignKeys();
        $this->removeTables();

        return true;
    }

    // Protected Methods
    // =========================================================================

    /**
     * Creates the tables needed for the Records used by the plugin
     *
     * @return bool
     */
    protected function createTables()
    {
        $tablesCreated = false;

        // businesstobusiness_employee table
        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%businesstobusiness_employee}}');
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                '{{%businesstobusiness_employee}}', [
                    'id' => $this->primaryKey(),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'uid' => $this->uid(),
                    
                    // foreign keys
                    'userId' => $this->integer(),
                    'voucherId' => $this->integer(),
                    'businessId' => $this->integer(),

                    // employee fields
                    // 'employeeNumber' => $this->string(),
                    // 'number' => $this->string(),

                    'firstName' => $this->string(),
                    'lastName' => $this->string(),
                    'authorized' => $this->integer(),
                    'voucherAvailable' => $this->integer(),
                    'timesVoucherUsed' => $this->integer(),
                    'dateVoucherUsed' => $this->dateTime(),
                    'voucherExpired' => $this->integer(),
                    'email' => $this->string(),
                    'phone' => $this->string(),
                    
                    
                ]);
        }

        // businesstobusiness_business table
        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%businesstobusiness_business}}');
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                '{{%businesstobusiness_business}}',
                [
                    'id' => $this->primaryKey(),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'uid' => $this->uid(),

                    // foreign keys
                    'managerId' => $this->integer()->notNull(), // the id of the user account that is connected to the business
                    'addressId' =>$this->integer(),

                    // business values
                    'fieldLayoutId' => $this->integer(),
                    'name' => $this->string()->notNull(),
                    'handle' => $this->string()->notNull(),
                    'discount' => $this->integer(),
                    'autoVerify' => $this->integer(),
                    'passcode' => $this->string()->notNull(),
                    'taxExempt' => $this->integer(),
                    'limitShippingMethods' => $this->integer(),
                ]
            );
        }

    
        // businesstobusiness_business table
        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%businesstobusiness_shippingrules_business}}');
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                '{{%businesstobusiness_shippingrules_business}}',
                [
                    'id' => $this->primaryKey(),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'uid' => $this->uid(),

                    // foreign keys
                    'shippingMethodId' => $this->integer()->notNull(),
                    'businessId' => $this->integer()->notNull(),
                    'voucherId' => $this->integer(),

                    'name' => $this->string(),
                    'condition' => $this->string(), // can employees use this shipping method?
                ]
            );
        }

        // businesstobusiness_business table
        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%businesstobusiness_gatewayrules_business}}');
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                '{{%businesstobusiness_gatewayrules_business}}',
                [
                    'id' => $this->primaryKey(),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'uid' => $this->uid(),

                    // foreign keys
                    'gatewayId' => $this->integer()->notNull(),
                    'businessId' => $this->integer()->notNull(),
                    'voucherId' => $this->integer(),
                    'orderStatusId' => $this->integer(), //default order status

                    // business_gatewayrules values
                    'name' => $this->string(),
                    'condition' => $this->string(), // can employees use this gateway method?
                ]
            );
        }

        // businesstobusiness_defaultrules table
        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%businesstobusiness_defaultrules}}');
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                '{{%businesstobusiness_defaultrules}}',
                [
                    'id' => $this->primaryKey(),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'uid' => $this->uid(),

                    // foreign keys
                    'gatewayId' => $this->integer(),
                    'shippingMethodId' => $this->integer(),
                    'orderStatusId' => $this->integer(),

                    // business_defaultrules values
                    'name' => $this->string(),
                    'condition' => $this->string(),

                ]
            );
        }

        // businesstobusiness_business_sites table
        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%businesstobusiness_business_sites}}');
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                '{{%businesstobusiness_business_sites}}',
                [
                    'id' => $this->primaryKey(),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'uid' => $this->uid(),

                    // foreign keys
                    'businessId' => $this->integer()->notNull(),
                    'siteId' => $this->integer()->notNull(),
                    
                    // business_sites values
                    'hasUrls' => $this->boolean(),
                    'uriFormat' => $this->text(),
                    'template' => $this->string(500),
                    
                ]
            );
        }

    // businesstobusiness_voucher table
        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%businesstobusiness_voucher}}');
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                '{{%businesstobusiness_voucher}}',
                [
                    'id' => $this->primaryKey(),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'uid' => $this->uid(),
                    
                    // foreign keys
                    'businessId' => $this->integer(),
                    'addressId' =>$this->integer(),

                    // voucher values
                    'sku' => $this->string(),
                    'postDate' => $this->dateTime(),
                    'expiryDate' => $this->dateTime(),
                    'amount' => $this->decimal(12, 2)->notNull(),
                    'code' => $this->string(),
                    'payrollDeduction' => $this->integer(),
                    'productLimit' => $this->integer()->notNull(),
                    'products' => $this->longText()
                ]
            );
        }

    // businesstotbusiness_businesstobusiness_redemptions
        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%businesstobusiness_redemptions}}');
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable('{{%businesstobusiness_redemptions}}', [
                'id' => $this->primaryKey(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),

                // foreign keys
                'businessId' => $this->integer(),
                'voucherId' => $this->integer(),
                'orderId' => $this->integer(),
                'employeeId' => $this->integer(), // for tracking the original purchaser's identity

                // businesstobusiness_redemptions values
                'voucherChargeTotal' => $this->decimal(12, 2)->notNull(),
                'payrollDeductTotal' => $this->decimal(12, 2),
                'businessTotal' => $this->decimal(12, 2)->notNull(),
            ]);
        }

        return $tablesCreated;
    }

    /**
     * Creates the indexes needed for the Records used by the plugin
     *
     * @return void
     */
    protected function createIndexes()
    {   
        // employee indexes
        $this->createIndex($this->db->getIndexName('{{%businesstobusiness_employee}}', 'id', true), '{{%businesstobusiness_employee}}', 'id', true);

        // business indexes 
        $this->createIndex($this->db->getIndexName('{{%businesstobusiness_business}}', 'id', true), '{{%businesstobusiness_business}}', 'id', true);
        $this->createIndex($this->db->getIndexName('{{%businesstobusiness_business}}', 'handle', true), '{{%businesstobusiness_business}}', 'handle', true);
        $this->createIndex($this->db->getIndexName('{{%businesstobusiness_business}}', 'fieldLayoutId', false), '{{%businesstobusiness_business}}', 'fieldLayoutId', false);


        // business_sites indexes
        $this->createIndex($this->db->getIndexName('{{%businesstobusiness_business_sites}}', ['businessId', 'siteId'], true), '{{%businesstobusiness_business_sites}}', ['businessId', 'siteId'], true);
        $this->createIndex($this->db->getIndexName('{{%businesstobusiness_business_sites}}', 'siteId', false), '{{%businesstobusiness_business_sites}}', 'siteId', false);

        // business_gatewayrules indexes
        $this->createIndex($this->db->getIndexName('{{%businesstobusiness_gatewayrules_business}}', 'id', true), '{{%businesstobusiness_gatewayrules_business}}', 'id', true);
        $this->createIndex($this->db->getIndexName('{{%businesstobusiness_gatewayrules_business}}', ['businessId', 'voucherId', 'gatewayId'], true), '{{%businesstobusiness_gatewayrules_business}}', ['businessId', 'voucherId', 'gatewayId'], true);
        $this->createIndex($this->db->getIndexName('{{%businesstobusiness_gatewayrules_business}}', 'businessId', false), '{{%businesstobusiness_gatewayrules_business}}', 'businessId', false);
        $this->createIndex($this->db->getIndexName('{{%businesstobusiness_gatewayrules_business}}', 'voucherId', false), '{{%businesstobusiness_gatewayrules_business}}', 'voucherId', false);

        // business_shippingrules indexes
        $this->createIndex($this->db->getIndexName('{{%businesstobusiness_shippingrules_business}}', 'id', true), '{{%businesstobusiness_shippingrules_business}}', 'id', true);
        $this->createIndex($this->db->getIndexName('{{%businesstobusiness_shippingrules_business}}', ['businessId', 'voucherId', 'shippingMethodId'], true), '{{%businesstobusiness_shippingrules_business}}', ['businessId', 'voucherId', 'shippingMethodId'], true);
        $this->createIndex($this->db->getIndexName('{{%businesstobusiness_shippingrules_business}}', 'businessId', false), '{{%businesstobusiness_shippingrules_business}}', 'businessId', false);
        $this->createIndex($this->db->getIndexName('{{%businesstobusiness_shippingrules_business}}', 'voucherId', false), '{{%businesstobusiness_shippingrules_business}}', 'voucherId', false);

        // business_shippingrules indexes
        $this->createIndex($this->db->getIndexName('{{%businesstobusiness_defaultrules}}', 'shippingMethodId', false), '{{%businesstobusiness_defaultrules}}', 'shippingMethodId', false);
        $this->createIndex($this->db->getIndexName('{{%businesstobusiness_defaultrules}}', 'gatewayId', false), '{{%businesstobusiness_defaultrules}}', 'gatewayId', false);
        
        // voucher indexes
        $this->createIndex($this->db->getIndexName('{{%businesstobusiness_voucher}}', 'id', true), '{{%businesstobusiness_voucher}}', 'id', true);
        $this->createIndex($this->db->getIndexName('{{%businesstobusiness_voucher}}', 'sku', true), '{{%businesstobusiness_voucher}}', 'sku', true);


        // businesstobusiness_redemptions indexes
        $this->createIndex($this->db->getIndexName('{{%businesstobusiness_redemptions}}', 'id', true), '{{%businesstobusiness_redemptions}}', 'id', true);

        // Additional commands depending on the db driver
        switch ($this->driver) {
            case DbConfig::DRIVER_MYSQL:
                break;
            case DbConfig::DRIVER_PGSQL:
                break;
        }
    }

    /**
     * Creates the foreign keys needed for the Records used by the plugin
     *
     * @return void
     */
    protected function addForeignKeys()
    {
    // businesstobusiness_employee fks

        $this->addForeignKey(
            $this->db->getForeignKeyName('{{%businesstobusiness_employee}}', 'userId'),
            '{{%businesstobusiness_employee}}',
            'userId',
            '{{%users}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            $this->db->getForeignKeyName('{{%businesstobusiness_employee}}', 'voucherId'),
            '{{%businesstobusiness_employee}}',
            'voucherId',
            '{{%businesstobusiness_voucher}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            $this->db->getForeignKeyName('{{%businesstobusiness_employee}}', 'businessId'),
            '{{%businesstobusiness_employee}}',
            'businessId',
            '{{%businesstobusiness_business}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

    // businesstobusiness_business fks 
        $this->addForeignKey(
            $this->db->getForeignKeyName('{{%businesstobusiness_business}}', 'managerId'),
            '{{%businesstobusiness_business}}',
            'managerId',
            '{{%users}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            $this->db->getForeignKeyName('{{%businesstobusiness_business}}', 'addressId'),
            '{{%businesstobusiness_business}}',
            'addressId',
            '{{%commerce_addresses}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
        
    // businesstobusiness_shippingrules_business fks 

        $this->addForeignKey(
            $this->db->getForeignKeyName('{{%businesstobusiness_shippingrules_business}}', 'businessId'),
            '{{%businesstobusiness_shippingrules_business}}',
            'businessId',
            '{{%businesstobusiness_business}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            $this->db->getForeignKeyName('{{%businesstobusiness_shippingrules_business}}', 'voucherId'),
            '{{%businesstobusiness_shippingrules_business}}',
            'voucherId',
            '{{%businesstobusiness_voucher}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            $this->db->getForeignKeyName('{{%businesstobusiness_shippingrules_business}}', 'shippingMethodId'),
            '{{%businesstobusiness_shippingrules_business}}',
            'shippingMethodId',
            '{{%commerce_shippingmethods}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        // businesstobusiness_shippingrules_business fks 

        $this->addForeignKey(
            $this->db->getForeignKeyName('{{%businesstobusiness_gatewayrules_business}}', 'businessId'),
            '{{%businesstobusiness_gatewayrules_business}}',
            'businessId',
            '{{%businesstobusiness_business}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            $this->db->getForeignKeyName('{{%businesstobusiness_gatewayrules_business}}', 'voucherId'),
            '{{%businesstobusiness_gatewayrules_business}}',
            'voucherId',
            '{{%businesstobusiness_voucher}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            $this->db->getForeignKeyName('{{%businesstobusiness_gatewayrules_business}}', 'gatewayId'),
            '{{%businesstobusiness_gatewayrules_business}}',
            'gatewayId',
            '{{%commerce_gateways}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            $this->db->getForeignKeyName('{{%businesstobusiness_gatewayrules_business}}', 'orderStatusId'),
            '{{%businesstobusiness_gatewayrules_business}}',
            'orderStatusId',
            '{{%commerce_orderstatuses}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

    // businesstobusiness_shippingrules_business fks

        $this->addForeignKey(
            $this->db->getForeignKeyName('{{%businesstobusiness_defaultrules}}', 'gatewayId'),
            '{{%businesstobusiness_defaultrules}}',
            'gatewayId',
            '{{%commerce_gateways}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            $this->db->getForeignKeyName('{{%businesstobusiness_defaultrules}}', 'shippingMethodId'),
            '{{%businesstobusiness_defaultrules}}',
            'shippingMethodId',
            '{{%commerce_shippingmethods}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            $this->db->getForeignKeyName('{{%businesstobusiness_defaultrules}}', 'orderStatusId'),
            '{{%businesstobusiness_defaultrules}}',
            'orderStatusId',
            '{{%commerce_orderstatuses}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

    // businesstobusiness_business_sites fks
        $this->addForeignKey(
            $this->db->getForeignKeyName('{{%businesstobusiness_business_sites}}', 'siteId'),
            '{{%businesstobusiness_business_sites}}',
            'siteId',
            '{{%sites}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            $this->db->getForeignKeyName('{{%businesstobusiness_business_sites}}', 'businessId'),
            '{{%businesstobusiness_business_sites}}',
            'businessId',
            '{{%businesstobusiness_business}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    
    // businesstobusiness_voucher fks
        $this->addForeignKey(
            $this->db->getForeignKeyName('{{%businesstobusiness_voucher}}', 'businessId'),
            '{{%businesstobusiness_voucher}}',
            'businessId',
            '{{%businesstobusiness_business}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            $this->db->getForeignKeyName('{{%businesstobusiness_voucher}}', 'addressId'),
            '{{%businesstobusiness_voucher}}',
            'addressId',
            '{{%commerce_addresses}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        // businesstobusiness_redemptions fks
        $this->addForeignKey(
            $this->db->getForeignKeyName('{{%businesstobusiness_redemptions}}', 'businessId'),
            '{{%businesstobusiness_redemptions}}',
            'businessId',
            '{{%businesstobusiness_business}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            $this->db->getForeignKeyName('{{%businesstobusiness_redemptions}}', 'orderId'),
            '{{%businesstobusiness_redemptions}}',
            'orderId',
            '{{%commerce_orders}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            $this->db->getForeignKeyName('{{%businesstobusiness_redemptions}}', 'voucherId'),
            '{{%businesstobusiness_redemptions}}',
            'voucherId',
            '{{%businesstobusiness_voucher}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            $this->db->getForeignKeyName('{{%businesstobusiness_redemptions}}', 'employeeId'),
            '{{%businesstobusiness_redemptions}}',
            'employeeId',
            '{{%businesstobusiness_employee}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    /**
     * Populates the DB with the default data.
     *
     * @return void
     */
    protected function insertDefaultData()
    {
    }
    

    /**
     * Removes the foreign keys
     *
     * @return void
     */
    protected function dropForeignKeys()
    {
        MigrationHelper::dropAllForeignKeysOnTable('{{%businesstobusiness_employee}}', $this);
        MigrationHelper::dropAllForeignKeysOnTable('{{%businesstobusiness_business}}', $this);
        MigrationHelper::dropAllForeignKeysOnTable('{{%businesstobusiness_shippingrules_business}}', $this);
        MigrationHelper::dropAllForeignKeysOnTable('{{%businesstobusiness_gatewayrules_business}}', $this);
        MigrationHelper::dropAllForeignKeysOnTable('{{%businesstobusiness_defaultrules}}', $this);
        MigrationHelper::dropAllForeignKeysOnTable('{{%businesstobusiness_business_sites}}', $this);
        MigrationHelper::dropAllForeignKeysOnTable('{{%businesstobusiness_voucher}}', $this);
        MigrationHelper::dropAllForeignKeysOnTable('{{%businesstobusiness_redemptions}}', $this);
    }


    /**
     * Removes the tables needed for the Records used by the plugin
     *
     * @return void
     */
    protected function removeTables()
    {
    
    //businesstobusiness_employee table
        $this->dropTableIfExists('{{%businesstobusiness_employee}}');

    // businesstobusiness_business table
        $this->dropTableIfExists('{{%businesstobusiness_business}}');

    // businesstobusiness_shippingrules_business table
        $this->dropTableIfExists('{{%businesstobusiness_shippingrules_business}}');

    // businesstobusiness_gatewayrules_business table
    $this->dropTableIfExists('{{%businesstobusiness_gatewayrules_business}}');

    // businesstobusiness_business table
        $this->dropTableIfExists('{{%businesstobusiness_defaultrules}}');
    
        // businesstobusiness_business_sites table
        $this->dropTableIfExists('{{%businesstobusiness_business_sites}}');
        
    // businesstobusiness_voucher table
        $this->dropTableIfExists('{{%businesstobusiness_voucher}}');

        // businesstobusiness_redemptions table
         $this->dropTableIfExists('{{%businesstobusiness_redemptions}}');
    }

    
}
