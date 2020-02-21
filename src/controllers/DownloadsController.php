<?php
/**
 *
 * @link      http://importantcoding.com
 * @copyright Copyright (c) 2019 Darryl Hardin
 */

namespace importantcoding\businesstobusiness\controllers;

use importantcoding\businesstobusiness\BusinessToBusiness;

use Craft;
use craft\web\Controller;
use yii\web\Response;
/**
 * Downloads Controller
 *
 *
 * @author    Darryl Hardin
 * @package   BusinessToBusiness
 * @since     1.0.0
 */
class DownloadsController extends Controller
{

    // Public Methods
    // =========================================================================

    /**
     *
     * @return mixed
     */
    public function actionBusiness(int $businessId = null): Response
    {
        $variables = [
            'businessId' => $businessId,
        ];

        return $this->renderTemplate('business-to-business/csv/_business', $variables);
    }

}
