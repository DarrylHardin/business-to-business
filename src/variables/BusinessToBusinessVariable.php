<?php
/**
 * Business To Business plugin for Craft CMS 3.x
 *
 * Allow businesses to create vouchers for employees that allows the purchasing of products to be charged to the company at a later date.
 *
 * @link      http://importantcoding.com
 * @copyright Copyright (c) 2019 Darryl Hardin
 */

namespace importantcoding\businesstobusiness\variables;

use importantcoding\businesstobusiness\BusinessToBusiness;
use importantcoding\businesstobusiness\elements\db\VoucherQuery;
use importantcoding\businesstobusiness\elements\Voucher;
use importantcoding\businesstobusiness\elements\db\EmployeeQuery;
use importantcoding\businesstobusiness\elements\Employee;

use Craft;

use craft\commerce\Plugin as Commerce;
use craft\commerce\elements\Order;
use craft\commerce\models\LineItem;

/**
 * Business To Business Variable
 *
 * Craft allows plugins to provide their own template variables, accessible from
 * the {{ craft }} global variable (e.g. {{ craft.businessToBusiness }}).
 *
 * https://craftcms.com/docs/plugins/variables
 *
 * @author    Darryl Hardin
 * @package   BusinessToBusiness
 * @since     1.0.0
 */
class BusinessToBusinessVariable
{
    // Public Methods
    // =========================================================================

    public function getPlugin(): BusinessToBusiness
    {
        return BusinessToBusiness::$plugin;
    }

    public function getBusinesses(): array
    {
        return BusinessToBusiness::$plugin->business->getAllBusinesses();
    }

    public function getEditableBusinesses(): array
    {
        return BusinessToBusiness::$plugin->business->getEditableBusiness();
    }

    public function getBusinessById($id)
    {
        return BusinessToBusiness::$plugin->business->getBusinessById($id);

    }

    public function getOrders(int $gatewayId): array
    {
        return BusinessToBusiness::$plugin->getOrders()->getBusinessOrders($gatewayId);
    }

    public function getAllVouchers(): array
    {
        return Voucher::find()->anyStatus()->all();
    }

    public function getVoucherById(int $voucherId = 0)
    {
        if($voucherId == 0)
        {
            return null;
        }
        return Voucher::find()->id($voucherId)->anyStatus()->one();
    }

    public function getVouchersById(): array
    {
        return Voucher::find()->all();
    }

    public function vouchers(): VoucherQuery
    {
        return Voucher::find();
    }

    public function getEnabledGatewayRulesByBusinessId(int $businessId)
    {
        return BusinessToBusiness::$plugin->gatewayRulesBusinesses->getEnabledGatewayRulesByBusinessId($businessId);
    }

    public function getEnabledShippingRulesByBusinessId(int $businessId)
    {
        return BusinessToBusiness::$plugin->shippingRulesBusinesses->getEnabledShippingRulesByBusinessId($businessId);
    }

    public function getVouchersByBusiness(): array
    {   
        $user = Craft::$app->getUser()->getIdentity();
        $employee = BusinessToBusiness::$plugin->employee->getCurrentEmployee($user->id);
        // $business = BusinessToBusiness::$plugin->business->getBusinessById();
        $query = Voucher::find()
            ->addSelect(['businesstobusiness_voucher.businessId'])
            ->anyStatus();

        if (Craft::$app->getDb()->getIsMysql()) {
            $query
                ->where([
                    'businessId' => $employee->businessId,
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

    public function getEditableVouchersByBusiness(): array
    {   
        $user = Craft::$app->getUser()->getIdentity();
        $employee = BusinessToBusiness::$plugin->employee->getCurrentEmployee($user->id);
        // $business = BusinessToBusiness::$plugin->business->getBusinessById();
        $query = Voucher::find()
            ->addSelect(['businesstobusiness_voucher.businessId']);

        if (Craft::$app->getDb()->getIsMysql()) {
            $query
                ->where([
                    'businessId' => $employee->businessId,
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

    public function getEditableVouchers(): array
    {
        return BusinessToBusiness::$plugin->voucher->getEditableVouchers();
    }

    public function getVoucher()
    {
        return BusinessToBusiness::$plugin->voucher->getAllVouchers();
    }

    public function isEmployee()
    {
        $user = Craft::$app->getUser()->getIdentity();
        return BusinessToBusiness::$plugin->employee->isEmployee($user->id);
    }
    
    public function getCurrentEmployee(int $userId)
    {
        // $user = Craft::$app->getUser()->getIdentity();
        return BusinessToBusiness::$plugin->employee->getCurrentEmployee($userId);
    }

    public function employees(): EmployeeQuery
    {
        return Employee::find();
    }

    public function getPdfUrl(LineItem $lineItem)
    {
        if ($this->isVoucher($lineItem)) {
            $order = $lineItem->order;

            return BusinessToBusiness::$plugin->getPdf()->getPdfUrl($order, $lineItem);
        }

        return null;
    }

    public function getOrderPdfUrl(Order $order)
    {
        return BusinessToBusiness::$plugin->getPdf()->getPdfUrl($order);
    }
}
