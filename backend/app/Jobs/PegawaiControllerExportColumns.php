<?php

namespace App\Jobs;

final class PegawaiControllerExportColumns
{
    /**
     * Kolom file export: data kepegawaian saja (tanpa fingerprint, sync, audit DB).
     *
     * @var list<string>
     */
    public const ALL = [
        'nip',
        'nama',
        'nip_lama',
        'tempat_tanggal_lahir',
        'jenis_kelamin',
        'agama',
        'jenis_pegawai',
        'jabatan',
        'unit_kerja',
        'satker_induk',
        'pangkat_golongan',
        'pangkat_pns_nama',
        'golongan_pns',
        'pendidikan_terakhir',
        'tmt_pensiun',
        'source_unit_slug',
    ];

    /**
     * Kolom di tabel `pegawai` saja (untuk SELECT); `tmt_pensiun` diisi setelah query.
     *
     * @return list<string>
     */
    public static function databaseColumns(): array
    {
        return array_values(array_diff(self::ALL, ['tmt_pensiun', 'pangkat_pns_nama', 'golongan_pns']));
    }
}
