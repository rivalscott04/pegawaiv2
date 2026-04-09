<?php

namespace App\Support;

use App\Models\Pegawai;

/**
 * Aturan pensiun / keaktifan pegawai — sama dengan PegawaiController::index (bukan kolom DB is_active mentah).
 */
final class PegawaiLifecycle
{
	public static function isRetiredByRule(Pegawai $pegawai): bool
	{
		$jenisPegawai = self::normalizeText($pegawai->jenis_pegawai ?? '');
		if (!self::containsAny($jenisPegawai, ['pns'])) {
			return false;
		}

		$birth = self::extractBirthDate($pegawai->tempat_tanggal_lahir ?? null);
		if ($birth === null) {
			return false;
		}

		$retirementAge = self::determineRetirementAge($pegawai);
		if ($retirementAge === null) {
			return false;
		}

		$retirementYm = ((int) $birth->format('Y') + $retirementAge) * 100 + (int) $birth->format('m');
		$todayYm = ((int) now()->format('Y')) * 100 + (int) now()->format('m');

		return $todayYm >= $retirementYm;
	}

	private static function determineRetirementAge(Pegawai $pegawai): ?int
	{
		$jabatan = self::normalizeText($pegawai->jabatan ?? '');
		$pangkatGolongan = self::normalizeText($pegawai->pangkat_golongan ?? '');

		if (self::containsAny($jabatan, ['guru'])) {
			return self::isGolonganIi($pangkatGolongan) ? 58 : 60;
		}

		if (self::containsAny($jabatan, ['penghulu', 'penyuluh', 'pengawas'])) {
			if (self::containsAny($jabatan, ['madya'])) {
				return 60;
			}
			if (self::containsAny($jabatan, ['muda'])) {
				return 58;
			}
			return null;
		}

		if (self::containsAny($jabatan, ['pelaksana'])) {
			return 58;
		}

		return null;
	}

	private static function extractBirthDate(?string $tempatTanggalLahir): ?\DateTimeImmutable
	{
		if ($tempatTanggalLahir === null) {
			return null;
		}

		$text = trim($tempatTanggalLahir);
		if ($text === '') {
			return null;
		}

		if (!preg_match('/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})/', $text, $matches)) {
			return null;
		}

		$day = (int) $matches[1];
		$month = (int) $matches[2];
		$year = (int) $matches[3];
		if ($year < 100) {
			$year += 1900;
		}

		if (!checkdate($month, $day, $year)) {
			return null;
		}

		return new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));
	}

	private static function normalizeText(string $value): string
	{
		return mb_strtolower(trim($value));
	}

	private static function containsAny(string $haystack, array $needles): bool
	{
		foreach ($needles as $needle) {
			if ($needle !== '' && str_contains($haystack, $needle)) {
				return true;
			}
		}

		return false;
	}

	private static function isGolonganIi(string $pangkatGolongan): bool
	{
		if ($pangkatGolongan === '') {
			return false;
		}

		return (bool) preg_match('/(^|[^a-z0-9])ii([\/\.\s-]|$)/i', $pangkatGolongan);
	}
}
