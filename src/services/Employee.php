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
use importantcoding\businesstobusiness\elements\Employee as EmployeeElement;

use Craft;
use craft\base\Component;
use craft\db\Query;
use yii\web\HttpException;
use yii\base\Exception;
/**
 * Employee Service
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
class Employee extends Component
{
    // const CONFIG_USERLAYOUT_KEY = 'users.fieldLayouts';
    const CONFIG_FIELDLAYOUT_KEY = 'employees.fieldLayouts';
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

    public function getEmployeeById(int $id, $siteId = null)
    {
        return Craft::$app->getElements()->getElementById($id, EmployeeElement::class, $siteId);
    }

    public function getEmployeeByUserId(int $userId)
    {
        $query = EmployeeElement::find()
            ->addSelect(['businesstobusiness_employee.userId'])
            ->anyStatus();

        if (Craft::$app->getDb()->getIsMysql()) {
            $query
                ->where([
                    'userId' => $userId,
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
        return $query->one();
    }

    public function getCurrentEmployee(int $userId)
    {
        $employees = EmployeeElement::find()->all();
        foreach ($employees as $employee) {
            if($userId == $employee->userId)
            {
                return $employee;
            }
        }
        return [];        
    }

    public function getEmployeesByVoucherId(int $voucherId)
    {
        $query = EmployeeElement::find()
            ->addSelect(['businesstobusiness_employee.voucherId'])
            ->anyStatus();

        if (Craft::$app->getDb()->getIsMysql()) {
            $query
                ->where([
                    'voucherId' => $voucherId,
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

    public function isEmployee($userId)
    {

        $employeeExists = EmployeeElement::find()
        ->userId($userId)
        ->exists();
        if($employeeExists){
            return true;
        }
        return false;
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
                'elementType' => $elementType,
                'criteria' => [
                    'siteId' => $siteId,
                    'status' => null,
                    'enabledForSite' => false
                ]
            ]));
        }
        
    }

    public function delete(EmployeeElement $employee) : bool
    {
        $orders = \craft\commerce\elements\Order::find()
            ->user($employee->userId)
            ->orderStatus([9, 10])
            ->all();
        if($orders)
        {
            foreach($orders as $order)
            {
                // $order->setFieldValue('orderStatusId', 11);
                $order->orderStatusId = 11;
                Craft::$app->getElements()->saveElement($order);
            }
        }
        
        if (!$employee) {
            throw new Exception(Craft::t('business-to-business', 'No employee exists with the ID “{id}”.',['id' => $employee->id]));
        }

        // $this->enforceEmployeePermissions($employee);

        if (!Craft::$app->getElements()->deleteElement($employee)) {
            if (Craft::$app->getRequest()->getAcceptsJson()) {
                $this->asJson(['success' => false]);
            }

            Craft::$app->getSession()->setError(Craft::t('business-to-business', 'Couldn’t delete employee.'));
            Craft::$app->getUrlManager()->setRouteParams([
                'employee' => $employee
            ]);

            return false;
        }

        

        if (Craft::$app->getRequest()->getAcceptsJson()) {
            $this->asJson(['success' => true]);
        }

        Craft::$app->getSession()->setNotice(Craft::t('business-to-business', 'Employee deleted.'));
        return true;
    }

    // public function massDelete(int $employeeId) : bool
    // {  
    //     $employee = $this->getEmployeeById($employeeId);
    //     $orders = \craft\commerce\elements\Order::find()
    //         ->user($employee->userId)
    //         ->orderStatus([9, 10])
    //         ->all();

    //     foreach($orders as $order)
    //     {
    //         $order->orderStatusId = 11;
    //         Craft::$app->getElements()->saveElement($order);
    //     }
    //     if (!$employee) {
    //         throw new Exception(Craft::t('business-to-business', 'No employee exists with the ID “{id}”.',['id' => $employee->id]));
    //     }

    //     $this->enforceEmployeePermissions($employee);

    //     if (!Craft::$app->getElements()->deleteElement($employee)) {
    //         if (Craft::$app->getRequest()->getAcceptsJson()) {
    //             $this->asJson(['success' => false]);
    //         }

    //         Craft::$app->getSession()->setError(Craft::t('business-to-business', 'Couldn’t delete employee.'));
    //         Craft::$app->getUrlManager()->setRouteParams([
    //             'employee' => $employee
    //         ]);

    //         return false;
    //     }

        

    //     if (Craft::$app->getRequest()->getAcceptsJson()) {
    //         $this->asJson(['success' => true]);
    //     }

    //     Craft::$app->getSession()->setNotice(Craft::t('business-to-business', 'Employee deleted.'));
    //     return true;
    // }
}

