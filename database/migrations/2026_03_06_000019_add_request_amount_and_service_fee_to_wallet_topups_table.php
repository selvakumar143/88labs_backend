<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('wallet_topups')) {
            return;
        }

        Schema::table('wallet_topups', function (Blueprint $table) {
            if (!Schema::hasColumn('wallet_topups', 'request_amount')) {
                $table->decimal('request_amount', 12, 2)->nullable()->after('amount');
            }

            if (!Schema::hasColumn('wallet_topups', 'service_fee')) {
                $table->decimal('service_fee', 12, 2)->default(0)->after('request_amount');
            }
        });

        if (Schema::hasColumn('wallet_topups', 'amount') && Schema::hasColumn('wallet_topups', 'request_amount')) {
            DB::table('wallet_topups')->update([
                'request_amount' => DB::raw('COALESCE(request_amount, amount)'),
            ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('wallet_topups')) {
            return;
        }

        Schema::table('wallet_topups', function (Blueprint $table) {
            if (Schema::hasColumn('wallet_topups', 'service_fee')) {
                $table->dropColumn('service_fee');
            }

            if (Schema::hasColumn('wallet_topups', 'request_amount')) {
                $table->dropColumn('request_amount');
            }
        });
    }
};
