<?php

namespace justinholtweb\lightning\services;

use Craft;
use craft\base\Component;
use justinholtweb\lightning\Plugin;

class PageSpeedService extends Component
{
    private const API_ENDPOINT = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

    /**
     * Run a PageSpeed Insights audit for a URL.
     *
     * @param string $url The URL to audit
     * @param string $strategy 'mobile' or 'desktop'
     * @return array Parsed API response
     */
    public function runAudit(string $url, string $strategy = 'mobile'): array
    {
        $apiKey = Craft::parseEnv(Plugin::getInstance()->getSettings()->apiKey);

        if (empty($apiKey)) {
            return ['error' => 'No API key configured. Go to Settings → Lightning to add your Google PageSpeed Insights API key.'];
        }

        $params = http_build_query([
            'url' => $url,
            'strategy' => $strategy,
            'category' => 'PERFORMANCE',
            'key' => $apiKey,
        ]);

        $apiUrl = self::API_ENDPOINT . '?' . $params;

        $context = stream_context_create([
            'http' => [
                'timeout' => 60,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($apiUrl, false, $context);

        if ($response === false) {
            return ['error' => 'Failed to connect to Google PageSpeed Insights API.'];
        }

        $data = json_decode($response, true);

        if (isset($data['error'])) {
            return ['error' => $data['error']['message'] ?? 'Unknown API error.'];
        }

        return $this->_parseResponse($data);
    }

    /**
     * Run audits for both mobile and desktop.
     */
    public function runFullAudit(string $url): array
    {
        return [
            'url' => $url,
            'mobile' => $this->runAudit($url, 'mobile'),
            'desktop' => $this->runAudit($url, 'desktop'),
        ];
    }

    /**
     * Parse the PSI API response into a clean structure.
     */
    private function _parseResponse(array $data): array
    {
        $lighthouse = $data['lighthouseResult'] ?? [];
        $audits = $lighthouse['audits'] ?? [];
        $categories = $lighthouse['categories'] ?? [];

        $performanceScore = isset($categories['performance']['score'])
            ? round($categories['performance']['score'] * 100)
            : null;

        // Core Web Vitals
        $metrics = [
            'fcp' => $this->_extractMetric($audits, 'first-contentful-paint'),
            'lcp' => $this->_extractMetric($audits, 'largest-contentful-paint'),
            'tbt' => $this->_extractMetric($audits, 'total-blocking-time'),
            'cls' => $this->_extractMetric($audits, 'cumulative-layout-shift'),
            'speedIndex' => $this->_extractMetric($audits, 'speed-index'),
            'si' => $this->_extractMetric($audits, 'speed-index'),
        ];

        // Optimization opportunities (savings > 100ms)
        $opportunities = [];
        foreach ($audits as $key => $audit) {
            $savings = $audit['details']['overallSavingsMs'] ?? 0;
            if ($savings > 100) {
                $opportunities[] = [
                    'id' => $key,
                    'title' => $audit['title'] ?? $key,
                    'description' => strip_tags($audit['description'] ?? ''),
                    'savingsMs' => round($savings),
                    'displayValue' => $audit['displayValue'] ?? null,
                    'score' => isset($audit['score']) ? round($audit['score'] * 100) : null,
                ];
            }
        }

        // Sort by savings descending
        usort($opportunities, fn($a, $b) => $b['savingsMs'] <=> $a['savingsMs']);

        // Diagnostics (informational audits with score < 1)
        $diagnostics = [];
        foreach ($audits as $key => $audit) {
            if (
                ($audit['details']['type'] ?? '') === 'table' &&
                ($audit['score'] ?? 1) < 0.9 &&
                !isset($audit['details']['overallSavingsMs'])
            ) {
                $diagnostics[] = [
                    'id' => $key,
                    'title' => $audit['title'] ?? $key,
                    'description' => strip_tags($audit['description'] ?? ''),
                    'displayValue' => $audit['displayValue'] ?? null,
                    'score' => isset($audit['score']) ? round($audit['score'] * 100) : null,
                ];
            }
        }

        return [
            'performanceScore' => $performanceScore,
            'metrics' => $metrics,
            'opportunities' => array_slice($opportunities, 0, 10),
            'diagnostics' => array_slice($diagnostics, 0, 10),
        ];
    }

    private function _extractMetric(array $audits, string $key): array
    {
        $audit = $audits[$key] ?? [];
        return [
            'displayValue' => $audit['displayValue'] ?? '—',
            'numericValue' => $audit['numericValue'] ?? null,
            'score' => isset($audit['score']) ? round($audit['score'] * 100) : null,
        ];
    }
}
