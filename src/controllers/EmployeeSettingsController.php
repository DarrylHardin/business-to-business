<?php
/**
 *
 * @link      http://importantcoding.com
 * @copyright Copyright (c) 2019 Darryl Hardin
 */
namespace importantcoding\businesstobusiness\controllers;
use importantcoding\businesstobusiness\BusinessToBusiness;
use importantcoding\businesstobusiness\elements\Employee as EmployeeElement;
use importantcoding\businesstobusiness\services\Employee as EmployeeServices;

use Craft;
use craft\helpers\StringHelper;
use yii\web\Response;
use craft\web\Controller;
/**
 * Class Employee Settings Controller
 *
 * @author    Darryl Hardin
 * @package   BusinessToBusiness
 * @since     1.0.0
 */
class EmployeeSettingsController extends Controller
{
    // Public Methods
    // =========================================================================

    /**
     * @param array $variables
     * @return Response
     */
    public function actionEdit(array $variables = []): Response
    {
        $fieldLayout = Craft::$app->getFields()->getLayoutByType(EmployeeElement::class);
        $variables['fieldLayout'] = $fieldLayout;
        $variables['title'] = Craft::t('business-to-business', 'Employee Settings');

        return $this->renderTemplate('business-to-business/settings/employeesettings/_edit', $variables);
    }

    public function actionSave()
    {
        $this->requirePostRequest();

        $fieldLayout = Craft::$app->getFields()->assembleLayoutFromPost();
        $configData = [StringHelper::UUID() => $fieldLayout->getConfig()];

        Craft::$app->getProjectConfig()->set(EmployeeServices::CONFIG_FIELDLAYOUT_KEY, $configData);

        Craft::$app->getSession()->setNotice(Craft::t('app', 'Employee fields saved.'));
        return $this->redirectToPostedUrl();

        // $fieldLayout = \Craft::$app->getFields()->assembleLayoutFromPost();
        // $fieldLayout->type = EmployeeElement::class;
        // \Craft::$app->getFields()->saveLayout($fieldLayout);
    }
}