<?php

namespace App\Jobs;

use App\Exports\PegawaiQueryExport;
use App\Models\Pegawai;
use App\Support\PegawaiLifecycle;
use App\Support\PegawaiSpreadsheetIdentifiers;
use App\Models\PegawaiExportTask;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class GeneratePegawaiExportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public string $taskId)
    {
        //
    }

    public function handle(): void
    {
        $task = PegawaiExportTask::query()->find($this->taskId);
        if (!$task || $task->status !== 'queued') {
            return;
        }

        $task->status = 'processing';
        $task->started_at = now();
        $task->save();

        try {
            $filters = is_array($task->filters) ? $task->filters : [];
            $query = $this->buildQuery($filters);
            $total = (clone $query)->count();

            $timestamp = now()->format('Ymd_His');
            $ext = $task->format === 'csv' ? 'csv' : 'xlsx';
            $filename = "pegawai_{$timestamp}_all_{$total}.{$ext}";
            $path = "exports/pegawai/{$task->id}/{$filename}";

            if ($task->format === 'csv') {
                $this->storeCsvFromQuery($query, $task->separator === 'semicolon' ? ';' : ',', $path);
            } else {
                Excel::store(new PegawaiQueryExport($query, PegawaiControllerExportColumns::ALL), $path, 'public');
            }

            $task->status = 'completed';
            $task->file_name = $filename;
            $task->file_path = $path;
            $task->total_rows = (int) $total;
            $task->finished_at = now();
            $task->error_message = null;
            $task->save();
        } catch (Throwable $e) {
            $task->status = 'failed';
            $task->error_message = $e->getMessage();
            $task->finished_at = now();
            $task->save();
        }
    }

    private function buildQuery(array $filters): Builder
    {
        $query = Pegawai::query()->select(PegawaiControllerExportColumns::databaseColumns())->orderBy('nip');

        if (!empty($filters['pangkat_golongan'] ?? '')) {
            $query->where('pangkat_golongan', 'like', '%' . $filters['pangkat_golongan'] . '%');
        }
        if (!empty($filters['jenis_pegawai'] ?? '')) {
            $query->where('jenis_pegawai', 'like', '%' . $filters['jenis_pegawai'] . '%');
        }
        if (!empty($filters['satker_induk'] ?? '')) {
            $query->where('satker_induk', 'like', '%' . $filters['satker_induk'] . '%');
        }
        if (!empty($filters['unit_kerja'] ?? '')) {
            $query->where('unit_kerja', 'like', '%' . $filters['unit_kerja'] . '%');
        }
        if (!empty($filters['jabatan'] ?? '')) {
            $query->where('jabatan', 'like', '%' . $filters['jabatan'] . '%');
        }
        if (!empty($filters['wilayah_source_unit_slug'] ?? '')) {
            $query->where('source_unit_slug', $filters['wilayah_source_unit_slug']);
        } elseif (!empty($filters['source_unit_slug'] ?? '')) {
            $query->where('source_unit_slug', 'like', '%' . $filters['source_unit_slug'] . '%');
        }
        if (!empty($filters['search'] ?? '')) {
            $search = (string) $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', '%' . $search . '%')
                    ->orWhere('nip', 'like', '%' . $search . '%')
                    ->orWhere('jabatan', 'like', '%' . $search . '%');
            });
        }
        if (!empty($filters['is_active'] ?? '')) {
            $query->where('is_active', $filters['is_active'] === 'true');
        }

        return $query;
    }

    private function storeCsvFromQuery(Builder $query, string $separator, string $path): void
    {
        $absolutePath = Storage::disk('public')->path($path);
        $dir = dirname($absolutePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $handle = fopen($absolutePath, 'w');
        if ($handle === false) {
            throw new \RuntimeException('Gagal membuat file CSV export.');
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, PegawaiControllerExportColumns::ALL, $separator);

        $columns = PegawaiControllerExportColumns::ALL;
        (clone $query)->chunk(500, function ($rows) use ($handle, $separator, $columns) {
            foreach ($rows as $row) {
                $line = [];
                foreach ($columns as $column) {
                    $value = null;
                    if ($column === 'tmt_pensiun') {
                        $d = PegawaiLifecycle::akanPensiunDate($row);
                        $value = $d !== null ? $d->format('Y-m-d') : null;
                    } else {
                        $value = $row->{$column} ?? null;
                    }
                    $line[] = PegawaiSpreadsheetIdentifiers::csvFieldForExcel($column, $value);
                }
                fputcsv($handle, $line, $separator);
            }
        });

        fclose($handle);
    }
}
