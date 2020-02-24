<?php
/**
 * @link      http://importantcoding.com
 * @copyright Copyright (c) 2019 Darryl Hardin
 */

namespace importantcoding\businesstobusiness\elements\actions;

use Craft;
use craft\base\ElementAction;
use craft\elements\db\ElementQueryInterface;

/**
 * Verifies an employee
 *
 * @author    Darryl Hardin
 * @package   BusinessToBusiness
 * @since     1.0.0
 */
class Verify extends ElementAction
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
        return Craft::t('app', 'Verify Employees');
    }

    /**
     * @inheritdoc
     */
    public static function isDestructive(): bool
    {
        return false;
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
        foreach($employees as $employee)
        {
            $employee->authorized = 1;
            Craft::$app->getElements()->saveElement($employee);

            $orders = \craft\commerce\elements\Order::find()
            ->user($employee->userId)
            ->orderStatusId(10)
            ->all();
            
            if($orders)
            {
                foreach($orders as $order)
                {
                    
                    // $order->setFieldValue('message', "Moved to rejected orders by deletion of employee");
                    // $order->setFieldValue('orderStatusId', 11);
                    $order->message = "Moved to New Orders by VerifyAction";
                    $order->orderStatusId = 9;
                    if(!Craft::$app->getElements()->saveElement($order))
                    {
                        return false;
                    }
                }
            }
        }

        $this->setMessage($this->successMessage);

        return true;
    }
}