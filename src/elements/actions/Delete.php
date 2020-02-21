<?php
/**
 * Business To Business plugin for Craft CMS 3.x
 *
 * Allow businesses to create vouchers for employees that allows the purchasing of products to be charged to the company at a later date.
 *
 * @link      http://importantcoding.com
 * @copyright Copyright (c) 2019 Darryl Hardin
 */

namespace importantcoding\businesstobusiness\elements\actions;
use importantcoding\businesstobusiness\BusinessToBusiness;
use importantcoding\businesstobusiness\controllers\EmployeesController;
use Craft;
use craft\base\ElementAction;
use craft\elements\db\ElementQueryInterface;
use yii\base\Exception;
/**
 * Delete represents a Delete element action.
 *
 * @author    Darryl Hardin
 * @package   BusinessToBusiness
 * @since     1.0.0
 */
class Delete extends ElementAction
{
    // Properties
    // =========================================================================

    /**
     * @var string|null The confirmation message that should be shown before the elements get deleted
     */
    public $confirmationMessage;

    /**
     * @var string|null The message that should be shown after the elements get deleted
     */
    public $successMessage;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function getTriggerLabel(): string
    {
        return Craft::t('app', 'Delete Employee');
    }

    /**
     * @inheritdoc
     */
    public static function isDestructive(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function getConfirmationMessage()
    {
        return $this->confirmationMessage;
    }

    /**
     * @inheritdoc
     */
    public function performAction(ElementQueryInterface $query): bool
    {
        $employees = $query->all();
        $elementsService = Craft::$app->getElements();
        foreach($employees as $employee)
        {
            $orders = \craft\commerce\elements\Order::find()
            ->user($employee->userId)
            ->orderStatusId([9, 10])
            ->all();
            
            if($orders)
            {
                foreach($orders as $order)
                {
                    
                    // $order->setFieldValue('message', "Moved to rejected orders by deletion of employee");
                    // $order->setFieldValue('orderStatusId', 11);
                    $order->message = "Moved to rejected orders by deletion of employee";
                    $order->orderStatusId = 11;
                    if(!Craft::$app->getElements()->saveElement($order))
                    {
                        return false;
                    }
                }
            }

            // $this->enforceEmployeePermissions($employee);
            
            $elementsService->deleteElement($employee);
        }

        $this->setMessage($this->successMessage);

        return true;
    }
}
