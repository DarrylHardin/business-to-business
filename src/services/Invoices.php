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
// use importantcoding\businesstobusiness\services\Employee;
use Craft;
use craft\events\SiteEvent;
use craft\queue\jobs\ResaveElements;
use craft\db\Query;
use yii\base\Component;
use craft\commerce\Plugin as Commerce;
use craft\commerce\elements\Order;
use craft\commerce\models\Customer;
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
public function getInvoice(Business $business)
    {
        // create invoice order
        if (!$customer = Commerce::getInstance()->getCustomers()->getCustomerByUserId($business->managerId)) {
            $customer = new Customer();
            Commerce::getInstance()->getCustomers()->saveCustomer($customer);
        }

        
        $invoice = $this->findInvoice($customer);

        if(!$invoice)
        {
            if($this->createInvoice($business))
            {
                $invoice = $this->findInvoice($customer);
            }
        }

        return $invoice;
        
    }

    public function createInvoice(Business $business): bool
    {
        $invoice = new Order();
        $invoice->number = Commerce::getInstance()->getCarts()->generateCartNumber();
        $invoice->setFieldValue('businessInvoice', 1);
        $invoice->setFieldValue('businessId', $business->id);
        $invoice->setFieldValue('businessName', $business->name);
        $invoice->setFieldValue('businessHandle', $business->handle);
        $invoice->orderStatusId = 29;
        if (!Craft::$app->getElements()->saveElement($invoice)) {
            throw new Exception(Commerce::t('Can not create a new order'));
        }
        return true;

    }

    public function findInvoice(Customer $customer): Order
    {
        $orders = Commerce::getInstance()->getOrders()->getOrdersByCustomer($customer);
        foreach($orders as $order)
        {
            if($order->getFieldValue('businessInvoice'))
            {
                $invoice = $order;
            }
        }
        return $invoice;
    }
}
?>