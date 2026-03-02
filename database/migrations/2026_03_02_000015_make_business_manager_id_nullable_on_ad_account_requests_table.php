<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ad_account_requests') || !Schema::hasColumn('ad_account_requests', 'business_manager_id')) {
            return;
        }

        DB::statement('ALTER TABLE `ad_account_requests` MODIFY `business_manager_id` VARCHAR(255) NULL');
    }

    public function down(): void
    {
        if (!Schema::hasTable('ad_account_requests') || !Schema::hasColumn('ad_account_requests', 'business_manager_id')) {
            return;
        }

        DB::statement('ALTER TABLE `ad_account_requests` MODIFY `business_manager_id` VARCHAR(255) NOT NULL');
    }
};
