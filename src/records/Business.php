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

use Craft;
use craft\db\ActiveRecord;
use craft\records\FieldLayout;

use yii\db\ActiveQueryInterface;
/**
 * Business Record
 *
 *
 * @author    Darryl Hardin
 * @package   BusinessToBusiness
 * @since     1.0.0
 */
class Business extends ActiveRecord
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
        return '{{%businesstobusiness_business}}';
    }

    public function getFieldLayout(): ActiveQueryInterface
    {
        return $this->hasOne(FieldLayout::class, ['id' => 'fieldLayoutId']);
    }
}
