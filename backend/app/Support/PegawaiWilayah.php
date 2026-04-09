<?php

namespace App\Support;

/**
 * Pemetaan wilayah heatmap dari kolom pegawai v2 (sinkron dengan tabel source_units).
 */
final class PegawaiWilayah
{
	/**
	 * @return array<string, string> slug → induk_unit kanonik (EmployeeController::canonicalIndukList)
	 */
	public static function sourceUnitSlugToCanonicalIndukMap(): array
	{
		return [
			'kantor_kementerian_agama_kabupaten_bima' => 'Kantor Kementerian Agama Kabupaten Bima',
			'kantor_kementerian_agama_kabupaten_dompu' => 'Kantor Kementerian Agama Kabupaten Dompu',
			'kantor_kementerian_agama_kabupaten_lombok_barat' => 'Kantor Kementerian Agama Kabupaten Lombok Barat',
			'kantor_kementerian_agama_kabupaten_lombok_tengah' => 'Kantor Kementerian Agama Kabupaten Lombok Tengah',
			'kantor_kementerian_agama_kabupaten_lombok_timur' => 'Kantor Kementerian Agama Kabupaten Lombok Timur',
			'kantor_kementerian_agama_kabupaten_lombok_utara' => 'Kantor Kementerian Agama Kabupaten Lombok Utara',
			'kantor_kementerian_agama_kabupaten_sumbawa' => 'Kantor Kementerian Agama Kabupaten Sumbawa',
			'kantor_kementerian_agama_kabupaten_sumbawa_barat' => 'Kantor Kementerian Agama Kabupaten Sumbawa Barat',
			'kantor_kementerian_agama_kota_bima' => 'Kantor Kementerian Agama Kota Bima',
			'kantor_kementerian_agama_kota_mataram' => 'Kantor Kementerian Agama Kota Mataram',
			'kanwil_kementerian_agama_provinsi_nusa_tenggara_barat' => 'Kantor Wilayah Kementerian Agama Provinsi Nusa Tenggara Barat',
		];
	}

	public static function canonicalKanwilIndukName(): string
	{
		return 'Kantor Wilayah Kementerian Agama Provinsi Nusa Tenggara Barat';
	}

	/** Slug source_unit pegawai v2 untuk baris Kantor Wilayah (bukan kab/kota). */
	public static function kanwilSourceUnitSlug(): string
	{
		return 'kanwil_kementerian_agama_provinsi_nusa_tenggara_barat';
	}

	public static function canonicalIndukFromSourceUnitSlug(?string $slug): ?string
	{
		if ($slug === null || trim($slug) === '') {
			return null;
		}

		$key = trim($slug);
		$map = self::sourceUnitSlugToCanonicalIndukMap();

		if (isset($map[$key])) {
			return $map[$key];
		}

		// Data lama / input beda kapitalisasi
		$lower = mb_strtolower($key);
		foreach ($map as $k => $canonical) {
			if (mb_strtolower((string) $k) === $lower) {
				return $canonical;
			}
		}

		return null;
	}

	/**
	 * Kebalikan dari sourceUnitSlugToCanonicalIndukMap — untuk link heatmap → filter pegawai v2 (source_unit_slug).
	 */
	public static function sourceUnitSlugFromCanonicalInduk(string $canonicalInduk): ?string
	{
		$needle = trim($canonicalInduk);
		if ($needle === '') {
			return null;
		}

		foreach (self::sourceUnitSlugToCanonicalIndukMap() as $slug => $canonical) {
			if ($canonical === $needle) {
				return $slug;
			}
		}

		return null;
	}
}
