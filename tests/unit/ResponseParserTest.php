<?php

namespace justinholtweb\lightning\tests\unit;

use justinholtweb\lightning\helpers\ResponseParser;
use PHPUnit\Framework\TestCase;

class ResponseParserTest extends TestCase
{
    private array $fixture;

    protected function setUp(): void
    {
        $json = file_get_contents(__DIR__ . '/../_fixtures/psi-response.json');
        $this->fixture = json_decode($json, true);
    }

    public function testParsesPerformanceScoreAsRoundedInteger(): void
    {
        $result = ResponseParser::parse($this->fixture);

        // 0.86 * 100 = 86
        $this->assertSame(86, $result['performanceScore']);
    }

    public function testPerformanceScoreIsNullWhenMissing(): void
    {
        $result = ResponseParser::parse([]);

        $this->assertNull($result['performanceScore']);
    }

    public function testReturnsAllCoreWebVitalMetrics(): void
    {
        $metrics = ResponseParser::parse($this->fixture)['metrics'];

        $this->assertArrayHasKey('fcp', $metrics);
        $this->assertArrayHasKey('lcp', $metrics);
        $this->assertArrayHasKey('tbt', $metrics);
        $this->assertArrayHasKey('cls', $metrics);
        $this->assertArrayHasKey('si', $metrics);
        // `speedIndex` is retained alongside the `si` alias the JS consumes.
        $this->assertArrayHasKey('speedIndex', $metrics);
    }

    public function testExtractsMetricValueAndScore(): void
    {
        $lcp = ResponseParser::parse($this->fixture)['metrics']['lcp'];

        $this->assertSame('2.5 s', $lcp['displayValue']);
        $this->assertSame(2500.0, $lcp['numericValue']);
        $this->assertSame(62, $lcp['score']);
    }

    public function testMissingMetricFallsBackToDash(): void
    {
        $metric = ResponseParser::extractMetric([], 'first-contentful-paint');

        $this->assertSame('—', $metric['displayValue']);
        $this->assertNull($metric['numericValue']);
        $this->assertNull($metric['score']);
    }

    public function testOpportunitiesAreSortedBySavingsDescending(): void
    {
        $opportunities = ResponseParser::parse($this->fixture)['opportunities'];

        $this->assertCount(2, $opportunities);
        $this->assertSame('unused-css-rules', $opportunities[0]['id']);
        $this->assertSame('render-blocking-resources', $opportunities[1]['id']);
    }

    public function testOpportunitySavingsAreRoundedIntegers(): void
    {
        $opportunities = ResponseParser::parse($this->fixture)['opportunities'];

        // 1200.7 rounds to 1201
        $this->assertSame(1201, $opportunities[0]['savingsMs']);
        $this->assertIsInt($opportunities[0]['savingsMs']);
    }

    public function testOpportunitiesBelowThresholdAreExcluded(): void
    {
        $ids = array_column(ResponseParser::parse($this->fixture)['opportunities'], 'id');

        // 50ms savings is below the 100ms threshold.
        $this->assertNotContains('tiny-savings', $ids);
    }

    public function testOpportunityDescriptionIsStrippedOfHtml(): void
    {
        $opportunities = ResponseParser::parse($this->fixture)['opportunities'];
        $renderBlocking = $opportunities[1];

        $this->assertStringNotContainsString('[Learn more]', $renderBlocking['description']);
        $this->assertStringNotContainsString('<', $renderBlocking['description']);
    }

    public function testDiagnosticsIncludeFailingTableAudits(): void
    {
        $diagnostics = ResponseParser::parse($this->fixture)['diagnostics'];
        $ids = array_column($diagnostics, 'id');

        $this->assertContains('mainthread-work-breakdown', $ids);
    }

    public function testDiagnosticsExcludePassingAndOpportunityAudits(): void
    {
        $ids = array_column(ResponseParser::parse($this->fixture)['diagnostics'], 'id');

        $this->assertNotContains('passing-diagnostic', $ids); // score == 1
        $this->assertNotContains('unused-css-rules', $ids);   // has overallSavingsMs
    }

    public function testDiagnosticDescriptionIsStrippedOfHtml(): void
    {
        $diagnostics = ResponseParser::parse($this->fixture)['diagnostics'];
        $mainThread = $diagnostics[array_search('mainthread-work-breakdown', array_column($diagnostics, 'id'), true)];

        $this->assertStringNotContainsString('<b>', $mainThread['description']);
    }

    public function testHandlesEmptyResponseGracefully(): void
    {
        $result = ResponseParser::parse([]);

        $this->assertNull($result['performanceScore']);
        $this->assertSame([], $result['opportunities']);
        $this->assertSame([], $result['diagnostics']);
        $this->assertSame('—', $result['metrics']['fcp']['displayValue']);
    }

    public function testCapsOpportunitiesAtMaxItems(): void
    {
        $audits = [];
        for ($i = 0; $i < 15; $i++) {
            $audits["op-$i"] = [
                'title' => "Opportunity $i",
                'details' => ['type' => 'opportunity', 'overallSavingsMs' => 200 + $i],
            ];
        }

        $opportunities = ResponseParser::extractOpportunities($audits);

        $this->assertCount(ResponseParser::MAX_ITEMS, $opportunities);
    }
}
