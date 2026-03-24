<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\variables;

use Luremo\DataExportBuilder\helpers\CapabilityHelper;
use Luremo\DataExportBuilder\Plugin;

final class DataExportBuilderVariable
{
    public function edition(): string
    {
        return CapabilityHelper::getEdition();
    }

    public function commerceEnabled(): bool
    {
        return CapabilityHelper::supportsElementTypeHandle('orders');
    }

    public function isPro(): bool
    {
        return isset(Plugin::$plugin) && Plugin::$plugin->is(Plugin::EDITION_PRO);
    }
}
