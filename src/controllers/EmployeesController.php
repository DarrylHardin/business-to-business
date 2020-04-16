<?php
/**
 *
 * @link      http://importantcoding.com
 * @copyright Copyright (c) 2019 Darryl Hardin
 */

namespace importantcoding\businesstobusiness\controllers;

use importantcoding\businesstobusiness\BusinessToBusiness;
use importantcoding\businesstobusiness\elements\Employee;

use Craft;
use craft\base\Element;
use craft\helpers\DateTimeHelper;
use craft\helpers\Json;
use craft\helpers\Localization;
use craft\helpers\UrlHelper;
use craft\models\Site;
use craft\web\Controller;
use craft\commerce\Plugin as Commerce;

use yii\base\Exception;
use yii\web\ForbiddenHttpException;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\ServerErrorHttpException;

/**
 * Employees Controller
 *
 *
 * @author    Darryl Hardin
 * @package   BusinessToBusiness
 * @since     1.0.0
 */
class EmployeesController extends Controller
{

    // public function init()
    // {
    //     $this->requirePermission('businessToBusiness-manageEmployees');

    //     parent::init();
    // }

    public function actionIndex(): Response
    {
        return $this->renderTemplate('business-to-business/employees/index');
    }

    public function actionEdit(string $businessHandle, int $employeeId = null, string $siteHandle = null, Employee $employee = null): Response
    {
        $business = null;
        $vouchers = BusinessToBusiness::$plugin->voucher->getVouchersByBusinessHandle($businessHandle);
        $variables = [
            'businessHandle' => $businessHandle,
            'employeeId' => $employeeId,
            'employee' => $employee,
            'vouchers' => $vouchers,
        ];
        // Make sure a correct business handle was passed so we can check permissions
        if ($businessHandle) {
            $business = BusinessToBusiness::$plugin->business->getBusinessByHandle($businessHandle);
        }

        if (!$business) {
            throw new Exception('The business was not found.');
        }

        // $this->requirePermission('businessToBusiness-manageBusiness:' . $business->id);
        $variables['business'] = $business;
        $variables['vouchers'] = $vouchers;
        if ($siteHandle !== null) {
            $variables['site'] = Craft::$app->getSites()->getSiteByHandle($siteHandle);

            if (!$variables['site']) {
                throw new Exception('Invalid site handle: '.$siteHandle);
            }
        }

        $this->_prepareVariableArray($variables);

        if (!empty($variables['employee']->id)) {
            $variables['title'] = $variables['employee']->title;
        } else {
            $variables['title'] = Craft::t('business-to-business', 'Create a new employee');
        }

        // Can't just use the entry's getCpEditUrl() because that might include the site handle when we don't want it
        $variables['baseCpEditUrl'] = 'business-to-business/employees/' . $variables['businessHandle'] . '/{id}';

        // Set the "Continue Editing" URL
        $variables['continueEditingUrl'] = $variables['baseCpEditUrl'] . (Craft::$app->getIsMultiSite() && Craft::$app->getSites()->currentSite->id !== $variables['site']->id ? '/' . $variables['site']->handle : '');

        $this->_maybeEnableLivePreview($variables);

        $variables['tabs'] = [];

        foreach ($variables['business']->getFieldLayout()->getTabs() as $index => $tab) {
            // Do any of the fields on this tab have errors?
            $hasErrors = false;

            if ($variables['employee']->hasErrors()) {
                foreach ($tab->getFields() as $field) {
                    if ($hasErrors = $variables['employee']->hasErrors($field->handle . '.*')) {
                        break;
                    }
                }
            }

            $variables['tabs'][] = [
                'label' => Craft::t('site', $tab->name),
                'url' => '#' . $tab->getHtmlId(),
                'class' => $hasErrors ? 'error' : null
            ];
        }

        return $this->renderTemplate('business-to-business/employees/_edit', $variables);
    }

    public function actionEditFieldlayout(array $variables = []): Response
    {
        $fieldLayout = Craft::$app->getFields()->getLayoutByType(Employee::class);
        $variables['fieldLayout'] = $fieldLayout;
        $variables['title'] = Craft::t('business-to-business', 'Employee Settings');

        return $this->renderTemplate('business-to-business/settings/employeesettings/_edit', $variables);
    }

