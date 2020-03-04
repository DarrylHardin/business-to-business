<?php
namespace importantcoding\businesstobusiness\elements;

use importantcoding\businesstobusiness\BusinessToBusiness;
use importantcoding\businesstobusiness\elements\db\EmployeeQuery;
// use importantcoding\businesstobusiness\events\CustomizeEmployeeSnapshotDataEvent;
// use importantcoding\businesstobusiness\events\CustomizeEmployeeSnapshotFieldsEvent;
use importantcoding\businesstobusiness\models\Business as BusinessModel;
use importantcoding\businesstobusiness\records\Employee as EmployeeRecord;
use importantcoding\businesstobusiness\elements\actions\Reset;
use importantcoding\businesstobusiness\elements\actions\Verify;
use importantcoding\businesstobusiness\elements\actions\Remove;
use importantcoding\businesstobusiness\elements\actions\Restore;
use importantcoding\businesstobusiness\elements\actions\UnsetVouchers;
use importantcoding\businesstobusiness\elements\actions\Delete;
use Craft;
use craft\base\Element;
use craft\elements\db\ElementQueryInterface;
use craft\db\Query;

use craft\helpers\ArrayHelper;
use craft\helpers\DateTimeHelper;
use craft\helpers\UrlHelper;
use craft\validators\DateTimeValidator;

// use craft\commerce\base\Purchasable;
// use craft\commerce\elements\Order;
use craft\commerce\models\LineItem;
// use craft\commerce\models\TaxCategory;
// use craft\commerce\models\ShippingCategory;
// use craft\commerce\Plugin as Commerce;

use yii\base\Exception;
// use yii\base\InvalidConfigException;
// use yii\web\IdentityInterface;
class Employee extends Element
{
    // Constants
    // =========================================================================

    const STATUS_AVAILABLE = 'available';
    const STATUS_EXPIRED = 'expired';
    const STATUS_USED = 'used';
    const STATUS_PENDING = 'pending';
    const STATUS_LIVE = 'enabled';
    const STATUS_DISABLED = 'disabled';

    // const EVENT_BEFORE_CAPTURE_VOUCHER_SNAPSHOT = 'beforeCaptureEmployeeSnapshot';
    // const EVENT_AFTER_CAPTURE_VOUCHER_SNAPSHOT = 'afterCaptureEmployeeSnapshot';


    // Properties
    // =========================================================================

    public $id;
    public $businessId;
    public $firstName;
    public $lastName;
    public $employeeNumber;
    public $userId;
    public $voucherId;
    public $voucherAvailable;
    public $timesVoucherUsed;
    public $dateVoucherUsed;
    public $voucherExpired;
    public $phone;
    public $email;
    public $authorized;
    private $_voucher;
    private $_business;
    private $_existingEmployees;


    // Public Methods
    // =========================================================================

    public static function displayName(): string
    {
        return Craft::t('business-to-business', 'Business Employee');
    }

    public function __toString(): string
    {
        return (string)$this->title;
    }

    public function getName()
    {
        return $this->title;
    }

    public function getEmployeeNumber()
    {
        return $this->employeeNumber;
    }

    public static function hasContent(): bool
    {
        return true;
    }

    public static function hasTitles(): bool
    {
        return true;
    }

    // public static function hasUris(): bool
    // {
    //     return true;
    // }

    public static function hasStatuses(): bool
    {
        return true;
    }

    public static function isLocalized(): bool
    {
        return true;
    }

    public static function defineSources(string $context = null): array
    {
        if ($context === 'index') {
            $businesses = BusinessToBusiness::$plugin->business->getEditableBusiness();
            $editable = true;
        } else {
            $businesses = BusinessToBusiness::$plugin->business->getAllBusiness();
            $editable = false;
        }

        $businessIds = [];

        foreach ($businesses as $business) {
            $businessIds[] = $business->id;
        }

        $sources = [
            [
                'key' => '*',
                'label' => Craft::t('business-to-business', 'All employees'),
                'criteria' => [
                    'businessId' => $businessIds,
                    'editable' => $editable
                ],
                'defaultSort' => ['timesVoucherUsed', 'desc']
            ]
        ];

        $sources[] = ['heading' => Craft::t('business-to-business', 'Employee Types')];

        foreach ($businesses as $business) {
            $key = 'business:'.$business->id;
            $canEditEmployees = Craft::$app->getUser()->checkPermission('businessToBusiness-manageEmployee:' . $business->id);

            $sources[$key] = [
                'key' => $key,
                'label' => $business->name,
                'data' => [
                    'handle' => $business->handle,
                    'editable' => $canEditEmployees
                ],
                'criteria' => ['businessId' => $business->id, 'editable' => $editable]
            ];
        }

        return $sources;
    }

