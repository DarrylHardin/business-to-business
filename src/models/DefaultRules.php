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
 * DefaultRules Model
 *
 *
 * @author    Darryl Hardin
 * @package   BusinessToBusiness
 * @since     1.0.0
 */
class DefaultRules extends Model
{
      // Properties
    // =========================================================================

    public $id;
    public $shippingMethodId;
    public $gatewayId;
    public $orderStatusId;
    public $condition;
    public $name;


    private $_gatewayRulesBusinesses;
    private $_shippingRulesBusinesses;
    // Public Methods
    // =========================================================================

    public function rules()
    {
        return [
            [['id', 'shippingMethodId', 'gatewayId', 'orderStatusId'], 'number', 'integerOnly' => true],
            ['condition', 'name', 'string'],
            [['id', 'name', 'condition'], 'required'],
        ];
    }

    public function getShippingRules(): array
    {
        // if (null === $this->_shippingRulesBusinesses) {
        //     $this->_shippingRulesBusinesses = BusinessToBusiness::$plugin->shippingRulesBusinesses->getShippingRulesByBusinessId((int)$this->id);
        // }

        return $this->_shippingRulesBusinesses;
    }

    public function setShippingRuleBusinesses(array $models)
    {
        $this->_shippingRulesBusinesses = $models;
    }

    public function getGatewayRules(): array
    {
        // if (null === $this->_gatewayRulesBusinesses) {
        //     $this->_gatewayRulesBusinesses = BusinessToBusiness::$plugin->gatewayRulesBusinesses->getGatewayRulesByBusinessId((int)$this->id);
        // }

        return $this->_gatewayRulesBusinesses;
    }

    public function setGatewayRuleBusinesses(array $models)
    {
        $this->_gatewayRulesBusinesses = $models;
    }
}
