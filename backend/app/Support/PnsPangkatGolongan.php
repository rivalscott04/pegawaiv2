<?php

namespace App\Support;

use App\Models\Pegawai;

/**
 * Pangkat (nama jabatan) dan golongan/ruang PNS dipisah: teks sumber bisa diurai
 * menjadi "Penata Muda" + "III/a". Hanya untuk jenis pegawai PNS (bukan PPPK, dll.).
 */
final class PnsPangkatGolongan
{
    /**
     * @var array<string, array<string, string>>
     */
    private const GOLONGAN_RUANG_TO_LABEL = [
        'I' => [
            'a' => 'Juru Muda',
            'b' => 'Juru Muda Tingkat I',
            'c' => 'Juru',
            'd' => 'Juru Tingkat I',
        ],
        'II' => [
            'a' => 'Pengatur Muda',
            'b' => 'Pengatur Muda Tingkat I',
            'c' => 'Pengatur',
            'd' => 'Pengatur Tingkat I',
        ],
        'III' => [
            'a' => 'Penata Muda',
            'b' => 'Penata Muda Tingkat I',
            'c' => 'Penata',
            'd' => 'Penata Tingkat I',
        ],
        'IV' => [
            'a' => 'Pembina',
            'b' => 'Pembina Tingkat I',
            'c' => 'Pembina Utama Muda',
            'd' => 'Pembina Utama Madya',
            'e' => 'Pembina Utama',
        ],
    ];

    /**
     * @return array{pangkat: ?string, golongan: ?string}
     *         pangkat = nama jabatan (mis. Penata Muda); golongan = teks golongan/ruang (mis. III/a).
     *         Jika pola tidak dikenali tapi PNS: pangkat null, golongan = teks asli (tetap bisa disalin).
     */
    public static function parts(?string $jenisPegawai, ?string $pangkatGolongan): array
    {
        if (!self::isPns($jenisPegawai)) {
            return ['pangkat' => null, 'golongan' => null];
        }

        $raw = trim((string) $pangkatGolongan);
        if ($raw === '') {
            return ['pangkat' => null, 'golongan' => null];
        }

        $pair = self::extractGolonganRuang($raw);
        if ($pair !== null) {
            [$gol, $ruang] = $pair;
            $nama = self::GOLONGAN_RUANG_TO_LABEL[$gol][$ruang] ?? null;
            $golonganDisplay = $gol.'/'.$ruang;

            return ['pangkat' => $nama, 'golongan' => $golonganDisplay];
        }

        return ['pangkat' => null, 'golongan' => $raw];
    }

    /** Nama pangkat saja (nilai kunci `pangkat` dari {@see parts()}). */
    public static function label(?string $jenisPegawai, ?string $pangkatGolongan): ?string
    {
        return self::parts($jenisPegawai, $pangkatGolongan)['pangkat'];
    }

    /**
     * @param iterable<int, Pegawai> $rows
     */
    public static function attachPegawaiPnsPangkatGolonganForExport(iterable $rows): void
    {
        foreach ($rows as $row) {
            if (!$row instanceof Pegawai) {
                continue;
            }
            $p = self::parts($row->jenis_pegawai, $row->pangkat_golongan);
            $row->setAttribute('pangkat_pns_nama', $p['pangkat'] ?? '');
            $row->setAttribute('golongan_pns', $p['golongan'] ?? '');
        }
    }

    private static function isPns(?string $jenisPegawai): bool
    {
        $n = self::normalizeText((string) $jenisPegawai);
        if ($n === '') {
            return false;
        }
        if (str_contains($n, 'pppk')) {
            return false;
        }

        return str_contains($n, 'pns');
    }

    private static function normalizeText(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    /**
     * @return array{0: string, 1: string}|null Pasangan [golongan Roman, ruang a-e]
     */
    private static function extractGolonganRuang(string $text): ?array
    {
        $t = $text;

        // "IV / e", "III-a", "Golongan II / c"
        if (preg_match('#\b(IV|III|II|I)\s*[/.\-_]\s*([a-e])\b#iu', $t, $m)) {
            return [mb_strtoupper($m[1]), strtolower($m[2])];
        }

        // "IIIa", "IIId" (tanpa pemisah)
        if (preg_match('#\b(IV|III|II|I)([a-e])\b#iu', $t, $m)) {
            return [mb_strtoupper($m[1]), strtolower($m[2])];
        }

        // Angka Arab: "3/a", "4 e"
        if (preg_match('#\b([1-4])\s*[/.\-_]\s*([a-e])\b#iu', $t, $m)) {
            $roman = self::digitToRoman((int) $m[1]);
            if ($roman !== null) {
                return [$roman, strtolower($m[2])];
            }
        }

        if (preg_match('#\b([1-4])([a-e])\b#iu', $t, $m)) {
            $roman = self::digitToRoman((int) $m[1]);
            if ($roman !== null) {
                return [$roman, strtolower($m[2])];
            }
        }

        return null;
    }

    private static function digitToRoman(int $d): ?string
    {
        return match ($d) {
            1 => 'I',
            2 => 'II',
            3 => 'III',
            4 => 'IV',
            default => null,
        };
    }
}
