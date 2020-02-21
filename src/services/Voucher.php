<?php
/**
 * Business To Business plugin for Craft CMS 3.x
 *
 * Allow businesses to create vouchers for employees that allows the purchasing of products to be charged to the company at a later date.
 *
 * @link      http://importantcoding.com
 * @copyright Copyright (c) 2019 Darryl Hardin
 */

namespace importantcoding\businesstobusiness\services;
use importantcoding\businesstobusiness\BusinessToBusiness;
use importantcoding\businesstobusiness\elements\Voucher as VoucherElement;
// use importantcoding\businesstobusiness\services\Employee;
use Craft;
use craft\events\SiteEvent;
use craft\queue\jobs\ResaveElements;
use craft\db\Query;
use yii\base\Component;

/**
 * Voucher Service
 *
 * All of your plugin’s business logic should go in services, including saving data,
 * retrieving data, etc. They provide APIs that your controllers, template variables,
 * and other plugins can interact with.
 *
 * https://craftcms.com/docs/plugins/services
 *
 * @author    Darryl Hardin
 * @package   BusinessToBusiness
 * @since     1.0.0
 */
class Voucher extends Component
{

    // Properties
    // =========================================================================

    private $_fetchedAllVoucher = false;
    private $_voucherById;
    private $_voucherBybusinessId;
    private $_allVoucherIds;
    private $_editableVoucherIds;
    private $_siteSettingsByVoucherId = [];
    // Public Methods
    // =========================================================================

    /**
     * This function can literally be anything you want, and you can have as many service
     * functions as you want
     *
     * From any other plugin file, call it like this:
     *
     *     BusinessToBusiness::$plugin->voucher->exampleService()
     *
     * @return mixed
     */
     // Public Methods
    // =========================================================================

    public function getEditableVouchers(): array
    {
        $editableVoucherIds = $this->getEditableVoucherIds();
        $editableVoucher = [];

        foreach ($this->getAllVouchers() as $voucher) {
            if (in_array($voucher->id, $editableVoucherIds, false)) {
                $editableVoucher[] = $voucher;
            }
        }

        return $editableVoucher;
    }

    public function getEditableVoucherIds(): array
    {
        if (null === $this->_editableVoucherIds) {
            $this->_editableVoucherIds = [];
            $allVoucherIds = $this->getAllVoucherIds();

            foreach ($allVoucherIds as $businessId) {
                if (Craft::$app->getUser()->checkPermission('businessToVoucher-manageVoucher:' . $businessId)) {
                    $this->_editableVoucherIds[] = $businessId;
                }
            }
        }

        return $this->_editableVoucherIds;
    }

    public function getAllVoucherIds(): array
    {
        if (null === $this->_allVoucherIds) {
            $this->_allVoucherIds = [];
            $voucher = $this->getAllVouchers();

            foreach ($voucher as $voucher) {
                $this->_allVoucherIds[] = $voucher->id;
            }
        }

        return $this->_allVoucherIds;
    }

    public function getAllVouchers()
    {
        if (!$this->_fetchedAllVoucher) {
            $results = $this->_createVoucherQuery()->all();

            foreach ($results as $result) {
                $this->_memoizeVoucher(new VoucherElement($result));
            }

            $this->_fetchedAllVoucher = true;
        }

        return $this->_voucherById ?: [];
    }

    public function getVoucherById(int $id, $siteId = null)
    {
        return Craft::$app->getElements()->getElementById($id, VoucherElement::class, $siteId);
    }

    public function getSortVoucherById(int $id)
    {
        if (isset($this->_voucherById[$id])) {
            return $this->_voucherById[$id];
        }

        if ($this->_fetchedAllVoucher) {
            return null;
        }

        $result = $this->_createVoucherQuery()
            ->where(['id' => $id])
            ->one();

        if (!$result) {
            return null;
        }

        $this->_memoizeVoucher(new VoucherElement($result));

        return $this->_voucherById[$id];
    }

