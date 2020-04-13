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


class BusinessAdjuster extends Component implements AdjusterInterface
{
    // Constants
    // =========================================================================

    // const EVENT_AFTER_VOUCHER_ADJUSTMENTS_CREATED = 'afterVoucherAdjustmentsCreated';

    const ADJUSTMENT_TYPE = 'discount';    

    // Public Methods
    // =========================================================================

    public function adjust(Order $order): array
    {
        $site = Craft::$app->getSites()->currentSite;
        // die($site->id);
        // $user = Craft::$app->getUser()->getIdentity();
        // $employee = null;
        // if($user)
        // {
        //     $employee = BusinessToBusiness::$plugin->employee->getEmployeeByUserId($user->id);
        // }
        if($order->businessId or $order->businessInvoice)
        {
            $adjustments = [];
            foreach($order->getLineItems() as $lineItem)
            {
                
                $business = BusinessToBusiness::$plugin->business->getBusinessById($order->businessId);
                $lineItemTotal = $lineItem->price;
                $discount = $business->discount/100;
                $discountPercent = $lineItemTotal * $discount;
                
                // die($discountPercent);
                //preparing model
                $adjustment = new OrderAdjustment();
                $adjustment->type = self::ADJUSTMENT_TYPE;
                $adjustment->name = $business->name ." employee discount for " . $lineItem->description;
                $adjustment->orderId = $order->id;
                $adjustment->description = $business->name . ' discount for ' . $lineItem->description;
                $adjustment->sourceSnapshot = [];
                $adjustment->amount = -1 * $discountPercent;
                $adjustment->setOrder($order);
                $adjustment->setLineItem($lineItem);
                $adjustments[] = $adjustment;
                    
            }
            return $adjustments;
        }
        
        return [];
            // checks if user is in employee group and if user has a voucher available
            // if($currentEmployee)
            // {
            //     $voucher = BusinessToBusiness::$plugin->voucher->getVoucherById($currentEmployee->voucherId);


            // //SETTING UP FOR BUSINESS DISCOUNTS  
            //     // $business = BusinessToBusiness::$plugin->business->getBusinessById($voucher->businessId);
            //     // $discount = $business->discount;
                
            //     // if($currentEmployee->dateVoucherUsed == NULL || $currentEmployee->dateVoucherUsed < $voucher->postDate
            //     if($currentEmployee->voucherAvailable)
            //     {
            //         $adjustments = [];
            //         $tax = $order->getTotalTax();
            //         $this->_orderTotal = $order->getTotalPrice() + $tax;
            //         // $voucherTotal = $order->getAdjustmentsTotalByType('voucher');
            //         $voucherTotal = $order->getTotalDiscount();
            //         if(!$voucherTotal)
            //             {
            //             $voucherValue = $voucher->amount;
            //             $maxQty = $voucher->productLimit;
            //             $maxQtyCount = 0;
            
            
            //             foreach ($order->getLineItems() as $item) {
            //                 // can rewrite to check if being used with voucher
            //                 $isValidItem = 0;
            //                 if($item->options['purchasedWithVoucher'] == 'yes' && $maxQtyCount <= $maxQty)
            //                 {
            //                     for ($i=0; $i < $item->qty; $i++) {
                                    
            //                         if($maxQtyCount == $maxQty)
            //                         {
            //                             break;
            //                         }
            //                         $maxQtyCount++;
            //                         $isValidItem++;
            //                     }
            
                                
                                
            //                     $existingLineItemPrice = ($item->price + $order->getTotalTax()) * $isValidItem;
            //                     // $existingLineItemPrice = $item->price;
            //                     $adjustment = $this->_createOrderAdjustment($voucher, $order);
            //                     $adjustment->setLineItem($item);
                                

            //                     $existingLineItemPrice  = $existingLineItemPrice * 1;
            //                     //if the item is less than the vouchers allowance then the adjustment shouldn't go under 0
            //                     if ($existingLineItemPrice < $voucherValue) 
            //                     {
            //                         $adjustment->amount = $existingLineItemPrice * -1;
            //                     } 
            //                     else {
            //                         $adjustment->amount = $voucherValue * -1;
            //                     }
                                

            //                     if ($adjustment->amount != 0) {
            //                         $adjustments[] = $adjustment;
            //                     }

            //                 }
            //             }
            //         }
            //     }
           
                
                // $this->trigger(self::EVENT_AFTER_VOUCHER_ADJUSTMENTS_CREATED, $event);
                // if (!$event->isValid) {
                //     return false;
                // }
                // return false;
                // }
        //         $event = new VoucherAdjustmentsEvent([
        //             'order' => $order,
        //             'adjustments' => $adjustments,
        //         ]);

        //         if (!$event->isValid) {
        //             return false;
        //         }
                
        //         return $event->adjustments;
        // }

        
        // return $adjustments = [];
    }



    // // Private Methods
    // // =========================================================================

    // private function _createOrderAdjustment(Voucher $voucher, Order $order)
    // {
        
    // }

}
