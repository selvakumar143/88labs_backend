<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('top_requests', function (Blueprint $table) {
            $table->foreignId('ad_account_request_id')
                ->nullable()
                ->after('client_id')
                ->constrained('ad_account_requests')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('top_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ad_account_request_id');
        });
    }
};
