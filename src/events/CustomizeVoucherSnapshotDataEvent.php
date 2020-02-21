<?php
namespace importantcoding\businesstobusiness\events;

use yii\base\Event;

class CustomizeVoucherSnapshotDataEvent extends Event
{
    // Properties
    // =========================================================================

    public $voucher;
    public $fieldData;
}
