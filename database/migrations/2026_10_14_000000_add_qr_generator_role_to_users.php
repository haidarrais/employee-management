<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add a QR-only operator role that can generate QR codes but cannot access payroll.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY role ENUM('admin', 'management', 'qr_generator', 'employee') NOT NULL");
    }

    /**
     * Remove the QR-only operator role from the enum.
     *
     * Existing qr_generator users must be reassigned before rolling back.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY role ENUM('admin', 'management', 'employee') NOT NULL");
    }
};
