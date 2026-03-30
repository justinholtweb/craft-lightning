<?php

namespace justinholtweb\lightning;

use Craft;
use craft\base\Element;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\elements\Entry;
use craft\events\DefineHtmlEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\services\Dashboard;
use craft\web\UrlManager;
use justinholtweb\lightning\models\Settings;
use justinholtweb\lightning\services\PageSpeedService;
use justinholtweb\lightning\widgets\PageSpeedWidget;
use yii\base\Event;

/**
 * Lightning — Google PageSpeed Insights for Craft CMS
 */
class Plugin extends BasePlugin
{
    public bool $hasCpSettings = true;
    public string $schemaVersion = '1.0.0';

    public static function config(): array
    {
        return [
            'components' => [
                'pageSpeed' => PageSpeedService::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        Craft::$app->onInit(function () {
            $this->_registerEventListeners();
        });
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate(
            'lightning/_settings/index',
            ['settings' => $this->getSettings()]
        );
    }

    private function _registerEventListeners(): void
    {
        // Register dashboard widget
        Event::on(
            Dashboard::class,
            Dashboard::EVENT_REGISTER_WIDGET_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = PageSpeedWidget::class;
            }
        );

        // Register CP routes for our API controller
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['lightning/api/run-audit'] = 'lightning/api/run-audit';
            }
        );

        // Add PageSpeed panel to entry sidebar for entries with URLs
        Event::on(
            Entry::class,
            Element::EVENT_DEFINE_SIDEBAR_HTML,
            function (DefineHtmlEvent $event) {
                /** @var Entry $entry */
                $entry = $event->sender;

                // Only show for entries that have a public URL
                if (!$entry->uri || $entry->uri === '__temp_' . $entry->uid) {
                    return;
                }

                try {
                    $url = $entry->getUrl();
                } catch (\Throwable) {
                    return;
                }

                if (!$url) {
                    return;
                }

                $event->html .= Craft::$app->getView()->renderTemplate(
                    'lightning/_sidebar/pagespeed',
                    [
                        'entry' => $entry,
                        'url' => $url,
                    ]
                );
            }
        );
    }
}
