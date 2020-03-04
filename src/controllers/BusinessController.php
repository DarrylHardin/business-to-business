<?php
/**
 *
 * @link      http://importantcoding.com
 * @copyright Copyright (c) 2019 Darryl Hardin
 */

namespace importantcoding\businesstobusiness\controllers;

use importantcoding\businesstobusiness\BusinessToBusiness;
use importantcoding\businesstobusiness\elements\Voucher;
use importantcoding\businesstobusiness\models\Business as BusinessModel;
use importantcoding\businesstobusiness\models\BusinessSites as BusinessSitesModel;
use importantcoding\businesstobusiness\models\ShippingRulesBusiness as ShippingRulesBusinessModel;
use importantcoding\businesstobusiness\models\GatewayRulesBusiness as GatewayRulesBusinessModel;
use importantcoding\businesstobusiness\models\DefaultRules as DefaultRulesModel;
use importantcoding\businesstobusiness\records\DefaultRules as DefaultRulesRecord;
use importantcoding\businesstobusiness\records\Business as BusinessRecord;

use Craft;
use craft\web\Controller;
use craft\elements\User;
use yii\base\Exception;
use craft\helpers\ArrayHelper;
use yii\web\Response;

use craft\commerce\Plugin as Commerce;
use craft\commerce\elements\Order;
use craft\commerce\models\Customer;
use importantcoding\businesstobusiness\records\ShippingRulesBusiness as ShippingRulesBusinessRecord;
use importantcoding\businesstobusiness\records\GatewayRulesBusiness as GatewayRulesBusinessRecord;

/**
 * Business Controller
 *
 *
 * @author    Darryl Hardin
 * @package   BusinessToBusiness
 * @since     1.0.0
 */
class BusinessController extends Controller
{

    public function init()
    {
        $this->requirePermission('businessToBusiness-manageBusiness');

        parent::init();
    }


    // Public Methods
    // =========================================================================

    /**
     *
     * @return mixed
     */
    public function actionIndex()
    {
        return $this->renderTemplate('business-to-business/business/index');
    }

