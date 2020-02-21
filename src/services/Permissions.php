<?php

/**
 * @link      http://importantcoding.com
 * @copyright Copyright (c) 2019 Darryl Hardin
 */
 */

namespace importantcoding\businesstobusiness\services;
use importantcoding\businesstobusiness\BusinessToBusiness;

use Craft;
use craft\base\Plugin;
use craft\base\UtilityInterface;
use craft\base\Volume;
use craft\db\Query;
use craft\db\Table;
use craft\elements\User;
use craft\errors\WrongEditionException;
use craft\events\ConfigEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\helpers\ProjectConfig as ProjectConfigHelper;
use craft\models\CategoryGroup;
use craft\models\Section;
use craft\models\UserGroup;
use craft\records\UserPermission as UserPermissionRecord;
use yii\base\Component;
use yii\db\Exception;

/**
 * User Permissions service.
 * An instance of the User Permissions service is globally accessible in Craft via [[\craft\base\ApplicationTrait::getUserPermissions()|`Craft::$app->userPermissions`]].
 * @author    Darryl Hardin
 * @package   BusinessToBusiness
 * @since     1.0.0
 */
class Permissions extends Component
{

    public function businessPermissions($event){
        $businesses = $this->getBusiness()->getAllBusinesses();
            
        $businessPermissions = [];

        foreach ($businesses as $id => $business) {
            $suffix = ':' . $id;
            $businessPermissions['businessToBusiness-manageBusiness' . $suffix] = ['label' => Craft::t('business-to-business', 'Manage {type}', ['type' => $business->id])];
        }

        $vouchers = BusinessToBusiness::$plugin->voucher->getAllVouchers();
        $voucherPermissions = [];

        foreach ($vouchers as $id => $voucher) {
            $suffix = ';' . $id;
            $voucherPermissions['businessToBusiness-manageBusiness' . $suffix] = ['label' => Craft::t('business-to-business', 'Mmanage {type}', ['type' => $voucher->id])];
        }



        $event->permissions[Craft::t('business-to-business', 'Business To Business')] = [
            'businessToBusiness-manageBusiness' => ['label' => Craft::t('business-to-business', 'Manage businesses')],
            'businessToBusiness-manageVouchers' => ['label' => Craft::t('business-to-business', 'Manage vouchers'), 'nested' => $businessPermissions],
            $voucherPermissions,
        ];

        $event->permissions[Craft::t('test', 'Test')] = [
            'businessToBusiness-manageBusiness' => ['label' => Craft::t('business-to-business', 'Manage businesses')],
            'businessToBusiness-manageVouchers' => ['label' => Craft::t('business-to-business', 'Manage vouchers'), 'nested' => $businessPermissions],
            $voucherPermissions,
        ];
    }
}
