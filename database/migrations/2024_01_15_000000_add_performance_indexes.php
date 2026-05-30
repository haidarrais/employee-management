<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds database indexes for frequently queried columns to improve performance.
     */
    public function up(): void
    {
        // Users table indexes
        Schema::table('users', function (Blueprint $table) {
            $table->index('email', 'users_email_index');
            $table->index('role', 'users_role_index');
        });

        // QR Codes table indexes
        Schema::table('qr_codes', function (Blueprint $table) {
            $table->index('generated_by', 'qr_codes_generated_by_index');
            $table->index('status', 'qr_codes_status_index');
            $table->index('expires_at', 'qr_codes_expires_at_index');
        });

        // Attendance Records table indexes
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->index('user_id', 'attendance_records_user_id_index');
            $table->index('created_at', 'attendance_records_created_at_index');
            $table->index(['user_id', 'created_at'], 'attendance_records_user_created_index');
        });

        // Audit Logs table indexes
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index('user_id', 'audit_logs_user_id_index');
            $table->index('action', 'audit_logs_action_index');
            $table->index('created_at', 'audit_logs_created_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_email_index');
            $table->dropIndex('users_role_index');
        });

        Schema::table('qr_codes', function (Blueprint $table) {
            $table->dropIndex('qr_codes_generated_by_index');
            $table->dropIndex('qr_codes_status_index');
            $table->dropIndex('qr_codes_expires_at_index');
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropIndex('attendance_records_user_id_index');
            $table->dropIndex('attendance_records_created_at_index');
            $table->dropIndex('attendance_records_user_created_index');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_user_id_index');
            $table->dropIndex('audit_logs_action_index');
            $table->dropIndex('audit_logs_created_at_index');
        });
    }
};