<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('clients') || !Schema::hasColumn('clients', 'business_manager_id')) {
            return;
        }

        Schema::table('clients', function (Blueprint $table) {
            $table->dropForeign(['business_manager_id']);
            $table->dropColumn('business_manager_id');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('clients') || Schema::hasColumn('clients', 'business_manager_id')) {
            return;
        }

        Schema::table('clients', function (Blueprint $table) {
            $table->foreignId('business_manager_id')
                ->nullable()
                ->after('user_id')
                ->constrained('business_managers')
                ->nullOnDelete();
        });
    }
};
