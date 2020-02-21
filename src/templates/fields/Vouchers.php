<?php
namespace importantcoding\businesstobusiness\fields;

use importantcoding\businesstobusiness\elements\Voucher;

use Craft;
use craft\fields\BaseRelationField;

class Vouchers extends BaseRelationField
{
    // Public Methods
    // =========================================================================

    public static function displayName(): string
    {
        return Craft::t('business-to-business', 'Business Vouchers');
    }

    protected static function elementType(): string
    {
        return Voucher::class;
    }

    public static function defaultSelectionLabel(): string
    {
        return Craft::t('business-to-business', 'Add a business voucher');
    }
}
