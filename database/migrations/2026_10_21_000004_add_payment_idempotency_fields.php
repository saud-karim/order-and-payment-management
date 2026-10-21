<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('operation_reference')->nullable()->unique()->after('idempotency_key');
            $table->char('request_fingerprint', 64)->nullable()->after('operation_reference');
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement(
                "CREATE UNIQUE INDEX IF NOT EXISTS payments_one_successful_per_order ON payments (order_id) WHERE status = 'successful'"
            );
        }

        if ($driver === 'mysql') {
            // MySQL 8.0.13+ functional unique index (one successful payment per order).
            DB::statement(
                'CREATE UNIQUE INDEX payments_one_successful_per_order ON payments ((IF(`status` = \'successful\', `order_id`, NULL)))'
            );
        }

        if ($driver === 'pgsql') {
            DB::statement(
                "CREATE UNIQUE INDEX payments_one_successful_per_order ON payments (order_id) WHERE status = 'successful'"
            );
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS payments_one_successful_per_order');
        }

        if ($driver === 'mysql') {
            DB::statement('DROP INDEX payments_one_successful_per_order ON payments');
        }

        if ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS payments_one_successful_per_order');
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['operation_reference']);
            $table->dropColumn(['operation_reference', 'request_fingerprint']);
        });
    }
};
