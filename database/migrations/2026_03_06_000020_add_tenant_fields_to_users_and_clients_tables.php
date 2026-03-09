<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'client_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('client_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('clients')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('clients') && !Schema::hasColumn('clients', 'primary_admin_user_id')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->foreignId('primary_admin_user_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('clients') && Schema::hasTable('users') && Schema::hasColumn('users', 'client_id') && Schema::hasColumn('clients', 'user_id')) {
            DB::statement('UPDATE users u INNER JOIN clients c ON c.user_id = u.id SET u.client_id = c.id WHERE u.client_id IS NULL');
        }

        if (Schema::hasTable('clients') && Schema::hasColumn('clients', 'primary_admin_user_id') && Schema::hasColumn('clients', 'user_id')) {
            DB::statement('UPDATE clients SET primary_admin_user_id = user_id WHERE primary_admin_user_id IS NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('clients') && Schema::hasColumn('clients', 'primary_admin_user_id')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropForeign(['primary_admin_user_id']);
                $table->dropColumn('primary_admin_user_id');
            });
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'client_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['client_id']);
                $table->dropColumn('client_id');
            });
        }
    }
};