    protected static function defineActions(string $source = null): array
    {
        $actions = [];

        $actions[] = Craft::$app->getElements()->createAction([
            'type' => Reset::class,
            'confirmationMessage' => Craft::t('business-to-business', 'Are you sure you want to restore vouchers to the selected employees?'),
            'successMessage' => Craft::t('business-to-business', 'Vouchers restored.'),
        ]);

        $actions[] = Craft::$app->getElements()->createAction([
            'type' => Verify::class,
            'confirmationMessage' => Craft::t('business-to-business', 'Are you sure you want to verify the selected employees?'),
            'successMessage' => Craft::t('business-to-business', 'Employees Verified.'),
        ]);

        $actions[] = Craft::$app->getElements()->createAction([
            'type' => Remove::class,
            'confirmationMessage' => Craft::t('business-to-business', 'Are you sure you want to remove the selected employees?'),
            'successMessage' => Craft::t('business-to-business', 'Employees Removed.'),
        ]);

        $actions[] = Craft::$app->getElements()->createAction([
            'type' => Restore::class,
            'confirmationMessage' => Craft::t('business-to-business', 'Are you sure you want to restore the selected employees?'),
            'successMessage' => Craft::t('business-to-business', 'Employees Restored.'),
        ]);

        $actions[] = Craft::$app->getElements()->createAction([
            'type' => UnsetVouchers::class,
            'confirmationMessage' => Craft::t('business-to-business', 'Are you sure you want to remove vouchers from the selected employees?'),
            'successMessage' => Craft::t('business-to-business', 'Vouchers Removed.'),
        ]);

        $actions[] = Craft::$app->getElements()->createAction([
            'type' => Delete::class,
            'confirmationMessage' => Craft::t('business-to-business', 'This action will completely remove the selected employee from the system, do you wish to continue?'),
            'successMessage' => Craft::t('business-to-business', 'Employees Deleted.'),
        ]);

        return $actions;
    }

    public static function statuses(): array
    {
        return [
            'enabled' => ['label' => \Craft::t('business-to-business', 'Employees'), 'color' => 'grey'],
            'available' => ['label' => \Craft::t('business-to-business', 'Employee W/ Voucher Available'), 'color' => 'enabled'],
            'pending' => ['label' => \Craft::t('business-to-business', 'Employee Pending Verification'), 'color' => 'pending'],
            'expired' => ['label' => \Craft::t('business-to-business', 'Employee Voucher Expired Before Use'), 'color' => 'expired'],
            'used' => ['label' => \Craft::t('business-to-business', 'Employee Voucher Claimed'), 'color' => 'black'],
            'disabled' => ['label' => \Craft::t('business-to-business', 'Removed Employees')],
        ];
    }

    public function getStatuses(): array
    {
        if($this->enabledIsTrue)
        {
            return 'enabled';
        }
        if($this->availableIsTrue)
        {
            return'available';
        }
        if($this->pendingIsTrue)
        {
            return 'pending';
        }
        if($this->expiredIsTrue)
        {
            return 'expired';
        }

        if($this->usedIsTrue)
        {
            return 'used';
        }

        if($this->disabledIsTrue)
        {
            return 'disabled';
        }

        return 'pending';
        return [
            self::STATUS_LIVE => Craft::t('business-to-business', 'Employees'),
            self::STATUS_AVAILABLE => Craft::t('business-to-business', 'Employee Voucher Available'),
            self::STATUS_PENDING => Craft::t('business-to-business', 'Employee Pending'),
            self::STATUS_EXPIRED => Craft::t('business-to-business', 'Employee W/Voucher Expired'),
            self::STATUS_USED => Craft::t('business-to-business', 'Employee W/Voucher Used'),
            self::STATUS_DISABLED => Craft::t('business-to-business', 'Removed Employees')
        ];
    }

    ///// DO  I NEED THIS????
    public function getEditorHtml(): string
    {
        $viewService = Craft::$app->getView();
        $html = $viewService->renderTemplateMacro('business-to-business/employees/_fields', 'titleField', [$this]);
        $html .= parent::getEditorHtml();
        $html .= $viewService->renderTemplateMacro('business-to-business/employees/_fields', 'generalFields', [$this]);
        $html .= $viewService->renderTemplateMacro('business-to-business/employees/_fields', 'pricingFields', [$this]);
        $html .= $viewService->renderTemplateMacro('business-to-business/employees/_fields', 'behavioralMetaFields', [$this]);
        $html .= $viewService->renderTemplateMacro('business-to-business/employees/_fields', 'generalMetaFields', [$this]);

        return $html;
    }

