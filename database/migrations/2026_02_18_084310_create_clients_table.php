<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('client_code')->unique(); // CL-1001
            $table->string('client_name');
            $table->string('country');
            $table->string('email')->unique();
            $table->string('phone');
            $table->string('client_type');
            $table->string('niche');
            $table->string('market_country');
            $table->string('settlement_mode');
            $table->string('statement_cycle');
            $table->string('settlement_currency');
            $table->date('cooperation_start');
            $table->decimal('service_fee_percent', 5, 2);
            $table->dateTime('service_fee_effective_time');
            $table->boolean('enabled')->default(true);
    
            // One-to-One with Users
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
    
            $table->timestamps();
        });
    }
    

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
