<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_management', function (Blueprint $table) {
            $table->foreignId('business_manager_id')
                ->nullable()
                ->after('client_id')
                ->constrained('business_managers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('account_management', function (Blueprint $table) {
            $table->dropForeign(['business_manager_id']);
            $table->dropColumn('business_manager_id');
        });
    }
};
