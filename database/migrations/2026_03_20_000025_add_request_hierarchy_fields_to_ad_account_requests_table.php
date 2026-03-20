<?php

use App\Models\AdAccountRequest;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ad_account_requests')) {
            return;
        }

        Schema::table('ad_account_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('ad_account_requests', 'req_name')) {
                $table->string('req_name')->nullable()->after('sub_user_id');
            }

            if (!Schema::hasColumn('ad_account_requests', 'type')) {
                $table->enum('type', [
                    AdAccountRequest::TYPE_MASTER,
                    AdAccountRequest::TYPE_CHILD,
                ])->default(AdAccountRequest::TYPE_MASTER)->after('req_name');
            }

            if (!Schema::hasColumn('ad_account_requests', 'api')) {
                $table->enum('api', [
                    AdAccountRequest::API_ENABLE,
                    AdAccountRequest::API_DISABLE,
                ])->default(AdAccountRequest::API_ENABLE)->after('type');
            }

            if (!Schema::hasColumn('ad_account_requests', 'master_id')) {
                $table->foreignId('master_id')
                    ->nullable()
                    ->after('api')
                    ->constrained('ad_account_requests')
                    ->nullOnDelete();
            }
        });

        DB::table('ad_account_requests')
            ->whereNull('req_name')
            ->update([
                'req_name' => DB::raw('request_id'),
                'type' => AdAccountRequest::TYPE_MASTER,
                'api' => AdAccountRequest::API_ENABLE,
            ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('ad_account_requests')) {
            return;
        }

        Schema::table('ad_account_requests', function (Blueprint $table) {
            if (Schema::hasColumn('ad_account_requests', 'master_id')) {
                $table->dropForeign(['master_id']);
                $table->dropColumn('master_id');
            }

            if (Schema::hasColumn('ad_account_requests', 'api')) {
                $table->dropColumn('api');
            }

            if (Schema::hasColumn('ad_account_requests', 'type')) {
                $table->dropColumn('type');
            }

            if (Schema::hasColumn('ad_account_requests', 'req_name')) {
                $table->dropColumn('req_name');
            }
        });
    }
};
