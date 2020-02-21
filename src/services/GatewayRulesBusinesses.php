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
use importantcoding\businesstobusiness\elements\Employee as EmployeeElement;

use Craft;
use craft\base\Component;
use importantcoding\businesstobusiness\models\GatewayRulesBusiness as GatewayRulesBusinessModel;
use importantcoding\businesstobusiness\records\GatewayRulesBusiness as GatewayRulesRecord;
use yii\web\HttpException;
use yii\base\Exception;
use craft\db\Query;
/**
 * GatewayRulesBusinesses Service
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
class GatewayRulesBusinesses extends Component
{

    private $_gatewayCategoriesById = [];
    private $_fetchedAllGatewayBusinesses;
    private $_fetchedAllGatewayRules;
    private $_GatewayRulesByBusinessId = [];
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

    public function getGatewayRulesByBusinessId(int $businessId)
    {

        if (isset($this->_GatewayRulesByBusinessId[$businessId])) {
            return $this->_GatewayRulesByBusinessId[$businessId];
        }
        
        $rows = $this->_createGatewayRulesBusinessQuery()
            ->where(['businessId' => $businessId])
            ->all();
       
        $this->_GatewayRulesByBusinessId[$businessId] = [];
        foreach ($rows as $row) {
            $this->_GatewayRulesByBusinessId[$businessId][$row['id']] = new GatewayRulesBusinessModel($row);
        }
        
        return $this->_GatewayRulesByBusinessId[$businessId];
    }

    public function checkExistingRule(int $businessId, int $gatewayId)
    {
        return (new Query())
            ->select([
                'id',
            ])
            ->from(['{{%businesstobusiness_gatewayrules_business}}'])
            ->where(['businessId' => $businessId, 'gatewayId' => $gatewayId])
            ->one();
    }

    public function saveGatewayRule(GatewayRulesBusinessModel $GatewayRule): bool
    {
        $GatewayRuleExists = null;
        $isNewGatewayRule = !$GatewayRule->id;
        if($isNewGatewayRule)
        {
            $GatewayRuleExists = $this->checkExistingRule($GatewayRule->businessId, $GatewayRule->gatewayId);
        }

        if (!$isNewGatewayRule) {
            $GatewayRuleRecord = GatewayRulesRecord::findOne($GatewayRule->id);

            if (!$GatewayRuleRecord) {
                throw new Exception("No gateway exists with the ID '{$GatewayRule->id}'");
            }

        } elseif($GatewayRuleExists) {
            $GatewayRuleRecord = GatewayRulesRecord::findOne($GatewayRuleExists['id']);
        } else {
            $GatewayRuleRecord = new GatewayRulesRecord();
            
        }
        
        $GatewayRuleRecord->name = $GatewayRule->name;
        $GatewayRuleRecord->businessId = $GatewayRule->businessId;
        $GatewayRuleRecord->voucherId = $GatewayRule->voucherId;
        $GatewayRuleRecord->gatewayId = $GatewayRule->gatewayId;
        $GatewayRuleRecord->orderStatusId = $GatewayRule->orderStatusId;
        $GatewayRuleRecord->condition = $GatewayRule->condition;
        

        
        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {


            // Save the voucher type
            $GatewayRuleRecord->save(false);

            // Now that we have a voucher type ID, save it on the model
            if (!$GatewayRule->id) {
                $GatewayRule->id = $GatewayRuleRecord->id;
            }

            // Might as well update our cache of the voucher type while we have it.
            // $this->_gatewayRuleById[$gatewayRule->id] = $gatewayRule;

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

    public function deleteGatewayRulesById(int $businessId): bool
    {
        $business = BusinessToBusiness::$plugin->business->getBusinessById($businessId);

        $criteria = GatewayRulesRecord::find();
        $criteria->businessId = $business->id;
        $criteria->status = null;
        $criteria->limit = null;
        $gatewayRules = $criteria->all();

        foreach ($gatewayRules as $gatewayRule) {
            $this->deleteGatewayRule($gatewayRule);
        }

        return true; // need to fiture out what to actually return
    }


    public function deleteGatewayRules(GatewayRulesRecord $gatewayRuleRecord): bool
    {
        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();
        try{
            $affectedRows = $gatewayRuleRecord->delete();

                if ($affectedRows) {
                    $transaction->commit();
                }

                return (bool)$affectedRows;
        } catch (\Throwable $e) {
            $transaction->rollBack();

            throw $e;
        }
    }

    private function _createGatewayRulesBusinessQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
                'name',
                'gatewayId',
                'businessId',
                'voucherId',
                'orderStatusId',
                'condition'
            ])
            ->from(['{{%businesstobusiness_gatewayrules_business}}']);
    }
    
}

