<?php
/**
 * Business To Business plugin for Craft CMS 3.x
 *
 * Allow businesses to create vouchers for employees that allows the purchasing of products to be charged to the company at a later date.
 *
 * @link      http://importantcoding.com
 * @copyright Copyright (c) 2019 Darryl Hardin
 */

namespace importantcoding\businesstobusiness\records;

use Craft;
use craft\db\ActiveRecord;
use craft\commerce\records\Gateway;



use yii\db\ActiveQueryInterface;
/**
 * GatewayRulesBusiness Record
 *
 *
 * @author    Darryl Hardin
 * @package   BusinessToBusiness
 * @since     1.0.0
 */
class GatewayRulesBusiness extends ActiveRecord
{

    const CONDITION_ALLOW = 'allow';
    const CONDITION_DISALLOW = 'disallow';
    const CONDITION_REQUIRE = 'require';

    // Public Static Methods
    // =========================================================================

     /**
      *
     *
     * @return string the table name
     */
    public static function tableName()
    {
        return '{{%businesstobusiness_gatewayrules_business}}';
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getGateways(): ActiveQueryInterface
    {
        return $this->hasMany(Gateway::class, ['id' => 'gatewayId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getBusiness(): ActiveQueryInterface
    {
        return $this->hasOne(Business::class, ['id' => 'businessId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getVoucher(): ActiveQueryInterface
    {
        return $this->hasOne(Voucher::class, ['id' => 'voucherId']);
    }
}
