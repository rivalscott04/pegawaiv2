<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel domain HRMS (pegawai v2) — selaras dengan hrms_full_dump_mysql.sql.
 * Tanpa migrasi ini, migrate:fresh menghapus tabel yang hanya dibuat lewat SQL dump.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_units', function (Blueprint $table) {
            $table->string('slug', 180)->primary();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('pegawai', function (Blueprint $table) {
            $table->char('nip', 18)->primary();
            $table->string('nama');
            $table->string('nip_lama', 32)->nullable();
            $table->string('tempat_tanggal_lahir', 500);
            $table->string('jenis_kelamin', 100);
            $table->string('agama', 100);
            $table->string('jenis_pegawai', 100);
            $table->string('jabatan', 1000);
            $table->string('unit_kerja', 500);
            $table->string('satker_induk', 500);
            $table->string('pangkat_golongan', 200);
            $table->string('pendidikan_terakhir', 500);
            $table->string('source_unit_slug', 180);
            $table->boolean('is_active')->default(true);
            $table->dateTime('last_seen_at')->nullable();
            $table->char('list_fingerprint', 64)->nullable();
            $table->char('detail_fingerprint', 64)->nullable();
            $table->timestamps();

            $table->index(['source_unit_slug', 'last_seen_at'], 'idx_pegawai_source_unit_last_seen');
            $table->index('is_active', 'idx_pegawai_is_active');

            $table->foreign('source_unit_slug')
                ->references('slug')
                ->on('source_units')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });

        Schema::create('riwayat_kenaikan_pangkat', function (Blueprint $table) {
            $table->char('nip', 18);
            $table->string('no', 16);
            $table->string('pangkat', 255);
            $table->string('golongan', 50);
            $table->dateTime('tmt')->nullable();
            $table->string('nomor_sk', 100);
            $table->timestamps();

            $table->primary(['nip', 'no']);
            $table->index(['nip', 'tmt'], 'idx_riwayat_nip_tmt');

            $table->foreign('nip')
                ->references('nip')
                ->on('pegawai')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_kenaikan_pangkat');
        Schema::dropIfExists('pegawai');
        Schema::dropIfExists('source_units');
    }
};
