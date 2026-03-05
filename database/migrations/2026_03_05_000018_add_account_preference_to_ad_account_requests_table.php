<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ad_account_requests') || Schema::hasColumn('ad_account_requests', 'account_preference')) {
            return;
        }

        Schema::table('ad_account_requests', function (Blueprint $table) {
            $table->string('account_preference')->nullable()->after('account_name');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('ad_account_requests') || !Schema::hasColumn('ad_account_requests', 'account_preference')) {
            return;
        }

        Schema::table('ad_account_requests', function (Blueprint $table) {
            $table->dropColumn('account_preference');
        });
    }
};
