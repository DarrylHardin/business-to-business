<?php
/**
 * Business To Business plugin for Craft CMS 3.x
 *
 * Allow businesses to create vouchers for employees that allows the purchasing of products to be charged to the company at a later date.
 *
 * @link      http://importantcoding.com
 * @copyright Copyright (c) 2019 Darryl Hardin
 */

namespace importantcoding\businesstobusiness\models;

use importantcoding\businesstobusiness\BusinessToBusiness;

use Craft;
use craft\base\Model;
use craft\models\Site;

use yii\base\InvalidConfigException;

/**
 * BusinessSites Model
 *
 *
 * https://craftcms.com/docs/plugins/models
 *
 * @author    Darryl Hardin
 * @package   BusinessToBusiness
 * @since     1.0.0
 */
class BusinessSites extends Model
{
      // Properties
    // =========================================================================

    public $id;
    public $businessId;
    public $siteId;
    public $hasUrls;
    public $uriFormat;
    public $template;
    public $uriFormatIsRequired = true;

    private $_business;
    private $_site;


    // Public Methods
    // =========================================================================

    public function getBusiness(): Business
    {
        if ($this->_business !== null) {
            return $this->_business;
        }

        if (!$this->businessId) {
            throw new InvalidConfigException('Site is missing its business ID');
        }

        if (($this->_business = BusinessToBusiness::$plugin->business->getBusinessById($this->businessId)) === null) {
            throw new InvalidConfigException('Invalid business ID: ' . $this->businessId);
        }

        return $this->_business;
    }

    public function setBusiness(Business $business)
    {
        $this->_business = $business;
    }

    public function getSite(): Site
    {
        if (!$this->_site) {
            $this->_site = Craft::$app->getSites()->getSiteById($this->siteId);
        }
        
        return $this->_site;
    }

    public function rules(): array
    {
        $rules = parent::rules();

        if ($this->uriFormatIsRequired) {
            $rules[] = ['uriFormat', 'required'];
        }

        return $rules;
    }
}
