<?php

namespace justinholtweb\lightning\models;

use craft\base\Model;

class Settings extends Model
{
    /** Google PageSpeed Insights API key */
    public string $apiKey = '';

    /** Default strategy: 'mobile', 'desktop', or 'both' */
    public string $defaultStrategy = 'both';

    protected function defineRules(): array
    {
        return [
            [['apiKey'], 'required', 'message' => 'A Google PageSpeed Insights API key is required.'],
            [['apiKey'], 'string'],
            // `skipOnEmpty` is on by default for `in`, which would let an empty
            // strategy through.
            [['defaultStrategy'], 'in', 'range' => ['mobile', 'desktop', 'both'], 'skipOnEmpty' => false],
        ];
    }
}
