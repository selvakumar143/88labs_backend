<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('top_requests', function (Blueprint $table) {
            $table->string('request_id', 32)->nullable()->after('id');
        });

        Schema::table('exchange_requests', function (Blueprint $table) {
            $table->string('request_id', 32)->nullable()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('top_requests', function (Blueprint $table) {
            $table->dropColumn('request_id');
        });

        Schema::table('exchange_requests', function (Blueprint $table) {
            $table->dropColumn('request_id');
        });
    }
};
