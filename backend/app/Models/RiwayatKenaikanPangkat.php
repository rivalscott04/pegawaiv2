<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatKenaikanPangkat extends Model
{
    protected $table = 'riwayat_kenaikan_pangkat';
    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = null;

    protected $fillable = [
        'nip',
        'no',
        'pangkat',
        'golongan',
        'tmt',
        'nomor_sk',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'tmt' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'nip', 'nip');
    }
}
