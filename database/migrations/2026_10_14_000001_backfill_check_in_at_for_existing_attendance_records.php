<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('attendance_records')
            ->whereNull('check_in_at')
            ->update(['check_in_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        // Intentionally no-op: clearing check_in_at would lose useful history.
    }
};
