<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PegawaiExportTask extends Model
{
    protected $table = 'pegawai_export_tasks';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'status',
        'format',
        'scope',
        'separator',
        'filters',
        'file_name',
        'file_path',
        'total_rows',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}