    public function getVoucher()
    {
        $user = Craft::$app->getUser()->getIdentity();
        $employee = BusinessToBusiness::$plugin->employee->getEmployeeByUserId($user->id);
        
        if($employee->voucherId)
        {
            $employeeVoucher = VoucherElement::find()
            ->id($employee->voucherId)
            ->one();
            return $employeeVoucher;
        }
        return false;
    }

    

    // public function getEditableVouchers(): array
    // {
    //     $editableVoucherIds = $this->getEditableVoucherIds();
    //     $editableVoucher = [];

    //     foreach ($this->getAllVouchers() as $voucher) {
    //         if (in_array($voucher->id, $editableVoucherIds, false)) {
    //             $editableVoucher[] = $voucher;
    //         }
    //     }

    //     return $editableVoucher;
    // }

    
    

    public function getVouchersByBusiness()
    {
        $user = Craft::$app->getUser()->getIdentity();
        $employee = BusinessToBusiness::$plugin->employee->getEmployeeByUserId($user->id);
        
        if($employee->businessId)
        {
            $vouchers = VoucherElement::find()
            ->businessId($employee->businessId)
            ->all();
            return $vouchers;
        }
        return false;
    }

    public function getVouchersByBusinessName($businessName)
    {
        $businesses = BusinessToBusiness::$plugin->business->getAllBusinesses();

        foreach ($businesses as $business)
        {
            if($businessName == $business->name)
            {
                return $business;
            }
        }
        
        return null;
    }

    public function getVouchersByBusinessId($businessId)
    {
        $query = VoucherElement::find()
            ->addSelect(['businesstobusiness_voucher.businessId'])
            ->anyStatus();

        if (Craft::$app->getDb()->getIsMysql()) {
            $query
                ->where([
                    'businessId' => $businessId,
                ]);
        } 
        // else {
        //     // Postgres is case-sensitive
        //     $query
        //         ->where([
        //             'lower([[username]])' => mb_strtolower($usernameOrEmail),
        //         ])
        //         ->orWhere([
        //             'lower([[email]])' => mb_strtolower($usernameOrEmail),
        //         ]);
        // }
        return $query->all();  
    }

    public function getVouchersByBusinessHandle($businessHandle)
    {
        // $businesses = BusinessToBusiness::$plugin->business->getAllBusinesses();
        $business = (new \yii\db\Query())
        ->select(['id', 'handle'])
        ->from('{{%businesstobusiness_business}}')
        ->where(['handle' => $businessHandle])
        ->one();
        $vouchers = $this->getVouchersByBusinessId($business['id']);

        // foreach ($businesses as $business)
        // {
        //     if($businessHandle == $business->handle)
        //     {
        //         return $business;
        //     }
        // }
        
        return $vouchers;
    }

    public function afterSaveSiteHandler(SiteEvent $event)
    {
        $queue = Craft::$app->getQueue();
        $siteId = $event->oldPrimarySiteId;
        $elementTypes = [
            BusinessToBusiness::class,
        ];

        foreach ($elementTypes as $elementType) {
            $queue->push(new ResaveElements([
                'description' => Craft::t('app', 'Resaving {type} vouchers ({site})', [
                    'type' => $this->name,
                ]),
                'elementType' => $elementType,
                'criteria' => [
                    'siteId' => $siteId,
                    'status' => null,
                    'enabledForSite' => false
                ]
            ]));
        }
        
    }

    // Private methods
    // =========================================================================

    private function _memoizeVoucher(VoucherElement $voucher)
    {
        $this->_voucherById[$voucher->id] = $voucher;
        $this->_voucherBybusinessId[$voucher->businessId] = $voucher;
    }

    private function _createVoucherQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
                // 'name',
                // 'handle',
                'businessId',
                'expiryDate',
                'postDate',
                'amount',
                'payrollDeduction',
                'code',
                'productLimit',
                'products'
                // 'fieldLayoutId',
            ])
            ->from(['{{%businesstobusiness_voucher}}']);
    }
}
