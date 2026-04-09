<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('users')) {
            return;
        }

        $now = now();
        foreach ([
            ['key' => 'pegawai.lingkup.kabupaten', 'label' => 'Lingkup pegawai: satu kabupaten/kota (sesuai unit pada profil user)', 'group' => 'pegawai', 'sort_order' => 15],
            ['key' => 'pegawai.lingkup.kanwil', 'label' => 'Lingkup pegawai: seluruh wilayah Kanwil (semua kabupaten/kota + induk Kanwil)', 'group' => 'pegawai', 'sort_order' => 16],
        ] as $row) {
            DB::table('permissions')->updateOrInsert(
                ['key' => $row['key']],
                array_merge($row, ['created_at' => $now, 'updated_at' => $now])
            );
        }

        $kabPermId = DB::table('permissions')->where('key', 'pegawai.lingkup.kabupaten')->value('id');
        $kanwilPermId = DB::table('permissions')->where('key', 'pegawai.lingkup.kanwil')->value('id');
        if (! $kabPermId || ! $kanwilPermId) {
            return;
        }

        $pegawaiActionKeys = [
            'pegawai.view',
            'pegawai.export',
            'pegawai.edit',
            'pegawai.edit_all',
            'pegawai.create',
            'pegawai.delete',
        ];
        $pegawaiPermIds = DB::table('permissions')->whereIn('key', $pegawaiActionKeys)->pluck('id');
        if ($pegawaiPermIds->isEmpty()) {
            return;
        }

        $userIds = DB::table('permission_user')
            ->whereIn('permission_id', $pegawaiPermIds->all())
            ->distinct()
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            $user = DB::table('users')->where('id', $userId)->first();
            if (! $user) {
                continue;
            }

            $role = DB::table('roles')->where('id', $user->role_id)->first();
            if ($role && in_array($role->name, ['superadmin', 'admin'], true)) {
                continue;
            }

            $hasKab = DB::table('permission_user')
                ->where('user_id', $userId)
                ->where('permission_id', $kabPermId)
                ->exists();
            $hasKanwil = DB::table('permission_user')
                ->where('user_id', $userId)
                ->where('permission_id', $kanwilPermId)
                ->exists();
            if ($hasKab || $hasKanwil) {
                continue;
            }

            if ($role && $role->name === 'admin_kanwil') {
                $targetId = $kanwilPermId;
            } elseif ($user->wilayah_unit_id !== null) {
                $targetId = $kabPermId;
            } else {
                $targetId = $kanwilPermId;
            }

            DB::table('permission_user')->updateOrInsert(
                ['user_id' => $userId, 'permission_id' => $targetId],
                ['user_id' => $userId, 'permission_id' => $targetId]
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $ids = DB::table('permissions')
            ->whereIn('key', ['pegawai.lingkup.kabupaten', 'pegawai.lingkup.kanwil'])
            ->pluck('id');
        if ($ids->isNotEmpty()) {
            DB::table('permission_user')->whereIn('permission_id', $ids->all())->delete();
            DB::table('permissions')->whereIn('id', $ids->all())->delete();
        }
    }
};
