<?php

namespace justinholtweb\lightning\tests\integration;

use craft\test\TestCase;
use justinholtweb\lightning\models\Settings;

class SettingsTest extends TestCase
{
    public function testDefaults(): void
    {
        $settings = new Settings();

        $this->assertSame('', $settings->apiKey);
        $this->assertSame('both', $settings->defaultStrategy);
    }

    public function testApiKeyIsRequired(): void
    {
        $settings = new Settings();

        $this->assertFalse($settings->validate());
        $this->assertArrayHasKey('apiKey', $settings->getErrors());
        $this->assertSame(
            'A Google PageSpeed Insights API key is required.',
            $settings->getFirstError('apiKey')
        );
    }

    public function testValidSettingsPassValidation(): void
    {
        $settings = new Settings(['apiKey' => 'AIza-test-key', 'defaultStrategy' => 'mobile']);

        $this->assertTrue($settings->validate(), print_r($settings->getErrors(), true));
    }

    /**
     * @dataProvider strategyProvider
     */
    public function testStrategyMustBeAKnownValue(string $strategy, bool $expectedValid): void
    {
        $settings = new Settings(['apiKey' => 'key', 'defaultStrategy' => $strategy]);

        $this->assertSame($expectedValid, $settings->validate());
    }

    public static function strategyProvider(): array
    {
        return [
            'mobile' => ['mobile', true],
            'desktop' => ['desktop', true],
            'both' => ['both', true],
            'unknown' => ['tablet', false],
            'empty' => ['', false],
        ];
    }

    public function testApiKeyMayBeAnEnvironmentVariable(): void
    {
        $settings = new Settings(['apiKey' => '$PAGESPEED_API_KEY']);

        $this->assertTrue($settings->validate(), print_r($settings->getErrors(), true));
    }
}
