<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('business_managers', function (Blueprint $table) {
            $table->dropColumn(['mail', 'contact']);
        });
    }

    public function down(): void
    {
        Schema::table('business_managers', function (Blueprint $table) {
            $table->string('mail')->unique();
            $table->string('contact');
        });
    }
};
