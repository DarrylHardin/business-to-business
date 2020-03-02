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


class VoucherAdjuster extends Component implements AdjusterInterface
{
    // Constants
    // =========================================================================

    // const EVENT_AFTER_VOUCHER_ADJUSTMENTS_CREATED = 'afterVoucherAdjustmentsCreated';

    const ADJUSTMENT_TYPE = 'voucher';


    // Properties
    // =========================================================================
    private $_orderTotal;
    private $_shippingTotal;
    // private $_voucherTotal;
    

    // Public Methods
    // =========================================================================

    public function adjust(Order $order): array
    {
        
        $user = Craft::$app->getUser()->getIdentity();
        $adjustments = [];
        if($user)
        {
            $currentEmployee = null;
            $user = Craft::$app->getUser()->getIdentity();
            $employees = EmployeeElement::find()->all();
            foreach ($employees as $employee) {
                if($employee->userId == $user->id)
                {
                    $currentEmployee = $employee;
                }
            }
            $adjustments = [];
            // checks if user is in employee group and if user has a voucher available
            if($currentEmployee)
            {
                $voucher = BusinessToBusiness::$plugin->voucher->getVoucherById($currentEmployee->voucherId);


            //SETTING UP FOR BUSINESS DISCOUNTS  
                // $business = BusinessToBusiness::$plugin->business->getBusinessById($voucher->businessId);
                // $discount = $business->discount;
                
                // if($currentEmployee->dateVoucherUsed == NULL || $currentEmployee->dateVoucherUsed < $voucher->postDate
                if($currentEmployee->voucherAvailable)
                {
                    $adjustments = [];
                    $tax = $order->getTotalTax();
                    $this->_orderTotal = $order->getTotalPrice() + $tax;
                    // $voucherTotal = $order->getAdjustmentsTotalByType('voucher');
                    $voucherTotal = $order->getTotalDiscount();
                    if(!$voucherTotal)
                        {
                        $voucherValue = $voucher->amount;
                        $maxQty = $voucher->productLimit;
                        $maxQtyCount = 0;
            
            
                        foreach ($order->getLineItems() as $item) {
                            // can rewrite to check if being used with voucher
                            $isValidItem = 0;
                            if($item->options['purchasedWithVoucher'] == 'yes' && $maxQtyCount <= $maxQty)
                            {
                                for ($i=0; $i < $item->qty; $i++) {
                                    
                                    if($maxQtyCount == $maxQty)
                                    {
                                        break;
                                    }
                                    $maxQtyCount++;
                                    $isValidItem++;
                                }
            
                                
                                
                                $existingLineItemPrice = ($item->price + $order->getTotalTax()) * $isValidItem;
                                // $existingLineItemPrice = $item->price;
                                $adjustment = $this->_createOrderAdjustment($voucher, $order);
                                $adjustment->setLineItem($item);
                                

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



    // Private Methods
    // =========================================================================

    private function _createOrderAdjustment(Voucher $voucher, Order $order)
    {
        $businessId = $voucher->businessId;
        $business = BusinessToBusiness::$plugin->business->getBusinessById($businessId);
        $voucherValue = $voucher->amount;
        //preparing model
        $adjustment = new OrderAdjustment();
        $adjustment->type = self::ADJUSTMENT_TYPE;
        $adjustment->name = $business->name ." ". $voucher;
        $adjustment->orderId = $order->id;
        $adjustment->description = 'Voucher for ' . $business->name ." ". $voucher;
        $adjustment->sourceSnapshot = ['business' => $business->name, 'businessId' => $businessId, 'voucher' => $voucherValue];
        $adjustment->setOrder($order);
        if ($this->_orderTotal < $voucherValue) 
        {
            $adjustment->amount = $this->_orderTotal * -1;
        } 
        else {
            $adjustment->amount = $voucherValue * -1;
        }

        $this->_orderTotal += $adjustment->amount;

        return $adjustment;
    }

}
