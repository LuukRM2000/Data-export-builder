<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\migrations;

use craft\db\Migration;

final class m260318_133301_add_indexes_and_constraints extends Migration
{
    public function safeUp(): bool
    {
        if (
            !$this->db->tableExists('{{%dataexportbuilder_export_templates}}') ||
            !$this->db->tableExists('{{%dataexportbuilder_export_fields}}') ||
            !$this->db->tableExists('{{%dataexportbuilder_export_runs}}')
        ) {
            return true;
        }

        $this->safeCreateIndex('idx_deb_templates_handle', '{{%dataexportbuilder_export_templates}}', 'handle', true);
        $this->safeCreateIndex('idx_deb_templates_type_format', '{{%dataexportbuilder_export_templates}}', ['elementType', 'format'], false);
        $this->safeCreateIndex('idx_deb_fields_template_sort', '{{%dataexportbuilder_export_fields}}', ['templateId', 'sortOrder'], false);
        $this->safeCreateIndex('idx_deb_fields_template_path', '{{%dataexportbuilder_export_fields}}', ['templateId', 'fieldPath'], false);
        $this->safeCreateIndex('idx_deb_runs_template_status', '{{%dataexportbuilder_export_runs}}', ['templateId', 'status'], false);
        $this->safeCreateIndex('idx_deb_runs_user_date', '{{%dataexportbuilder_export_runs}}', ['triggeredByUserId', 'dateCreated'], false);

        $this->safeAddForeignKey('fk_deb_templates_creator', '{{%dataexportbuilder_export_templates}}', 'creatorId', '{{%users}}', 'id', 'SET NULL', 'CASCADE');
        $this->safeAddForeignKey('fk_deb_fields_template', '{{%dataexportbuilder_export_fields}}', 'templateId', '{{%dataexportbuilder_export_templates}}', 'id', 'CASCADE', 'CASCADE');
        $this->safeAddForeignKey('fk_deb_runs_template', '{{%dataexportbuilder_export_runs}}', 'templateId', '{{%dataexportbuilder_export_templates}}', 'id', 'CASCADE', 'CASCADE');
        $this->safeAddForeignKey('fk_deb_runs_user', '{{%dataexportbuilder_export_runs}}', 'triggeredByUserId', '{{%users}}', 'id', 'SET NULL', 'CASCADE');

        return true;
    }

    public function safeDown(): bool
    {
        if ($this->db->tableExists('{{%dataexportbuilder_export_runs}}')) {
            $this->safeDropForeignKey('fk_deb_runs_user', '{{%dataexportbuilder_export_runs}}');
            $this->safeDropForeignKey('fk_deb_runs_template', '{{%dataexportbuilder_export_runs}}');
            $this->safeDropIndex('idx_deb_runs_user_date', '{{%dataexportbuilder_export_runs}}');
            $this->safeDropIndex('idx_deb_runs_template_status', '{{%dataexportbuilder_export_runs}}');
        }

        if ($this->db->tableExists('{{%dataexportbuilder_export_fields}}')) {
            $this->safeDropForeignKey('fk_deb_fields_template', '{{%dataexportbuilder_export_fields}}');
            $this->safeDropIndex('idx_deb_fields_template_path', '{{%dataexportbuilder_export_fields}}');
            $this->safeDropIndex('idx_deb_fields_template_sort', '{{%dataexportbuilder_export_fields}}');
        }

        if ($this->db->tableExists('{{%dataexportbuilder_export_templates}}')) {
            $this->safeDropForeignKey('fk_deb_templates_creator', '{{%dataexportbuilder_export_templates}}');
            $this->safeDropIndex('idx_deb_templates_type_format', '{{%dataexportbuilder_export_templates}}');
            $this->safeDropIndex('idx_deb_templates_handle', '{{%dataexportbuilder_export_templates}}');
        }

        return true;
    }

    private function safeDropForeignKey(string $name, string $table): void
    {
        try {
            $this->dropForeignKey($name, $table);
        } catch (\Throwable) {
        }
    }

    private function safeDropIndex(string $name, string $table): void
    {
        try {
            $this->dropIndex($name, $table);
        } catch (\Throwable) {
        }
    }

    private function safeCreateIndex(string $name, string $table, string|array $columns, bool $unique): void
    {
        if ($this->indexExists($table, $name)) {
            return;
        }

        $this->createIndex($name, $table, $columns, $unique);
    }

    private function safeAddForeignKey(
        string $name,
        string $table,
        string|array $columns,
        string $refTable,
        string|array $refColumns,
        ?string $delete = null,
        ?string $update = null
    ): void {
        if ($this->foreignKeyExists($table, $name)) {
            return;
        }

        $this->addForeignKey($name, $table, $columns, $refTable, $refColumns, $delete, $update);
    }

    private function indexExists(string $table, string $name): bool
    {
        return (new \yii\db\Query())
            ->from('information_schema.statistics')
            ->where([
                'table_schema' => $this->db->createCommand('SELECT DATABASE()')->queryScalar(),
                'table_name' => $this->db->getSchema()->getRawTableName($table),
                'index_name' => $name,
            ])
            ->exists($this->db);
    }

    private function foreignKeyExists(string $table, string $name): bool
    {
        return (new \yii\db\Query())
            ->from('information_schema.referential_constraints')
            ->where([
                'constraint_schema' => $this->db->createCommand('SELECT DATABASE()')->queryScalar(),
                'table_name' => $this->db->getSchema()->getRawTableName($table),
                'constraint_name' => $name,
            ])
            ->exists($this->db);
    }
}
