<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\migrations;

use craft\db\Migration;

final class m0002_add_indexes_and_constraints extends Migration
{
    public function safeUp(): bool
    {
        $this->createIndex(
            'idx_deb_templates_handle',
            '{{%dataexportbuilder_export_templates}}',
            'handle',
            true
        );

        $this->createIndex(
            'idx_deb_templates_type_format',
            '{{%dataexportbuilder_export_templates}}',
            ['elementType', 'format'],
            false
        );

        $this->createIndex(
            'idx_deb_fields_template_sort',
            '{{%dataexportbuilder_export_fields}}',
            ['templateId', 'sortOrder'],
            false
        );

        $this->createIndex(
            'idx_deb_fields_template_path',
            '{{%dataexportbuilder_export_fields}}',
            ['templateId', 'fieldPath'],
            false
        );

        $this->createIndex(
            'idx_deb_runs_template_status',
            '{{%dataexportbuilder_export_runs}}',
            ['templateId', 'status'],
            false
        );

        $this->createIndex(
            'idx_deb_runs_user_date',
            '{{%dataexportbuilder_export_runs}}',
            ['triggeredByUserId', 'dateCreated'],
            false
        );

        $this->addForeignKey(
            'fk_deb_templates_creator',
            '{{%dataexportbuilder_export_templates}}',
            'creatorId',
            '{{%users}}',
            'id',
            'SET NULL',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk_deb_fields_template',
            '{{%dataexportbuilder_export_fields}}',
            'templateId',
            '{{%dataexportbuilder_export_templates}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk_deb_runs_template',
            '{{%dataexportbuilder_export_runs}}',
            'templateId',
            '{{%dataexportbuilder_export_templates}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk_deb_runs_user',
            '{{%dataexportbuilder_export_runs}}',
            'triggeredByUserId',
            '{{%users}}',
            'id',
            'SET NULL',
            'CASCADE'
        );

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropForeignKey('fk_deb_runs_user', '{{%dataexportbuilder_export_runs}}');
        $this->dropForeignKey('fk_deb_runs_template', '{{%dataexportbuilder_export_runs}}');
        $this->dropForeignKey('fk_deb_fields_template', '{{%dataexportbuilder_export_fields}}');
        $this->dropForeignKey('fk_deb_templates_creator', '{{%dataexportbuilder_export_templates}}');

        $this->dropIndex('idx_deb_runs_user_date', '{{%dataexportbuilder_export_runs}}');
        $this->dropIndex('idx_deb_runs_template_status', '{{%dataexportbuilder_export_runs}}');
        $this->dropIndex('idx_deb_fields_template_path', '{{%dataexportbuilder_export_fields}}');
        $this->dropIndex('idx_deb_fields_template_sort', '{{%dataexportbuilder_export_fields}}');
        $this->dropIndex('idx_deb_templates_type_format', '{{%dataexportbuilder_export_templates}}');
        $this->dropIndex('idx_deb_templates_handle', '{{%dataexportbuilder_export_templates}}');

        return true;
    }
}
