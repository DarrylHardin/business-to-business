<?php
namespace importantcoding\businesstobusiness\adjusters;

// use importantcoding\voucher\elements\Code;
use importantcoding\businesstobusiness\BusinessToBusiness;
use importantcoding\businesstobusiness\events\VoucherAdjustmentsEvent;
use importantcoding\businesstobusiness\elements\Voucher;
use importantcoding\businesstobusiness\elements\Employee as EmployeeElement;
use Craft;
use craft\base\Component;
use craft\commerce\base\AdjusterInterface;
use craft\commerce\elements\Order;
use craft\commerce\models\OrderAdjustment;
use craft\commerce\helpers\Currency;
use craft\commerce\records\Discount as DiscountRecord;

use DateTime;


class InvoiceAdjuster extends Component implements AdjusterInterface
{
    const ADJUSTMENT_TYPE = 'outstanding';

    public function adjust(Order $order): array
    {

        // $paidStatus = $order->getPaidStatus();
        $adjustmentAmount = 0;
        $voucherExists = false;

        $order->getFieldValue('businessId');

        foreach ($order->getAdjustments() as $adjustment) {
            if ($adjustment->type === VoucherAdjuster::ADJUSTMENT_TYPE) {
                $adjustmentAmount = -1 * $adjustment->amount;
                $voucherExists = true;
            }
        }

        if (
            $order->getFieldValue('businessInvoice') &&
            $voucherExists
        ) {
            $adjustment = new OrderAdjustment();
            $adjustment->type = self::ADJUSTMENT_TYPE;
            $adjustment->name = 'Outstanding balance';
            $adjustment->sourceSnapshot = [];
            $adjustment->amount = $adjustmentAmount;
            $adjustment->setOrder($order);

            return [$adjustment];
        }

        return [];
    }
}
