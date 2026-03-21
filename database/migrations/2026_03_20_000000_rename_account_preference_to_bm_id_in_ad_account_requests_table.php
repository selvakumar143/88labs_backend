<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ad_account_requests')) {
            return;
        }

        if (Schema::hasColumn('ad_account_requests', 'account_preference')
            && !Schema::hasColumn('ad_account_requests', 'bm_id')) {
            Schema::table('ad_account_requests', function (Blueprint $table) {
                $table->renameColumn('account_preference', 'bm_id');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('ad_account_requests')) {
            return;
        }

        if (Schema::hasColumn('ad_account_requests', 'bm_id')
            && !Schema::hasColumn('ad_account_requests', 'account_preference')) {
            Schema::table('ad_account_requests', function (Blueprint $table) {
                $table->renameColumn('bm_id', 'account_preference');
            });
        }
    }
};
