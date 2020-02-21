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


use craft\web\Controller;

/**
 * GatewayRulesBusiness Controller
 *
 * @author    Darryl Hardin
 * @package   BusinessToBusiness
 * @since     1.0.0
 */
class GatewayRulesBusinessController extends Controller
{

    public function init()
    {
        $this->requirePermission('businessToBusiness-manageBusiness');

        parent::init();
    }

}
