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
        Schema::table('ad_account_requests', function (Blueprint $table) {
            $table->string('timezone')->after('platform');
            $table->string('business_manager_id')->after('currency');
            $table->string('website_url')->after('business_manager_id');
            $table->string('account_type')->after('website_url');
            $table->string('personal_profile')->after('account_type');
            $table->integer('number_of_accounts')->default(1)->after('personal_profile');
        });
    }
    
    public function down()
    {
        Schema::table('ad_account_requests', function (Blueprint $table) {
            $table->dropColumn([
                'timezone',
                'business_manager_id',
                'website_url',
                'account_type',
                'personal_profile',
                'number_of_accounts'
            ]);
        });
    }
};
