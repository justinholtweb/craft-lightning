<?php

namespace justinholtweb\lightning\tests\integration;

use Craft;
use craft\base\Element;
use craft\elements\Entry;
use craft\events\DefineHtmlEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\services\Dashboard;
use craft\test\TestCase;
use craft\web\UrlManager;
use craft\web\View;
use justinholtweb\lightning\models\Settings;
use justinholtweb\lightning\Plugin;
use justinholtweb\lightning\services\PageSpeedService;
use justinholtweb\lightning\widgets\PageSpeedWidget;
use yii\base\Event;

class PluginTest extends TestCase
{
    public function testPluginIsInstalledAndRegistered(): void
    {
        $plugin = Plugin::getInstance();

        $this->assertInstanceOf(Plugin::class, $plugin);
        $this->assertSame('lightning', $plugin->handle);
        $this->assertTrue($plugin->hasCpSettings);
    }

    public function testPageSpeedComponentIsRegistered(): void
    {
        $this->assertInstanceOf(PageSpeedService::class, Plugin::getInstance()->pageSpeed);
    }

    public function testSettingsModelIsUsed(): void
    {
        $this->assertInstanceOf(Settings::class, Plugin::getInstance()->getSettings());
    }

    public function testWidgetIsRegisteredWithTheDashboard(): void
    {
        $event = new RegisterComponentTypesEvent(['types' => []]);
        Event::trigger(Dashboard::class, Dashboard::EVENT_REGISTER_WIDGET_TYPES, $event);

        $this->assertContains(PageSpeedWidget::class, $event->types);
    }

    public function testAuditRouteIsRegisteredInTheControlPanel(): void
    {
        $event = new RegisterUrlRulesEvent(['rules' => []]);
        Event::trigger(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, $event);

        $this->assertSame(
            'lightning/api/run-audit',
            $event->rules['lightning/api/run-audit'] ?? null
        );
    }

    public function testSettingsTemplateRenders(): void
    {
        Craft::$app->getView()->setTemplateMode(View::TEMPLATE_MODE_CP);
        Plugin::getInstance()->setSettings(['apiKey' => 'abc123']);

        $html = $this->invokeMethod(Plugin::getInstance(), 'settingsHtml');

        $this->assertNotEmpty($html);
        $this->assertStringContainsString('apiKey', $html);
    }

    public function testSidebarPanelIsSkippedForEntriesWithoutAUri(): void
    {
        $event = new DefineHtmlEvent(['html' => '']);
        $event->sender = new Entry();

        Event::trigger(Entry::class, Element::EVENT_DEFINE_SIDEBAR_HTML, $event);

        $this->assertSame('', $event->html);
    }

    public function testSidebarPanelIsSkippedForUnsavedEntriesWithTempUris(): void
    {
        $entry = new Entry();
        $entry->uri = '__temp_' . $entry->uid;

        $event = new DefineHtmlEvent(['html' => '']);
        $event->sender = $entry;

        Event::trigger(Entry::class, Element::EVENT_DEFINE_SIDEBAR_HTML, $event);

        $this->assertSame('', $event->html);
    }

    public function testSidebarPanelIsRenderedForEntriesWithAPublicUrl(): void
    {
        Craft::$app->getView()->setTemplateMode(View::TEMPLATE_MODE_CP);

        $entry = new Entry();
        $entry->siteId = Craft::$app->getSites()->getPrimarySite()->id;
        $entry->uri = 'about';

        $event = new DefineHtmlEvent(['html' => '']);
        $event->sender = $entry;

        Event::trigger(Entry::class, Element::EVENT_DEFINE_SIDEBAR_HTML, $event);

        $this->assertNotSame('', $event->html);
        $this->assertStringContainsString($entry->getUrl(), $event->html);
    }
}
