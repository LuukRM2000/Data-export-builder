<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\migrations;

use craft\db\Migration;

final class m260318_133300_create_export_tables extends Migration
{
    public function safeUp(): bool
    {
        if (!$this->db->tableExists('{{%dataexportbuilder_export_templates}}')) {
            $this->createTable('{{%dataexportbuilder_export_templates}}', [
            'id' => $this->primaryKey(),
            'uid' => $this->uid(),
            'name' => $this->string(255)->notNull(),
            'handle' => $this->string(255)->notNull(),
            'elementType' => $this->string(255)->notNull(),
            'format' => $this->string(20)->notNull()->defaultValue('csv'),
            'filtersJson' => $this->json(),
            'settingsJson' => $this->json(),
            'creatorId' => $this->integer(),
            'lastRunAt' => $this->dateTime(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            ]);
        }

        if (!$this->db->tableExists('{{%dataexportbuilder_export_fields}}')) {
            $this->createTable('{{%dataexportbuilder_export_fields}}', [
            'id' => $this->primaryKey(),
            'uid' => $this->uid(),
            'templateId' => $this->integer()->notNull(),
            'fieldPath' => $this->string(255)->notNull(),
            'columnLabel' => $this->string(255)->notNull(),
            'sortOrder' => $this->smallInteger()->unsigned()->notNull()->defaultValue(1),
            'settingsJson' => $this->json(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            ]);
        }

        if (!$this->db->tableExists('{{%dataexportbuilder_export_runs}}')) {
            $this->createTable('{{%dataexportbuilder_export_runs}}', [
            'id' => $this->primaryKey(),
            'uid' => $this->uid(),
            'templateId' => $this->integer()->notNull(),
            'status' => $this->string(20)->notNull()->defaultValue('queued'),
            'format' => $this->string(20)->notNull()->defaultValue('csv'),
            'rowCount' => $this->integer()->unsigned(),
            'filePath' => $this->text(),
            'fileName' => $this->string(255),
            'fileMimeType' => $this->string(100),
            'storageType' => $this->string(50)->notNull()->defaultValue('local'),
            'startedAt' => $this->dateTime(),
            'finishedAt' => $this->dateTime(),
            'triggeredByUserId' => $this->integer(),
            'errorMessage' => $this->text(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            ]);
        }

        return true;
    }

    public function safeDown(): bool
    {
        if ($this->db->tableExists('{{%dataexportbuilder_export_runs}}')) {
            $this->dropTable('{{%dataexportbuilder_export_runs}}');
        }

        if ($this->db->tableExists('{{%dataexportbuilder_export_fields}}')) {
            $this->dropTable('{{%dataexportbuilder_export_fields}}');
        }

        if ($this->db->tableExists('{{%dataexportbuilder_export_templates}}')) {
            $this->dropTable('{{%dataexportbuilder_export_templates}}');
        }

        return true;
    }
}
