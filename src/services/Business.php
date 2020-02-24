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

use importantcoding\businesstobusiness\elements\Voucher;
// use importantcoding\businesstobusiness\events\BusinessEvent;
use importantcoding\businesstobusiness\models\Business as BusinessModel;
use importantcoding\businesstobusiness\models\BusinessSites as BusinessSitesModel;
use importantcoding\businesstobusiness\records\Business as BusinessRecord;
use importantcoding\businesstobusiness\records\BusinessSites as BusinessSitesRecord;
use importantcoding\businesstobusiness\models\DefaultRules as DefaultRulesModel;
use importantcoding\businesstobusiness\records\DefaultRules as DefaultRulesRecord;
use Craft;
use craft\db\Query;
use craft\events\SiteEvent;
use craft\helpers\App;
use craft\queue\jobs\ResaveElements;
use craft\helpers\ArrayHelper;

use yii\base\Component;
use yii\base\Exception;

/**
 * Business Service
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
class Business extends Component
{
 // Constants
    // =========================================================================

    const EVENT_BEFORE_SAVE_BUSINESS = 'beforeSaveBusiness';
    const EVENT_AFTER_SAVE_BUSINESS = 'afterSaveBusiness';


    // Properties
    // =========================================================================

    private $_fetchedAllBusiness = false;
    private $_businessById;
    private $_businessByHandle;
    private $_allBusinessIds;
    private $_editableBusinessIds;
    private $_siteSettingsByVoucherId = [];


    // Public Methods
    // =========================================================================

    public function getEditableBusiness(): array
    {
        $editableBusinessIds = $this->getEditableBusinessIds();
        $editableBusiness = [];

        foreach ($this->getAllBusinesses() as $business) {
            if (in_array($business->id, $editableBusinessIds, false)) {
                $editableBusiness[] = $business;
            }
        }

        return $editableBusiness;
    }

    public function getEditableBusinessIds(): array
    {
        if (null === $this->_editableBusinessIds) {
            $this->_editableBusinessIds = [];
            $allBusinessIds = $this->getAllBusinessIds();

            foreach ($allBusinessIds as $businessId) {
                if (Craft::$app->getUser()->checkPermission('businessToBusiness-manageBusiness:' . $businessId)) {
                    $this->_editableBusinessIds[] = $businessId;
                }
            }
        }

        return $this->_editableBusinessIds;
    }

    public function getAllBusinessIds(): array
    {
        if (null === $this->_allBusinessIds) {
            $this->_allBusinessIds = [];
            $business = $this->getAllBusinesses();

            foreach ($business as $business) {
                $this->_allBusinessIds[] = $business->id;
            }
        }

        return $this->_allBusinessIds;
    }

    public function getAllBusinesses(): array
    {
        if (!$this->_fetchedAllBusiness) {
            $results = $this->_createBusinessQuery()->all();

            foreach ($results as $result) {
                $this->_memoizeBusiness(new BusinessModel($result));
            }

            $this->_fetchedAllBusiness = true;
        }

        return $this->_businessById ?: [];
    }

    public function getBusinessByHandle($handle)
    {
        if (isset($this->_businessByHandle[$handle])) {
            return $this->_businessByHandle[$handle];
        }

        if ($this->_fetchedAllBusiness) {
            return null;
        }

        $result = $this->_createBusinessQuery()
            ->where(['handle' => $handle])
            ->one();

        if (!$result) {
            return null;
        }

        $this->_memoizeBusiness(new BusinessModel($result));

        return $this->_businessByHandle[$handle];
    }

    public function getBusinessSites($businessId): array
    {
        if (!isset($this->_siteSettingsByVoucherId[$businessId])) {
            $rows = (new Query())
                ->select([
                    'id',
                    'businessId',
                    'siteId',
                    'uriFormat',
                    'hasUrls',
                    'template'
                ])
                ->from('{{%businesstobusiness_business_sites}}')
                ->where(['businessId' => $businessId])
                ->all();

            $this->_siteSettingsByVoucherId[$businessId] = [];

            foreach ($rows as $row) {
                $this->_siteSettingsByVoucherId[$businessId][] = new BusinessSitesModel($row);
            }
        }

        return $this->_siteSettingsByVoucherId[$businessId];
    }

    // public function saveBusiness(BusinessModel $business, bool $runValidation = true): bool
    public function saveBusiness(BusinessModel $business): bool
    {
        $isNewBusiness = !$business->id;

        // // Fire a 'beforeSaveBusiness' event
        // if ($this->hasEventHandlers(self::EVENT_BEFORE_SAVE_BUSINESS)) {
        //     $this->trigger(self::EVENT_BEFORE_SAVE_BUSINESS, new BusinessEvent([
        //         'business' => $business,
        //         'isNew' => $isNewBusiness,
        //     ]));
        // }

        // if ($runValidation && !$business->validate()) {
        //     Craft::info('Business not saved due to validation error.', __METHOD__);

        //     return false;
        // }

        if (!$isNewBusiness) {
            $businessRecord = BusinessRecord::findOne($business->id);

            if (!$businessRecord) {
                throw new Exception("No business exists with the ID '{$business->id}'");
            }

        } else {
            $businessRecord = new BusinessRecord();
        }

        $businessRecord->name = $business->name;
        $businessRecord->handle = $business->handle;
        $businessRecord->autoVerify = $business->autoVerify;
        $businessRecord->passcode = $business->passcode;
        $businessRecord->discount = $business->discount;
        $businessRecord->managerId = $business->managerId;
        $businessRecord->taxExempt = $business->taxExempt;
        $businessRecord->limitShippingMethods = $business->limitShippingMethods;
        // Get the site settings
        $allSiteSettings = $business->getSiteSettings();

        // Make sure they're all there
        foreach (Craft::$app->getSites()->getAllSiteIds() as $siteId) {
            if (!isset($allSiteSettings[$siteId])) {
                throw new Exception('Tried to save a business that is missing site settings');
            }
        }

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            // Voucher Field Layout
            $fieldLayout = $business->getVoucherFieldLayout();
            Craft::$app->getFields()->saveLayout($fieldLayout);
            $business->fieldLayoutId = $fieldLayout->id;
            $businessRecord->fieldLayoutId = $fieldLayout->id;

            // Save the voucher type
            $businessRecord->save(false);

            // Now that we have a voucher type ID, save it on the model
            if (!$business->id) {
                $business->id = $businessRecord->id;
            }

            // Might as well update our cache of the voucher type while we have it.
            $this->_businessById[$business->id] = $business;

            // Update Gateway Rules 
            // ----------------------------------------------------------------
            
            // BusinessToBusiness::$plugin->gatewayRules->saveGatewayRule();

            // Update the site settings
            // -----------------------------------------------------------------

            $sitesNowWithoutUrls = [];
            $sitesWithNewUriFormats = [];
            $allOldSiteSettingsRecords = [];

            if (!$isNewBusiness) {
                // Get the old voucher type site settings
                $allOldSiteSettingsRecords = BusinessSitesRecord::find()
                    ->where(['businessId' => $business->id])
                    ->indexBy('siteId')
                    ->all();
            }

            

            foreach ($allSiteSettings as $siteId => $siteSettings) {
                // Was this already selected?
                if (!$isNewBusiness && isset($allOldSiteSettingsRecords[$siteId])) {
                    $siteSettingsRecord = $allOldSiteSettingsRecords[$siteId];
                } else {
                    $siteSettingsRecord = new BusinessSitesRecord();
                    $siteSettingsRecord->businessId = $business->id;
                    $siteSettingsRecord->siteId = $siteId;
                }

                $siteSettingsRecord->hasUrls = $siteSettings->hasUrls;
                $siteSettingsRecord->uriFormat = $siteSettings->uriFormat;
                $siteSettingsRecord->template = $siteSettings->template;

                if (!$siteSettingsRecord->getIsNewRecord()) {
                    // Did it used to have URLs, but not anymore?
                    if ($siteSettingsRecord->isAttributeChanged('hasUrls', false) && !$siteSettings->hasUrls) {
                        $sitesNowWithoutUrls[] = $siteId;
                    }

                    // Does it have URLs, and has its URI format changed?
                    if ($siteSettings->hasUrls && $siteSettingsRecord->isAttributeChanged('uriFormat', false)) {
                        $sitesWithNewUriFormats[] = $siteId;
                    }
                }

                $siteSettingsRecord->save(false);

                // Set the ID on the model
                $siteSettings->id = $siteSettingsRecord->id;
            }

            if (!$isNewBusiness) {
                // Drop any site settings that are no longer being used, as well as the associated voucher/element
                // site rows
                $siteIds = array_keys($allSiteSettings);

                foreach ($allOldSiteSettingsRecords as $siteId => $siteSettingsRecord) {
                    if (!in_array($siteId, $siteIds, false)) {
                        $siteSettingsRecord->delete();
                    }
                }
            }

            if (!$isNewBusiness) {
                foreach ($allSiteSettings as $siteId => $siteSettings) {
                    Craft::$app->getQueue()->push(new ResaveElements([
                        'description' => Craft::t('app', 'Resaving {type} vouchers ({site})', [
                            'type' => $business->name,
                            'site' => $siteSettings->getSite()->name,
                        ]),
                        'elementType' => Voucher::class,
                        'criteria' => [
                            'siteId' => $siteId,
                            'businessId' => $business->id,
                            'status' => null,
                            'enabledForSite' => false,
                        ]
                    ]));
                }
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();

            throw $e;
        }

        // // Fire an 'afterSaveBusiness' event
        // if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_BUSINESS)) {
        //     $this->trigger(self::EVENT_AFTER_SAVE_BUSINESS, new BusinessEvent([
        //         'business' => $business,
        //         'isNew' => $isNewBusiness,
        //     ]));
        // }

        return true;
    }

    public function deleteBusinessById(int $id): bool
    {
        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            $business = $this->getBusinessById($id);

            $criteria = Voucher::find();
            $criteria->businessId = $business->id;
            $criteria->status = null;
            $criteria->limit = null;
            $vouchers = $criteria->all();

            foreach ($vouchers as $voucher) {
                Craft::$app->getElements()->deleteElement($voucher);
            }

            $fieldLayoutId = $business->getVoucherFieldLayout()->id;
            Craft::$app->getFields()->deleteLayoutById($fieldLayoutId);

            $businessRecord = BusinessRecord::findOne($business->id);
            $affectedRows = $businessRecord->delete();

            if ($affectedRows) {
                $transaction->commit();
            }

            return (bool)$affectedRows;
        } catch (\Throwable $e) {
            $transaction->rollBack();

            throw $e;
        }
    }

    public function getBusinessById(int $businessId)
    {
        if (isset($this->_businessById[$businessId])) {
            return $this->_businessById[$businessId];
        }

        if ($this->_fetchedAllBusiness) {
            return null;
        }

        $result = $this->_createBusinessQuery()
            ->where(['id' => $businessId])
            ->one();

        if (!$result) {
            return null;
        }

        $this->_memoizeBusiness(new BusinessModel($result));

        return $this->_businessById[$businessId];
    }

    public function isBusinessTemplateValid(BusinessModel $business, int $siteId): bool
    {
        $businessSitesSettings = $business->getSiteSettings();

        if (isset($businessSitesSettings[$siteId]) && $businessSitesSettings[$siteId]->hasUrls) {
            // Set Craft to the site template mode
            $view = Craft::$app->getView();
            $oldTemplateMode = $view->getTemplateMode();
            $view->setTemplateMode($view::TEMPLATE_MODE_SITE);

            // Does the template exist?
            $templateExists = Craft::$app->getView()->doesTemplateExist((string)$businessSitesSettings[$siteId]->template);

            // Restore the original template mode
            $view->setTemplateMode($oldTemplateMode);

            if ($templateExists) {
                return true;
            }
        }

        return false;
    }

    public function afterSaveSiteHandler(SiteEvent $event)
    {
        if ($event->isNew) {
            $primarySiteSettings = (new Query())
                ->select(['businessId', 'uriFormat', 'template', 'hasUrls'])
                ->from(['{{%businesstobusiness_business_sites}}'])
                ->where(['siteId' => $event->oldPrimarySiteId])
                ->one();

            if ($primarySiteSettings) {
                $newSiteSettings = [];

                $newSiteSettings[] = [
                    $primarySiteSettings['businessId'],
                    $event->site->id,
                    $primarySiteSettings['uriFormat'],
                    $primarySiteSettings['template'],
                    $primarySiteSettings['hasUrls']
                ];

                Craft::$app->getDb()->createCommand()
                    ->batchInsert(
                        '{{%businesstobusiness_business_sites}}',
                        ['businessId', 'siteId', 'uriFormat', 'template', 'hasUrls'],
                        $newSiteSettings)
                    ->execute();
            }
        }
    }

    public function checkExistingRule(array $rules)
    {
        $gatewayId = $rules['gatewayId'];
        $shippingMethodId = $rules['shippingMethodId'];
        return (new Query())
            ->select([
                'id',
            ])
            ->from(['{{%businesstobusiness_defaultrules}}'])
            ->where(['gatewayId' => $gatewayId] OR ['shippingMethodId' => $shippingMethodId])
            ->one();
    }


    public function saveDefaultRule(DefaultRulesModel $DefaultRule): bool
    {
        $DefaultRuleExists = null;
        $isNewDefaultRule = !$DefaultRule->id;
        // $rules = ['shippingMethodId' => $DefaultRule->shippingMethodId, 'gatewayId' => $DefaultRule->gatewayId];

        if (!$isNewDefaultRule) {
            $DefaultRuleRecord = DefaultRulesRecord::findOne($DefaultRule->id);

            if (!$DefaultRuleRecord) {
                throw new Exception("No DefaultRule exists with the ID '{$DefaultRule->id}'");
            }

        } elseif($DefaultRuleExists) {
            $DefaultRuleRecord = DefaultRulesRecord::findOne($DefaultRuleExists['id']);
        } else {
            $DefaultRuleRecord = new DefaultRulesRecord();
        }
        
        $DefaultRuleRecord->name = $DefaultRule->name;
        $DefaultRuleRecord->shippingMethodId = $DefaultRule->shippingMethodId;
        $DefaultRuleRecord->gatewayId = $DefaultRule->gatewayId;
        $DefaultRuleRecord->orderStatusId = $DefaultRule->orderStatusId;
        $DefaultRuleRecord->condition = $DefaultRule->condition;
        

        
        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {


            // Save the voucher type
            $DefaultRuleRecord->save(false);

            // Now that we have a voucher type ID, save it on the model
            if (!$DefaultRule->id) {
                $DefaultRule->id = $DefaultRuleRecord->id;
            }

            // Might as well update our cache of the voucher type while we have it.
            // $this->_DefaultRuleById[$DefaultRule->id] = $DefaultRule;

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();

            throw $e;
        }

        return true;
    }
    

    // Private methods
    // =========================================================================

    private function _memoizeBusiness(BusinessModel $business)
    {
        $this->_businessById[$business->id] = $business;
        $this->_businessByHandle[$business->handle] = $business;
    }

    private function _createBusinessQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
                'fieldLayoutId',
                'name',
                'handle',
                'discount',
                'autoVerify',
                'passcode',
                'managerId',
                'taxExempt',
                'limitShippingMethods'
            ])
            ->from(['{{%businesstobusiness_business}}']);
    }
}