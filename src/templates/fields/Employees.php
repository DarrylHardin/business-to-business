<?php
namespace importantcoding\businesstobusiness\fields;

use importantcoding\businesstobusiness\elements\Employee;

use Craft;
use craft\fields\BaseRelationField;

class Employees extends BaseRelationField
{
    // Public Methods
    // =========================================================================

    public static function displayName(): string
    {
        return Craft::t('business-to-business', 'Business Employees');
    }

    protected static function elementType(): string
    {
        return Employee::class;
    }

    public static function defaultSelectionLabel(): string
    {
        return Craft::t('business-to-business', 'Add a business employee');
    }
}
