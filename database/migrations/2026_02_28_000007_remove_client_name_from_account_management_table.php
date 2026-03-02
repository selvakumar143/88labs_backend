<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('account_management') || !Schema::hasColumn('account_management', 'client_name')) {
            return;
        }

        Schema::table('account_management', function (Blueprint $table) {
            $table->dropColumn('client_name');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('account_management') || Schema::hasColumn('account_management', 'client_name')) {
            return;
        }

        Schema::table('account_management', function (Blueprint $table) {
            $table->string('client_name')->after('account_id');
        });
    }
};
