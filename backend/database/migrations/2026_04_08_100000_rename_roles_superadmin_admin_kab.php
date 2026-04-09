<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $now = now();
        DB::table('roles')->where('name', 'admin')->update(['name' => 'superadmin', 'updated_at' => $now]);
        DB::table('roles')->where('name', 'operator')->update(['name' => 'admin_kab', 'updated_at' => $now]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $now = now();
        DB::table('roles')->where('name', 'superadmin')->update(['name' => 'admin', 'updated_at' => $now]);
        DB::table('roles')->where('name', 'admin_kab')->update(['name' => 'operator', 'updated_at' => $now]);
    }
};