    /**
     *
     * @return mixed
     */
    public function actionEdit(int $businessId = null, BusinessModel $business = null): Response
    {   
        $variables = compact('businessId', 'business');
        $variables['brandNewBusiness'] = false;
        $variables['userElementType'] = User::class;
        
        if (empty($variables['business'])) {
            if (!empty($variables['businessId'])) {
                $businessId = $variables['businessId'];
                $variables['business'] = BusinessToBusiness::$plugin->business->getBusinessById($businessId);

                if (!$variables['business']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['business'] = new BusinessModel();
                $variables['brandNewBusiness'] = true;
            }
        }
        $variables['shippingRules'] = BusinessToBusiness::$plugin->defaultRules->getDefaultRulesByShippingMethod();
        $variables['gatewayRules'] = BusinessToBusiness::$plugin->defaultRules->getDefaultRulesByGateway();
        $variables['vouchersExist'] = false;
        
        if(!$variables['brandNewBusiness'])
        {
            $variables['shippingMethods'] = Commerce::getInstance()->getShippingMethods()->getAllShippingMethods();
            $variables['shippingRules'] = BusinessToBusiness::$plugin->shippingRulesBusinesses->getShippingRulesByBusinessId($businessId);

            if(!$variables['shippingRules'])
            {
                $variables['rulesNotice'] = "Please go to Settings and set your default rules";
                $shippingRules = [];
                foreach($variables['shippingMethods'] as $shippingMethod)
                {   
                    $shippingRules[$shippingMethod->id] = new ShippingRulesBusinessModel();
                    $shippingRules[$shippingMethod->id]->businessId = $businessId;
                    $shippingRules[$shippingMethod->id]->name = $shippingMethod->name;
                    $shippingRules[$shippingMethod->id]->shippingMethodId = $shippingMethod->id;
                }
                $variables['shippingRules'] = $shippingRules;
            }

            $variables['gateways'] = Commerce::getInstance()->getGateways()->getAllGateways();
            $variables['gatewayRules'] = BusinessToBusiness::$plugin->gatewayRulesBusinesses->getGatewayRulesByBusinessId($businessId);
            if(!$variables['gatewayRules'])
            {
                $gatewayRules = [];
                foreach($variables['gateways'] as $gateway)
                {   
                    $gatewayRules[$gateway->id] = new GatewayRulesBusinessModel();
                    $gatewayRules[$gateway->id]->businessId = $businessId;
                    $gatewayRules[$gateway->id]->name = $gateway->name;
                    $gatewayRules[$gateway->id]->gatewayId = $gateway->id;
                }
                $variables['gatewayRules'] = $gatewayRules;
            }
            
           

            if(BusinessToBusiness::$plugin->voucher->getVouchersByBusinessId($businessId))
            {
                $variables['vouchersExist'] = true;
            }
            
        }  else {
            if($defaultDiscount = BusinessToBusiness::$plugin->getSettings()->defaultDiscount)
            {
                $variables['business']->discount = $defaultDiscount;
            }
            if($defaultAutoVerify = BusinessToBusiness::$plugin->getSettings()->defaultAutoVerify)
            {
                $variables['business']->autoVerify = $defaultAutoVerify;
            }            
        }
        
        
        $variables['ShippingRulesOptions'] = [];
        $variables['ShippingRulesOptions'][] = ['label' => 'Allow', 'value' => ShippingRulesBusinessRecord::CONDITION_ALLOW];
        $variables['ShippingRulesOptions'][] = ['label' => 'Disallow', 'value' => ShippingRulesBusinessRecord::CONDITION_DISALLOW];
        $variables['ShippingRulesOptions'][] = ['label' => 'Require', 'value' => ShippingRulesBusinessRecord::CONDITION_REQUIRE];
        $variables['ShippingRulesOptions'][] = ['label' => 'Apply Cost to Voucher', 'value' => ShippingRulesBusinessRecord::CONDITION_INCLUDED];

        $variables['GatewayRulesOptions'] = [];
        $variables['GatewayRulesOptions'][] = ['label' => 'Allow', 'value' => GatewayRulesBusinessRecord::CONDITION_ALLOW];
        $variables['GatewayRulesOptions'][] = ['label' => 'Disallow', 'value' => GatewayRulesBusinessRecord::CONDITION_DISALLOW];
        $variables['GatewayRulesOptions'][] = ['label' => 'Require', 'value' => GatewayRulesBusinessRecord::CONDITION_REQUIRE];

        $orderStatuses = Commerce::getInstance()->getOrderStatuses()->getAllOrderStatuses();
        foreach($orderStatuses as $orderStatus)
        {
            if($orderStatus->default)
            {
                $variables['defaultOrderStatus'] = $orderStatus->id;
                break;
            } 
        }
        $variables['orderStatusOptions'] = ArrayHelper::map($orderStatuses, 'id', 'name');
        
        
        

        

        $hasPermission = [
            'manageBusiness' => false,
            'manageVouchers' => false,
            'deleteVouchers' => false,
            'manageEmployees' => false,
            'deleteEmployees' => false,
        ];

        if(!empty($variables['businessId']))
        {
            $variables['manager'] = $variables['business']->getManager();
            
            if ($variables['manager']->can(('businessToBusiness-manageVouchers:'.$variables['business']->id))) {
                $hasPermission['manageBusiness'] = true;
                
                if ($variables['manager']->can(('businessToBusiness-manageVouchers:'.$variables['business']->id))) {
                    $hasPermission['manageVouchers'] = true;
                    
                }
                
                if ($variables['manager']->can(('businessToBusiness-deleteVouchers:'.$variables['business']->id))) {
                    $hasPermission['deleteVouchers'] = true;
                    
                }
                
                if ($variables['manager']->can(('businessToBusiness-manageEmployees:'.$variables['business']->id))) {
                    $hasPermission['manageEmployees'] = true;
                    
                }
                
                if ($variables['manager']->can(('businessToBusiness-deleteEmployees:'.$variables['business']->id))) {
                    $hasPermission['deleteEmployees'] = true;
                    
                }
            }
        } else {
            $hasPermission = [
                'manageBusiness' => true,
                'manageVouchers' => true,
                'deleteVouchers' => true,
                'manageEmployees' => true,
                'deleteEmployees' => true,
            ];
        }

        // $shippingMethods = Commerce::getInstance()->getShippingMethods()->getAllShippingMethods();
        // $variables['shippingMethods'] = ArrayHelper::map($shippingMethods, 'id', 'name');
        


        $variables['hasPermission'] = $hasPermission;
        $sites = Craft::$app->getSites()->getAllSites();
        $variables['sitesList'] = ArrayHelper::map($sites, 'uid', 'name');
        
        if (!empty($variables['businessId'])) {
            $variables['title'] = $variables['business']->name;
        } else {
            $variables['title'] = Craft::t('business-to-business', 'Add a Business');
        }

        // $variables['business']->discount;
        // $variables['business']->passcode;

        return $this->renderTemplate('business-to-business/business/_edit', $variables);
    }



    public function actionSave()
    {
        $this->requirePostRequest();

        $business = new BusinessModel();

        $request = Craft::$app->getRequest();
        
        $shippingMethods = null;

        $business->id = $request->getBodyParam('businessId');
        $business->name = $request->getBodyParam('name');
        $business->handle = $request->getBodyParam('handle');
        $business->autoVerify = $request->getBodyParam('autoVerify');
        $business->passcode = $request->getBodyParam('passcode');
        $business->discount = $request->getBodyParam('discount');
        $business->taxExempt = $request->getBodyParam('taxExempt');
        $business->limitShippingMethods = $request->getBodyParam('limitShippingMethods');
        // $business->shippingRules = $request->getBodyParam('shippingRules');
        // if($business->limitShippingMethods)
        // {
        //     $shippingMethods = $request->getBodyParam('shippingMethods');
        // }
        


        // Manager
        $managerId = $request->getBodyParam('manager');
        $manageBusiness = $request->getBodyParam('manageBusiness');
        
        if (is_array($managerId)) {
            $managerId = $managerId[0] ?? null;
        }

        $business->managerId = $managerId;
        // set up permissions
        $permissions = Craft::$app->getUserPermissions()->getPermissionsByUserId($managerId);
        
        // Craft::craft()->db->createCommand()
        // ->insert('%businesstotbusiness_business', array(
        //     'discount'    => $business->discount,
        //     'passcode'    => $business->passcode
        // ));
        // Site-specific settings
        $sitePermissions = [];
        $allSiteSettings = [];
        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            $postedSettings = $request->getBodyParam('sites.' . $site->handle);
            $siteSettings = new BusinessSitesModel();
            $siteSettings->siteId = $site->id;
            $siteSettings->hasUrls = !empty($postedSettings['uriFormat']);

            if ($siteSettings->hasUrls) {
                if($manageBusiness)
                {
                    ArrayHelper::append($sitePermissions, 'editSite:' . $site->uid);
                }
                $siteSettings->uriFormat = $postedSettings['uriFormat'];
                $siteSettings->template = $postedSettings['template'];
            } else {
                ArrayHelper::removeValue($permissions, "editsite:".$site->uid);
                $siteSettings->uriFormat = null;
                $siteSettings->template = null;
            }

            $allSiteSettings[$site->id] = $siteSettings;
        }

        
        // foreach($unsetSitePermissions as $value)
        // {
        
        //     ArrayHelper::removeValue($permissions, $value);
        // }
        
        // $pos = array_search('editsite:f753a396-99a2-4e89-a886-8b81a72820a6', $permissions);
        // if($pos)
        // {  
            
        // }

        $business->setSiteSettings($allSiteSettings);

        // Set the voucher type field layout
        $fieldLayout = Craft::$app->getFields()->assembleLayoutFromPost();
        $fieldLayout->type = Voucher::class;
        $business->setFieldLayout($fieldLayout);
        // $sitePermissions = $request->getBodyParam('sitePermissions', []);
        // if (!$sitePermissions) {
        //     $sitePermissions = [];
        // }
        
        // Save it
        if (BusinessToBusiness::$plugin->business->saveBusiness($business)) {
        // if  { seems this could replace the above just fine and forget about the traits file
            $finalPermissions = [];
            if($request->getBodyParam('manageBusiness'))
            {   
                ArrayHelper::append($permissions, 
                'accessCp',
                'accessPlugin-business-to-business',
                'businessToBusiness-manageBusiness:'.$business->id
                );
                if($request->getBodyParam('manageVouchers'))
                {
                    ArrayHelper::append($permissions, 
                    'businessToBusiness-manageVouchers:'.$business->id
                    );
                }

                if($request->getBodyParam('deleteVouchers'))
                {
                    ArrayHelper::append($permissions, 
                    'businessToBusiness-deleteVouchers:'.$business->id
                    );
                }

                if($request->getBodyParam('manageEmployees'))
                {
                    ArrayHelper::append($permissions, 
                    'businessToBusiness-manageEmployees:'.$business->id
                    );
                }

                if($request->getBodyParam('deleteEmployees'))
                {
                    ArrayHelper::append($permissions, 
                    'businessToBusiness-deleteEmployees:'.$business->id
                    );
                }
                $finalPermissions = array_merge($permissions, $sitePermissions);
            }

            if(!Craft::$app->getUserPermissions()->saveUserPermissions($managerId, $finalPermissions))
            {
                Craft::$app->getSession()->setError(Craft::t('business-to-business', 'Couldn’t save user permissions.'));
            }

            $shippingRulesBusiness = [];
            $allShippingRulesBusiness = Craft::$app->getRequest()->getBodyParam('shippingRulesBusiness');
            if($allShippingRulesBusiness)
            {
                foreach ($allShippingRulesBusiness as $key => $shippingRuleBusiness) {

                    $shippingMethod = Commerce::getInstance()->getShippingMethods()->getShippingMethodById($key);

                    $shippingRulesBusiness[$key] = new ShippingRulesBusinessModel($shippingRuleBusiness);
                    $shippingRulesBusiness[$key]->name = $shippingMethod->name;
                    $shippingRulesBusiness[$key]->shippingMethodId = $key;
                    $shippingRulesBusiness[$key]->businessId = $business->id;
                    $shippingRulesBusiness[$key]->condition = $shippingRuleBusiness['condition'];
                    if(!BusinessToBusiness::$plugin->shippingRulesBusinesses->saveShippingRule($shippingRulesBusiness[$key]))
                    {
                        
                    }
                    
                    // if(BusinessToBusiness::$plugin->shippingRulesBusinesses->checkExistingRule($business->id, $key))
                    // {
                    //     BusinessToBusiness::$plugin->shippingRulesBusinesses->saveShippingRuleBusiness($shippingRulesBusiness[$key], false);
                    // } else {
                    //     BusinessToBusiness::$plugin->shippingRulesBusinesses->createShippingRuleBusiness($shippingRulesBusiness[$key], false);
                    // }

                    
                }
            }

            $gatewayRulesBusiness = [];
            $allGatewayRulesBusiness = Craft::$app->getRequest()->getBodyParam('gatewayRulesBusiness');
            if($allGatewayRulesBusiness)
            {
                foreach ($allGatewayRulesBusiness as $key => $gatewayRuleBusiness) {

                    $gateway = Commerce::getInstance()->getGateways()->getGatewayById($key);

                    $gatewayRulesBusiness[$key] = new GatewayRulesBusinessModel($gatewayRuleBusiness);
                    $gatewayRulesBusiness[$key]->name = $gateway->name;
                    $gatewayRulesBusiness[$key]->gatewayId = $key;
                    $gatewayRulesBusiness[$key]->businessId = $business->id;
                    $gatewayRulesBusiness[$key]->condition = $gatewayRuleBusiness['condition'];
                    $gatewayRulesBusiness[$key]->orderStatusId = $gatewayRuleBusiness['orderStatusId'];

                    if(!BusinessToBusiness::$plugin->gatewayRulesBusinesses->saveGatewayRule($gatewayRulesBusiness[$key]))
                    {
                        
                    }
                    
                    // if(BusinessToBusiness::$plugin->shippingRulesBusinesses->checkExistingRule($business->id, $key))
                    // {
                    //     BusinessToBusiness::$plugin->shippingRulesBusinesses->saveShippingRuleBusiness($shippingRulesBusiness[$key], false);
                    // } else {
                    //     BusinessToBusiness::$plugin->shippingRulesBusinesses->createShippingRuleBusiness($shippingRulesBusiness[$key], false);
                    // }

                    
                }
            }
            // $business->setShippingRuleBusinesses($shippingRulesBusinesses);
            
            // Generate a rule category record for all categories regardless of data submitted
            // foreach (BusinessToBusiness::$plugin->shippingRulesBusinesses->getAllShippingRules() as $shippingRule) {
            
            //     if (isset($business->getShippingRuleBusinesses()[$shippingRule->id]) && $shippingRuleBusiness = $business->getShippingRuleBusinesses()[$shippingBusinessRule->id]) {
            //         $shippingRuleBusiness = new ShippingRulesBusinessModel([
            //             'businessId' => $business->id,
            //             'shippingMethodId' => $shippingRule->id,
            //             'condition' => $shippingRuleBusiness->condition,
            //         ]);
            //     } else {
            //         $shippingRuleBusiness = new ShippingRulesBusinessModel([
            //             'businessId' => $business->id,
            //             'shippingMethodId' => $shippingRule->id,
            //             'condition' => ShippingRulesBusinessRecord::CONDITION_ALLOW
            //         ]);
            //     }

                
            // }

            // create invoice order
            // if (!$customer = Commerce::getInstance()->getCustomers()->getCustomerByUserId($business->managerId)) {
            //     $customer = new Customer();
            //     Commerce::getInstance()->getCustomers()->saveCustomer($customer);
            // }

            // $invoice = null;
            $order = BusinessToBusiness::$plugin->invoices->getInvoice($business);
            $order->markAsComplete();


            // die($order);
            // foreach($orders as $order)
            // {
            //     if($order->getFieldValue('businessInvoice'))
            //     {
            //         $invoice = $order;
            //     }
            // }
            // if(!$invoice)
            // {
            //     $invoice = new Order();
            //     $invoice->number = Commerce::getInstance()->getCarts()->generateCartNumber();
            //     $invoice->setFieldValue('businessInvoice', 1);
            //     $invoice->setFieldValue('businessId', $business->id);
            //     $invoice->setFieldValue('businessName', $business->name);
            //     $invoice->setFieldValue('businessHandle', $business->handle);
            //     $invoice->orderStatusId = 29;
            //     if (!Craft::$app->getElements()->saveElement($invoice)) {
            //         throw new Exception(Commerce::t('Can not create a new order'));
            //     }
            // }
            

            Craft::$app->getSession()->setNotice(Craft::t('business-to-business', 'Business saved.'));

            return $this->redirectToPostedUrl($business);
        }

        Craft::$app->getSession()->setError(Craft::t('business-to-business', 'Couldn’t save business.'));

        // Send the business back to the template
        Craft::$app->getUrlManager()->setRouteParams([
            'business' => $business
        ]);
        
        

        

        return null;
    }

    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $businessId = Craft::$app->getRequest()->getRequiredParam('id');
        BusinessToBusiness::$plugin->business->deleteBusinessById($businessId);

