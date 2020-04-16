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
use importantcoding\businesstobusiness\elements\Voucher;
use importantcoding\businesstobusiness\records\ShippingRulesBusiness as ShippingRulesBusinessRecord;
use importantcoding\businesstobusiness\records\GatewayRulesBusiness as GatewayRulesBusinessRecord;
use importantcoding\businesstobusiness\models\Business as BusinessModel;
use importantcoding\businesstobusiness\models\BusinessSites as BusinessSitesModel;
use importantcoding\businesstobusiness\models\ShippingRulesBusiness as ShippingRulesBusinessModel;
use importantcoding\businesstobusiness\models\GatewayRulesBusiness as GatewayRulesBusinessModel;

use Craft;
use craft\base\Element;
use craft\commerce\plugin as Commerce;
use craft\commerce\elements\Product;
use craft\helpers\ArrayHelper;
use craft\helpers\DateTimeHelper;
use craft\helpers\Json;
use craft\helpers\Localization;
use craft\helpers\UrlHelper;
use craft\models\Site;
use craft\web\Controller;

use yii\base\Exception;
use yii\web\ForbiddenHttpException;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\ServerErrorHttpException;

/**
 * Voucher Controller
 *
 * @author    Darryl Hardin
 * @package   BusinessToBusiness
 * @since     1.0.0
 */
class VouchersController extends Controller
{

    public function init()
    {

        parent::init();
    }

    public function actionIndex(): Response
    {
        return $this->renderTemplate('business-to-business/vouchers/index');
    }

