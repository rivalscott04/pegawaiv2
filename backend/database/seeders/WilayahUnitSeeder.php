<?php

namespace Database\Seeders;

use App\Models\WilayahUnit;
use Illuminate\Database\Seeder;

/**
 * 10 kabupaten/kota NTB (selain Kanwil) — slug selaras dengan pegawai.source_unit_slug / PegawaiWilayah.
 */
class WilayahUnitSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['slug' => 'kantor_kementerian_agama_kabupaten_bima', 'name' => 'Kabupaten Bima', 'kind' => 'kabupaten', 'sort_order' => 1],
            ['slug' => 'kantor_kementerian_agama_kabupaten_dompu', 'name' => 'Kabupaten Dompu', 'kind' => 'kabupaten', 'sort_order' => 2],
            ['slug' => 'kantor_kementerian_agama_kabupaten_lombok_barat', 'name' => 'Kabupaten Lombok Barat', 'kind' => 'kabupaten', 'sort_order' => 3],
            ['slug' => 'kantor_kementerian_agama_kabupaten_lombok_tengah', 'name' => 'Kabupaten Lombok Tengah', 'kind' => 'kabupaten', 'sort_order' => 4],
            ['slug' => 'kantor_kementerian_agama_kabupaten_lombok_timur', 'name' => 'Kabupaten Lombok Timur', 'kind' => 'kabupaten', 'sort_order' => 5],
            ['slug' => 'kantor_kementerian_agama_kabupaten_lombok_utara', 'name' => 'Kabupaten Lombok Utara', 'kind' => 'kabupaten', 'sort_order' => 6],
            ['slug' => 'kantor_kementerian_agama_kabupaten_sumbawa', 'name' => 'Kabupaten Sumbawa', 'kind' => 'kabupaten', 'sort_order' => 7],
            ['slug' => 'kantor_kementerian_agama_kabupaten_sumbawa_barat', 'name' => 'Kabupaten Sumbawa Barat', 'kind' => 'kabupaten', 'sort_order' => 8],
            ['slug' => 'kantor_kementerian_agama_kota_bima', 'name' => 'Kota Bima', 'kind' => 'kota', 'sort_order' => 9],
            ['slug' => 'kantor_kementerian_agama_kota_mataram', 'name' => 'Kota Mataram', 'kind' => 'kota', 'sort_order' => 10],
        ];

        foreach ($rows as $row) {
            WilayahUnit::query()->updateOrCreate(
                ['slug' => $row['slug']],
                [
                    'name' => $row['name'],
                    'kind' => $row['kind'],
                    'sort_order' => $row['sort_order'],
                ]
            );
        }
    }
}
