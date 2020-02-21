<?php
/**
 * Business To Business plugin for Craft CMS 3.x
 *
 * Allow businesses to create vouchers for employees that allows the purchasing of products to be charged to the company at a later date.
 *
 * @link      http://importantcoding.com
 * @copyright Copyright (c) 2019 Darryl Hardin
 */

namespace importantcoding\businesstobusiness\records;

use importantcoding\businesstobusiness\BusinessToBusiness;
use importantcoding\businesstobusiness\records\Business as BusinessRecord;
use Craft;
use craft\db\ActiveRecord;
use craft\records\Site;

use yii\db\ActiveQueryInterface;

/**
 * BusinessSites Record
 *
 *
 * @author    Darryl Hardin
 * @package   BusinessToBusiness
 * @since     1.0.0
 */
class BusinessSites extends ActiveRecord
{
    // Public Static Methods
    // =========================================================================

     /**
      *
     *
     * @return string the table name
     */
    public static function tableName()
    {
        return '{{%businesstobusiness_business_sites}}';
    }

    public function getBusiness(): ActiveQueryInterface
    {
        return $this->hasOne(BusinessRecord::class, ['id', 'businessId']);
    }

    public function getSite(): ActiveQueryInterface
    {
        return $this->hasOne(Site::class, ['id', 'siteId']);
    }
}
