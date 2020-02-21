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
 * 
 * @author    Darryl Hardin
 * @package   BusinessToBusiness
 * @since     1.0.0
 */
class Restore extends ElementAction
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
        return Craft::t('app', 'Restore Employee');
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
        foreach ($query->all() as $employee) {
            $employee->enabled = true;
            
            $employee->voucherExpired = NULL;
            $employee->voucherAvailable = 1;
            $employee->authorized = 1;
            Craft::$app->getElements()->saveElement($employee);
        }

        $this->setMessage($this->successMessage);

        return true;
    }
}