    public function actionSaveFieldlayout()
    {
        $this->requirePostRequest();

        $fieldLayout = Craft::$app->getFields()->assembleLayoutFromPost();
        // $configData = [StringHelper::UUID() => $fieldLayout->getConfig()];
        $fieldLayout->type = 'importantcoding\businesstobusiness\elements\Employee';
        // Craft::$app->getProjectConfig()->set(EmployeeServices::CONFIG_FIELDLAYOUT_KEY, $configData);
        \Craft::$app->getFields()->saveLayout($fieldLayout);
        Craft::$app->getSession()->setNotice(Craft::t('app', 'Employee fields saved.'));
        return $this->redirectToPostedUrl();

        // $fieldLayout = \Craft::$app->getFields()->assembleLayoutFromPost();
        // $fieldLayout->type = EmployeeElement::class;
        // \Craft::$app->getFields()->saveLayout($fieldLayout);
    }

    public function actionDelete(int $employeeId = NULL)
    {
        if(!$employeeId)
        {
            $this->requirePostRequest();

            $employeeId = Craft::$app->getRequest()->getRequiredParam('employeeId');
        }
        $employee = Employee::findOne($employeeId);
        if(BusinessToBusiness::$plugin->employee->delete($employee))
        {
            return $this->redirectToPostedUrl($employee);    
        }
    }

    public function actionSave()
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $employeeId = $request->getBodyParam('employeeId');
        $user = Craft::$app->getUser()->getIdentity();
        // $employee = BusinessToBusiness::$plugin->employee->getEmployeeByUserId($user->id);
        // if($employee)
        // {
        //     $employeeId = $employee->id;
        // }
        $employee = $this->_setEmployeeFromPost($employeeId);
        $employee->businessId = $request->getBodyParam('businessId');

        // $employee->authorized = $request->getBodyParam('authorized');
        $employee->timesVoucherUsed = $request->getBodyParam('timesVoucherUsed');
        $employee->dateVoucherUsed = $request->getBodyParam('dateVoucherUsed');
        $employee->title = $request->getBodyParam('title');
        $employee->setFieldValuesFromRequest('fields');
        Craft::$app->getUsers()->assignUserToGroups($user->id, [1, 3]);
        if ($employee->businessId == 0){
            $variables = [
                "errorEmployer" => "Please select your employer",
            ];
            return $this->renderTemplate('employee/new', $variables);
        }       
        $passcode = $request->getBodyParam('passcode');
        
        $business = BusinessToBusiness::$plugin->business->getBusinessById($employee->businessId);

        if($passcode != $business->passcode)
        {
            // $variables['tabs'][] = [
            //     'label' => Craft::t('site', $tab->name),
            //     'url' => '#' . $tab->getHtmlId(),
            //     'class' => $hasErrors ? 'error' : null
            // ];
            $variables = [
                "firstName" => $employee->firstName,
                "lastName" => $employee->lastName,
                "employeeNumber" => $employee->title,
                "employeeEmail" => $employee->email,
                "businessSel" => $employee->businessId,
                "passcode" => "Your passcode value is incorrect, please recheck it and try again.",
            ];
            return $this->renderTemplate('employee/new', $variables);
        }
        if($business->autoVerify == 1)
        {
            $employee->authorized = 1;
        }
        $employee->voucherAvailable = 1;
        // $employee->authorized = null;
        
        // $this->enforceEmployeePermissions($employee);

        // if($employee->authorized == 1)
        // {
        //     $orders = \craft\commerce\elements\Order::find()
        //     ->user($employee->userId)
        //     ->orderStatus(10)
        //     ->all();

        //     foreach($orders as $order)
        //     {
        //         $order->orderStatusId = 9;
        //         Craft::$app->getElements()->saveElement($order);
        //     }
        // }

        // if($employee->authorized == 0)
        // {
        //     $orders = \craft\commerce\elements\Order::find()
        //     ->user($employee->userId)
        //     ->orderStatus(9)
        //     ->all();

        //     foreach($orders as $order)
        //     {
        //         $order->orderStatusId = 10;
        //         Craft::$app->getElements()->saveElement($order);
        //     }
        // }
        
