<?php

namespace justinholtweb\lightning\tests\integration;

use justinholtweb\lightning\services\PageSpeedService;

/**
 * A PageSpeedService whose HTTP layer is replaced with canned responses, so the
 * response handling can be exercised without hitting the PSI API.
 */
class StubPageSpeedService extends PageSpeedService
{
    /** Responses returned by successive `fetch()` calls. The last one repeats. */
    public array $responses = [''];

    /** Every URL `fetch()` was called with, in order. */
    public array $requestedUrls = [];

    protected function fetch(string $apiUrl): string|false
    {
        $this->requestedUrls[] = $apiUrl;

        return count($this->responses) > 1
            ? array_shift($this->responses)
            : $this->responses[0];
    }
}
