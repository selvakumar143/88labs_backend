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

        Schema::table('ad_account_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('ad_account_requests', 'account_name')) {
                $table->string('account_name')->nullable()->after('account_management_id');
            }

            if (!Schema::hasColumn('ad_account_requests', 'account_id')) {
                $table->string('account_id')->nullable()->after('account_name');
            }

            if (!Schema::hasColumn('ad_account_requests', 'card_type')) {
                $table->string('card_type')->nullable()->after('account_id');
            }

            if (!Schema::hasColumn('ad_account_requests', 'card_number')) {
                $table->string('card_number')->nullable()->after('card_type');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('ad_account_requests')) {
            return;
        }

        Schema::table('ad_account_requests', function (Blueprint $table) {
            if (Schema::hasColumn('ad_account_requests', 'card_number')) {
                $table->dropColumn('card_number');
            }

            if (Schema::hasColumn('ad_account_requests', 'card_type')) {
                $table->dropColumn('card_type');
            }

            if (Schema::hasColumn('ad_account_requests', 'account_id')) {
                $table->dropColumn('account_id');
            }

            if (Schema::hasColumn('ad_account_requests', 'account_name')) {
                $table->dropColumn('account_name');
            }
        });
    }
};
