<?php

namespace justinholtweb\lightning\assetbundles;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class LightningAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = '@justinholtweb/lightning/resources';

        $this->depends = [
            CpAsset::class,
        ];

        $this->js = [
            'js/lightning.js',
        ];

        $this->css = [
            'css/lightning.css',
        ];

        parent::init();
    }
}
