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

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\commerce\models\ShippingRule;
use importantcoding\businesstobusiness\models\ShippingRulesBusiness as ShippingRulesBusinessModel;
use importantcoding\businesstobusiness\records\ShippingRulesBusiness as ShippingRulesBusinessRecord;
use yii\web\HttpException;
use yii\base\Exception;
/**
 * ShippingRulesBusinesses Service
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
class ShippingRulesBusinesses extends Component
{   
    private $_shippingCategoriesById = [];
    private $_fetchedAllShippingBusinesses;
    private $_fetchedAllShippingRules;
    private $_ShippingRulesByBusinessId = [];
    private $_EnabledShippingRulesByBusinessId = [];
    /**
     * @var ShippingRule[]
     */
    private $_allShippingRules = [];
    // Public Methods
    // =========================================================================

    /**
     * This function can literally be anything you want, and you can have as many service
     * functions as you want
     *
     * From any other plugin file, call it like this:
     *
     *     BusinessToBusiness::$plugin->voucher->exampleService()
     *
     * @return mixed
     */
     // Public Methods
    // =========================================================================

    public function getEnabledShippingRulesByBusinessId(int $businessId)
    {
        if (isset($this->_EnabledShippingRulesByBusinessId[$businessId])) {
            return $this->_EnabledShippingRulesByBusinessId[$businessId];
        }

        $rows = $this->_createShippingRulesBusinessQuery()
            ->where(['businessId' => $businessId])
            ->andWhere(['condition' => 'allow'])
            ->all();
       
        $this->_EnabledShippingRulesByBusinessId[$businessId] = [];
        foreach ($rows as $row) {
            $this->_EnabledShippingRulesByBusinessId[$businessId][$row['id']] = new ShippingRulesBusinessModel($row);
        }
        
        return $this->_EnabledShippingRulesByBusinessId[$businessId];

    }

    public function getAllShippingRulesBusinesses(): array
    {
        if (!$this->_fetchedAllShippingBusinesses) {
            $results = $this->_createShippingRulesBusinessQuery()->all();

            foreach ($results as $result) {
                $shippingRules = new ShippingRulesBusinessModel($result);
                $this->_memoizeShippingRuleBusiness($shippingRules);
            }

            $this->_fetchedAllShippingBusinesses = true;
        }

        return $this->_shippingCategoriesById;
    }

    public function getAllShippingRules(): array
    {
        if (!$this->_fetchedAllShippingRules) {
            $this->_fetchedAllShippingRules = true;
            $rows = $this->_createShippingRulesBusinessQuery()->all();

            foreach ($rows as $row) {
                $this->_allShippingRules[$row['id']] = new ShippingRule($row);
            }
        }

        return $this->_allShippingRules;
    }
    
    public function getShippingRulesByBusinessId(int $businessId)
    {

        if (isset($this->_ShippingRulesByBusinessId[$businessId])) {
            return $this->_ShippingRulesByBusinessId[$businessId];
        }
        
        $rows = $this->_createShippingRulesBusinessQuery()
            ->where(['businessId' => $businessId])
            ->all();
       
        $this->_ShippingRulesByBusinessId[$businessId] = [];
        foreach ($rows as $row) {
            $this->_ShippingRulesByBusinessId[$businessId][$row['id']] = new ShippingRulesBusinessModel($row);
        }
        
        return $this->_ShippingRulesByBusinessId[$businessId];
    }

    public function getShippingRuleByBusinessId(int $businessId)
    {

        if (isset($this->_ShippingRulesByBusinessId[$businessId])) {
            return $this->_ShippingRulesByBusinessId[$businessId];
        }
        
        $rows = $this->_createShippingRulesBusinessQuery()
            ->where(['businessId' => $businessId])
            ->all();
       
        $this->_ShippingRulesByBusinessId[$businessId] = [];
        foreach ($rows as $row) {
            $this->_ShippingRulesByBusinessId[$businessId][$row['id']] = new ShippingRulesBusinessModel($row);
        }
        
        return $this->_ShippingRulesByBusinessId[$businessId];
    }

    public function getShippingRuleCategoriesByRuleId(int $ruleId): array
    {
        if (!isset($this->_shippingRuleCategoriesByRuleId[$ruleId])) {
            $rows = $this->_createShippingRuleCategoriesQuery()
                ->where(['shippingRuleId' => $ruleId])
                ->all();

            $this->_shippingRuleCategoriesByRuleId[$ruleId] = [];
            foreach ($rows as $row) {
                $this->_shippingRuleCategoriesByRuleId[$ruleId][$row['businessId']] = new ShippingRulesBusinessModel($row);
            }
        }

        return $this->_shippingRuleCategoriesByRuleId[$ruleId];
    }

    public function createShippingRuleBusiness(ShippingRulesBusinessModel $model, bool $runValidation = true): bool
    {
        if ($runValidation && !$model->validate()) {
            Craft::info('Shipping rule category not saved due to validation error.', __METHOD__);

            return false;
        }

        $record = new ShippingRulesBusinessRecord();

        $fields = [
            'id',
            'name',
            'shippingMethodId',
            'businessId',
            'condition'
        ];

        foreach ($fields as $field) {
            $record->$field = $model->$field;
        }

        // Save it!
        $record->save(false);

        // Now that we have a record ID, save it on the model
        $model->id = $record->id;

        return true;
    }

    public function checkExistingRule(int $businessId, int $shippingMethodId)
    {
        return (new Query())
            ->select([
                'id',
            ])
            ->from(['{{%businesstobusiness_shippingrules_business}}'])
            ->where(['businessId' => $businessId, 'shippingMethodId' => $shippingMethodId])
            ->one();
    }

    public function saveShippingRule(ShippingRulesBusinessModel $ShippingRule): bool
    {   

        
        $ShippingRuleExists = null;
        $isNewShippingRule = !$ShippingRule->id;
        if($isNewShippingRule)
        {
            $ShippingRuleExists = $this->checkExistingRule($ShippingRule->businessId, $ShippingRule->shippingMethodId);
        }

        if (!$isNewShippingRule) {
            $ShippingRuleRecord = ShippingRulesBusinessRecord::findOne($ShippingRule->id);

            if (!$ShippingRuleRecord) {
                throw new Exception("No gateway exists with the ID '{$ShippingRule->id}'");
            }

        } elseif($ShippingRuleExists) {
            $ShippingRuleRecord = ShippingRulesBusinessRecord::findOne($ShippingRuleExists['id']);
        } else {
            $ShippingRuleRecord = new ShippingRulesBusinessRecord();
        }
        
        $ShippingRuleRecord->shippingMethodId = $ShippingRule->shippingMethodId;
        $ShippingRuleRecord->name = $ShippingRule->name;
        $ShippingRuleRecord->businessId = $ShippingRule->businessId;
        $ShippingRuleRecord->voucherId = $ShippingRule->voucherId;
        $ShippingRuleRecord->condition = $ShippingRule->condition;

        
        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {


            // Save the voucher type
            $ShippingRuleRecord->save(false);

            // Now that we have a voucher type ID, save it on the model
            // if (!$ShippingRule->id) {
            //     $ShippingRule->id = $ShippingRuleRecord->id;
            // }

            // Might as well update our cache of the voucher type while we have it.
            // $this->_ShippingRuleById[$ShippingRule->id] = $ShippingRule;

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();

            throw $e;
        }

        return true;
    }


    // public function afterSaveSiteHandler(SiteEvent $event)
    // {
       
    // }

    public function deleteShippingRulesById(int $businessId): bool
    {
        $business = BusinessToBusiness::$plugin->business->getBusinessById($businessId);

        $criteria = ShippingRulesBusinessRecord::find();
        $criteria->businessId = $business->id;
        $criteria->status = null;
        $criteria->limit = null;
        $ShippingRules = $criteria->all();

        foreach ($ShippingRules as $ShippingRule) {
            $this->deleteShippingRule($ShippingRule);
        }

        return true; // need to fiture out what to actually return
    }


    public function deleteShippingRules(ShippingRulesBusinessRecord $ShippingRuleRecord): bool
    {
        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();
        try{
            $affectedRows = $ShippingRuleRecord->delete();

                if ($affectedRows) {
                    $transaction->commit();
                }

                return (bool)$affectedRows;
        } catch (\Throwable $e) {
            $transaction->rollBack();

            throw $e;
        }
    }

    private function _createShippingRulesBusinessQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
                'name',
                'shippingMethodId',
                'businessId',
                'voucherId',
                'condition'
            ])
            ->from(['{{%businesstobusiness_shippingrules_business}}']);
    }

    private function _createShippingRulesQuery(): Query
    {
        $query = (new Query())
            ->select([
                'id',
                'name',
                'methodId',
            ])
            ->orderBy('name')
            ->from(["{{%commerce_shippingrules}}"]);

        return $query;
    }

    private function _memoizeShippingRuleBusiness(ShippingRulesBusinessModel $shippingRuleBusiness)
    {
        $this->_ShippingRuleBusinessesById[$shippingRuleBusiness->id] = $shippingRuleBusiness;
        $this->_ShippingRuleBusinessesByHandle[$shippingRuleBusiness->handle] = $shippingRuleBusiness;
    }
    
}