    public function setEagerLoadedElements(string $handle, array $elements)
    {
        if ($handle === 'existingEmployees') {
            $this->_existingEmployees = $elements;

            return;
        }

        parent::setEagerLoadedElements($handle, $elements);
    }

    public static function eagerLoadingMap(array $sourceElements, string $handle)
    {
        if ($handle === 'existingEmployees') {
            $userId = Craft::$app->getUser()->getId();

            if ($userId)
            {
                $sourceElementIds = ArrayHelper::getColumn($sourceElements, 'id');

                $map = (new Query())
                    ->select('id as source, id as target')
                    ->from('{{%businesstobusiness_employees}}')
                    ->where(['in', 'id', $sourceElementIds])
                    ->andWhere(['userId' => $userId])
                    ->all();

                return array(
                    'elementType' => Employee::class,
                    'map' => $map
                );
            }
        }

        return parent::eagerLoadingMap($sourceElements, $handle);
    }

    // public function getIsAvailable(): bool
    // {
    //     return $this->getStatus() === static::STATUS_LIVE;
    // }

    public function getStatus()
    {
        $status = parent::getStatus();
        $currentTime = DateTimeHelper::currentTimeStamp();
        
        $expired = $this->voucherExpired;

        if($this->voucherId && !$expired)
        {
            $voucher = BusinessToBusiness::$plugin->voucher->getVoucherById($this->voucherId);
            if($voucher->expiryDate)
            {
                if($voucher->expiryDate->getTimestamp() < $currentTime)
                {
                    $expired = 1;
                    $employee = BusinessToBusiness::$plugin->employee->getEmployeeById($this->id);
                    $employee->voucherExpired = 1;
                    Craft::$app->getElements()->saveElement($employee);
                }
            }
        }
        
        if($this->voucherId)
        {
            $voucher = BusinessToBusiness::$plugin->voucher->getVoucherById($this->voucherId);
        }
        
        
        

        if ($status === self::STATUS_LIVE) {
            
            $voucherAvailable = $this->voucherAvailable;
            // $dateVoucherUsed = $this->dateVoucherUsed ? $this->dateVoucherUsed->getTimestamp() : null;
            $authorized = $this->authorized;
            if ($authorized && $voucher) {
                $voucherExpiry = $voucher->expiryDate;
                $voucherExpired = false;
                if($voucherExpiry != NULL)
                {
                    $voucherExpiry = $voucher->expiryDate->getTimestamp();
                    if($voucherAvailable == 1 && $voucher->expiryDate->getTimestamp() < $currentTime)
                    {
                        $voucherExpired = true;
                    }
                }
                if ($voucherAvailable == 1 && $voucherExpired) {
                    return self::STATUS_EXPIRED;
                } else if($voucherAvailable === NULL)
                {
                    return self::STATUS_USED;
                }
                return self::STATUS_AVAILABLE;
            } else {
                return self::STATUS_PENDING;
            }
        }
        
        return self::STATUS_DISABLED;
        return $status;
    }

    public function rules(): array
    {
        $rules = parent::rules();
        // $rules[] = [['businessId', 'userId', 'timesVoucherUsed'], 'required'];
        $rules[] = [['businessId', 'userId'], 'required'];
        // $rules[] = ['number', 'string'];
        $rules[] = ['dateVoucherUsed', DateTimeValidator::class];

        return $rules;
    }

    public static function find(): ElementQueryInterface
    {
        return new EmployeeQuery(static::class);
    }


    public function datetimeAttributes(): array
    {
        $attributes = parent::datetimeAttributes();
        $attributes[] = 'dateVoucherUsed';

        return $attributes;
    }

    public function getIsEditable(): bool
    {
        if ($this->getBusiness()) {
            $id = $this->getBusiness()->id;

            return Craft::$app->getUser()->checkPermission('businessToBusiness-manageEmployee:' . $id);
        }

        return false;
    }

    public function getCpEditUrl()
    {
        $business = $this->getBusiness();

        if ($business) {
            return UrlHelper::cpUrl('business-to-business/employees/' . $business->handle . '/' . $this->id);
        }

        return null;
    }