    public function actionEdit(string $businessHandle, int $voucherId = null, string $siteHandle = null, Voucher $voucher = null): Response
    {
        $business = null;

        $variables = [
            'businessHandle' => $businessHandle,
            'voucherId' => $voucherId,
            'voucher' => $voucher,
        ];

        // Make sure a correct business handle was passed so we can check permissions
        if ($businessHandle) {
            $business = BusinessToBusiness::$plugin->business->getBusinessByHandle($businessHandle);
        }

        if (!$business) {
            throw new Exception('The business was not found.');
        }

        
        $variables['shippingMethods'] = Commerce::getInstance()->getShippingMethods()->getAllShippingMethods();
        $variables['shippingRules'] = BusinessToBusiness::$plugin->shippingRulesBusinesses->getShippingRulesByBusinessId($business->id);
        if(!$variables['shippingRules'])
        {
            $shippingRules = [];
            foreach($variables['shippingMethods'] as $shippingMethod)
            {   
                $shippingRules[$shippingMethod->id] = new ShippingRulesBusinessModel();
                $shippingRules[$shippingMethod->id]->businessId = $business->id;
                $shippingRules[$shippingMethod->id]->name = $shippingMethod->name;
                $shippingRules[$shippingMethod->id]->shippingMethodId = $shippingMethod->id;
            }
            $variables['shippingRules'] = $shippingRules;
        }


        $variables['gateways'] = Commerce::getInstance()->getGateways()->getAllGateways();
        $variables['gatewayRules'] = BusinessToBusiness::$plugin->gatewayRulesBusinesses->getGatewayRulesByBusinessId($business->id);
        if(!$variables['gatewayRules'])
        {
            $gatewayRules = [];
            foreach($variables['gateways'] as $gateway)
            {   
                $gatewayRules[$gateway->id] = new GatewayRulesBusinessModel();
                $gatewayRules[$gateway->id]->businessId = $business->id;
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
        
        if(!$variables['voucher'])
        {
            if($variables['voucherId'])
            {
                
                $variables['voucher'] = BusinessToBusiness::$plugin->voucher->getVoucherById($variables['voucherId']);
    
                
            }
        }

        

        $this->requirePermission('businessToBusiness-manageBusiness:' . $business->id);

        $variables['business'] = $business;

        if ($siteHandle !== null) {
            $variables['site'] = Craft::$app->getSites()->getSiteByHandle($siteHandle);

            if (!$variables['site']) {
                throw new Exception('Invalid site handle: '.$siteHandle);
            }
        }
        // die($variables['site']->id);
        $this->_prepareVariableArray($variables);

        if (!empty($variables['voucher']->id)) {
            $variables['title'] = $variables['voucher']->title;
            // $productIds = JSON::decode($variables['voucher']->products);
            $site = $variables['site'];
            $variables['voucher']->siteId = $site->id;
            
            $variables['products'] = $variables['voucher']->getProducts($variables['site']->id);
            
            // foreach($productIds as $productId)
            // {
            //     $product = Commerce::getInstance()->getProducts()->getProductById($productId);
            //     ArrayHelper::append($variables['products'], $product);
            // }
            
            
            
            

        } else {
            $variables['title'] = Craft::t('business-to-business', 'Create a new voucher');
        }
// die($variables['site']->id);
        // Can't just use the entry's getCpEditUrl() because that might include the site handle when we don't want it
        $variables['baseCpEditUrl'] = 'business-to-business/vouchers/' . $variables['businessHandle'] . '/{id}';

        // Set the "Continue Editing" URL
        $variables['continueEditingUrl'] = $variables['baseCpEditUrl'] . (Craft::$app->getIsMultiSite() && Craft::$app->getSites()->currentSite->id !== $variables['site']->id ? '/' . $variables['site']->handle : '');
        // $variables['continueEditingUrl'] = $variables['baseCpEditUrl'] . (Craft::$app->getIsMultiSite() && Craft::$app->getSites()->currentSite->id !== $variables['site']->id ? '/' . $variables['site']->handle : '');

        $this->_maybeEnableLivePreview($variables);

        $variables['tabs'] = [];

        foreach ($variables['business']->getFieldLayout()->getTabs() as $index => $tab) {
            // Do any of the fields on this tab have errors?
            $hasErrors = false;

            if ($variables['voucher']->hasErrors()) {
                foreach ($tab->getFields() as $field) {
                    if ($hasErrors = $variables['voucher']->hasErrors($field->handle . '.*')) {
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
        $variables['productElementType'] = Product::class;
        // die($variables['site']->id);
        return $this->renderTemplate('business-to-business/vouchers/_edit', $variables);
    }

    public function actionDelete()
    {
        $this->requirePostRequest();

        $voucherId = Craft::$app->getRequest()->getRequiredParam('voucherId');
        $voucher = Voucher::findOne($voucherId);

        if (!$voucher) {
            throw new Exception(Craft::t('business-to-business', 'No voucher exists with the ID “{id}”.',['id' => $voucherId]));
        }

        $this->enforceVoucherPermissions($voucher);

        if (!Craft::$app->getElements()->deleteElement($voucher)) {
            if (Craft::$app->getRequest()->getAcceptsJson()) {
                $this->asJson(['success' => false]);
            }

            Craft::$app->getSession()->setError(Craft::t('business-to-business', 'Couldn’t delete voucher.'));
            Craft::$app->getUrlManager()->setRouteParams([
                'voucher' => $voucher
            ]);

            return null;
        }

        if (Craft::$app->getRequest()->getAcceptsJson()) {
            $this->asJson(['success' => true]);
        }

        Craft::$app->getSession()->setNotice(Craft::t('business-to-business', 'Voucher deleted.'));

        return $this->redirectToPostedUrl($voucher);
    }

    public function actionSave()
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();

        $voucher = $this->_setVoucherFromPost();

        $this->enforceVoucherPermissions($voucher);

        if ($voucher->enabled && $voucher->enabledForSite) {
            $voucher->setScenario(Element::SCENARIO_LIVE);
        }

        if (!Craft::$app->getElements()->saveElement($voucher)) {
            if ($request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'errors' => $voucher->getErrors(),
                ]);
            }

            Craft::$app->getSession()->setError(Craft::t('business-to-business', 'Couldn’t save voucher.'));

            // Send the category back to the template
            Craft::$app->getUrlManager()->setRouteParams([
                'voucher' => $voucher
            ]);

            return null;
        }

        if ($request->getAcceptsJson()) {
            return $this->asJson([
                'success' => true,
                'id' => $voucher->id,
                'title' => $voucher->title,
                'status' => $voucher->getStatus(),
                'url' => $voucher->getUrl(),
                'cpEditUrl' => $voucher->getCpEditUrl()
            ]);
        }

        Craft::$app->getSession()->setNotice(Craft::t('app', 'Voucher saved.'));

        return $this->redirectToPostedUrl($voucher);
    }

    public function actionPreviewVoucher(): Response
    {

        $this->requirePostRequest();

        $voucher = $this->_setVoucherFromPost();

        $this->enforceVoucherPermissions($voucher);

        return $this->_showVoucher($voucher);
    }

    public function actionShareVoucher($voucherId, $siteId): Response
    {
        $voucher = BusinessToBusiness::$plugin->voucher->getVoucherById($voucherId, $siteId);

        if (!$voucher) {
            throw new HttpException(404);
        }

        $this->enforceVoucherPermissions($voucher);

        if (!BusinessToBusiness::$plugin->business->isBusinessTemplateValid($voucher->getBusiness(), $voucher->siteId)) {
            throw new HttpException(404);
        }

        $this->requirePermission('businessToBusiness-manageBusiness:' . $voucher->businessId);

        // Create the token and redirect to the voucher URL with the token in place
        $token = Craft::$app->getTokens()->createToken([
            'business-to-business/vouchers/view-shared-voucher', ['voucherId' => $voucher->id, 'siteId' => $siteId]
        ]);

        $url = UrlHelper::urlWithToken($voucher->getUrl(), $token);

        return $this->redirect($url);
    }

    public function actionViewSharedVoucher($voucherId, $site = null)
    {
        $this->requireToken();

        $voucher = BusinessToBusiness::$plugin->voucher->getVoucherById($voucherId, $site);

        if (!$voucher) {
            throw new HttpException(404);
        }

        $this->_showVoucher($voucher);

        return null;
    }


    // Protected Methods
    // =========================================================================

    protected function enforceVoucherPermissions(Voucher $voucher)
    {
        if (!$voucher->getBusiness()) {
            Craft::error('Attempting to access a voucher that doesn’t have a type', __METHOD__);
            throw new HttpException(404);
        }

        $this->requirePermission('businessToBusiness-manageBusiness:' . $voucher->getBusiness()->id);
    }


    // Private Methods
    // =========================================================================

    private function _showVoucher(Voucher $voucher): Response
    {

        $business = $voucher->getBusiness();

        if (!$business) {
            throw new ServerErrorHttpException('Voucher business not found.');
        }

        $siteSettings = $business->getSiteSettings();

        if (!isset($siteSettings[$voucher->siteId]) || !$siteSettings[$voucher->siteId]->hasUrls) {
            throw new ServerErrorHttpException('The voucher ' . $voucher->id . ' doesn\'t have a URL for the site ' . $voucher->siteId . '.');
        }

        $site = Craft::$app->getSites()->getSiteById($voucher->siteId);

        if (!$site) {
            throw new ServerErrorHttpException('Invalid site ID: ' . $voucher->siteId);
        }

        Craft::$app->language = $site->language;

        // Have this voucher override any freshly queried vouchers with the same ID/site
        Craft::$app->getElements()->setPlaceholderElement($voucher);

        $this->getView()->getTwig()->disableStrictVariables();

        return $this->renderTemplate($siteSettings[$voucher->siteId]->template, [
            'voucher' => $voucher
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
                $variables['site'] = Craft::$app->getSites()->currentSite;

            if (!in_array($variables['site']->id, $variables['siteIds'], false)) {
                $variables['site'] = Craft::$app->getSites()->getSiteById($variables['siteIds'][0]);
            }
            $site = $variables['site'];
        } else {
            // Make sure they were requesting a valid site
            /** @var Site $site */
            $site = $variables['site'];
            if (!in_array($site->id, $variables['siteIds'], false)) {
                throw new ForbiddenHttpException('User not permitted to edit content in this site');
            }
        }
        if (empty($variables['site'])) {
            $variables['site'] = Craft::$app->getSites()->currentSite;

            if (!in_array($variables['site']->id, $variables['siteIds'], false)) {
                $variables['site'] = Craft::$app->getSites()->getSiteById($variables['siteIds'][0]);
            }

            $site = $variables['site'];
        } else {
            // Make sure they were requesting a valid site
            /** @var Site $site */
            $site = $variables['site'];
            if (!in_array($site->id, $variables['siteIds'], false)) {
                throw new ForbiddenHttpException('User not permitted to edit content in this site');
            }
        }

        // Voucher related checks
        if (empty($variables['voucher'])) {
            if (!empty($variables['voucherId'])) {
                $variables['voucher'] = Craft::$app->getElements()->getElementById($variables['voucherId'], Voucher::class, $site->id);

                if (!$variables['voucher']) {
                    throw new Exception('Missing voucher data.');
                }
            } else {
                $variables['voucher'] = new Voucher();
                $variables['voucher']->businessId = $variables['business']->id;

                if (!empty($variables['siteId'])) {
                    $variables['voucher']->site = $variables['siteId'];
                }
            }
        }

        // Enable locales
        if ($variables['voucher']->id) {
            $variables['enabledSiteIds'] = Craft::$app->getElements()->getEnabledSiteIdsForElement($variables['voucher']->id);
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
                    'previewUrl' => $variables['voucher']->getUrl(),
                    'previewAction' => 'business-to-business/vouchers/preview-voucher',
                    'previewParams' => [
                        'businessId' => $variables['business']->id,
                        'voucherId' => $variables['voucher']->id,
                        'siteId' => $variables['voucher']->siteId,
                    ]
                ]).');');

            $variables['showPreviewBtn'] = true;

            // Should we show the Share button too?
            if ($variables['voucher']->id) {
                // If the voucher is enabled, use its main URL as its share URL.
                if ($variables['voucher']->getStatus() === Voucher::STATUS_LIVE) {
                    $variables['shareUrl'] = $variables['voucher']->getUrl();
                } else {
                    $variables['shareUrl'] = UrlHelper::actionUrl('business-to-business/vouchers/share-voucher', [
                        'voucherId' => $variables['voucher']->id,
                        'siteId' => $variables['voucher']->siteId
                    ]);
                }
            }
        } else {
            $variables['showPreviewBtn'] = false;
        }
    }

    private function _setVoucherFromPost(): Voucher
    {
        $request = Craft::$app->getRequest();
        $voucherId = $request->getBodyParam('voucherId');
        $siteId = $request->getBodyParam('siteId');
        // die($siteId);

        if ($voucherId) {
            $voucher = BusinessToBusiness::$plugin->voucher->getVoucherById($voucherId, $siteId);
            if (!$voucher) {
                throw new Exception(Craft::t('business-to-business', 'No voucher with the ID “{id}”', ['id' => $voucherId]));
            }
        } else {
            $voucher = new Voucher();
        }

        $voucher->businessId = $request->getBodyParam('businessId');
        $voucher->siteId = $siteId ?? $voucher->siteId;
        $voucher->enabled = (bool)$request->getBodyParam('enabled');

        $voucher->amount = Localization::normalizeNumber($request->getBodyParam('amount'));
        // $voucher->sku = $request->getBodyParam('sku');
        $voucher->code = $request->getRequiredBodyParam('code');
        $voucher->productLimit = $request->getBodyParam('productLimit');
        $voucher->payrollDeduction = $request->getBodyParam('payrollDeduction');
        $voucher->products = $request->getBodyParam('products');
        
        // die(var_dump($voucher->products));
        //  = $products;
        
        // $voucher->customAmount = $request->getBodyParam('customAmount');

        // if ($voucher->customAmount) {
        //     $voucher->value = 0;
        // }

        if (($postDate = Craft::$app->getRequest()->getBodyParam('postDate')) !== null) {
            $voucher->postDate = DateTimeHelper::toDateTime($postDate) ?: null;
        }
        
        if (($expiryDate = Craft::$app->getRequest()->getBodyParam('expiryDate')) !== null) {
            $voucher->expiryDate = DateTimeHelper::toDateTime($expiryDate) ?: null;
        }

        // $voucher->shippingCategoryId = $request->getBodyParam('shippingCategoryId');
        // $voucher->slug = $request->getBodyParam('slug');

        $voucher->enabledForSite = (bool)$request->getBodyParam('enabledForSite', $voucher->enabledForSite);
        $voucher->title = $request->getBodyParam('title', $voucher->title);

        $voucher->setFieldValuesFromRequest('fields');

        // Last checks
        // if (empty($voucher->sku)) {
        //     $business = $voucher->getBusiness();
        //     $voucher->sku = Craft::$app->getView()->renderObjectTemplate($business->skuFormat, $voucher);
        // }

        return $voucher;
    }
}

