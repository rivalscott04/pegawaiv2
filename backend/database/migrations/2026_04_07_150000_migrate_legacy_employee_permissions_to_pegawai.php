<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('permission_user')) {
            return;
        }

        $now = now();

        $newDefinitions = [
            ['key' => 'pegawai.edit', 'label' => 'Edit pegawai (tanpa ubah NIP Baru)', 'group' => 'pegawai', 'sort_order' => 40],
            ['key' => 'pegawai.edit_all', 'label' => 'Edit pegawai (lengkap, termasuk NIP Baru)', 'group' => 'pegawai', 'sort_order' => 50],
            ['key' => 'pegawai.create', 'label' => 'Tambah pegawai', 'group' => 'pegawai', 'sort_order' => 60],
            ['key' => 'pegawai.delete', 'label' => 'Hapus pegawai', 'group' => 'pegawai', 'sort_order' => 70],
        ];

        foreach ($newDefinitions as $row) {
            DB::table('permissions')->updateOrInsert(
                ['key' => $row['key']],
                array_merge($row, ['created_at' => $now, 'updated_at' => $now])
            );
        }

        DB::table('permissions')->where('key', 'pegawai.view')->update(['group' => 'pegawai', 'label' => 'Lihat data pegawai', 'sort_order' => 20, 'updated_at' => $now]);
        DB::table('permissions')->where('key', 'pegawai.export')->update(['group' => 'pegawai', 'label' => 'Export data pegawai', 'sort_order' => 30, 'updated_at' => $now]);

        $map = [
            'employees.view' => 'pegawai.view',
            'employees.edit' => 'pegawai.edit',
            'employees.edit_all' => 'pegawai.edit_all',
            'employees.create' => 'pegawai.create',
            'employees.delete' => 'pegawai.delete',
        ];

        foreach ($map as $oldKey => $newKey) {
            $old = DB::table('permissions')->where('key', $oldKey)->first();
            $new = DB::table('permissions')->where('key', $newKey)->first();
            if (! $old || ! $new) {
                continue;
            }

            $rows = DB::table('permission_user')->where('permission_id', $old->id)->get();
            foreach ($rows as $pu) {
                DB::table('permission_user')->updateOrInsert(
                    ['user_id' => $pu->user_id, 'permission_id' => $new->id],
                    ['user_id' => $pu->user_id, 'permission_id' => $new->id]
                );
            }

            DB::table('permission_user')->where('permission_id', $old->id)->delete();
            DB::table('permissions')->where('id', $old->id)->delete();
        }
    }

    public function down(): void
    {
        // Irreversible: employees.* permissions dihapus dari skema izin utama.
    }
};
