<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->decimal('check_out_latitude', 10, 8)->nullable()->after('distance_meters');
            $table->decimal('check_out_longitude', 11, 8)->nullable()->after('check_out_latitude');
            $table->decimal('check_out_distance_meters', 10, 2)->nullable()->after('check_out_longitude');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn([
                'check_out_latitude',
                'check_out_longitude',
                'check_out_distance_meters',
            ]);
        });
    }
};
