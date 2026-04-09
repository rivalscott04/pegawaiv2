<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Mengisi ulang source_units, pegawai, dan riwayat_kenaikan_pangkat dari snapshot SQL di root repo.
 * Dump memakai urutan INSERT riwayat/pegawai yang campur; impor wajib dengan FOREIGN_KEY_CHECKS=0.
 */
class HrmsMysqlDumpSeeder extends Seeder
{
    public function run(): void
    {
        $conn = config('database.connections.mysql');
        if (($conn['driver'] ?? '') !== 'mysql') {
            $this->command?->warn('HrmsMysqlDumpSeeder: lewati (bukan koneksi mysql).');

            return;
        }

        if (! class_exists(\mysqli::class)) {
            throw new RuntimeException('Ekstensi mysqli diperlukan untuk mengimpor hrms_full_dump_mysql.sql.');
        }

        $repoRoot = dirname((string) base_path());
        $path = realpath($repoRoot.DIRECTORY_SEPARATOR.'hrms_full_dump_mysql.sql');
        if ($path === false || ! is_readable($path)) {
            throw new RuntimeException(
                'Berkas hrms_full_dump_mysql.sql tidak ada atau tidak terbaca. Letakkan di: '.$repoRoot.DIRECTORY_SEPARATOR.'hrms_full_dump_mysql.sql (sejajar folder backend).'
            );
        }

        $sql = file_get_contents($path);
        if ($sql === false || $sql === '') {
            throw new RuntimeException('Gagal membaca hrms_full_dump_mysql.sql.');
        }

        $port = (int) ($conn['port'] ?? 3306);
        $mysqli = @new \mysqli(
            $this->stripSocketHost($conn['host'] ?? '127.0.0.1'),
            $conn['username'] ?? 'root',
            (string) ($conn['password'] ?? ''),
            $conn['database'] ?? '',
            $port,
        );

        if ($mysqli->connect_error) {
            throw new RuntimeException('MySQL: '.$mysqli->connect_error);
        }

        $mysqli->set_charset('utf8mb4');

        $mysqli->query('SET FOREIGN_KEY_CHECKS=0');
        $mysqli->query('SET NAMES utf8mb4');

        $mysqli->query('TRUNCATE TABLE `riwayat_kenaikan_pangkat`');
        $mysqli->query('TRUNCATE TABLE `pegawai`');
        $mysqli->query('TRUNCATE TABLE `source_units`');

        if (! $mysqli->multi_query($sql)) {
            $err = $mysqli->error;
            $mysqli->close();
            throw new RuntimeException('Impor dump gagal di awal: '.$err);
        }

        do {
            if ($result = $mysqli->store_result()) {
                $result->free();
            }
        } while ($mysqli->next_result());

        if ($mysqli->errno) {
            $err = $mysqli->error;
            $mysqli->close();
            throw new RuntimeException('Impor dump gagal: '.$err);
        }

        $mysqli->query('SET FOREIGN_KEY_CHECKS=1');

        $countRes = $mysqli->query('SELECT COUNT(*) AS c FROM `pegawai`');
        $pegawaiCount = 0;
        if ($countRes instanceof \mysqli_result) {
            $row = $countRes->fetch_assoc();
            $pegawaiCount = (int) ($row['c'] ?? 0);
            $countRes->free();
        }
        $mysqli->close();

        if ($pegawaiCount < 1) {
            throw new RuntimeException(
                'Impor SQL selesai tetapi tabel pegawai masih kosong. Periksa isi hrms_full_dump_mysql.sql dan hak akses MySQL.'
            );
        }

        $this->command?->info("HRMS: impor ok — {$pegawaiCount} baris pegawai.");
    }

    /** mysqli tidak memakai "localhost" dengan socket seperti PDO; pakai 127.0.0.1. */
    private function stripSocketHost(string $host): string
    {
        return $host === 'localhost' ? '127.0.0.1' : $host;
    }
}
