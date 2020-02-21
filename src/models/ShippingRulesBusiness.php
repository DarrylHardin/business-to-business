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
 * ShippingRulesBusiness Model
 *
 *
 * @author    Darryl Hardin
 * @package   BusinessToBusiness
 * @since     1.0.0
 */
class ShippingRulesBusiness extends Model
{
      // Properties
    // =========================================================================

    public $id;
    public $businessId;
    public $voucherId;
    public $shippingMethodId;
    public $condition;
    public $name;
    // pretty sure the below will be deleted
    public $includeShippingCosts;
    private $_shippingRulesBusinesses;

    // Public Methods
    // =========================================================================

    public function rules()
    {
        return [
            [['id', 'businessId', 'voucherId', 'shippingMethodId'], 'number', 'integerOnly' => true],
            ['includeShippingCosts', 'number'],
            ['name','condition', 'string'],
            [['id', 'name', 'businessId', 'shippingMethodId', 'condition'], 'required'],
        ];
    }

    public function getShippingRuleBusinesses(): array
    {
        if (null === $this->_shippingRulesBusinesses) {
            $this->_shippingRulesBusinesses = BusinessToBusiness::$plugin->shippingRulesBusinesses->getShippingRulesByBusinessId((int)$this->id);
        }

        return $this->_shippingRulesBusinesses;
    }

    public function setShippingRuleBusinesses(array $models)
    {
        $this->_shippingRulesBusinesses = $models;
    }
}
