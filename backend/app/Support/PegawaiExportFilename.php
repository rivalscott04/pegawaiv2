<?php

namespace App\Support;

use App\Models\WilayahUnit;

/**
 * Nama file export pegawai: jelas untuk «semua Kanwil» vs per kabupaten/kota.
 * Untuk wilayah di tabel WilayahUnit, prefiks kabupaten_/kota_ selalu diambil dari kolom `kind`
 * (mis. kabupaten_bima vs kota_bima — tidak disingkat jadi «bima» saja).
 */
final class PegawaiExportFilename
{
    private const KANTOR_KEMENTERIAN_AGAMA_PREFIX = 'kantor_kementerian_agama_';

    /**
     * Segmen setelah "pegawai_" tanpa ekstensi, mis. "all", "kota_mataram", "kanwil_ntb".
     */
    public static function regionKeyFromFilters(array $filters): string
    {
        $slug = null;
        if (!empty($filters['wilayah_source_unit_slug'])) {
            $slug = (string) $filters['wilayah_source_unit_slug'];
        } elseif (!empty($filters['source_unit_slug'])) {
            $slug = (string) $filters['source_unit_slug'];
        }

        if ($slug === null || trim($slug) === '') {
            return 'all';
        }

        return self::humanRegionKey($slug);
    }

    public static function allRecordsFilename(string $ext, array $filters): string
    {
        return 'pegawai_' . self::regionKeyFromFilters($filters) . '.' . ltrim($ext, '.');
    }

    /**
     * Export halaman saja (sinkron): tetap memuat nomor halaman & jumlah baris.
     */
    public static function pageExportFilename(
        string $ext,
        ?string $wilayahSlug,
        string $sourceUnitSlugFilter,
        int $page,
        int $rowCount
    ): string {
        $filters = [];
        if ($wilayahSlug !== null && trim($wilayahSlug) !== '') {
            $filters['wilayah_source_unit_slug'] = $wilayahSlug;
        }
        if (trim($sourceUnitSlugFilter) !== '') {
            $filters['source_unit_slug'] = $sourceUnitSlugFilter;
        }

        $key = self::regionKeyFromFilters($filters);
        $ext = ltrim($ext, '.');
        $p = max(1, $page);
        $n = max(0, $rowCount);

        return "pegawai_{$key}_halaman{$p}_{$n}.{$ext}";
    }

    private static function humanRegionKey(string $slug): string
    {
        $slug = trim($slug);
        if ($slug === '') {
            return 'wilayah';
        }

        if ($slug === PegawaiWilayah::kanwilSourceUnitSlug()) {
            return 'kanwil_ntb';
        }

        $unit = WilayahUnit::query()
            ->whereRaw('LOWER(slug) = ?', [mb_strtolower($slug)])
            ->first();

        if ($unit !== null) {
            return self::regionKeyFromWilayahUnit($unit);
        }

        if (str_starts_with($slug, self::KANTOR_KEMENTERIAN_AGAMA_PREFIX)) {
            $slug = substr($slug, strlen(self::KANTOR_KEMENTERIAN_AGAMA_PREFIX));
        }

        $s = strtolower($slug);
        $s = preg_replace('/[^a-z0-9_]+/', '_', $s) ?? '';
        $s = preg_replace('/_+/', '_', $s);
        $s = trim($s, '_');

        return $s !== '' ? $s : 'wilayah';
    }

    /**
     * @return string mis. "kabupaten_bima", "kota_bima", "kabupaten_lombok_barat"
     */
    private static function regionKeyFromWilayahUnit(WilayahUnit $unit): string
    {
        $kind = strtolower(trim((string) $unit->kind));
        if ($kind !== 'kabupaten' && $kind !== 'kota') {
            $kind = 'wilayah';
        }

        $canonical = (string) $unit->slug;
        $afterKantor = str_starts_with($canonical, self::KANTOR_KEMENTERIAN_AGAMA_PREFIX)
            ? substr($canonical, strlen(self::KANTOR_KEMENTERIAN_AGAMA_PREFIX))
            : $canonical;
        $afterKantor = strtolower($afterKantor);

        $kindPrefix = $kind . '_';
        $tail = str_starts_with($afterKantor, $kindPrefix)
            ? substr($afterKantor, strlen($kindPrefix))
            : $afterKantor;

        $tail = preg_replace('/[^a-z0-9_]+/', '_', $tail) ?? '';
        $tail = preg_replace('/_+/', '_', $tail);
        $tail = trim($tail, '_');

        if ($tail === '') {
            return $kind;
        }

        return $kind . '_' . $tail;
    }
}
