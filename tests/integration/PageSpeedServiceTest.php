<?php

namespace justinholtweb\lightning\tests\integration;

use craft\test\TestCase;
use justinholtweb\lightning\Plugin;

class PageSpeedServiceTest extends TestCase
{
    private StubPageSpeedService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Plugin::getInstance()->setSettings(['apiKey' => 'test-api-key']);
        $this->service = new StubPageSpeedService();
    }

    protected function tearDown(): void
    {
        putenv('PAGESPEED_API_KEY');
        parent::tearDown();
    }

    private function fixture(): string
    {
        return file_get_contents(dirname(__DIR__) . '/_fixtures/psi-response.json');
    }

    public function testReturnsAnErrorWhenNoApiKeyIsConfigured(): void
    {
        Plugin::getInstance()->setSettings(['apiKey' => '']);

        $result = $this->service->runAudit('https://example.com');

        $this->assertStringContainsString('No API key configured', $result['error']);
        $this->assertSame([], $this->service->requestedUrls, 'No request should be made without a key.');
    }

    public function testResolvesTheApiKeyFromAnEnvironmentVariable(): void
    {
        putenv('PAGESPEED_API_KEY=key-from-env');
        Plugin::getInstance()->setSettings(['apiKey' => '$PAGESPEED_API_KEY']);
        $this->service->responses = [$this->fixture()];

        $this->service->runAudit('https://example.com');

        $this->assertStringContainsString('key=key-from-env', $this->service->requestedUrls[0]);
    }

    public function testTreatsAnUnresolvedEnvironmentVariableAsAMissingKey(): void
    {
        Plugin::getInstance()->setSettings(['apiKey' => '$PAGESPEED_API_KEY']);

        $result = $this->service->runAudit('https://example.com');

        $this->assertStringContainsString('No API key configured', $result['error']);
        $this->assertSame([], $this->service->requestedUrls);
    }

    public function testBuildsTheRequestUrlFromTheAuditParameters(): void
    {
        $this->service->responses = [$this->fixture()];

        $this->service->runAudit('https://example.com/page?a=b', 'desktop');

        $url = $this->service->requestedUrls[0];

        $this->assertStringStartsWith(
            'https://www.googleapis.com/pagespeedonline/v5/runPagespeed?',
            $url
        );
        $this->assertStringContainsString('url=' . urlencode('https://example.com/page?a=b'), $url);
        $this->assertStringContainsString('strategy=desktop', $url);
        $this->assertStringContainsString('category=PERFORMANCE', $url);
        $this->assertStringContainsString('key=test-api-key', $url);
    }

    public function testDefaultsToTheMobileStrategy(): void
    {
        $this->service->responses = [$this->fixture()];

        $this->service->runAudit('https://example.com');

        $this->assertStringContainsString('strategy=mobile', $this->service->requestedUrls[0]);
    }

    public function testParsesASuccessfulResponse(): void
    {
        $this->service->responses = [$this->fixture()];

        $result = $this->service->runAudit('https://example.com');

        $this->assertArrayNotHasKey('error', $result);
        $this->assertSame(86, $result['performanceScore']);
        $this->assertSame('2.5 s', $result['metrics']['lcp']['displayValue']);
        $this->assertNotEmpty($result['opportunities']);
    }

    public function testReturnsAnErrorWhenTheRequestFails(): void
    {
        $this->service->responses = [false];

        $result = $this->service->runAudit('https://example.com');

        $this->assertSame('Failed to connect to Google PageSpeed Insights API.', $result['error']);
    }

    public function testReturnsAnErrorForMalformedJson(): void
    {
        $this->service->responses = ['<html>502 Bad Gateway</html>'];

        $result = $this->service->runAudit('https://example.com');

        $this->assertStringContainsString('invalid response', $result['error']);
    }

    public function testReturnsAnErrorForANonArrayJsonResponse(): void
    {
        $this->service->responses = ['"just a string"'];

        $result = $this->service->runAudit('https://example.com');

        $this->assertStringContainsString('invalid response', $result['error']);
    }

    public function testSurfacesApiErrorMessages(): void
    {
        $this->service->responses = [json_encode([
            'error' => ['code' => 400, 'message' => 'API key not valid.'],
        ])];

        $result = $this->service->runAudit('https://example.com');

        $this->assertSame('API key not valid.', $result['error']);
    }

    public function testFallsBackToAGenericMessageForUnstructuredApiErrors(): void
    {
        $this->service->responses = [json_encode(['error' => ['code' => 500]])];

        $result = $this->service->runAudit('https://example.com');

        $this->assertSame('Unknown API error.', $result['error']);
    }

    public function testFullAuditRunsBothStrategies(): void
    {
        $this->service->responses = [$this->fixture()];

        $result = $this->service->runFullAudit('https://example.com');

        $this->assertSame('https://example.com', $result['url']);
        $this->assertSame(86, $result['mobile']['performanceScore']);
        $this->assertSame(86, $result['desktop']['performanceScore']);

        $this->assertCount(2, $this->service->requestedUrls);
        $this->assertStringContainsString('strategy=mobile', $this->service->requestedUrls[0]);
        $this->assertStringContainsString('strategy=desktop', $this->service->requestedUrls[1]);
    }

    public function testFullAuditPropagatesPerStrategyErrors(): void
    {
        $this->service->responses = [$this->fixture(), false];

        $result = $this->service->runFullAudit('https://example.com');

        $this->assertArrayNotHasKey('error', $result['mobile']);
        $this->assertArrayHasKey('error', $result['desktop']);
    }
}
