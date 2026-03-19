<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->updateClientIdsToClients();

        $this->swapClientForeignKeyToClients('wallet_topups');
        $this->swapClientForeignKeyToClients('top_requests');
        $this->swapClientForeignKeyToClients('ad_account_requests');
        $this->swapClientForeignKeyToClients('exchange_requests');
    }

    public function down(): void
    {
        $this->updateClientIdsToUsers();

        $this->swapClientForeignKeyToUsers('wallet_topups');
        $this->swapClientForeignKeyToUsers('top_requests');
        $this->swapClientForeignKeyToUsers('ad_account_requests');
        $this->swapClientForeignKeyToUsers('exchange_requests');
    }

    private function updateClientIdsToClients(): void
    {
        if (!Schema::hasTable('clients')) {
            return;
        }

        foreach ($this->clientTables() as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'client_id')) {
                continue;
            }

            DB::statement(
                "UPDATE {$table} t "
                . "INNER JOIN clients c ON c.primary_admin_user_id = t.client_id "
                . "SET t.client_id = c.id "
                . "WHERE t.client_id IS NOT NULL"
            );
        }
    }

    private function updateClientIdsToUsers(): void
    {
        if (!Schema::hasTable('clients')) {
            return;
        }

        foreach ($this->clientTables() as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'client_id')) {
                continue;
            }

            DB::statement(
                "UPDATE {$table} t "
                . "INNER JOIN clients c ON c.id = t.client_id "
                . "SET t.client_id = c.primary_admin_user_id "
                . "WHERE t.client_id IS NOT NULL AND c.primary_admin_user_id IS NOT NULL"
            );
        }
    }

    private function swapClientForeignKeyToClients(string $tableName): void
    {
        if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, 'client_id')) {
            return;
        }

        $this->dropForeignIfExists($tableName, $tableName . '_client_id_foreign');

        Schema::table($tableName, function (Blueprint $table) {
            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
        });
    }

    private function swapClientForeignKeyToUsers(string $tableName): void
    {
        if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, 'client_id')) {
            return;
        }

        $this->dropForeignIfExists($tableName, $tableName . '_client_id_foreign');

        Schema::table($tableName, function (Blueprint $table) {
            $table->foreign('client_id')->references('id')->on('users')->cascadeOnDelete();
        });
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

    private function clientTables(): array
    {
        return [
            'wallet_topups',
            'top_requests',
            'ad_account_requests',
            'exchange_requests',
            'get_spend_data',
        ];
    }
};
