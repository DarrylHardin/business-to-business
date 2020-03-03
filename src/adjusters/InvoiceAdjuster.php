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
use craft\helpers\ArrayHelper;
use craft\commerce\adjusters\Tax;
use DateTime;


class InvoiceAdjuster extends Component implements AdjusterInterface
{
    const ADJUSTMENT_TYPE = 'employeePaid';

    public function adjust(Order $order): array
    {
        $adjustments = [];
        if($order->getFieldValue('businessInvoice'))
        {

        
            // $paidStatus = $order->getPaidStatus();
            
            $voucherExists = false;
            // $originalPrice = 0;
            // $order->getFieldValue('businessId');
            
            // foreach ($order->getAdjustments() as $adjustment) 
            // {
            //     if ($adjustment->type === VoucherAdjuster::ADJUSTMENT_TYPE) {
                if(count($order->getLineItems()))
                foreach ($order->getLineItems() as $lineItem)
                {
                    $voucher = false;
                    $voucherValue = 0;
                    $voucherName = '';
                    $employeeId = null;
                    $tax = 0;
                    foreach($lineItem->options as $key => $value)
                    {
                        
                        if($key == 'voucherValue')
                        {
                            $voucherValue = $value;
                            $voucher = true;
                            
                            //just to make sure we trigger this only once
                            foreach($lineItem->getAdjustments() as $adjustment)
                            {
                                if($adjustment->type == Tax::ADJUSTMENT_TYPE)
                                {
                                    $tax = $adjustment->amount;
                                }
                            }  

                        }
                        if($key == '$voucherName')
                        {
                            $$voucherName = $value;
                            $voucher = true;
                        }
                        if($key == '$employeeId')
                        {
                            $$employeeId = $value;
                            $voucher = true;
                        }
                                        
                    }
                    if($voucher)
                    {
                        
                        // die($value);
                        $adjustmentAmount = 0; // if voucher covered complete cost
                        // die($lineItem->price + $tax);

                        // $voucherValue = -1 * $voucherValue;
                        // die($voucherValue);
                        // $tax = round($tax, 2);
                        // die($lineItem->price);
                        // die($voucherValue);
                        if($lineItem->price == -1 * $voucherValue) // if the price of the item is more than the the adjustment amount the difference is the employee's
                        {
                            $adjustmentAmount = 0;
                            // $adjustmentAmount = -1 * $adjustmentAmount;
                        } else {
                            // $tax = 0;
                            $adjustmentAmount = $voucherValue + $lineItem->price + $tax;
                        }
                        $adjustment = new OrderAdjustment();
                        $adjustment->type = self::ADJUSTMENT_TYPE;
                        $adjustment->name = $voucherName;
                        $adjustment->description = "Amount the employee paid";
                        $adjustment->sourceSnapshot = ['Employee Price' => -1 * $adjustmentAmount, 'Employee Taxes Paid' => -1 * $tax];
                        $adjustment->amount = -1 * $adjustmentAmount;
                        $adjustment->setOrder($order);
                        $adjustment->setLineItem($lineItem);
                        $adjustments[] = $adjustment;
                        // return [$adjustment];
                        
                        // die(var_dump($adjustments));
                        // ArrayHelper::append($adjustments, $adjustment);
                        // die('made it?');
                        // $voucherExists = true;
                    
                    }
                    
                }
            // die(var_dump($adjustments));
            if(count($adjustments))
            {
                // die('here');
                return $adjustments;
            }
            return [];
        }
            
        return [];
    }
}
