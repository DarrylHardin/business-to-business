<?php
namespace importantcoding\businesstobusiness\adjusters;

use importantcoding\businesstobusiness\BusinessToBusiness;
use importantcoding\businesstobusiness\events\VoucherAdjustmentsEvent;
use importantcoding\businesstobusiness\elements\Employee as EmployeeElement;

use Craft;
use craft\services\Sites;
use craft\base\Component;
use craft\commerce\base\AdjusterInterface;
use craft\commerce\elements\Order;
use craft\commerce\models\OrderAdjustment;

use DateTime;


class VoucherAdjuster extends Component implements AdjusterInterface
{
    // Constants
    // =========================================================================

    // const EVENT_AFTER_VOUCHER_ADJUSTMENTS_CREATED = 'afterVoucherAdjustmentsCreated';

    const ADJUSTMENT_TYPE = 'voucher';
    

    // Public Methods
    // =========================================================================

    public function adjust(Order $order): array
    {
        $site = Craft::$app->getSites()->currentSite;
        $user = Craft::$app->getUser()->getIdentity();
        $adjustments = [];
        // die($order->siteId);
        if($user && $order->businessId)
        {
            
            $employee = EmployeeElement::find()->userId($user->id)->one();
            
            $adjustments = [];
            // checks if user is in employee group and if user has a voucher available
            if($employee)
            {
                $voucher = BusinessToBusiness::$plugin->voucher->getVoucherById($employee->voucherId);


            //SETTING UP FOR BUSINESS DISCOUNTS  

                if($employee->voucherAvailable)
                {
                    
                    $business = BusinessToBusiness::$plugin->business->getBusinessById($employee->businessId);
                    
                    $adjustments = [];

                        $voucherValue = $voucher->amount;
                        $maxQty = $voucher->productLimit;
                        $maxQtyCount = 0;
                        $tax = 0;
                        if($business)
                        {

                            
                            foreach ($order->getLineItems() as $lineItem) {
                                $tax = 0;
                                // can rewrite to check if being used with voucher
                                $isValidItem = 0;
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
                                        }
                                    }
                                } else {
                                    $tax = $lineItem->getTax();
                                }
                                

                                if($lineItem->options['purchasedWithVoucher'] == 'yes' && $maxQtyCount <= $maxQty)
                                {
                                    for ($i=0; $i < $lineItem->qty; $i++) {
                                        
                                        if($maxQtyCount == $maxQty)
                                        {
                                            break;
                                        }
                                        $maxQtyCount++;
                                        $isValidItem++;
                                    }
                
            
                                    
                                    $discounts = -1 * $lineItem->getDiscount();
                                    $existingLineItemPrice = round($lineItem->price - $discounts + $tax, 2);
                                    $existingLineItemPrice = round($existingLineItemPrice * $isValidItem, 2);
                                    
                        
                                    
                                    //preparing model
                                    $adjustment = new OrderAdjustment();
                                    $adjustment->type = self::ADJUSTMENT_TYPE;
                                    $adjustment->name = $business->name ." ". $voucher;
                                    $adjustment->orderId = $order->id;
                                    $adjustment->description = 'Voucher for ' . $business->name ." ". $voucher;
                                    $adjustment->sourceSnapshot = ['business' => $business->name, 'businessId' => $business->id, 'voucher' => -1 * $voucherValue];
                                    $adjustment->setOrder($order);
                                    $adjustment->setLineItem($lineItem);
                                    
                                    
                                    $existingLineItemPrice  = $existingLineItemPrice * 1;
                                    //if the item is less than the vouchers allowance then the adjustment shouldn't go under 0
                                    if ($existingLineItemPrice < $voucherValue) 
                                    {
                                        $adjustment->amount = $existingLineItemPrice * -1;
                                    } 
                                    else {
                                        $adjustment->amount = $voucherValue * -1;
                                    }
                                    

                                    if ($adjustment->amount != 0) {
                                        $adjustments[] = $adjustment;
                                    }

                                }
                            }
                        }
                    }
            }
                
                // $this->trigger(self::EVENT_AFTER_VOUCHER_ADJUSTMENTS_CREATED, $event);
                // if (!$event->isValid) {
                //     return false;
                // }
                // return false;
                // }
                $event = new VoucherAdjustmentsEvent([
                    'order' => $order,
                    'adjustments' => $adjustments,
                ]);

                if (!$event->isValid) {
                    return false;
                }
                
                return $event->adjustments;
        }

        
        return $adjustments = [];
    }

}
