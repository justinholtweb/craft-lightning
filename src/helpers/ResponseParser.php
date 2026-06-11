<?php

namespace justinholtweb\lightning\helpers;

/**
 * Parses a raw Google PageSpeed Insights (v5) API response into the clean
 * structure consumed by Lightning's templates and JavaScript.
 *
 * This class has no Craft or Yii dependencies so the parsing logic can be
 * unit tested in isolation.
 */
class ResponseParser
{
    /** Only surface optimization opportunities saving more than this many ms. */
    public const MIN_OPPORTUNITY_SAVINGS_MS = 100;

    /** Maximum number of opportunities/diagnostics returned. */
    public const MAX_ITEMS = 10;

    /**
     * Parse the PSI API response into a clean structure.
     *
     * @param array $data The decoded PSI API response.
     * @return array{performanceScore: int|null, metrics: array, opportunities: array, diagnostics: array}
     */
    public static function parse(array $data): array
    {
        $lighthouse = $data['lighthouseResult'] ?? [];
        $audits = $lighthouse['audits'] ?? [];
        $categories = $lighthouse['categories'] ?? [];

        $performanceScore = isset($categories['performance']['score'])
            ? (int)round($categories['performance']['score'] * 100)
            : null;

        // Core Web Vitals
        $metrics = [
            'fcp' => self::extractMetric($audits, 'first-contentful-paint'),
            'lcp' => self::extractMetric($audits, 'largest-contentful-paint'),
            'tbt' => self::extractMetric($audits, 'total-blocking-time'),
            'cls' => self::extractMetric($audits, 'cumulative-layout-shift'),
            'speedIndex' => self::extractMetric($audits, 'speed-index'),
            'si' => self::extractMetric($audits, 'speed-index'),
        ];

        return [
            'performanceScore' => $performanceScore,
            'metrics' => $metrics,
            'opportunities' => self::extractOpportunities($audits),
            'diagnostics' => self::extractDiagnostics($audits),
        ];
    }

    /**
     * Optimization opportunities, sorted by estimated savings (descending).
     */
    public static function extractOpportunities(array $audits): array
    {
        $opportunities = [];

        foreach ($audits as $key => $audit) {
            $savings = $audit['details']['overallSavingsMs'] ?? 0;
            if ($savings > self::MIN_OPPORTUNITY_SAVINGS_MS) {
                $opportunities[] = [
                    'id' => $key,
                    'title' => $audit['title'] ?? $key,
                    'description' => strip_tags($audit['description'] ?? ''),
                    'savingsMs' => (int)round($savings),
                    'displayValue' => $audit['displayValue'] ?? null,
                    'score' => isset($audit['score']) ? (int)round($audit['score'] * 100) : null,
                ];
            }
        }

        usort($opportunities, fn($a, $b) => $b['savingsMs'] <=> $a['savingsMs']);

        return array_slice($opportunities, 0, self::MAX_ITEMS);
    }

    /**
     * Informational diagnostics (table audits scoring below 0.9 with no savings).
     */
    public static function extractDiagnostics(array $audits): array
    {
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
                    'score' => isset($audit['score']) ? (int)round($audit['score'] * 100) : null,
                ];
            }
        }

        return array_slice($diagnostics, 0, self::MAX_ITEMS);
    }

    /**
     * Extract a single metric's display value, numeric value, and score.
     */
    public static function extractMetric(array $audits, string $key): array
    {
        $audit = $audits[$key] ?? [];

        return [
            'displayValue' => $audit['displayValue'] ?? '—',
            'numericValue' => $audit['numericValue'] ?? null,
            'score' => isset($audit['score']) ? (int)round($audit['score'] * 100) : null,
        ];
    }
}
