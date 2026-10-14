<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE users MODIFY role ENUM('superadmin','admin','management','qr_generator','employee') NOT NULL");
        }

        // Bootstrap the role so the protected maintenance screen is reachable.
        if (!DB::table('users')->where('role', 'superadmin')->exists()) {
            $firstAdmin = DB::table('users')
                ->where('role', 'admin')
                ->orderBy('id')
                ->first();

            if ($firstAdmin) {
                DB::table('users')
                    ->where('id', $firstAdmin->id)
                    ->update(['role' => 'superadmin']);
            }
        }
    }

    public function down(): void
    {
        DB::table('users')
            ->where('role', 'superadmin')
            ->update(['role' => 'admin']);

        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE users MODIFY role ENUM('admin','management','qr_generator','employee') NOT NULL");
        }
    }
};
