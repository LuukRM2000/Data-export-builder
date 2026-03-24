<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\migrations;

use craft\db\Migration;

final class Install extends Migration
{
    public function safeUp(): bool
    {
        $createTables = new m260318_133300_create_export_tables();
        $createTables->db = $this->db;
        $createTables->safeUp();

        $indexes = new m260318_133301_add_indexes_and_constraints();
        $indexes->db = $this->db;
        $indexes->safeUp();

        return true;
    }

    public function safeDown(): bool
    {
        $indexes = new m260318_133301_add_indexes_and_constraints();
        $indexes->db = $this->db;
        $indexes->safeDown();

        $createTables = new m260318_133300_create_export_tables();
        $createTables->db = $this->db;
        $createTables->safeDown();

        return true;
    }
}
