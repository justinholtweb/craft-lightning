<?php

namespace justinholtweb\lightning\services;

use craft\base\Component;
use craft\helpers\App;
use justinholtweb\lightning\helpers\ResponseParser;
use justinholtweb\lightning\Plugin;

class PageSpeedService extends Component
{
    private const API_ENDPOINT = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

    /**
     * Run a PageSpeed Insights audit for a URL.
     *
     * @param string $url The URL to audit
     * @param string $strategy 'mobile' or 'desktop'
     * @return array Parsed API response, or `['error' => string]` on failure.
     */
    public function runAudit(string $url, string $strategy = 'mobile'): array
    {
        $apiKey = App::parseEnv(Plugin::getInstance()->getSettings()->apiKey);

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

        if (!is_array($data)) {
            return ['error' => 'Received an invalid response from the Google PageSpeed Insights API.'];
        }

        if (isset($data['error'])) {
            return ['error' => $data['error']['message'] ?? 'Unknown API error.'];
        }

        return ResponseParser::parse($data);
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
}
