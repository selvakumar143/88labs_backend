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
        Schema::dropIfExists('clients');
    
        Schema::create('clients', function (Blueprint $table) {
    
            $table->string('id')->primary(); // CL-1001 (string primary key)
    
            $table->string('clientName');
            $table->string('country');
            $table->string('email')->unique();
            $table->string('phone');
            $table->string('clientType');
            $table->string('niche');
            $table->string('marketCountry');
            $table->string('settlementMode');
            $table->string('statementCycle');
            $table->string('settlementCurrency');
            $table->date('cooperationStart');
            $table->decimal('serviceFeePercent', 5, 2);
            $table->dateTime('serviceFeeEffectiveTime');
            $table->boolean('enabled')->default(true);
    
            // One-to-One with User
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
    
            $table->timestamps();
        });
    }
    

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
