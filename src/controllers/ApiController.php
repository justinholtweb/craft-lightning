<?php

namespace justinholtweb\lightning\controllers;

use Craft;
use craft\web\Controller;
use justinholtweb\lightning\Plugin;
use yii\web\Response;

class ApiController extends Controller
{
    protected array|bool|int $allowAnonymous = false;

    /**
     * POST lightning/api/run-audit
     * Accepts: { url: string, strategy: 'mobile'|'desktop'|'both' }
     */
    public function actionRunAudit(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $url = Craft::$app->getRequest()->getRequiredBodyParam('url');
        $strategy = Craft::$app->getRequest()->getBodyParam('strategy', 'both');

        $service = Plugin::getInstance()->pageSpeed;

        if ($strategy === 'both') {
            $result = $service->runFullAudit($url);
        } else {
            $result = [
                'url' => $url,
                $strategy => $service->runAudit($url, $strategy),
            ];
        }

        // Check for errors
        foreach (['mobile', 'desktop'] as $s) {
            if (isset($result[$s]['error'])) {
                return $this->asJson([
                    'success' => false,
                    'error' => $result[$s]['error'],
                ]);
            }
        }

        return $this->asJson([
            'success' => true,
            'data' => $result,
        ]);
    }
}
