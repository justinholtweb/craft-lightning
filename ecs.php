<?php

use craft\ecs\SetList;
use Symplify\EasyCodingStandard\Config\ECSConfig;

return static function(ECSConfig $ecsConfig): void {
    $ecsConfig->paths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
        __DIR__ . '/ecs.php',
    ]);
    // Generated Codeception actors and Craft's compiled test templates aren't ours to style.
    $ecsConfig->skip([
        __DIR__ . '/tests/_support/_generated/*',
        __DIR__ . '/tests/_craft/storage/*',
    ]);
    $ecsConfig->sets([SetList::CRAFT_CMS_4]);
};
