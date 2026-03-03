<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('exchange_requests')) {
            return;
        }

        Schema::table('exchange_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('exchange_requests', 'base_currency')) {
                $table->string('base_currency', 10)->nullable()->after('client_id');
            }

            if (!Schema::hasColumn('exchange_requests', 'converion_currency')) {
                $table->string('converion_currency', 10)->nullable()->after('base_currency');
            }

            if (!Schema::hasColumn('exchange_requests', 'total_deduction')) {
                $table->decimal('total_deduction', 12, 2)->default(0)->after('service_fee');
            }

            if (!Schema::hasColumn('exchange_requests', 'return_amount')) {
                $table->decimal('return_amount', 12, 2)->default(0)->after('total_deduction');
            }

            if (!Schema::hasColumn('exchange_requests', 'convertion_rate')) {
                $table->decimal('convertion_rate', 14, 6)->default(1)->after('return_amount');
            }

            if (!Schema::hasColumn('exchange_requests', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete()->after('status');
            }

            if (!Schema::hasColumn('exchange_requests', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
        });

        if (Schema::hasColumn('exchange_requests', 'based_cur')) {
            DB::table('exchange_requests')
                ->whereNull('base_currency')
                ->update(['base_currency' => DB::raw('based_cur')]);
        }

        if (Schema::hasColumn('exchange_requests', 'convertion_cur')) {
            DB::table('exchange_requests')
                ->whereNull('converion_currency')
                ->update(['converion_currency' => DB::raw('convertion_cur')]);
        }

        if (Schema::hasColumn('exchange_requests', 'final_amount')) {
            DB::table('exchange_requests')
                ->where('return_amount', 0)
                ->update(['return_amount' => DB::raw('final_amount')]);
        }

        DB::table('exchange_requests')
            ->where('total_deduction', 0)
            ->update(['total_deduction' => DB::raw('request_amount + service_fee')]);

        DB::table('exchange_requests')
            ->whereNull('base_currency')
            ->orWhere('base_currency', '')
            ->update(['base_currency' => 'USD']);

        DB::table('exchange_requests')
            ->whereNull('converion_currency')
            ->orWhere('converion_currency', '')
            ->update(['converion_currency' => 'USD']);
    }

    public function down(): void
    {
        if (!Schema::hasTable('exchange_requests')) {
            return;
        }

        Schema::table('exchange_requests', function (Blueprint $table) {
            if (Schema::hasColumn('exchange_requests', 'approved_by')) {
                $table->dropForeign(['approved_by']);
            }

            $columns = [];
            foreach (['base_currency', 'converion_currency', 'total_deduction', 'return_amount', 'convertion_rate', 'approved_by', 'approved_at'] as $column) {
                if (Schema::hasColumn('exchange_requests', $column)) {
                    $columns[] = $column;
                }
            }

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
