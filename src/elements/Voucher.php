<?php
namespace importantcoding\businesstobusiness\elements;

use importantcoding\businesstobusiness\BusinessToBusiness;
use importantcoding\businesstobusiness\elements\db\VoucherQuery;
use importantcoding\businesstobusiness\events\CustomizeVoucherSnapshotDataEvent;
use importantcoding\businesstobusiness\events\CustomizeVoucherSnapshotFieldsEvent;
use importantcoding\businesstobusiness\models\Business as BusinessModel;
use importantcoding\businesstobusiness\records\Voucher as VoucherRecord;

use Craft;
use craft\base\Element;
use craft\elements\db\ElementQuery;
use craft\elements\db\ElementQueryInterface;
use craft\db\Query;
use craft\elements\actions\Delete;
use craft\helpers\ArrayHelper;
use craft\helpers\DateTimeHelper;
use craft\helpers\UrlHelper;
use craft\validators\DateTimeValidator;
use craft\helpers\Json;
// use craft\commerce\base\Purchasable;
// use craft\commerce\elements\Order;
// use craft\commerce\models\LineItem;
// use craft\commerce\models\ShippingCategory;
use craft\commerce\Plugin as Commerce;

use yii\base\Exception;
use yii\base\InvalidConfigException;

class Voucher extends Element
{
    // Constants
    // =========================================================================

    const STATUS_LIVE = 'enabled';
    const STATUS_EXPIRED = 'expired';
    const STATUS_PENDING = 'pending';
    const STATUS_DISABLED = 'disabled';

    const EVENT_BEFORE_CAPTURE_VOUCHER_SNAPSHOT = 'beforeCaptureVoucherSnapshot';
    const EVENT_AFTER_CAPTURE_VOUCHER_SNAPSHOT = 'afterCaptureVoucherSnapshot';


    // Properties
    // =========================================================================

    public $id;
    public $businessId;
    public $postDate;
    public $expiryDate;
    // public $sku;
    public $code;
    public $amount;
    public $productLimit;
    public $payrollDeduction;
    public $products;
    private $_business;
    private $_existingEmployees;
    private $_products;

    // Public Methods
    // =========================================================================

    public static function displayName(): string
    {
        return Craft::t('business-to-business', 'Business Voucher');
    }

    public function __toString(): string
    {
        return (string)$this->title;
    }

    public function getName()
    {
        return $this->title;
    }

    public static function hasContent(): bool
    {
        return true;
    }

    public static function hasTitles(): bool
    {
        return true;
    }

