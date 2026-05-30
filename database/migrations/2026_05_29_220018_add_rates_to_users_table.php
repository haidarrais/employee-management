<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Per-employee daily base fee (null = use global schedule default)
            $table->decimal('daily_rate', 12, 2)->nullable()->after('is_active')
                  ->comment('Daily base fee in local currency. Null = use global schedule default.');

            // Per-employee overtime rate per hour (null = use global schedule default)
            $table->decimal('overtime_rate', 12, 2)->nullable()->after('daily_rate')
                  ->comment('Overtime rate per hour. Null = use global schedule default.');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['daily_rate', 'overtime_rate']);
        });
    }
};
