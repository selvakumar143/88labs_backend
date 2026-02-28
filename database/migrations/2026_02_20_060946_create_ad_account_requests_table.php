<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('ad_account_requests', function (Blueprint $table) {
            $table->id();
    
            $table->string('request_id')->unique();
    
            $table->foreignId('client_id')
                  ->constrained('users')
                  ->onDelete('cascade');
    
            $table->string('business_name');
            $table->string('platform');
            $table->string('niche')->nullable();
            $table->string('market_country');
            $table->string('currency');
            $table->decimal('fee_percentage', 5, 2)->nullable();
            $table->text('additional_notes')->nullable();
    
            $table->enum('status', ['pending', 'approved', 'rejected'])
                  ->default('pending');
    
            $table->foreignId('approved_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
    
            $table->timestamp('approved_at')->nullable();
    
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ad_account_requests');
    }
};
