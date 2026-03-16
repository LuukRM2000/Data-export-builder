<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\web\assets\cp;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset as CraftCpAsset;

final class CpAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = '@luremo/dataexportbuilder/web/assets/cp/dist';
        $this->depends = [CraftCpAsset::class];
        $this->js = ['cp.js'];
        $this->css = ['cp.css'];

        parent::init();
    }
}
