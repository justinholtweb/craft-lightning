<?php

namespace justinholtweb\lightning\tests\integration;

use Craft;
use craft\test\TestCase;
use craft\web\View;
use justinholtweb\lightning\widgets\PageSpeedWidget;

class PageSpeedWidgetTest extends TestCase
{
    public function testDisplayNameAndIcon(): void
    {
        $this->assertSame('PageSpeed Insights', PageSpeedWidget::displayName());
        $this->assertSame('gauge', PageSpeedWidget::icon());
        $this->assertSame(3, PageSpeedWidget::maxColspan());
    }

    public function testDefaults(): void
    {
        $widget = new PageSpeedWidget();

        $this->assertSame('', $widget->auditUrl);
        $this->assertSame('both', $widget->strategy);
    }

    public function testTitleFallsBackToTheWidgetNameWhenNoUrlIsSet(): void
    {
        $this->assertSame('PageSpeed Insights', (new PageSpeedWidget())->getTitle());
    }

    public function testTitleUsesTheAuditUrl(): void
    {
        $widget = new PageSpeedWidget(['auditUrl' => 'https://example.com/pricing']);

        $this->assertSame('https://example.com/pricing', $widget->getTitle());
    }

    public function testAnEmptyAuditUrlIsValid(): void
    {
        $widget = new PageSpeedWidget();

        $this->assertTrue($widget->validate(), print_r($widget->getErrors(), true));
    }

    public function testAValidAuditUrlPassesValidation(): void
    {
        $widget = new PageSpeedWidget(['auditUrl' => 'https://example.com/page']);

        $this->assertTrue($widget->validate(), print_r($widget->getErrors(), true));
    }

    public function testAnInvalidAuditUrlFailsValidation(): void
    {
        $widget = new PageSpeedWidget(['auditUrl' => 'not a url']);

        $this->assertFalse($widget->validate());
        $this->assertArrayHasKey('auditUrl', $widget->getErrors());
    }

    public function testASchemelessUrlIsNormalizedToHttps(): void
    {
        $widget = new PageSpeedWidget(['auditUrl' => 'example.com']);

        $this->assertTrue($widget->validate(), print_r($widget->getErrors(), true));
        $this->assertSame('https://example.com', $widget->auditUrl);
    }

    public function testAnUnknownStrategyFailsValidation(): void
    {
        $widget = new PageSpeedWidget(['strategy' => 'tablet']);

        $this->assertFalse($widget->validate());
        $this->assertArrayHasKey('strategy', $widget->getErrors());
    }

    public function testAnEmptyStrategyFailsValidation(): void
    {
        $widget = new PageSpeedWidget(['strategy' => '']);

        $this->assertFalse($widget->validate());
        $this->assertArrayHasKey('strategy', $widget->getErrors());
    }

    public function testBodyHtmlRenders(): void
    {
        Craft::$app->getView()->setTemplateMode(View::TEMPLATE_MODE_CP);

        $widget = new PageSpeedWidget(['auditUrl' => 'https://example.com', 'strategy' => 'mobile']);
        $widget->id = 42;

        $html = $widget->getBodyHtml();

        $this->assertNotEmpty($html);
        $this->assertStringContainsString('https://example.com', $html);
    }

    public function testSettingsHtmlRenders(): void
    {
        Craft::$app->getView()->setTemplateMode(View::TEMPLATE_MODE_CP);

        $html = (new PageSpeedWidget())->getSettingsHtml();

        $this->assertNotEmpty($html);
        $this->assertStringContainsString('auditUrl', $html);
    }
}
