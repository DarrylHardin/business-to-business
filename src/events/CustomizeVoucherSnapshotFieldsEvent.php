<?php
namespace importantcoding\businesstobusiness\events;

use yii\base\Event;

class CustomizeVoucherSnapshotFieldsEvent extends Event
{
    // Properties
    // =========================================================================

    public $voucher;
    public $fields;
}