    public function getPdfUrl(LineItem $lineItem, $option = null)
    {
        return BusinessToBusiness::$plugin->getPdf()->getPdfUrl($lineItem->order, $lineItem);
    }

    // public function getCodes(LineItem $lineItem)
    // {
    //     return Code::find()
    //         ->orderId($lineItem->order->id)
    //         ->lineItemId($lineItem->id)
    //         ->all();
    // }

    // public function getProduct()
    // {
    //     return $this;
    // }

    public function getFieldLayout()
    {
        // $business = $this->getBusiness();

        // return $business ? $business->getVoucherFieldLayout() : null;
        return \Craft::$app->fields->getLayoutByType(Employee::class);
    }


    public function getBusiness()
    {
        if ($this->_business) {
            return $this->_business;
        }

        return $this->businessId ? $this->_business = BusinessToBusiness::$plugin->business->getBusinessById($this->businessId) : null;
    }

    public function getVoucher()
    {
        if ($this->_voucher) {
            return $this->_voucher;
        }

        return $this->voucherId ? $this->_voucher = BusinessToBusiness::$plugin->voucher->getVoucherById($this->voucherId) : null;
    }


    public function beforeSave(bool $isNew): bool
    {
        // if ($this->enabled && !$this->timesVoucherUsed) {
        //     // Default the post date to the current date/time
        //     $this->timesVoucherUsed = DateTimeHelper::currentUTCDateTime();
        // }

        return parent::beforeSave($isNew);
    }

    public function afterSave(bool $isNew)
    {
        if (!$isNew) {
            $employeeRecord = EmployeeRecord::findOne($this->id);

            if (!$employeeRecord) {
                throw new Exception('Invalid employee id: '.$this->id);
            }
            
            
        } else {
            $employeeRecord = new EmployeeRecord();
            $employeeRecord->id = $this->id;
        }
        $employeeRecord->voucherAvailable = $this->voucherAvailable;
        $employeeRecord->authorized = $this->authorized;
        $employeeRecord->userId = $this->userId;
        $employeeRecord->phone = $this->phone;
        $employeeRecord->firstName = $this->firstName;
        $employeeRecord->lastName = $this->lastName;
        $employeeRecord->email = $this->email;
        $employeeRecord->businessId = $this->businessId;
        
        $employeeRecord->voucherId = $this->voucherId;
        $employeeRecord->timesVoucherUsed = $this->timesVoucherUsed;
        $employeeRecord->dateVoucherUsed = $this->dateVoucherUsed;
        $employeeRecord->voucherExpired = $this->voucherExpired;
        $employeeRecord->save(false);

        return parent::afterSave($isNew);
    }


    // Implement Purchasable
    // =========================================================================

    // public function getPurchasableId(): int
    // {
    //     return $this->id;
    // }
    
    // public function getSnapshot(): array
    // {
    //     $data = [];

    //     $data['type'] = self::class;

    //     // Default Employee custom field handles
    //     $employeeFields = [];
    //     // $employeeDataEvent = new CustomizeEmployeeSnapshotFieldsEvent([
    //         'employee' => $this,
    //         'fields' => $employeeFields,
    //     ]);

    //     // // Allow plugins to modify fields to be fetched
    //     // if ($this->hasEventHandlers(self::EVENT_BEFORE_CAPTURE_VOUCHER_SNAPSHOT)) {
    //     //     $this->trigger(self::EVENT_BEFORE_CAPTURE_VOUCHER_SNAPSHOT, $voucherFieldsEvent);
    //     // }

    //     // Capture specified Employee field data
    //     // $voucherFieldData = $this->getSerializedFieldValues($voucherFieldsEvent->fields);
    //     // $employeeDataEvent = new CustomizeEmployeeSnapshotDataEvent([
    //     //     'employee' => $this,
    //     //     'fieldData' => $voucherFieldData,
    //     // ]);

    //     // // Allow plugins to modify captured Employee data
    //     // if ($this->hasEventHandlers(self::EVENT_AFTER_CAPTURE_VOUCHER_SNAPSHOT)) {
    //     //     $this->trigger(self::EVENT_AFTER_CAPTURE_VOUCHER_SNAPSHOT, $employeeDataEvent);
    //     // }

    //     $data['fields'] = $employeeDataEvent->fieldData;

