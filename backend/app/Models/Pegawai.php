<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pegawai extends Model
{
    protected $table = 'pegawai';
    protected $primaryKey = 'nip';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * We rely on DB-managed timestamps (per schema dump).
     * For read endpoints, this is mainly to avoid Eloquent touching dates.
     */
    public $timestamps = false;

    protected $fillable = [
        'nip',
        'nama',
        'nip_lama',
        'tempat_tanggal_lahir',
        'jenis_kelamin',
        'agama',
        'jenis_pegawai',
        'jabatan',
        'unit_kerja',
        'satker_induk',
        'pangkat_golongan',
        'pendidikan_terakhir',
        'source_unit_slug',
        'is_active',
        'last_seen_at',
        'list_fingerprint',
        'detail_fingerprint',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_seen_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function riwayatKenaikanPangkat(): HasMany
    {
        return $this->hasMany(RiwayatKenaikanPangkat::class, 'nip', 'nip');
    }
}