        return $this->asJson(['success' => true]);
    }

    public function actionEditDefaultRules()
    {
        // $variables['shippingMethods'] = BusinessToBusiness::$plugin->defaultRules->getDefaultRulesByShippingMethod();
        // if(!$variables['shippingMethods'])
        // {
        //     $variables['shippingMethods'] = Commerce::getInstance()->getShippingMethods()->getAllShippingMethods();
        //     $shippingRules = [];
        //     foreach($variables['shippingMethods'] as $shippingMethod)
        //     {   
        //         $shippingRules[$shippingMethod->id] = new DefaultRulesModel();
        //         $shippingRules[$shippingMethod->id]->name = $shippingMethod->name;
        //         $shippingRules[$shippingMethod->id]->shippingMethodId = $shippingMethod->id;
        //     }
        //     $variables['shippingRules'] = $shippingRules;
        // } else {
        //     $shippingRules = [];
        //     foreach($variables['shippingMethods'] as $shippingMethod)
        //     {   
        //         $shippingRules[$shippingMethod->id]->name = $shippingMethod->name;
        //         $shippingRules[$shippingMethod->id]->shippingMethodId = $shippingMethod->id;
        //     }
        //     $variables['shippingRules'] = $shippingRules;
        // }

        $variables['shippingMethods'] = Commerce::getInstance()->getShippingMethods()->getAllShippingMethods();
        $variables['shippingRules'] = BusinessToBusiness::$plugin->defaultRules->getDefaultRulesByShippingMethod();
        if(!$variables['shippingRules'])
        {
            $shippingRules = [];
            foreach($variables['shippingMethods'] as $shippingMethod)
            {   
                $shippingRules[$shippingMethod->id] = new DefaultRulesModel();
                $shippingRules[$shippingMethod->id]->name = $shippingMethod->name;
                $shippingRules[$shippingMethod->id]->shippingMethodId = $shippingMethod->id;
            }
            $variables['shippingRules'] = $shippingRules;
        }
            

        // $variables['gateways'] = BusinessToBusiness::$plugin->defaultRules->getDefaultRulesByShippingMethod();
        // if(!$variables['gateways'])
        // {
        //         $variables['gateways'] = Commerce::getInstance()->getGateways()->getAllGateways();
            
            
        //     $gatewayRules = [];
        //     foreach($variables['gateways'] as $gateway)
        //     {   
        //         $gatewayRules[$gateway->id] = new GatewayRulesBusinessModel();
        //         $gatewayRules[$gateway->id]->name = $gateway->name;
        //         $gatewayRules[$gateway->id]->gatewayId = $gateway->id;
        //     }
        // }
        // $variables['gatewayRules'] = BusinessToBusiness::$plugin->defaultRules->getDefaultRulesByShippingMethod();



        //     $variables['gatewayRules'] = $gatewayRules;

        $variables['gateways'] = Commerce::getInstance()->getGateways()->getAllGateways();
        $variables['gatewayRules'] = BusinessToBusiness::$plugin->defaultRules->getDefaultRulesByGateway();
        if(!$variables['gatewayRules'])
        {
            $gatewayRules = [];
            foreach($variables['gateways'] as $gateway)
            {   
                $gatewayRules[$gateway->id] = new GatewayRulesBusinessModel();
                $gatewayRules[$gateway->id]->name = $gateway->name;
                $gatewayRules[$gateway->id]->gatewayId = $gateway->id;
            }
            $variables['gatewayRules'] = $gatewayRules;
        }
            
            $variables['ShippingRulesOptions'] = [];
            $variables['ShippingRulesOptions'][] = ['label' => 'Allow', 'value' => ShippingRulesBusinessRecord::CONDITION_ALLOW];
            $variables['ShippingRulesOptions'][] = ['label' => 'Disallow', 'value' => ShippingRulesBusinessRecord::CONDITION_DISALLOW];
            $variables['ShippingRulesOptions'][] = ['label' => 'Require', 'value' => ShippingRulesBusinessRecord::CONDITION_REQUIRE];
            $variables['ShippingRulesOptions'][] = ['label' => 'Apply Cost to Voucher', 'value' => ShippingRulesBusinessRecord::CONDITION_INCLUDED];

            $variables['GatewayRulesOptions'] = [];
            $variables['GatewayRulesOptions'][] = ['label' => 'Allow', 'value' => GatewayRulesBusinessRecord::CONDITION_ALLOW];
            $variables['GatewayRulesOptions'][] = ['label' => 'Disallow', 'value' => GatewayRulesBusinessRecord::CONDITION_DISALLOW];
            $variables['GatewayRulesOptions'][] = ['label' => 'Require', 'value' => GatewayRulesBusinessRecord::CONDITION_REQUIRE];

            $orderStatuses = Commerce::getInstance()->getOrderStatuses()->getAllOrderStatuses();
            foreach($orderStatuses as $orderStatus)
            {
                if($orderStatus->default)
                {
                    $variables['defaultOrderStatus'] = $orderStatus->id;
                    break;
                } 
            }
            $variables['orderStatusOptions'] = ArrayHelper::map($orderStatuses, 'id', 'name');

        return $this->renderTemplate('business-to-business/settings/businesssettings/_edit', $variables);
    }
    
    public function actionSaveDefaultRules()
    {
        $shippingRulesBusiness = [];
        $allShippingRulesBusiness = Craft::$app->getRequest()->getBodyParam('shippingRulesBusiness');
        
        if($allShippingRulesBusiness)
        {
            foreach ($allShippingRulesBusiness as $key => $shippingRuleBusiness) {

                $shippingMethod = Commerce::getInstance()->getShippingMethods()->getShippingMethodById($key);

                $shippingRulesBusiness[$key] = new DefaultRulesModel($shippingRuleBusiness);
                $shippingRulesBusiness[$key]->name = $shippingMethod->name;
                $shippingRulesBusiness[$key]->shippingMethodId = $key;
                $shippingRulesBusiness[$key]->condition = $shippingRuleBusiness['condition'];
                if(!BusinessToBusiness::$plugin->business->saveDefaultRule($shippingRulesBusiness[$key]))
                {
                    die('failed');
                }
                // if(BusinessToBusiness::$plugin->shippingRulesBusinesses->checkExistingRule($business->id, $key))
                // {
                //     BusinessToBusiness::$plugin->shippingRulesBusinesses->saveShippingRuleBusiness($shippingRulesBusiness[$key], false);
                // } else {
                //     BusinessToBusiness::$plugin->shippingRulesBusinesses->createShippingRuleBusiness($shippingRulesBusiness[$key], false);
                // }

                
            }
        }

        $gatewayRulesBusiness = [];
        $allGatewayRulesBusiness = Craft::$app->getRequest()->getBodyParam('gatewayRulesBusiness');
        if($allGatewayRulesBusiness)
        {
            foreach ($allGatewayRulesBusiness as $key => $gatewayRuleBusiness) {

                $gateway = Commerce::getInstance()->getGateways()->getGatewayById($key);

                $gatewayRulesBusiness[$key] = new DefaultRulesModel($gatewayRuleBusiness);
                $gatewayRulesBusiness[$key]->name = $gateway->name;
                $gatewayRulesBusiness[$key]->gatewayId = $key;
                $gatewayRulesBusiness[$key]->condition = $gatewayRuleBusiness['condition'];
                $gatewayRulesBusiness[$key]->orderStatusId = $gatewayRuleBusiness['orderStatusId'];

                if(!BusinessToBusiness::$plugin->business->saveDefaultRule($gatewayRulesBusiness[$key]))
                {
                    
                }
                
                // if(BusinessToBusiness::$plugin->shippingRulesBusinesses->checkExistingRule($business->id, $key))
                // {
                //     BusinessToBusiness::$plugin->shippingRulesBusinesses->saveShippingRuleBusiness($shippingRulesBusiness[$key], false);
                // } else {
                //     BusinessToBusiness::$plugin->shippingRulesBusinesses->createShippingRuleBusiness($shippingRulesBusiness[$key], false);
                // }

                
            }
        }
    }

}
