<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('account_management') || Schema::hasColumn('account_management', 'ad_account_request_id')) {
            return;
        }

        Schema::table('account_management', function (Blueprint $table) {
            $table->foreignId('ad_account_request_id')
                ->nullable()
                ->after('business_manager_id')
                ->constrained('ad_account_requests')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('account_management') || !Schema::hasColumn('account_management', 'ad_account_request_id')) {
            return;
        }

        Schema::table('account_management', function (Blueprint $table) {
            $table->dropForeign(['ad_account_request_id']);
            $table->dropColumn('ad_account_request_id');
        });
    }
};
