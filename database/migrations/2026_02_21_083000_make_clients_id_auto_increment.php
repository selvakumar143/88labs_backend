<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('clients') || Schema::hasColumn('clients', 'clientCode')) {
            return;
        }

        DB::statement('ALTER TABLE clients DROP PRIMARY KEY');
        DB::statement('ALTER TABLE clients CHANGE COLUMN id clientCode VARCHAR(255) NULL');
        DB::statement('ALTER TABLE clients ADD COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST');
        DB::statement('ALTER TABLE clients ADD UNIQUE INDEX clients_clientcode_unique (clientCode)');
    }

    public function down(): void
    {
        if (!Schema::hasTable('clients') || !Schema::hasColumn('clients', 'clientCode')) {
            return;
        }

        DB::statement("UPDATE clients SET clientCode = CONCAT('CL-', id) WHERE clientCode IS NULL OR clientCode = ''");
        DB::statement('ALTER TABLE clients DROP PRIMARY KEY');
        DB::statement('ALTER TABLE clients DROP INDEX clients_clientcode_unique');
        DB::statement('ALTER TABLE clients DROP COLUMN id');
        DB::statement('ALTER TABLE clients CHANGE COLUMN clientCode id VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE clients ADD PRIMARY KEY (id)');
    }
};