        if (!Craft::$app->getElements()->saveElement($employee)) {
            if ($request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'errors' => $employee->getErrors(),
                ]);
            }
        
            Craft::$app->getSession()->setError(Craft::t('business-to-business', 'Couldn’t save employee.'));

            // Send the category back to the template
            Craft::$app->getUrlManager()->setRouteParams([
                'employee' => $employee
            ]);

            return null;
        }

        if ($request->getAcceptsJson()) {
            return $this->asJson([
                'success' => true,
                'id' => $employee->id,
                'title' => $employee->title,
                'status' => $employee->getStatus(),
                'url' => $employee->getUrl(),
                'cpEditUrl' => $employee->getCpEditUrl()
            ]);
        }

        Craft::$app->getSession()->setNotice(Craft::t('app', 'Employee saved.'));

        return $this->redirectToPostedUrl($employee);
    }



    public function actionUpdateEmployeeAsManager()
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $employeeId = $request->getBodyParam('employeeId');
        $employee = $this->_setEmployeeFromPost($employeeId);
        $employee->businessId = $request->getBodyParam('businessId');
        $employee->authorized = $request->getBodyParam('authorized');
        $employee->timesVoucherUsed = $request->getBodyParam('timesVoucherUsed');
        $employee->title = $request->getBodyParam('title', $employee->title);
        $employee->setFieldValuesFromRequest('fields');
        $employee->voucherId = $request->getBodyParam('voucherId');
        if($employee->authorized == 1)
        {
            $orders = \craft\commerce\elements\Order::find()
            ->user($employee->userId)
            ->orderStatusId(10)
            ->all();

            foreach($orders as $order)
            {
                BusinessToBusiness::$plugin->invoices->addOrderToInvoice($order);
                // $order->setFieldValue('orderStatusId', 9);
                $order->orderStatusId = 9;
                Craft::$app->getElements()->saveElement($order);
            }
        } else if ($employee->authorized == 0)
        {
            $orders = \craft\commerce\elements\Order::find()
            ->user($employee->userId)
            ->orderStatusId(9)
            ->all();

            foreach($orders as $order)
            {
                // $order->setFieldValue('orderStatusId', 10);
                
                $order->orderStatusId = 10;
                Craft::$app->getElements()->saveElement($order);
            }
        }

        if (!Craft::$app->getElements()->saveElement($employee)) {
            if ($request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'errors' => $employee->getErrors(),
                ]);
            }
        
            Craft::$app->getSession()->setError(Craft::t('business-to-business', 'Couldn’t save employee.'));

            // Send the category back to the template
            Craft::$app->getUrlManager()->setRouteParams([
                'employee' => $employee
            ]);

            return null;
        }

        if ($request->getAcceptsJson()) {
            return $this->asJson([
                'success' => true,
                'id' => $employee->id,
                'title' => $employee->title,
                'status' => $employee->getStatus(),
                'url' => $employee->getUrl(),
                'cpEditUrl' => $employee->getCpEditUrl()
            ]);
        }

        Craft::$app->getSession()->setNotice(Craft::t('app', 'Employee saved.'));

        return $this->redirectToPostedUrl($employee);
    }

    public function actionUpdateEmployee()
    {
        $this->requirePostRequest();
        
        $request = Craft::$app->getRequest();
        $user = Craft::$app->getUser();
        $employeeId = BusinessToBusiness::$plugin->employee->getEmployeeByUserId($user->id);

        $employee = $this->_setEmployeeFromPost($employeeId->id);

        // $this->enforceEmployeePermissions($employee);

        if (!Craft::$app->getElements()->saveElement($employee)) {
            if ($request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'errors' => $employee->getErrors(),
                ]);
            }

            Craft::$app->getSession()->setError(Craft::t('business-to-business', 'Couldn’t save employee.'));

            // Send the category back to the template
            Craft::$app->getUrlManager()->setRouteParams([
                'employee' => $employee
            ]);

            return null;
        }

        if ($request->getAcceptsJson()) {
            return $this->asJson([
                'success' => true,
                'id' => $employee->id,
                'title' => $employee->title,
                'status' => $employee->getStatus(),
                'url' => $employee->getUrl(),
                'cpEditUrl' => $employee->getCpEditUrl()
            ]);
        }

        Craft::$app->getSession()->setNotice(Craft::t('app', 'Employee saved.'));

        return $this->redirectToPostedUrl($employee);
    }

    public function actionUpdateVoucher()
    {
        $this->requirePostRequest();
        
        $request = Craft::$app->getRequest();
        $user = Craft::$app->getUser();
        $employee = BusinessToBusiness::$plugin->employee->getEmployeeByUserId($user->id);

        if (!$employee) {
            throw new Exception(Craft::t('business-to-business', 'No employee with the ID “{id}”', ['id' => $employee->id]));
        }

        
        $code = $request->getBodyParam('code');

        $employee->voucherId = $request->getBodyParam('voucherId');
        
        $voucher = BusinessToBusiness::$plugin->voucher->getVoucherById($employee->voucherId);
        if($code != $voucher->code)
        {
            $variables = [
                "voucherCodeError" => "Your passcode value is incorrect, please recheck it and try again.",
            ];
            return $this->renderTemplate('business/employee/index', $variables);
        }

        if (!Craft::$app->getElements()->saveElement($employee)) {
            if ($request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'errors' => $employee->getErrors(),
                ]);
            }

            Craft::$app->getSession()->setError(Craft::t('business-to-business', 'Couldn’t update voucher.'));

            // Send the category back to the template
            Craft::$app->getUrlManager()->setRouteParams([
                'employee' => $employee
            ]);

            return null;
        }

        if ($request->getAcceptsJson()) {
            return $this->asJson([
                'success' => true,
                'id' => $employee->id,
                'title' => $employee->title,
                'status' => $employee->getStatus(),
                'url' => $employee->getUrl(),
                'cpEditUrl' => $employee->getCpEditUrl()
            ]);
        }

        Craft::$app->getSession()->setNotice(Craft::t('app', 'Employee saved.'));

        return $this->redirectToPostedUrl($employee);
    }

    public function actionPreviewEmployee(): Response
    {

        $this->requirePostRequest();
        $request = Craft::$app->getRequest();
        $employeeId = $request->getBodyParam('employeeId');
        $employee = $this->_setEmployeeFromPost($employeeId);

        $this->enforceEmployeePermissions($employee);

        return $this->_showEmployee($employee);
    }

    public function actionShareEmployee($employeeId, $siteId): Response
    {
        $employee = BusinessToBusiness::getInstance()->getEmployees()->getEmployeeById($employeeId, $siteId);

        if (!$employee) {
            throw new HttpException(404);
        }

        $this->enforceEmployeePermissions($employee);

        if (!BusinessToBusiness::$plugin->business->isBusinessTemplateValid($employee->getBusiness(), $employee->siteId)) {
            throw new HttpException(404);
        }

        $this->requirePermission('businessToBusiness-manageBusiness:' . $employee->businessId);

        // Create the token and redirect to the employee URL with the token in place
        $token = Craft::$app->getTokens()->createToken([
            'business-to-business/employees/view-shared-employee', ['employeeId' => $employee->id, 'siteId' => $siteId]
        ]);

        $url = UrlHelper::urlWithToken($employee->getUrl(), $token);

        return $this->redirect($url);
    }

    public function actionViewSharedEmployee($employeeId, $site = null)
    {
        $this->requireToken();

        $employee = BusinessToBusiness::getInstance()->getEmployees()->getEmployeeById($employeeId, $site);

        if (!$employee) {
            throw new HttpException(404);
        }

        $this->_showEmployee($employee);

        return null;
    }

    // public function actionIfEmployee(){
    //     $user = Craft::$app->getUser();
    //     $entryQuery = Employee::find()
    //     ->userId($user->id);
    // }


    // Protected Methods
    // =========================================================================

    protected function enforceEmployeePermissions(Employee $employee)
    {
        if (!$employee->getBusiness()) {
            Craft::error('Attempting to access a employee that doesn’t have a type', __METHOD__);
            throw new HttpException(404);
        }

        $this->requirePermission('businessToBusiness-manageBusiness:' . $employee->getBusiness()->id);
    }


    // Private Methods
    // =========================================================================

    private function _showEmployee(Employee $employee): Response
    {

        $business = $employee->getBusiness();

        if (!$business) {
            throw new ServerErrorHttpException('Employee business not found.');
        }

        $siteSettings = $business->getSiteSettings();

        if (!isset($siteSettings[$employee->siteId]) || !$siteSettings[$employee->siteId]->hasUrls) {
            throw new ServerErrorHttpException('The employee ' . $employee->id . ' doesn\'t have a URL for the site ' . $employee->siteId . '.');
        }

        $site = Craft::$app->getSites()->getSiteById($employee->siteId);

        if (!$site) {
            throw new ServerErrorHttpException('Invalid site ID: ' . $employee->siteId);
        }

        Craft::$app->language = $site->language;

        // Have this employee override any freshly queried employees with the same ID/site
        Craft::$app->getElements()->setPlaceholderElement($employee);

        $this->getView()->getTwig()->disableStrictVariables();

        return $this->renderTemplate($siteSettings[$employee->siteId]->template, [
            'employee' => $employee
        ]);
    }

    private function _prepareVariableArray(&$variables)
    {
        // Locale related checks
        if (Craft::$app->getIsMultiSite()) {
            // Only use the sites that the user has access to
            $variables['siteIds'] = Craft::$app->getSites()->getEditableSiteIds();
        } else {
            $variables['siteIds'] = [Craft::$app->getSites()->getPrimarySite()->id];
        }

        if (!$variables['siteIds']) {
            throw new ForbiddenHttpException('User not permitted to edit content in any sites supported by this section');
        }

        if (empty($variables['site'])) {
            $site = $variables['site'] = Craft::$app->getSites()->currentSite;

            if (!in_array($variables['site']->id, $variables['siteIds'], false)) {
                $site = $variables['site'] = Craft::$app->getSites()->getSiteById($variables['siteIds'][0]);
            }
        } else {
            // Make sure they were requesting a valid site
            /** @var Site $site */
            $site = $variables['site'];
            if (!in_array($site->id, $variables['siteIds'], false)) {
                throw new ForbiddenHttpException('User not permitted to edit content in this site');
            }
        }

        // Employee related checks
        if (empty($variables['employee'])) {
            if (!empty($variables['employeeId'])) {
                $variables['employee'] = Craft::$app->getElements()->getElementById($variables['employeeId'], Employee::class, $site->id);

                if (!$variables['employee']) {
                    throw new Exception('Missing employee data.');
                }
            } else {
                $variables['employee'] = new Employee();
                $variables['employee']->businessId = $variables['business']->id;

                if (!empty($variables['siteId'])) {
                    $variables['employee']->site = $variables['siteId'];
                }
            }
        }

        // Enable locales
        if ($variables['employee']->id) {
            $variables['enabledSiteIds'] = Craft::$app->getElements()->getEnabledSiteIdsForElement($variables['employee']->id);
        } else {
            $variables['enabledSiteIds'] = [];

            foreach (Craft::$app->getSites()->getEditableSiteIds() as $site) {
                $variables['enabledSiteIds'][] = $site;
            }
        }
    }

    private function _maybeEnableLivePreview(array &$variables)
    {
        if (!Craft::$app->getRequest()->isMobileBrowser(true) && BusinessToBusiness::$plugin->business->isBusinessTemplateValid($variables['business'], $variables['site']->id)) {
            $this->getView()->registerJs('Craft.LivePreview.init('.Json::encode([
                    'fields' => '#title-field, #fields > div > div > .field',
                    'extraFields' => '#meta-pane',
                    'previewUrl' => $variables['employee']->getUrl(),
                    'previewAction' => 'business-to-business/employees/preview-employee',
                    'previewParams' => [
                        'businessId' => $variables['business']->id,
                        'employeeId' => $variables['employee']->id,
                        'siteId' => $variables['employee']->siteId,
                    ]
                ]).');');

            // $variables['showPreviewBtn'] = true;

            // // Should we show the Share button too?
            // if ($variables['employee']->id) {
            //     // If the employee is enabled, use its main URL as its share URL.
            //     if ($variables['employee']->getStatus() === Employee::STATUS_LIVE) {
            //         $variables['shareUrl'] = $variables['employee']->getUrl();
            //     } else {
            //         $variables['shareUrl'] = UrlHelper::actionUrl('business-to-business/employees/share-employee', [
            //             'employeeId' => $variables['employee']->id,
            //             'siteId' => $variables['employee']->siteId
            //         ]);
            //     }
            // }
        } else {
            $variables['showPreviewBtn'] = false;
        }
    }

    private function _setEmployeeFromPost($employeeId): Employee
    {
        
        $request = Craft::$app->getRequest();
        // $employeeId = $request->getBodyParam('employeeId');

        if ($employeeId) {
            $employee = BusinessToBusiness::$plugin->employee->getEmployeeById($employeeId);
            if (!$employee) {
                throw new Exception(Craft::t('business-to-business', 'No employee with the ID “{id}”', ['id' => $employeeId]));
            }
        } else {
            
            $employee = new Employee();
            $user = Craft::$app->getUser();
            $employee->userId = $user->id;
        }
    

        $employee->firstName = $request->getBodyParam('firstName');
        $employee->lastName = $request->getBodyParam('lastName');
        $employee->email = $request->getBodyParam('email');
        $employee->phone = $request->getBodyParam('phone');
        

        return $employee;
    }

    function _setOrderStatus(){

    }
}
