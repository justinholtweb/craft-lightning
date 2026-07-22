<?php

namespace justinholtweb\lightning\controllers;

use Craft;
use craft\web\Controller;
use justinholtweb\lightning\Plugin;
use yii\web\Response;

class ApiController extends Controller
{
    /** Strategies the PSI API accepts. */
    private const STRATEGIES = ['mobile', 'desktop', 'both'];

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

        if (!in_array($strategy, self::STRATEGIES, true)) {
            return $this->asJson([
                'success' => false,
                'error' => 'Invalid strategy. Expected one of: ' . implode(', ', self::STRATEGIES) . '.',
            ]);
        }

        // PSI only audits public HTTP(S) pages; anything else is a bad request
        // that would otherwise be forwarded to Google verbatim.
        if (!preg_match('/^https?:\/\//i', $url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return $this->asJson([
                'success' => false,
                'error' => 'Enter a valid http:// or https:// URL to audit.',
            ]);
        }

        $service = Plugin::getInstance()->pageSpeed;

        if ($strategy === 'both') {
            $result = $service->runFullAudit($url);
        } else {
            $result = [
                'url' => $url,
                $strategy => $service->runAudit($url, $strategy),
            ];
        }

        // Surface the first failing strategy, whichever ones were run.
        foreach ($result as $key => $audit) {
            if ($key !== 'url' && isset($audit['error'])) {
                return $this->asJson([
                    'success' => false,
                    'error' => $audit['error'],
                ]);
            }
        }

        return $this->asJson([
            'success' => true,
            'data' => $result,
        ]);
    }
}