    public static function hasUris(): bool
    {
        return true;
    }

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
                'label' => Craft::t('business-to-business', 'All Business Vouchers'),
                'criteria' => [
                    'businessId' => $businessIds,
                    'editable' => $editable
                ],
                'defaultSort' => ['postDate', 'desc']
            ]
        ];

        $sources[] = ['heading' => Craft::t('business-to-business', 'Businesses')];

        foreach ($businesses as $business) {
            $key = 'business:'.$business->id;
            $canEditVouchers = Craft::$app->getUser()->checkPermission('businessToBusiness-manageEmployee:' . $business->id);

            $sources[$key] = [
                'key' => $key,
                'label' => $business->name,
                'data' => [
                    'handle' => $business->handle,
                    'editable' => $canEditVouchers
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
            'type' => Delete::class,
            'confirmationMessage' => Craft::t('business-to-business', 'Are you sure you want to delete the selected vouchers?'),
            'successMessage' => Craft::t('business-to-business', 'Vouchers deleted.'),
        ]);

        return $actions;
    }

    public static function statuses(): array
    {
        return [
            'enabled' => ['label' => \Craft::t('business-to-business', 'Enabled')],
            'pending' => ['label' => \Craft::t('business-to-business', 'Pending'), 'color' => 'pending'],
            'expired' => ['label' => \Craft::t('business-to-business', 'Expired'), 'color' => 'expired'],
            'disabled' => ['label' => \Craft::t('business-to-business', 'Disabled')],
        ];
    }

    public function getStatuses(): array
    {
        if($this->enabledIsTrue)
        {
            return 'enabled';
        }
        if($this->pendingIsTrue)
        {
            return 'pending';
        }
        if($this->expiredIsTrue)
        {
            return 'expired';
        }
        if($this->disabledIsTrue)
        {
            return 'disabled';
        }
        return [
            self::STATUS_LIVE => Craft::t('business-to-business', 'Enabled'),
            self::STATUS_PENDING => Craft::t('business-to-business', 'Pending'),
            self::STATUS_EXPIRED => Craft::t('business-to-business', 'Expired'),
            self::STATUS_DISABLED => Craft::t('business-to-business', 'Disabled')
        ];
    }

    

    // public function getEditorHtml(): string
    // {
    //     $viewService = Craft::$app->getView();
    //     $html = $viewService->renderTemplateMacro('business-to-business/vouchers/_fields', 'titleField', [$this]);
    //     $html .= parent::getEditorHtml();
    //     $html .= $viewService->renderTemplateMacro('business-to-business/vouchers/_fields', 'generalFields', [$this]);
    //     $html .= $viewService->renderTemplateMacro('business-to-business/vouchers/_fields', 'pricingFields', [$this]);
    //     $html .= $viewService->renderTemplateMacro('business-to-business/vouchers/_fields', 'behavioralMetaFields', [$this]);
    //     $html .= $viewService->renderTemplateMacro('business-to-business/vouchers/_fields', 'generalMetaFields', [$this]);

    //     return $html;
    // }

    // public function setEagerLoadedElements(string $handle, array $elements)
    // {
    //     if ($handle === 'existingEmployees') {
    //         $this->_existingEmployees = $elements;

    //         return;
    //     }

    //     parent::setEagerLoadedElements($handle, $elements);
    // }

    // public static function eagerLoadingMap(array $sourceElements, string $handle)
    // {
    //     if ($handle === 'existingEmployees') {
    //         $userId = Craft::$app->getUser()->getId();

    //         if ($userId)
    //         {
    //             $sourceElementIds = ArrayHelper::getColumn($sourceElements, 'id');

    //             $map = (new Query())
    //                 ->select('voucherId as source, id as target')
    //                 ->from('{{%businesstobusiness_employees}}')
    //                 ->where(['in', 'voucherId', $sourceElementIds])
    //                 ->andWhere(['userId' => $userId])
    //                 ->all();

    //             return array(
    //                 'elementType' => Employee::class,
    //                 'map' => $map
    //             );
    //         }
    //     }

    //     return parent::eagerLoadingMap($sourceElements, $handle);
    // }

    public function getIsAvailable(): bool
    {
        return $this->getStatus() === static::STATUS_LIVE;
    }

    public function getStatus()
    {
        $status = parent::getStatus();

        if ($status === self::STATUS_LIVE && $this->postDate) {


            $currentTime = DateTimeHelper::currentTimeStamp();
            $postDate = $this->postDate->getTimestamp();
            $expiryDate = $this->expiryDate ? $this->expiryDate->getTimestamp() : null;

            if ($postDate <= $currentTime && (!$expiryDate || $expiryDate > $currentTime)) {
                return self::STATUS_LIVE;
            }
            if ($postDate > $currentTime) {
                return self::STATUS_PENDING;
            }
            if ($expiryDate < $currentTime) {
                return self::STATUS_EXPIRED;
            }
            return self::STATUS_DISABLED;
        }

        return $status;
    }

    public function rules(): array
    {
        $rules = parent::rules();

        $rules[] = [['businessId', 'amount', 'productLimit'], 'required'];
        $rules[] = [['postDate', 'expiryDate'], DateTimeValidator::class];

        return $rules;
    }

    public static function find(): ElementQueryInterface
    {
        return new VoucherQuery(static::class);
    }


    public function datetimeAttributes(): array
    {
        $attributes = parent::datetimeAttributes();
        $attributes[] = 'postDate';
        $attributes[] = 'expiryDate';

        return $attributes;
    }

    public function getIsEditable(): bool
    {
        if ($this->getBusiness()) {
            $id = $this->getBusiness()->id;

            return Craft::$app->getUser()->checkPermission('businessToBusiness-manageVoucher:' . $id);
        }

        return false;
    }

    public function getCpEditUrl()
    {
        $business = $this->getBusiness();

        if ($business) {
            $url =  UrlHelper::cpUrl('business-to-business/vouchers/' . $business->handle . '/' . $this->id);
            if (Craft::$app->getIsMultiSite()) {
                $url .= '/' . $this->getSite()->handle;
            }
    
            return $url;
        }

        return null;
    }

    public function getPdfUrl(LineItem $lineItem, $option = null)
    {
        return BusinessToBusiness::$plugin->getPdf()->getPdfUrl($lineItem->order, $lineItem);
    }


    public function getFieldLayout()
    {
        $business = $this->getBusiness();

        return $business ? $business->getVoucherFieldLayout() : null;
    }

    public function getUriFormat()
    {
        $businessSitesSettings = $this->getBusiness()->getSiteSettings();

        if (!isset($businessSitesSettings[$this->siteId])) {
            throw new InvalidConfigException('Voucher’s business (' . $this->getBusiness()->id . ') is not enabled for site ' . $this->siteId);
        }

        return $businessSitesSettings[$this->siteId]->uriFormat;
    }

    public function getBusiness()
    {
        if ($this->_business) {
            return $this->_business;
        }

        return $this->businessId ? $this->_business = BusinessToBusiness::$plugin->business->getBusinessById($this->businessId) : null;
    }



    public function beforeSave(bool $isNew): bool
    {
        if ($this->enabled && !$this->postDate) {
            // Default the post date to the current date/time
            $this->postDate = DateTimeHelper::currentUTCDateTime();
        }

        return parent::beforeSave($isNew);
    }

    public function afterSave(bool $isNew)
    {
        if (!$isNew) {
            $voucherRecord = VoucherRecord::findOne($this->id);

            if (!$voucherRecord) {
                throw new Exception('Invalid voucher id: '.$this->id);
            }
        } else {
            $voucherRecord = new VoucherRecord();
            $voucherRecord->id = $this->id;
        }
        
        $voucherRecord->postDate = $this->postDate;
        $voucherRecord->expiryDate = $this->expiryDate;
        $voucherRecord->businessId = $this->businessId;
        $voucherRecord->amount = $this->amount;
        $voucherRecord->productLimit = $this->productLimit;
        $voucherRecord->products = $this->products;
        $voucherRecord->payrollDeduction = $this->payrollDeduction;
        $voucherRecord->code = $this->code;
        // // Generate SKU if empty
        // if (empty($this->sku)) {
        //     try {
        //         $business = BusinessToBusiness::$plugin->business->getBusinessById($this->businessId);
        //         $this->sku = Craft::$app->getView()->renderObjectTemplate($business->skuFormat, $this);
        //     } catch (\Exception $e) {
        //         $this->sku = '';
        //     }
        // }

        // $voucherRecord->sku = $this->sku;
        
        // Update employees voucher expiration status
        if(empty($voucherRecord->expiryDate) || $voucherRecord->expiryDate > DateTimeHelper::currentUTCDateTime())
        {

                $employees = BusinessToBusiness::$plugin->employee->getEmployeesByVoucherId($this->id);
                foreach($employees as $employee)
                {
                    if($employee->voucherExpired == 1)
                    {
                        $employee->voucherExpired = NULL;
                        Craft::$app->getElements()->saveElement($employee);
                    }
                    
                }
            
        }

        $voucherRecord->save(false);

        return parent::afterSave($isNew);
    }


    // Implement Purchasable
    // =========================================================================

    // public function getPurchasableId(): int
    // {
    //     return $this->id;
    // }
    
    public function getSnapshot(): array
    {
        $data = [];

        $data['type'] = self::class;

        // Default Voucher custom field handles
        $voucherFields = [];
        $voucherFieldsEvent = new CustomizeVoucherSnapshotFieldsEvent([
            'voucher' => $this,
            'fields' => $voucherFields,
        ]);

        // Allow plugins to modify fields to be fetched
        if ($this->hasEventHandlers(self::EVENT_BEFORE_CAPTURE_VOUCHER_SNAPSHOT)) {
            $this->trigger(self::EVENT_BEFORE_CAPTURE_VOUCHER_SNAPSHOT, $voucherFieldsEvent);
        }

        // Capture specified Voucher field data
        $voucherFieldData = $this->getSerializedFieldValues($voucherFieldsEvent->fields);
        $voucherDataEvent = new CustomizeVoucherSnapshotDataEvent([
            'voucher' => $this,
            'fieldData' => $voucherFieldData,
        ]);

        // Allow plugins to modify captured Voucher data
        if ($this->hasEventHandlers(self::EVENT_AFTER_CAPTURE_VOUCHER_SNAPSHOT)) {
            $this->trigger(self::EVENT_AFTER_CAPTURE_VOUCHER_SNAPSHOT, $voucherDataEvent);
        }

        $data['fields'] = $voucherDataEvent->fieldData;

        return array_merge($this->getAttributes(), $data);
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getProductLimit(): float
    {
        return $this->productLimit;
    }

    
    public function getPayrollDeduction(): float
    {
        return $this->payrollDeduction;
    }

    // public function getSku(): string
    // {
    //     return $this->sku;
    // }
    public function getProducts(int $siteId): array
    {
        if ($this->_products !== null) {
            return $this->_products;
        }

        if ($this->products === null) {
            return null;
        }

        $this->products = Json::decode($this->products);
        $this->_products = [];
        if($this->products)
        {
            foreach($this->products as $productId)
            {
                ArrayHelper::append($this->_products, Commerce::getInstance()->getProducts()->getProductById($productId, $siteId));
            }
            
            
        }
        return $this->_products;
    }

    public function allowedProducts(): array
    {
        if ($this->_products !== null) {
            return $this->_products;
        }

        if ($this->products === null) {
            return null;
        }

        $this->products = Json::decode($this->products);
        $this->_products = [];
        if($this->products)
        {
            foreach($this->products as $productId)
            {
                ArrayHelper::append($this->_products, Commerce::getInstance()->getProducts()->getProductById($productId));
            }
            
            
        }
        return $this->_products;
    }


    public function getCode(): string
    {
        return $this->code;
    }

    public function getDescription(): string
    {
        return $this->title;
    }


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

    protected function route()
    {
        // Make sure the voucher type is set to have URLs for this site
        $siteId = Craft::$app->getSites()->currentSite->id;
        $businessSitesSettings = $this->getBusiness()->getSiteSettings();

        if (!isset($businessSitesSettings[$siteId]) || !$businessSitesSettings[$siteId]->hasUrls) {
            return null;
        }

        return [
            'templates/render', [
                'template' => $businessSitesSettings[$siteId]->template,
                'variables' => [
                    'voucher' => $this,
                    // 'product' => $this,
                ]
            ]
        ];
    }

    protected static function defineTableAttributes(): array
    {
        return [
            'title' => ['label' => Craft::t('business-to-business', 'Title')],
            'type' => ['label' => Craft::t('business-to-business', 'Business')],
            // 'sku' => ['label' => Craft::t('business-to-business', 'SKU')],
            'amount' => ['label' => Craft::t('business-to-business', 'Amount')],
            'postDate' => ['label' => Craft::t('business-to-business', 'Post Date')],
            'expiryDate' => ['label' => Craft::t('business-to-business', 'Expiry Date')],
        ];
    }

    protected static function defineDefaultTableAttributes(string $source): array
    {
        $attributes = [];

        $attributes[] = 'type';
        $attributes[] = 'postDate';
        $attributes[] = 'expiryDate';
        $attributes[] = 'amount';
        
        return $attributes;
    }

    protected static function defineSearchableAttributes(): array
    {
        return ['title'];
    }

    protected function tableAttributeHtml(string $attribute): string
    {
        /* @var $business Business */
        // $business = $this->getBusiness();
        /* @var $business Business */
        $business = $this->getBusiness();
        switch ($attribute) {
            case 'type':
                return ($business ? Craft::t('site', $business->name) : '');
            
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
            'title' => Craft::t('business-to-business', 'Title'),
            'postDate' => Craft::t('business-to-business', 'Post Date'),
            'expiryDate' => Craft::t('business-to-business', 'Expiry Date'),
            'amount'  => Craft::t('business-to-business', 'Amount'),
        ];
    }
}
