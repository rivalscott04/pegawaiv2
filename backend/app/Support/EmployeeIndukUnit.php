<?php

namespace App\Support;

use App\Models\Employee;

/**
 * Menghitung induk_unit kanonik (sama dengan logika lama di EmployeeController).
 */
final class EmployeeIndukUnit
{
	public static function forEmployee(Employee $employee): string
	{
		return self::compute($employee->SATUAN_KERJA, $employee->kab_kota, $employee->KET_JABATAN ?? null);
	}

	public static function compute(?string $unit, ?string $kabKota, ?string $jabatan = null): string
	{
		$src = trim((string) ($unit ?? ''));
		if ($src === '' && $kabKota) {
			$src = $kabKota;
		}

		$lower = mb_strtolower($src);
		$jabLower = mb_strtolower(trim((string) ($jabatan ?? '')));

		$isGenericUnit = preg_match('/\bsub\s+bagian\b|\bsubbag\b/u', $lower) ||
			(preg_match('/\btata\s+usaha\b/u', $lower) && !preg_match('/\bbagian\s+tata\s+usaha\b/u', $lower));

		if ($isGenericUnit && $jabLower !== '') {
			if (preg_match('/\bkota\s+([a-zA-Z\s]+?)(?:\s+provinsi|\s+kantor|$)/ui', $jabLower, $m)) {
				$name = trim(strtolower($m[1]));
				$name = preg_replace('/\s+provinsi.*$/i', '', $name);
				$name = trim($name);
				if ($name === 'mataram') {
					return 'Kantor Kementerian Agama Kota Mataram';
				}
				if ($name === 'bima') {
					return 'Kantor Kementerian Agama Kota Bima';
				}
			}

			if (preg_match('/\bkab(upaten)?\s+([a-zA-Z\s]+?)(?:\s+provinsi|\s+kantor|$)/ui', $jabLower, $m)) {
				$name = trim(strtolower($m[count($m) - 1]));
				$name = preg_replace('/\s+provinsi.*$/i', '', $name);
				$name = trim($name);
				switch ($name) {
					case 'lombok barat':
						return 'Kantor Kementerian Agama Kabupaten Lombok Barat';
					case 'lombok tengah':
						return 'Kantor Kementerian Agama Kabupaten Lombok Tengah';
					case 'lombok timur':
						return 'Kantor Kementerian Agama Kabupaten Lombok Timur';
					case 'lombok utara':
						return 'Kantor Kementerian Agama Kabupaten Lombok Utara';
					case 'sumbawa barat':
						return 'Kantor Kementerian Agama Kabupaten Sumbawa Barat';
					case 'sumbawa':
						return 'Kantor Kementerian Agama Kabupaten Sumbawa';
					case 'dompu':
						return 'Kantor Kementerian Agama Kabupaten Dompu';
					case 'bima':
						return 'Kantor Kementerian Agama Kabupaten Bima';
				}
			}

			if (preg_match('/kantor\s+kementerian\s+agama\s+kota\s+([a-zA-Z\s]+?)(?:\s+provinsi|$)/ui', $jabLower, $m)) {
				$name = trim(strtolower($m[1]));
				$name = preg_replace('/\s+provinsi.*$/i', '', $name);
				$name = trim($name);
				if ($name === 'mataram') {
					return 'Kantor Kementerian Agama Kota Mataram';
				}
				if ($name === 'bima') {
					return 'Kantor Kementerian Agama Kota Bima';
				}
			}

			if (preg_match('/kantor\s+kementerian\s+agama\s+kab(upaten)?\s+([a-zA-Z\s]+?)(?:\s+provinsi|$)/ui', $jabLower, $m)) {
				$name = trim(strtolower($m[count($m) - 1]));
				$name = preg_replace('/\s+provinsi.*$/i', '', $name);
				$name = trim($name);
				switch ($name) {
					case 'lombok barat':
						return 'Kantor Kementerian Agama Kabupaten Lombok Barat';
					case 'lombok tengah':
						return 'Kantor Kementerian Agama Kabupaten Lombok Tengah';
					case 'lombok timur':
						return 'Kantor Kementerian Agama Kabupaten Lombok Timur';
					case 'lombok utara':
						return 'Kantor Kementerian Agama Kabupaten Lombok Utara';
					case 'sumbawa barat':
						return 'Kantor Kementerian Agama Kabupaten Sumbawa Barat';
					case 'sumbawa':
						return 'Kantor Kementerian Agama Kabupaten Sumbawa';
					case 'dompu':
						return 'Kantor Kementerian Agama Kabupaten Dompu';
					case 'bima':
						return 'Kantor Kementerian Agama Kabupaten Bima';
				}
			}
		}

		$isBagianTataUsaha = preg_match('/\bbagian\s+tata\s+usaha\b/u', $lower) && !preg_match('/\bsub\s+bagian\s+tata\s+usaha\b/u', $lower);

		$hasKanwilIndicator = false;
		if (preg_match('/\bkanwil\b|\bkantor\s+wilayah\b|\bprovinsi\s+nusa\s+tenggara\s+barat\b/u', $lower)) {
			$hasKanwilIndicator = true;
		} elseif (preg_match('/\bbimas\s+(islam|kristen|katolik|hindu|buddha)\b/u', $lower)) {
			$hasKanwilIndicator = true;
		} elseif (preg_match('/\bpembimbing\s+masyarakat\s+(islam|kristen|katolik|hindu|buddha)\b/u', $lower)) {
			$hasKanwilIndicator = true;
		} elseif (preg_match('/\bbidang\s+penyelenggara\s+haji\b|\bpenyelenggara\s+umroh\b/u', $lower)) {
			$hasKanwilIndicator = true;
		}

		if ($isBagianTataUsaha || $hasKanwilIndicator) {
			return PegawaiWilayah::canonicalKanwilIndukName();
		}

		if (preg_match('/\bsumbawa\s+barat\b/u', $lower)) {
			return 'Kantor Kementerian Agama Kabupaten Sumbawa Barat';
		}
		if (preg_match('/\bkota\s+bima\b/u', $lower)) {
			return 'Kantor Kementerian Agama Kota Bima';
		}
		if (preg_match('/\bbima\b/u', $lower)) {
			return 'Kantor Kementerian Agama Kabupaten Bima';
		}
		if (preg_match('/\bmataram\b/u', $lower)) {
			return 'Kantor Kementerian Agama Kota Mataram';
		}
		if (preg_match('/\bdompu\b/u', $lower)) {
			return 'Kantor Kementerian Agama Kabupaten Dompu';
		}
		if (preg_match('/\blombok\s+barat\b/u', $lower)) {
			return 'Kantor Kementerian Agama Kabupaten Lombok Barat';
		}
		if (preg_match('/\blombok\s+tengah\b/u', $lower)) {
			return 'Kantor Kementerian Agama Kabupaten Lombok Tengah';
		}
		if (preg_match('/\blombok\s+timur\b/u', $lower)) {
			return 'Kantor Kementerian Agama Kabupaten Lombok Timur';
		}
		if (preg_match('/\blombok\s+utara\b/u', $lower)) {
			return 'Kantor Kementerian Agama Kabupaten Lombok Utara';
		}
		if (preg_match('/\bsumbawa\b/u', $lower)) {
			return 'Kantor Kementerian Agama Kabupaten Sumbawa';
		}

		if (preg_match('/\b(kua\b[^\n]*\b)?kecamatan\b[^\n]*\balas\b/u', $lower) || preg_match('/\balas\b/u', $lower)) {
			return 'Kementerian Agama Kabupaten Sumbawa Barat';
		}

		if (preg_match('/\bkota\s+([a-zA-Z\s]+)\b/u', $lower, $m)) {
			$name = trim($m[1]);
			if ($name === 'mataram') {
				return 'Kantor Kementerian Agama Kota Mataram';
			}
			if ($name === 'bima') {
				return 'Kantor Kementerian Agama Kota Bima';
			}
		}
		if (preg_match('/\bkab(upaten)?\s+([a-zA-Z\s]+)\b/u', $lower, $m)) {
			$name = trim($m[count($m) - 1]);
			switch ($name) {
				case 'lombok barat':
					return 'Kantor Kementerian Agama Kabupaten Lombok Barat';
				case 'lombok tengah':
					return 'Kantor Kementerian Agama Kabupaten Lombok Tengah';
				case 'lombok timur':
					return 'Kantor Kementerian Agama Kabupaten Lombok Timur';
				case 'lombok utara':
					return 'Kantor Kementerian Agama Kabupaten Lombok Utara';
				case 'sumbawa':
					return 'Kantor Kementerian Agama Kabupaten Sumbawa';
				case 'sumbawa barat':
					return 'Kantor Kementerian Agama Kabupaten Sumbawa Barat';
				case 'dompu':
					return 'Kantor Kementerian Agama Kabupaten Dompu';
				case 'bima':
					return 'Kantor Kementerian Agama Kabupaten Bima';
			}
		}

		if ($kabKota) {
			$kk = trim($kabKota);
			if ($kk !== '') {
				$clean = preg_replace('/^\s*kantor\s+kementerian\s+agama\s+/iu', '', $kk);
				$clean = preg_replace('/^\s*kementerian\s+agama\s+/iu', '', $clean);
				$cleanLower = mb_strtolower($clean);
				if (preg_match('/^kota\s+mataram$/u', $cleanLower)) {
					return 'Kantor Kementerian Agama Kota Mataram';
				}
				if (preg_match('/^kota\s+bima$/u', $cleanLower)) {
					return 'Kantor Kementerian Agama Kota Bima';
				}
				if (preg_match('/^kab(upaten)?\s+lombok\s+barat$/u', $cleanLower)) {
					return 'Kantor Kementerian Agama Kabupaten Lombok Barat';
				}
				if (preg_match('/^kab(upaten)?\s+lombok\s+tengah$/u', $cleanLower)) {
					return 'Kantor Kementerian Agama Kabupaten Lombok Tengah';
				}
				if (preg_match('/^kab(upaten)?\s+lombok\s+timur$/u', $cleanLower)) {
					return 'Kantor Kementerian Agama Kabupaten Lombok Timur';
				}
				if (preg_match('/^kab(upaten)?\s+lombok\s+utara$/u', $cleanLower)) {
					return 'Kantor Kementerian Agama Kabupaten Lombok Utara';
				}
				if (preg_match('/^kab(upaten)?\s+sumbawa\s+barat$/u', $cleanLower)) {
					return 'Kantor Kementerian Agama Kabupaten Sumbawa Barat';
				}
				if (preg_match('/^kab(upaten)?\s+sumbawa$/u', $cleanLower)) {
					return 'Kantor Kementerian Agama Kabupaten Sumbawa';
				}
				if (preg_match('/^kab(upaten)?\s+dompu$/u', $cleanLower)) {
					return 'Kantor Kementerian Agama Kabupaten Dompu';
				}
				if (preg_match('/^kab(upaten)?\s+bima$/u', $cleanLower)) {
					return 'Kantor Kementerian Agama Kabupaten Bima';
				}
			}
		}

		return PegawaiWilayah::canonicalKanwilIndukName();
	}
}
