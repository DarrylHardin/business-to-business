<?php
namespace importantcoding\businesstobusiness\adjusters;

// use importantcoding\voucher\elements\Code;
use importantcoding\businesstobusiness\BusinessToBusiness;
use importantcoding\businesstobusiness\events\DiscountAdjustmentsEvent;
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


class BusinessDiscountAdjuster extends Component implements AdjusterInterface
{
    // Constants
    // =========================================================================

    // const EVENT_AFTER_VOUCHER_ADJUSTMENTS_CREATED = 'afterVoucherAdjustmentsCreated';

    const ADJUSTMENT_TYPE = 'discount';


    // Properties
    // =========================================================================
    private $_orderTotal;
    private $_shippingTotal;

    // Public Methods
    // =========================================================================

    public function adjust(Order $order): array
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
            // foreach($user['groups'] as $group)
            // {

            //     // if the user is in the employee group make coupon invalid
            //     if($group == 'employee')
            //     {
                    
            //         // if user has a voucher available the value to be set to true
            //         if($user['voucherAvailable'] == 'yes')
            //         {
            //             $isValid = true;
            //         }
            //     }
            // }

            $voucher = BusinessToBusiness::$plugin->voucher->getVoucherById($currentEmployee->voucherId);
            // $voucher = Voucher::find()
            // ->id($currentEmployee->voucherId)
            // ->one();

            $business = BusinessToBusiness::$plugin->business->getBusinessById($voucher->businessId);
            $discount = $business->discount;
            if($currentEmployee->dateVoucherUsed == NULL || $currentEmployee->dateVoucherUsed < $voucher->dateCreated)
            {
                $adjustments = [];
                $this->_orderTotal = $order->getTotalPrice();
                $this->_shippingTotal = $order->getAdjustmentsTotalByType('shipping');
                $voucherValue = $voucher->amount;
                $maxQty = $voucher->productLimit;
                $maxQtyCount = 0;

                // set business and department ids from users profile
                // $businessId = $user['employeeBusiness'];
                // $names['businessId'] = $businessId;
                // $businessId = $user['employeeDepartment'];
                // $departmentId = $user['employeeDepartment'];

                
                // get voucher values
                // if($voucherValue > 0)
                // {
                //     $voucherValue = $departmentVoucher;
                // }
                // else if($businessVoucher > 0)
                // {
                //     $voucherValue = $businessVoucher;
                // }
                // else 
                // {   
                //     return []; //returns false
                // }
    
                // working adjustment for whole order including shipping costs
                // $adjustment = $this->_getAdjustment($names, $order, $voucherValue);
    
                // if ($adjustment) {
                //     $adjustments[] = $adjustment;
                // }
    
    
                foreach ($order->getLineItems() as $item) {
                    // can rewrite to check if being used with voucher
                    // if (in_array($item->id, $matchingLineIds, false)) {
                    $isValidItem = 0;
                    if($item->options['purchasedWithVoucher'] == 'yes' && $maxQtyCount < $maxQty)
                    {
                        // if($maxQty != -1)
                        // {
                        for ($i=0; $i < $item->qty; $i++) {
                            $maxQtyCount++;
                            $isValidItem++;
                            if($maxQtyCount == $maxQty)
                            {
                                break;
                            }
                        }
    
                        // }
                        $existingLineItemPrice = $item->price * $isValidItem;
                        $adjustment = $this->_createOrderAdjustment($voucher, $order);
                        $adjustment->setLineItem($item);
                        $amountPerItem = Currency::round($voucherValue * $item->qty);
                        
                        // Default is percentage off already discounted price
                        $existingLineItemDiscount = $item->getAdjustmentsTotalByType('discount');
                        $existingLineItemPrice = ($item->getSubtotal() + $existingLineItemDiscount);
                        // // need to get discount from business not voucher
                        $amountPercentage = Currency::round($discount * $existingLineItemPrice);
                        if ($amountPercentage == DiscountRecord::TYPE_ORIGINAL_SALEPRICE) {
                            $amountPercentage = Currency::round($discount * $item->getSubtotal());
                        }
                        
                        //unsure what this is?
                        $adjustment->amount = $existingLineItemPrice * $amountPercentage;

                        if ($adjustment->amount != 0) {
                            $adjustments[] = $adjustment;
                        }

                        $existingLineItemPrice  = $existingLineItemPrice * 1.1;
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
    
                        // updates voucher availability
                        // $currentEmployee->dateVoucherUsed = date("l");
                        // Craft::$app->getElements()->saveElement($currentEmployee);
                    // }
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
            $event = new DiscountAdjustmentsEvent([
                'order' => $order,
                'adjustments' => $adjustments,
            ]);
            return $event->adjustments;
    }



    // Private Methods
    // =========================================================================

    // private function _getAdjustment(Array $names, Order $order, Int $voucherValue)
    // {
    //     //preparing model
    //     $adjustment = new OrderAdjustment;
    //     $adjustment->type = self::ADJUSTMENT_TYPE;
    //     $adjustment->name = $names['business'] ." ". $names['department'] . " voucher";
    //     $adjustment->orderId = $order->id;
    //     $adjustment->description = 'Voucher for ' . $names['business'] ." ". $names['department'];
    //     $adjustment->sourceSnapshot = [];
    //     // Check for expiry date
    //     // $today = new DateTime();
    //     if ($voucherValue <= 0) {
    //         return false;
    //     }

        

        // Make sure we don't go negative - also taking into account multiple vouchers on one order
        

        // return $adjustment;
    // }

    private function _createOrderAdjustment(Voucher $voucher, Order $order)
    {
        $businessId = $voucher->businessId;
        $business = BusinessToBusiness::$plugin->business->getBusinessById($businessId);
        $voucherValue = $voucher->amount;
        //preparing model
        $adjustment = new OrderAdjustment();
        $adjustment->type = self::ADJUSTMENT_TYPE;
        $adjustment->name = $business->name ." ". $voucher . " vouchersss";
        $adjustment->orderId = $order->id;
        $adjustment->description = 'Voucher for asdfas ' . $business->name ." ". $voucher;
        $adjustment->sourceSnapshot = ['business' => $business->name, 'businessId' => $businessId];

        // if ($this->_orderTotal <= $voucherValue) 
        // {
        //     $adjustment->amount = $this->_orderTotal * -1;
        // } 
        // else {
        //     $adjustment->amount = $voucherValue * -1;
        // }

        $this->_orderTotal += $adjustment->amount;

        return $adjustment;
    }

    // private function _discountItem(OrderAdjustment $adjustment)
    // {
        
    // }
}
