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
use craft\commerce\records\ShippingMethod;
use yii\db\ActiveQueryInterface;
/**
 * DefaultRules Record
 *
 *
 * @author    Darryl Hardin
 * @package   BusinessToBusiness
 * @since     1.0.0
 */
class DefaultRules extends ActiveRecord
{


    // Public Static Methods
    // =========================================================================

     /**
      *
     *
     * @return string the table name
     */
    public static function tableName()
    {
        return '{{%businesstobusiness_defaultrules}}';
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getGateways(): ActiveQueryInterface
    {
        return $this->hasOne(Gateway::class, ['id' => 'gatewayId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getShippingMethods(): ActiveQueryInterface
    {
        return $this->hasOne(ShippingMethod::class, ['id' => 'shippingMethodId']);
    }
}
