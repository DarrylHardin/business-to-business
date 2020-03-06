<?php
/**
 * Business To Business plugin for Craft CMS 3.x
 *
 * Allow businesses to create vouchers for employees that allows the purchasing of products to be charged to the company at a later date.
 *
 * @link      http://importantcoding.com
 * @copyright Copyright (c) 2019 Darryl Hardin
 */

namespace importantcoding\businesstobusiness\services;
use importantcoding\businesstobusiness\BusinessToBusiness;
use importantcoding\businesstobusiness\elements\Voucher as VoucherElement;
use importantcoding\businesstobusiness\elements\Employee;
// use importantcoding\businesstobusiness\services\Employee;
use Craft;
use craft\events\SiteEvent;
use craft\queue\jobs\ResaveElements;
use craft\db\Query;
use yii\base\Component;
use craft\commerce\Plugin as Commerce;
use craft\commerce\elements\Order;
use craft\commerce\models\Customer;
use importantcoding\businesstobusiness\models\Business;
use yii\base\Exception;

/**
 * Voucher Service
 *
 * All of your plugin’s business logic should go in services, including saving data,
 * retrieving data, etc. They provide APIs that your controllers, template variables,
 * and other plugins can interact with.
 *
 * https://craftcms.com/docs/plugins/services
 *
 * @author    Darryl Hardin
 * @package   BusinessToBusiness
 * @since     1.0.0
 */
class Invoices extends Component
{


public function getInvoice(Business $business): Order
    {
        // create invoice order
        if (!$manager = Commerce::getInstance()->getCustomers()->getCustomerByUserId($business->managerId)) {
            $manager = new Customer();
            Commerce::getInstance()->getCustomers()->saveCustomer($manager);
        }
        
        $invoice = $this->findInvoice($manager);

        if(!$invoice)
        {
            if($this->createInvoice($business, $manager))
            {
                $invoice = $this->findInvoice($manager);
            }
        }

        return $invoice;
        
    }

    public function createInvoice(Business $business, Customer $manager): bool
    {
        $invoice = new Order();
        $invoice->number = Commerce::getInstance()->getCarts()->generateCartNumber();
        $invoice->customerId = $manager->id;
        $invoice->setFieldValue('businessInvoice', 1);
        $invoice->setFieldValue('businessId', $business->id);
        $invoice->setFieldValue('businessName', $business->name);
        $invoice->setFieldValue('businessHandle', $business->handle);
        $invoice->origin = Order::ORIGIN_CP;
        $invoice->orderStatusId = 29;
        if (!Craft::$app->getElements()->saveElement($invoice)) {
            throw new Exception(Commerce::t('Can not create a new order'));
        }
        return true;

    }

    public function findInvoice(Customer $manager)
    {
        $invoice = null;
        $orders = $this->getOrdersByManager($manager);
        foreach($orders as $order)
        {
            if($order->getFieldValue('businessInvoice'))
            {
                $invoice = $order;
            }
        }
        return $invoice;
    }

    public function getOrdersByManager(Customer $manager)
    {
        if (!$manager) {
            return null;
        }

        $query = Order::find();
        if ($manager instanceof Customer) {
            $query->customer($manager);
        } else {
            $query->customerId($manager->id);
        }
        $query->limit(null);

        return $query->all();
    }

    public function deleteEmployeesInvoicedItems(Employee $employee)
    {
        $business = BusinessToBusiness::$plugin->business->getBusinessById($employee->businessId);
        
        $invoices = \craft\commerce\elements\Order::find()
            ->user($business->managerId)
            ->orderStatus([27])
            ->all();
        $options = [];
        
        foreach($invoices as $invoice)
        {
            foreach($invoice->getLineItems() as $invoiceItem)
            {
                $purchasable = Commerce::getInstance()->getPurchasables()->getPurchasableById($invoiceItem->purchasableId);
                $options = $invoiceItem->options;
                $lineItem = Commerce::getInstance()->getLineItems()->resolveLineItem($invoice->id, $purchasable->id, $options);
                $invoice->setRecalculationMode(\craft\commerce\elements\Order::RECALCULATION_MODE_ALL);
                $invoice->removeLineItem($lineItem);
                $invoice->recalculate();
                $invoice->setRecalculationMode(\craft\commerce\elements\Order::RECALCULATION_MODE_NONE);
                if (!Craft::$app->getElements()->saveElement($invoice)) {
                    throw new Exception(Commerce::t('Can not create a new order'));
                }   
            }
                        
        }
        return true;
    }
}
?>