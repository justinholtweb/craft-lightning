<?php

namespace justinholtweb\lightning\widgets;

use Craft;
use craft\base\Widget;
use justinholtweb\lightning\assetbundles\LightningAsset;

class PageSpeedWidget extends Widget
{
    /** URL to audit (user-configurable per widget) */
    public string $auditUrl = '';

    /** Strategy: 'mobile', 'desktop', or 'both' */
    public string $strategy = 'both';

    public static function displayName(): string
    {
        return Craft::t('lightning', 'PageSpeed Insights');
    }

    public static function icon(): ?string
    {
        return 'gauge';
    }

    public static function maxColspan(): ?int
    {
        return 3;
    }

    public function getTitle(): ?string
    {
        $label = $this->auditUrl ?: 'PageSpeed Insights';
        return Craft::t('lightning', $label);
    }

    public function getBodyHtml(): ?string
    {
        Craft::$app->getView()->registerAssetBundle(LightningAsset::class);

        return Craft::$app->getView()->renderTemplate(
            'lightning/_widgets/pagespeed',
            [
                'widget' => $this,
                'widgetId' => $this->id,
            ]
        );
    }

    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate(
            'lightning/_widgets/pagespeed-settings',
            ['widget' => $this]
        );
    }

    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['auditUrl'], 'url', 'defaultScheme' => 'https'];
        $rules[] = [['strategy'], 'in', 'range' => ['mobile', 'desktop', 'both'], 'skipOnEmpty' => false];
        return $rules;
    }
}
