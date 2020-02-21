<?php
namespace importantcoding\businesstobusiness\controllers;

use Craft;
use craft\web\Controller;

use importantcoding\businesstobusiness\BusinessToBusiness;

class BaseController extends Controller
{
    // Public Methods
    // =========================================================================

    public function actionSettings()
    {
        $settings = BusinessToBusiness::$plugin->getSettings();

        $this->renderTemplate('business-to-business/settings/default/settings', array(
            'settings' => $settings,
        ));
    }

}