<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['key' => 'users.manage', 'label' => 'Kelola pengguna & hak akses', 'group' => 'administrasi', 'sort_order' => 10],
            ['key' => 'pegawai.lingkup.kabupaten', 'label' => 'Lingkup pegawai: satu kabupaten/kota (sesuai unit pada profil user)', 'group' => 'pegawai', 'sort_order' => 15],
            ['key' => 'pegawai.lingkup.kanwil', 'label' => 'Lingkup pegawai: seluruh wilayah Kanwil (semua kabupaten/kota + induk Kanwil)', 'group' => 'pegawai', 'sort_order' => 16],
            ['key' => 'pegawai.view', 'label' => 'Lihat data pegawai', 'group' => 'pegawai', 'sort_order' => 20],
            ['key' => 'pegawai.export', 'label' => 'Export data pegawai', 'group' => 'pegawai', 'sort_order' => 30],
            ['key' => 'pegawai.edit', 'label' => 'Edit pegawai (tanpa ubah NIP Baru)', 'group' => 'pegawai', 'sort_order' => 40],
            ['key' => 'pegawai.edit_all', 'label' => 'Edit pegawai (lengkap, termasuk NIP Baru)', 'group' => 'pegawai', 'sort_order' => 50],
            ['key' => 'pegawai.create', 'label' => 'Tambah pegawai', 'group' => 'pegawai', 'sort_order' => 60],
            ['key' => 'pegawai.delete', 'label' => 'Hapus pegawai', 'group' => 'pegawai', 'sort_order' => 70],
            ['key' => 'coordinates.manage', 'label' => 'Kelola koordinat peta', 'group' => 'peta', 'sort_order' => 80],
        ];

        foreach ($rows as $row) {
            Permission::query()->updateOrCreate(
                ['key' => $row['key']],
                [
                    'label' => $row['label'],
                    'group' => $row['group'],
                    'sort_order' => $row['sort_order'],
                ]
            );
        }
    }
}
