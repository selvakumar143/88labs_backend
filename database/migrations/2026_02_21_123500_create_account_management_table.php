<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('account_management', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->string('name');
            $table->string('account_id')->unique();
            $table->string('client_name');
            $table->string('platform');
            $table->string('currency');
            $table->dateTime('account_created_at');
            $table->string('status')->default('pending'); // pending, active, inactive, suspended
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_management');
    }
};
