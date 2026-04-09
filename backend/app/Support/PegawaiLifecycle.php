<?php

namespace App\Support;

use App\Models\Pegawai;

/**
 * Aturan pensiun / keaktifan pegawai — sama dengan PegawaiController::index (bukan kolom DB is_active mentah).
 */
final class PegawaiLifecycle
{
	/**
	 * Tanggal pensiun (hari ulang tahun pensiun), selaras dengan getRetirementDate di frontend (lib/pension.ts).
	 */
	public static function retirementDate(Pegawai $pegawai): ?\DateTimeImmutable
	{
		$jenisPegawai = self::normalizeText($pegawai->jenis_pegawai ?? '');
		if (!self::containsAny($jenisPegawai, ['pns', 'pppk'])) {
			return null;
		}

		$birth = self::resolveBirthDate($pegawai);
		if ($birth === null) {
			return null;
		}

		$retirementAge = self::determineRetirementAge($pegawai);

		return $birth->modify(sprintf('+%d years', $retirementAge));
	}

	/**
	 * TMT pensiun untuk tampilan / export: tanggal 1 bulan berikutnya setelah tanggal pensiun (getAkanPensiunDate di frontend).
	 */
	public static function akanPensiunDate(Pegawai $pegawai): ?\DateTimeImmutable
	{
		$retirement = self::retirementDate($pegawai);
		if ($retirement === null) {
			return null;
		}

		return $retirement->modify('first day of next month');
	}

	/**
	 * @param iterable<int, Pegawai> $rows
	 */
	public static function attachTmtPensiunForExport(iterable $rows): void
	{
		foreach ($rows as $row) {
			if (!$row instanceof Pegawai) {
				continue;
			}
			$d = self::akanPensiunDate($row);
			$row->setAttribute('tmt_pensiun', $d !== null ? $d->format('Y-m-d') : null);
		}
	}

	public static function isRetiredByRule(Pegawai $pegawai): bool
	{
		$jenisPegawai = self::normalizeText($pegawai->jenis_pegawai ?? '');
		if (!self::containsAny($jenisPegawai, ['pns', 'pppk'])) {
			return false;
		}

		$birth = self::resolveBirthDate($pegawai);
		if ($birth === null) {
			return false;
		}

		$retirementAge = self::determineRetirementAge($pegawai);

		$retirementYm = ((int) $birth->format('Y') + $retirementAge) * 100 + (int) $birth->format('m');
		$todayYm = ((int) now()->format('Y')) * 100 + (int) now()->format('m');

		return $todayYm >= $retirementYm;
	}

	private static function determineRetirementAge(Pegawai $pegawai): int
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
			if (self::containsAny($jabatan, ['pertama'])) {
				return 58;
			}

			return 58;
		}

		return 58;
	}

	/** Tanggal lahir: utamakan NIP (8 digit pertama = YYYYMMDD); TTL hanya jika NIP tidak valid. */
	private static function resolveBirthDate(Pegawai $pegawai): ?\DateTimeImmutable
	{
		return self::extractBirthDateFromNip($pegawai->nip ?? null)
			?? self::extractBirthDate($pegawai->tempat_tanggal_lahir ?? null);
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

		if (preg_match('/(\d{4})-(\d{2})-(\d{2})/', $text, $m)) {
			$year = (int) $m[1];
			$month = (int) $m[2];
			$day = (int) $m[3];
			if (checkdate($month, $day, $year)) {
				return new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));
			}
		}

		if (!preg_match('/(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{2,4})/', $text, $matches)) {
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

	/** NIP ASN: 8 digit pertama = YYYYMMDD tanggal lahir */
	private static function extractBirthDateFromNip(?string $nip): ?\DateTimeImmutable
	{
		if ($nip === null) {
			return null;
		}

		$digits = preg_replace('/\D/', '', $nip) ?? '';
		if (strlen($digits) < 8) {
			return null;
		}

		$year = (int) substr($digits, 0, 4);
		$month = (int) substr($digits, 4, 2);
		$day = (int) substr($digits, 6, 2);
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