    //     return array_merge($this->getAttributes(), $data);
    // }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function getDescription(): string
    {
        return $this->title;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getVoucherId(): string
    {
        return $this->voucherId;
    }

    public function getAuthorized(): int
    {
        return $this->authorized;
    }

    public function getVoucherAvailable(): int
    {
        return $this->voucherAvailable;
    }

    public function getVoucherExpired(): int
    {
        return $this->voucherExpired;
    }

    // public function getTaxCategoryId(): int
    // {
    //     return $this->taxCategoryId;
    // }

    // public function getShippingCategoryId(): int
    // {
    //     return $this->shippingCategoryId;
    // }

    // public function hasFreeShipping(): bool
    // {
    //     return true;
    // }

    // public function getIsPromotable(): bool
    // {
    //     return true;
    // }

    // public function populateLineItem(LineItem $lineItem)
    // {
    //     if ($lineItem->purchasable === $this && $lineItem->purchasable->customAmount) {
    //         $options = $lineItem->options;

    //         if (isset($options['amount'])) {
    //             $lineItem->amount = $options['amount'];
    //         }
    //     }
    // }


    // Protected methods
    // =========================================================================

    // protected function route()
    // {
    //     // Make sure the employee type is set to have URLs for this site
    //     $siteId = Craft::$app->getSites()->currentSite->id;
    //     $businessSitesSettings = $this->getBusiness()->getSiteSettings();

    //     if (!isset($businessSitesSettings[$siteId]) || !$businessSitesSettings[$siteId]->hasUrls) {
    //         return null;
    //     }

    //     return [
    //         'templates/render', [
    //             'template' => $businessSitesSettings[$siteId]->template,
    //             'variables' => [
    //                 'employee' => $this,
    //                 // 'product' => $this,
    //             ]
    //         ]
    //     ];
    // }

    protected static function defineTableAttributes(): array
    {
        return [
            'title' => ['label' => Craft::t('business-to-business', 'Employee Number')],
            'firstName'  => ['label' => Craft::t('business-to-business', 'First Name')],
            'lastName'  => ['label' => Craft::t('business-to-business', 'Last Name')],
            'type' => ['label' => Craft::t('business-to-business', 'Business')],
            'voucher' => ['label' => Craft::t('business-to-business', 'Voucher')],
            'timesVoucherUsed' => ['label' => Craft::t('business-to-business', 'Times Voucher Used')],
            'dateVoucherUsed' => ['label' => Craft::t('business-to-business', 'Last Date Voucher Used')],
        ];
    }

    protected static function defineDefaultTableAttributes(string $source): array
    {
        $attributes = [];

        $attributes[] = 'type';
        $attributes[] = 'firstName';
        $attributes[] = 'lastName';
        $attributes[] = 'voucher';
        $attributes[] = 'timesVoucherUsed';
        $attributes[] = 'dateVoucherUsed';
        
        return $attributes;
    }

    protected static function defineSearchableAttributes(): array
    {
        return ['title'];
    }

    protected function tableAttributeHtml(string $attribute): string
    {
        /* @var $business Business */
        $business = $this->getBusiness();
        $voucher = $this->getVoucher();
        switch ($attribute) {
            case 'type':
                return ($business ? Craft::t('site', $business->name) : '');
            
            case 'voucher':
                return ($voucher ? Craft::t('site', $voucher->name) : '');
            // case 'taxCategory':
            //     $taxCategory = $this->getTaxCategory();

            //     return ($taxCategory ? Craft::t('site', $taxCategory->name) : '');

            // case 'shippingCategory':
            //     $shippingCategory = $this->getShippingCategory();

            //     return ($shippingCategory ? Craft::t('site', $shippingCategory->name) : '');

            // case 'defaultAmount':
            //     $code = Commerce::$plugin->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();

            //     return Craft::$app->getLocale()->getFormatter()->asCurrency($this->$attribute, strtoupper($code));

            // case 'promotable':
            //     return ($this->$attribute ? '<span data-icon="check" title="'.Craft::t('business-to-business', 'Yes').'"></span>' : '');

            default:
                return parent::tableAttributeHtml($attribute);
        }
    }

    protected static function defineSortOptions(): array
    {
        return [
            'title' => Craft::t('business-to-business', 'Employee Number'),
            'type' => Craft::t('business-to-business', 'Business'),
            // 'voucher' => Craft::t('business-to-business', 'Voucher'),
            'firstName'  => Craft::t('business-to-business', 'First Name'),
            'lastName'  => Craft::t('business-to-business', 'Last Name'),
            'voucherId' => Craft::t('business-to-business', 'Voucher'),
        ];
    }
}
