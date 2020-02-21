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
use importantcoding\businesstobusiness\models\DefaultRules as DefaultRulesModel;
use importantcoding\businesstobusiness\records\DefaultRules as DefaultRulesRecord;

use yii\web\HttpException;
use yii\base\Exception;
use craft\db\Query;
/**
 * DefaultRules Service
 *
 * @author    Darryl Hardin
 * @package   BusinessToBusiness
 * @since     1.0.0
 */
class DefaultRules extends Component
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

    public function saveRules(DefaultRulesModel $DefaultRules): bool
    {
        $DefaultRulesExists = null;
        $isNewDefaultRules = !$DefaultRules->id;
        if($isNewDefaultRules)
        {
            $DefaultRulesExists = $this->checkExistingRule($DefaultRules->businessId, $DefaultRules->gatewayId);
        }

        if (!$isNewDefaultRules) {
            $DefaultRulesRecord = DefaultRulesRecord::findOne($DefaultRules->id);

            if (!$DefaultRulesRecord) {
                throw new Exception("No gateway exists with the ID '{$DefaultRules->id}'");
            }

        } elseif($DefaultRulesExists) {
            $DefaultRulesRecord = DefaultRulesRecord::findOne($DefaultRulesExists['id']);
        } else {
            $DefaultRulesRecord = new DefaultRulesRecord();
            
        }
        
        $DefaultRulesRecord->name = $DefaultRules->name;
        $DefaultRulesRecord->businessId = $DefaultRules->businessId;
        $DefaultRulesRecord->voucherId = $DefaultRules->voucherId;
        $DefaultRulesRecord->gatewayId = $DefaultRules->gatewayId;
        $DefaultRulesRecord->orderStatusId = $DefaultRules->orderStatusId;
        $DefaultRulesRecord->condition = $DefaultRules->condition;
        

        
        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {


            // Save the voucher type
            $DefaultRulesRecord->save(false);

            // Now that we have a voucher type ID, save it on the model
            if (!$DefaultRules->id) {
                $DefaultRules->id = $DefaultRulesRecord->id;
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

