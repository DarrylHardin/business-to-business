<?php
/**
 * Business To Business plugin for Craft CMS 3.x
 *
 * Allow businesses to create vouchers for employees that allows the purchasing of products to be charged to the company at a later date.
 *
 * @link      http://importantcoding.com
 * @copyright Copyright (c) 2019 Darryl Hardin
 */

namespace importantcoding\businesstobusiness\models;

use importantcoding\businesstobusiness\BusinessToBusiness;

use Craft;
use craft\base\Model;
use craft\models\Site;

use yii\base\InvalidConfigException;

/**
 * GatewayRulesBusiness Model
 *
 *
 * @author    Darryl Hardin
 * @package   BusinessToBusiness
 * @since     1.0.0
 */
class GatewayRulesBusiness extends Model
{
      // Properties
    // =========================================================================

    public $id;
    public $businessId;
    public $voucherId;
    public $gatewayId;
    public $orderStatusId;
    public $condition;
    public $name;


    private $_gatewayRulesBusinesses;
    // Public Methods
    // =========================================================================

    public function rules()
    {
        return [
            [['id', 'businessId', 'voucherId', 'gatewayId', 'orderStatusId'], 'number', 'integerOnly' => true],
            ['condition', 'name', 'string'],
            [['id', 'businessId', 'gatewayId', 'orderStatusId', 'condition'], 'required'],
        ];
    }

    public function getShippingRuleBusinesses(): array
    {
        if (null === $this->_gatewayRulesBusinesses) {
            $this->_gatewayRulesBusinesses = BusinessToBusiness::$plugin->shippingRulesBusinesses->getShippingRulesByBusinessId((int)$this->id);
        }

        return $this->_gatewayRulesBusinesses;
    }

    public function setShippingRuleBusinesses(array $models)
    {
        $this->_gatewayRulesBusinesses = $models;
    }
}
