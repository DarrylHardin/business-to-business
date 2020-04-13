<?php
namespace importantcoding\businesstobusiness\adjusters;

// use importantcoding\voucher\elements\Code;
use importantcoding\businesstobusiness\BusinessToBusiness;
use importantcoding\businesstobusiness\events\VoucherAdjustmentsEvent;
use importantcoding\businesstobusiness\elements\Voucher;
use importantcoding\businesstobusiness\elements\Employee as EmployeeElement;
use importantcoding\businesstobusiness\adjusters\BusinessAdjuster;
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

            if(count($order->getLineItems()))
                foreach ($order->getLineItems() as $lineItem)
                {
                    $voucher = false;
                    $voucherValue = 0;
                    $voucherName = '';
                    $employeeId = null;
                    
                    foreach($lineItem->options as $key => $value)
                    {
                        
                        if($key == 'voucherValue')
                        {
                            $voucherValue = $value;
                            $voucher = true;
                            
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
                        $tax = $lineItem->getTax();
                        
                        $business = BusinessToBusiness::$plugin->business->getBusinessById($order->businessId);
                        if($business->taxExempt)
                        {
                            
                            foreach ($lineItem->getAdjustments() as $adjustment)
                            {
                                // die($adjustment->type);
                                if($adjustment->type == 'tax')
                                {
                                    $adjustment->amount = 0;
                                    $adjustment->setLineItem($lineItem);
                                    $adjustment->setOrder($order);
                                    $tax = 0;
                                }
                            }
                        }
                        $discounts = $lineItem->getDiscount();
                        $existingLineItemPrice = round($lineItem->price + $discounts + $tax, 2);
                        
                        $adjustmentAmount = 0; 
                        if($existingLineItemPrice == $voucherValue)
                        {
                            
                            $adjustmentAmount = -1 * $voucherValue;
                            
                        } else if ($existingLineItemPrice > $voucherValue)
                        {
                            $adjustmentAmount = ($existingLineItemPrice + $voucherValue) * -1;
                        }

                        $adjustment = new OrderAdjustment();
                        $adjustment->type = self::ADJUSTMENT_TYPE;
                        $adjustment->name = $voucherName;
                        $adjustment->description = "Amount the employee paid";
                        $adjustment->sourceSnapshot = ['Employee Price' => $adjustmentAmount];
                        $adjustment->amount = $adjustmentAmount;
                        $adjustment->setOrder($order);
                        $adjustment->setLineItem($lineItem);
                        $adjustments[] = $adjustment;
                    }
                    
                }
            
            if(count($adjustments))
            {
                return $adjustments;
            }
            return [];
        }
            
        return [];
    }
}
