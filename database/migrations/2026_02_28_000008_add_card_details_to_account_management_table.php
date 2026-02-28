<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('account_management')) {
            return;
        }

        Schema::table('account_management', function (Blueprint $table) {
            if (!Schema::hasColumn('account_management', 'card_type')) {
                $table->string('card_type')->nullable()->after('account_id');
            }

            if (!Schema::hasColumn('account_management', 'card_number')) {
                $table->string('card_number')->nullable()->after('card_type');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('account_management')) {
            return;
        }

        Schema::table('account_management', function (Blueprint $table) {
            if (Schema::hasColumn('account_management', 'card_number')) {
                $table->dropColumn('card_number');
            }

            if (Schema::hasColumn('account_management', 'card_type')) {
                $table->dropColumn('card_type');
            }
        });
    }
};
