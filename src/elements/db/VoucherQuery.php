<?php
namespace importantcoding\businesstobusiness\elements\db;

use importantcoding\businesstobusiness\BusinessToBusiness;
use importantcoding\businesstobusiness\elements\Voucher;
use importantcoding\businesstobusiness\models\Business as BusinessModel;

use Craft;
use craft\db\Query;
use craft\db\QueryAbortedException;
use craft\elements\db\ElementQuery;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;

use DateTime;
use yii\db\Connection;

class VoucherQuery extends ElementQuery
{
    // Properties
    // =========================================================================

    public $editable = false;
    public $businessId;
    public $postDate;
    public $expiryDate;


    // Public Methods
    // =========================================================================

    public function __construct(string $elementType, array $config = [])
    {
        // Default status
        if (!isset($config['status'])) {
            $config['status'] = Voucher::STATUS_LIVE;
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
        if ($value instanceof DateTime) {
            $value = $value->format(DateTime::W3C);
        }

        $this->postDate = ArrayHelper::toArray($this->postDate);
        $this->postDate[] = '<'.$value;

        return $this;
    }

    public function after($value)
    {
        if ($value instanceof DateTime) {
            $value = $value->format(DateTime::W3C);
        }

        $this->postDate = ArrayHelper::toArray($this->postDate);
        $this->postDate[] = '>='.$value;

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

    public function postDate($value)
    {
        $this->postDate = $value;

        return $this;
    }

    public function expiryDate($value)
    {
        $this->expiryDate = $value;

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

        $this->joinElementTable('businesstobusiness_voucher');

        $this->query->select([
            'businesstobusiness_voucher.id',
            'businesstobusiness_voucher.businessId',
            'businesstobusiness_voucher.postDate',
            'businesstobusiness_voucher.expiryDate',
            'businesstobusiness_voucher.code',
            'businesstobusiness_voucher.amount',
            'businesstobusiness_voucher.productLimit',
            'businesstobusiness_voucher.products'
        ]);

        if ($this->postDate) {
            $this->subQuery->andWhere(Db::parseDateParam('businesstobusiness_voucher.postDate', $this->postDate));
        }

        if ($this->expiryDate) {
            $this->subQuery->andWhere(Db::parseDateParam('businesstobusiness_voucher.expiryDate', $this->expiryDate));
        }

        if ($this->businessId) {
            $this->subQuery->andWhere(Db::parseParam('businesstobusiness_voucher.businessId', $this->businessId));
        }

        $this->_applyEditableParam();

        return parent::beforePrepare();
    }

    protected function statusCondition(string $status)
    {
        $currentTimeDb = Db::prepareDateForDb(new \DateTime());

        switch ($status) {
            case Voucher::STATUS_LIVE:
                return [
                    'and',
                    [
                        'elements.enabled' => true,
                        'elements_sites.enabled' => true
                    ],
                    ['<=', 'businesstobusiness_voucher.postDate', $currentTimeDb],
                    [
                        'or',
                        ['businesstobusiness_voucher.expiryDate' => null],
                        ['>', 'businesstobusiness_voucher.expiryDate', $currentTimeDb]
                    ]
                ];
            case Voucher::STATUS_PENDING:
                return [
                    'and',
                    [
                        'elements.enabled' => true,
                        'elements_sites.enabled' => true
                    ],
                    ['>', 'businesstobusiness_voucher.postDate', $currentTimeDb]
                ];
            case Voucher::STATUS_EXPIRED:
                return [
                    'and',
                    [
                        'elements.enabled' => true,
                        'elements_sites.enabled' => true
                    ],
                    ['not', ['businesstobusiness_voucher.expiryDate' => null]],
                    ['<=', 'businesstobusiness_voucher.expiryDate', $currentTimeDb]
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
