<?php

namespace Database\Seeders;

use App\Models\Coordinate;
use Illuminate\Database\Seeder;

/**
 * Seed titik peta heatmap untuk kantor Kemenag di NTB.
 *
 * String induk_unit harus sama persis dengan canonicalIndukList() di EmployeeController.
 * Koordinat adalah perkiraan (pusat kab/kota atau kawasan kantor umum); bisa disempurnakan lewat admin /coordinates.
 *
 * Sumber perkiraan: area ibu kota kab/kota dan Jl. Udayana Mataram (Kanwil), bukan survei lapangan.
 */
class CoordinateSeeder extends Seeder
{
	public function run(): void
	{
		$rows = [
			[
				'induk_unit' => 'Kantor Wilayah Kementerian Agama Provinsi Nusa Tenggara Barat',
				// Jl. Udayana No. 6, Mataram — perkiraan sekitar kawasan perkantoran
				'latitude' => -8.594_2,
				'longitude' => 116.105_4,
			],
			[
				'induk_unit' => 'Kantor Kementerian Agama Kota Mataram',
				'latitude' => -8.583_3,
				'longitude' => 116.116_7,
			],
			[
				'induk_unit' => 'Kantor Kementerian Agama Kota Bima',
				'latitude' => -8.460_4,
				'longitude' => 118.750_6,
			],
			[
				'induk_unit' => 'Kantor Kementerian Agama Kabupaten Lombok Barat',
				// Gerung
				'latitude' => -8.357_0,
				'longitude' => 116.171_0,
			],
			[
				'induk_unit' => 'Kantor Kementerian Agama Kabupaten Lombok Tengah',
				// Praya
				'latitude' => -8.705_0,
				'longitude' => 116.280_0,
			],
			[
				'induk_unit' => 'Kantor Kementerian Agama Kabupaten Lombok Timur',
				// Selong
				'latitude' => -8.641_5,
				'longitude' => 116.532_6,
			],
			[
				'induk_unit' => 'Kantor Kementerian Agama Kabupaten Lombok Utara',
				// Tanjung
				'latitude' => -8.347_3,
				'longitude' => 116.070_6,
			],
			[
				'induk_unit' => 'Kantor Kementerian Agama Kabupaten Sumbawa',
				// Sumbawa Besar
				'latitude' => -8.496_1,
				'longitude' => 117.963_6,
			],
			[
				'induk_unit' => 'Kantor Kementerian Agama Kabupaten Sumbawa Barat',
				// Taliwang
				'latitude' => -8.724_4,
				'longitude' => 116.959_7,
			],
			[
				'induk_unit' => 'Kantor Kementerian Agama Kabupaten Dompu',
				'latitude' => -8.536_5,
				'longitude' => 118.463_3,
			],
			[
				'induk_unit' => 'Kantor Kementerian Agama Kabupaten Bima',
				// Woha (ibu kota Kab. Bima)
				'latitude' => -8.640_8,
				'longitude' => 118.699_7,
			],
		];

		foreach ($rows as $row) {
			Coordinate::updateOrCreate(
				['induk_unit' => $row['induk_unit']],
				[
					'latitude' => $row['latitude'],
					'longitude' => $row['longitude'],
				]
			);
		}
	}
}
