<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'created_by')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('created_by')
                    ->nullable()
                    ->after('client_id')
                    ->constrained('clients')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'client_id') && Schema::hasColumn('users', 'created_by')) {
            DB::statement('UPDATE users SET created_by = client_id WHERE created_by IS NULL AND client_id IS NOT NULL');
        }

        if (!Schema::hasTable('clients')) {
            return;
        }

        if (Schema::hasColumn('clients', 'user_id') && !Schema::hasColumn('clients', 'admin_created_by')) {
            $this->dropForeignIfExists('clients', 'clients_user_id_foreign');
            $this->dropIndexIfExists('clients', 'clients_user_id_unique');

            DB::statement('ALTER TABLE clients CHANGE COLUMN user_id admin_created_by BIGINT UNSIGNED NULL');
        } elseif (!Schema::hasColumn('clients', 'admin_created_by')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->foreignId('admin_created_by')
                    ->nullable()
                    ->after('primary_admin_user_id');
            });
        }

        if (Schema::hasColumn('clients', 'admin_created_by')) {
            $this->dropForeignIfExists('clients', 'clients_admin_created_by_foreign');

            Schema::table('clients', function (Blueprint $table) {
                $table->foreign('admin_created_by')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('clients') && Schema::hasColumn('clients', 'admin_created_by')) {
            $this->dropForeignIfExists('clients', 'clients_admin_created_by_foreign');

            if (!Schema::hasColumn('clients', 'user_id')) {
                DB::statement('ALTER TABLE clients CHANGE COLUMN admin_created_by user_id BIGINT UNSIGNED NULL');
                Schema::table('clients', function (Blueprint $table) {
                    $table->unique('user_id');
                    $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                });
            }
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'created_by')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['created_by']);
                $table->dropColumn('created_by');
            });
        }
    }

    private function dropForeignIfExists(string $table, string $foreignKeyName): void
    {
        $exists = DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $foreignKeyName)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();

        if ($exists) {
            DB::statement(sprintf('ALTER TABLE %s DROP FOREIGN KEY %s', $table, $foreignKeyName));
        }
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        $exists = DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $indexName)
            ->exists();

        if ($exists) {
            DB::statement(sprintf('ALTER TABLE %s DROP INDEX %s', $table, $indexName));
        }
    }
};
