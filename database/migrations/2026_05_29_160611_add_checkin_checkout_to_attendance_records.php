<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            // Explicit check-in / check-out timestamps (separate from created_at)
            $table->timestamp('check_in_at')->nullable()->after('rejection_reason');
            $table->timestamp('check_out_at')->nullable()->after('check_in_at');

            // Computed fields stored for fast reporting
            $table->unsignedSmallInteger('late_minutes')->default(0)->after('check_out_at');
            $table->unsignedSmallInteger('overtime_minutes')->default(0)->after('late_minutes');
            $table->decimal('daily_fee', 10, 2)->nullable()->after('overtime_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn(['check_in_at', 'check_out_at', 'late_minutes', 'overtime_minutes', 'daily_fee']);
        });
    }
};
