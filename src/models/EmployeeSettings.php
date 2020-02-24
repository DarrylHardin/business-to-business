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
use importantcoding\businesstobusiness\elements\Voucher;
use importantcoding\businesstobusiness\records\Business as BusinessRecord;

use Craft;
use craft\base\Model;
use craft\behaviors\FieldLayoutBehavior;
use craft\helpers\ArrayHelper;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use craft\validators\HandleValidator;
use craft\validators\UniqueValidator;

/**
 * EmployeeSettings Model
 *
 * @author    Darryl Hardin
 * @package   BusinessToBusiness
 * @since     1.0.0
 */
class EmployeeSettings extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * Some model attribute
     *
     * @var string
     */
    public $id;
    public $name;
    public $handle;
    public $autoVerify;
    public $discount;
    public $new;
    public $passcode;
    public $taxExempt;
    public $limitShippingMethods;

    public $fieldLayoutId;
    public $managerId;

    private $_shippingRuleBusinesses;
    private $_siteSettings;
    private $_manager;
    // Public Methods
    // =========================================================================

    /**
     * Returns the validation rules for attributes.
     *
     * Validation rules are used by [[validate()]] to check if attribute values are valid.
     * Child classes may override this method to declare different validation rules.
     *
     * More info: http://www.yiiframework.com/doc-2.0/guide-input-validation.html
     *
     * @return array
     */
    public function rules()
    {
        return [
            [['id', 'managerId', 'fieldLayoutId'], 'number', 'integerOnly' => true],
            [['discount', 'autoVerify', 'taxExempt', 'limitShippingMethods'], 'number'],
            [['name', 'handle', 'passcode'], 'required'],
            [['name', 'handle', 'passcode'], 'string', 'max' => 255],
            [['handle'], UniqueValidator::class, 'targetClass' => BusinessRecord::class, 'targetAttribute' => ['handle'], 'message' => 'Not Unique'],
            [['handle'], HandleValidator::class, 'reservedWords' => ['id', 'dateCreated', 'dateUpdated', 'uid', 'title']],
        ];
    }

    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('business-to-business/business/' . $this->id);
    }

    public function getSiteSettings(): array
    {
        if ($this->_siteSettings !== null) {
            return $this->_siteSettings;
        }

        if (!$this->id) {
            return [];
        }

        $this->setSiteSettings(ArrayHelper::index(BusinessToBusiness::$plugin->business->getBusinessSites($this->id), 'siteId'));

        return $this->_siteSettings;
    }

    public function setSiteSettings(array $siteSettings)
    {
        $this->_siteSettings = $siteSettings;

        foreach ($this->_siteSettings as $settings) {
            $settings->setBusiness($this);
        }
    }

   

    public function getVoucherFieldLayout(): FieldLayout
    {
        $behavior = $this->getBehavior('voucherFieldLayout');
        return $behavior->getFieldLayout();
    }

    public function behaviors(): array
    {
        return [
            'voucherFieldLayout' => [
                'class' => FieldLayoutBehavior::class,
                'elementType' => Voucher::class,
                'idAttribute' => 'fieldLayoutId'
            ]
        ];
    }
    /**
     * Returns the Business' manager.
     */
    public function getManager()
    {
        if ($this->_manager !== null) {
            return $this->_manager;
        }

        if ($this->managerId === null) {
            return null;
        }

        if (($this->_manager = Craft::$app->getUsers()->getUserById($this->managerId)) === null) {
            // The manager is probably soft-deleted. Just no author is set
            return null;
        }

        return $this->_manager;
    }

    public function getShippingRuleBusinesses(): array
    {
        if (null === $this->_shippingRuleBusinesses) {
            $this->_shippingRuleBusinesses = BusinessToBusiness::$plugin->shippingRulesBusinesses->getShippingRulesByBusinessId((int)$this->id);
        }

        return $this->_shippingRuleBusinesses;
    }

    public function setShippingRuleBusinesses(array $models)
    {
        $this->_shippingRuleBusinesses = $models;
    }

    public function getGatewayRuleBusinesses(): array
    {
        if (null === $this->_gatewayRuleBusinesses) {
            $this->_gatewayRuleBusinesses = BusinessToBusiness::$plugin->gatewayRulesBusinesses->getGatewayRulesByBusinessId((int)$this->id);
        }

        return $this->_gatewayRuleBusinesses;
    }

    public function setGatewayRuleBusinesses(array $models)
    {
        $this->_gatewayRuleBusinesses = $models;
    }
}
