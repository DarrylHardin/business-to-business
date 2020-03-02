<?php
namespace importantcoding\businesstobusiness\adjusters;


use craft\base\Component;
use craft\commerce\base\AdjusterInterface;
use craft\commerce\elements\Order;
use craft\commerce\models\OrderAdjustment;
use craft\commerce\helpers\Currency;
use craft\commerce\records\Discount as DiscountRecord;

use DateTime;

class OutstandingAdjuster extends Component implements AdjusterInterface
{
    const TYPE = 'outstanding';

    public function adjust(Order $order): array
    {

        $paidStatus = $order->getPaidStatus();
        $adjustmentAmount = 0;
        $depositAdjusterExists = false;

        foreach ($order->getAdjustments() as $adjustment) {
            if ($adjustment->type === VoucherAdjuster::ADJUSTMENT_TYPE) {
                $adjustmentAmount = -1 * $adjustment->amount;
                $depositAdjusterExists = true;
            }
        }

        if (
            $depositAdjusterExists &&
            $paidStatus !== Order::PAID_STATUS_PAID
        ) {
            $adjustment = new OrderAdjustment();
            $adjustment->type = self::TYPE;
            $adjustment->name = 'Outstanding balance';
            $adjustment->sourceSnapshot = [];
            $adjustment->amount = $adjustmentAmount;
            $adjustment->setOrder($order);

            return [$adjustment];
        }

        return [];
    }
}
// public function adjust(Order $order): array
//     {
//         $adjustmentAmount = 0;

//         foreach ($order->getAdjustments() as $adjustment) {
//             if ($adjustment->type === VoucherAdjuster::ADJUSTMENT_TYPE) {
//                 $adjustmentAmount = -1 * $adjustment->amount;
//             }
//         }
//         if($adjustmentAmount != 0)
//         {
//             $adjustment = new OrderAdjustment();
//             $adjustment->type = self::TYPE;
//             $adjustment->name = 'Outstanding balance';
//             $adjustment->sourceSnapshot = [];
//             $adjustment->amount = $adjustmentAmount;
//             $adjustment->setOrder($order);

//             return [$adjustment];
//         }
//         return [];
//     }