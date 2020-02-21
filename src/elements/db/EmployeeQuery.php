<?php
namespace importantcoding\businesstobusiness\elements\db;

use importantcoding\businesstobusiness\BusinessToBusiness;
use importantcoding\businesstobusiness\elements\Employee;
use importantcoding\businesstobusiness\models\Business as BusinessModel;

use Craft;
use craft\db\Query;
use craft\db\QueryAbortedException;
use craft\elements\db\ElementQuery;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;

use DateTime;
use yii\db\Connection;

class EmployeeQuery extends ElementQuery
{
   // Properties
    // =========================================================================

    public $editable = false;
    public $businessId;
    public $dateVoucherUsed;
    public $timesVoucherUsed;
    public $voucherAvailable;
    public $voucherExpired;
    public $email;
    public $phone;
    public $authorized;
    public $firstName;
    public $lastName;
    public $userId;
    // Public Methods
    // =========================================================================

    public function __construct(string $elementType, array $config = [])
    {
        // Default status
        if (!isset($config['status'])) {
            $config['status'] = Employee::STATUS_LIVE;
        }

        parent::__construct($elementType, $config);
    }

    public function __set($name, $value)
    {
        switch ($name) {
            case 'type':
                $this->type($value);
                break;
            case 'before':
                $this->before($value);
                break;
            case 'after':
                $this->after($value);
                break;
            default:
                parent::__set($name, $value);
        }
    }

    public function type($value)
    {
        if ($value instanceof Business) {
            $this->businessId = $value->id;
        } else if ($value !== null) {
            $this->businessId = (new Query())
                ->select(['id'])
                ->from(['{{%businesstobusiness_business}}'])
                ->where(Db::parseParam('handle', $value))
                ->column();
        } else {
            $this->businessId = null;
        }

        return $this;
    }

    public function before($value)
    {
        // if ($value instanceof DateTime) {
        //     $value = $value->format(DateTime::W3C);
        // }

        // $this->postDate = ArrayHelper::toArray($this->postDate);
        // $this->postDate[] = '<'.$value;

        return $this;
    }

    public function after($value)
    {
        // if ($value instanceof DateTime) {
        //     $value = $value->format(DateTime::W3C);
        // }

        // $this->postDate = ArrayHelper::toArray($this->postDate);
        // $this->postDate[] = '>='.$value;

        return $this;
    }

    public function editable(bool $value = true)
    {
        $this->editable = $value;

        return $this;
    }

    public function businessId($value)
    {
        $this->businessId = $value;

        return $this;
    }

    public function dateVoucherUsed($value)
    {
        $this->dateVoucherUsed = $value;

        return $this;
    }

    public function timesVoucherUsed($value)
    {
        $this->timesVoucherUsed = $value;

        return $this;
    }

    public function voucherAvailable($value)
    {
        $this->voucherAvailable = $value;

        return $this;
    }

    public function email($value)
    {
        $this->email = $value;

        return $this;
    }

    public function phone($value)
    {
        $this->phone = $value;

        return $this;
    }

    public function firstName($value)
    {
        $this->firstName = $value;

        return $this;
    }

    public function lastName($value)
    {
        $this->lastName = $value;

        return $this;
    }

    public function userId($value)
    {
        $this->userId = $value;

        return $this;
    }

    public function authorized($value)
    {
        $this->authorized = $value;

        return $this;
    }

    public function voucherExpired($value)
    {
        $this->voucherExpired = $value;

        return $this;
    }

    // Protected Methods
    // =========================================================================

    protected function beforePrepare(): bool
    {
        // See if 'type' were set to invalid handles
        if ($this->businessId === []) {
            return false;
        }

        $this->joinElementTable('businesstobusiness_employee');

        $this->query->select([
            'businesstobusiness_employee.id',
            'businesstobusiness_employee.businessId',
            'businesstobusiness_employee.userId',
            'businesstobusiness_employee.firstName',
            'businesstobusiness_employee.lastName',
            'businesstobusiness_employee.voucherId',
            'businesstobusiness_employee.timesVoucherUsed',
            'businesstobusiness_employee.dateVoucherUsed',
            'businesstobusiness_employee.voucherAvailable',
            'businesstobusiness_employee.voucherExpired',
            // 'businesstobusiness_employee.number',
            'businesstobusiness_employee.email',
            'businesstobusiness_employee.phone',
            'businesstobusiness_employee.authorized',
        ]);

        if ($this->dateVoucherUsed) {
            $this->subQuery->andWhere(Db::parseDateParam('businesstobusiness_employee.dateVoucherUsed', $this->dateVoucherUsed));
        }

        // if ($this->timesVoucherUsed) {
        //     $this->subQuery->andWhere(Db::parseDateParam('businesstobusiness_employee.timesVoucherUsed', $this->timesVoucherUsed));
        // }

        if ($this->businessId) {
            $this->subQuery->andWhere(Db::parseParam('businesstobusiness_employee.businessId', $this->businessId));
        }

        if ($this->authorized) {
            $this->subQuery->andWhere(Db::parseParam('businesstobusiness_employee.authorized', $this->authorized));
        }

        if ($this->voucherExpired) {
            $this->subQuery->andWhere(Db::parseParam('businesstobusiness_employee.voucherExpired', $this->voucherExpired));
        }

        $this->_applyEditableParam();

        return parent::beforePrepare();
    }

    protected function statusCondition(string $status)
    {
        $currentTimeDb = Db::prepareDateForDb(new \DateTime());
        
        switch ($status) {
            case Employee::STATUS_AVAILABLE:
                return [
                    'and',
                    [
                        'elements.enabled' => true,
                        'businesstobusiness_employee.authorized' => 1,
                        'businesstobusiness_employee.voucherAvailable' => 1,
                        'businesstobusiness_employee.voucherExpired' => NULL,
                    ]
                    /**** The below commented code needs to either check against time or it needs to check an expired ****/
                    // ['<=', 
                    // 'businesstobusiness_employee.dateVoucherUsed', $currentTimeDb,
                    // ]
                ];
            case Employee::STATUS_PENDING:
                return [
                    'and',
                    [
                        'elements.enabled' => true,
                        'businesstobusiness_employee.authorized' => NULL
                    ]
                ];
            case Employee::STATUS_EXPIRED:
                return [
                    'and',
                    [
                        'elements.enabled' => true,
                        'businesstobusiness_employee.voucherAvailable' => 1,
                        'businesstobusiness_employee.voucherExpired' => 1,
                    ],   
                ];
            case Employee::STATUS_USED:
                return [
                    'and',
                    [
                        'elements.enabled' => true
                    ],   
                    ['and', ['businesstobusiness_employee.voucherAvailable' => NULL]]
                ];
            case Employee::STATUS_LIVE:
                return [
                    'and',
                    [
                        'elements.enabled' => true,
                    ],
                ];
            case Employee::STATUS_DISABLED:
                return [
                    'and',
                    [
                        'elements.enabled' => false,
                    ]
                ];
            default:
                return parent::statusCondition($status);
        }
    }

    // Private Methods
    // =========================================================================

    private function _applyEditableParam()
    {
        if (!$this->editable) {
            return;
        }

        $user = Craft::$app->getUser()->getIdentity();

        if (!$user) {
            throw new QueryAbortedException();
        }


    }
}
