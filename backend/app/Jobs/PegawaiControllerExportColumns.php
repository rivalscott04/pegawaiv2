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
        'pendidikan_terakhir',
        'tmt_pensiun',
        'source_unit_slug',
    ];

    /** @var array<string, string> */
    public const HEADERS = [
        'nip' => 'NIP',
        'nama' => 'Nama',
        'nip_lama' => 'NIP Lama',
        'tempat_tanggal_lahir' => 'Tempat/Tanggal Lahir',
        'jenis_kelamin' => 'Jenis Kelamin',
        'agama' => 'Agama',
        'jenis_pegawai' => 'Jenis Pegawai',
        'jabatan' => 'Jabatan',
        'unit_kerja' => 'Unit Kerja',
        'satker_induk' => 'Satuan Kerja Induk',
        'pangkat_golongan' => 'Pangkat/Golongan',
        'pendidikan_terakhir' => 'Pendidikan Terakhir',
        'tmt_pensiun' => 'TMT Pensiun',
        'source_unit_slug' => 'Kode Unit',
    ];

    /**
     * @param list<string> $columns
     * @return list<string>
     */
    public static function headings(array $columns): array
    {
        return array_map(fn (string $col) => self::HEADERS[$col] ?? $col, $columns);
    }

    /**
     * Kolom di tabel `pegawai` saja (untuk SELECT); `tmt_pensiun` diisi setelah query.
     *
     * @return list<string>
     */
    public static function databaseColumns(): array
    {
        return array_values(array_diff(self::ALL, ['tmt_pensiun']));
    }
}
