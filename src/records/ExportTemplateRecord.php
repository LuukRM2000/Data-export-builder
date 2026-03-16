<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\records;

use craft\db\ActiveRecord;

final class ExportTemplateRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%dataexportbuilder_export_templates}}';
    }
}
