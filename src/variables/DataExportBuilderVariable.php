<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\variables;

use Luremo\DataExportBuilder\helpers\CapabilityHelper;

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
}
