<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ad_account_requests') || Schema::hasColumn('ad_account_requests', 'account_management_id')) {
            return;
        }

        Schema::table('ad_account_requests', function (Blueprint $table) {
            $table->foreignId('account_management_id')
                ->nullable()
                ->after('business_manager_id')
                ->constrained('account_management')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('ad_account_requests') || !Schema::hasColumn('ad_account_requests', 'account_management_id')) {
            return;
        }

        Schema::table('ad_account_requests', function (Blueprint $table) {
            $table->dropForeign(['account_management_id']);
            $table->dropColumn('account_management_id');
        });
    }
};
