<?php
/**
 * Business To Business plugin for Craft CMS 3.x
 *
 * Allow businesses to create vouchers for employees that allows the purchasing of products to be charged to the company at a later date.
 *
 * @link      http://importantcoding.com
 * @copyright Copyright (c) 2019 Darryl Hardin
 */

namespace importantcoding\businesstobusiness\controllers;

use importantcoding\businesstobusiness\BusinessToBusiness;


use Craft;
use craft\commerce\elements\Order;
use craft\web\Controller;
/**
 * Orders Controller
 *
 * @author    Darryl Hardin
 * @package   BusinessToBusiness
 * @since     1.0.0
 */
class OrdersController extends Controller
{
    public function actionIndex(){
        // $businesses = BusinessToBusiness::getInstance()->business->getEditableBusiness();
        // $variables = [];
        // foreach ($businesses as $business) {
        //     $variables['business'] = [
        //         'handle' => $business['handle']
        //     ];
        // }
        // $variables['tabss'] = [
        //     'this' => 'Craft::t(site, $tab->name)',
        //     'that' => '#' . '$tab->getHtmlId()',
        //     'other' => '$hasErrors ? rror : null'
        // ];
        // $variables['orders'] = Order::find()
        // ->gatewayId(1)
        // ->fixedOrder()
        // ->all();
        $variables['bus'] = 
        [
            'id' => null,
        ];

        return $this->renderTemplate('business-to-business/orders/index', $variables);
    }

    public function actionBusiness(int $businessId){
        $variables['bus'] = 
        [
            'id' => $businessId,
        ];
        return $this->renderTemplate('business-to-business/orders/index', $variables);
    }
}