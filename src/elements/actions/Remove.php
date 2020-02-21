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

use Craft;
use craft\base\ElementAction;
use craft\elements\db\ElementQueryInterface;

/**
 * @author    Darryl Hardin
 * @package   BusinessToBusiness
 * @since     1.0.0
 */
class Remove extends ElementAction
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
        return Craft::t('app', 'Remove Employee');
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
        foreach ($query->all() as $element) {
            $element->enabled = false;
            $element->voucherAvailable = NULL;
            $element->authorized = NULL;
            Craft::$app->getElements()->saveElement($element);
        }

        $this->setMessage($this->successMessage);

        return true;
    }
}