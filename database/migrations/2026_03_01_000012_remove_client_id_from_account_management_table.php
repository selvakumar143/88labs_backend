<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('account_management') || !Schema::hasColumn('account_management', 'client_id')) {
            return;
        }

        Schema::table('account_management', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropColumn('client_id');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('account_management') || Schema::hasColumn('account_management', 'client_id')) {
            return;
        }

        Schema::table('account_management', function (Blueprint $table) {
            $table->foreignId('client_id')
                ->after('id')
                ->constrained('clients')
                ->cascadeOnDelete();
        });
    }
};
