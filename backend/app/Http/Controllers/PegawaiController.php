<?php

namespace App\Http\Controllers;

use App\Exports\PegawaiExport;
use App\Support\PegawaiExportFilename;
use App\Support\PegawaiLifecycle;
use App\Support\PnsPangkatGolongan;
use App\Support\PegawaiSpreadsheetIdentifiers;
use App\Jobs\GeneratePegawaiExportJob;
use App\Jobs\PegawaiControllerExportColumns;
use App\Models\Pegawai;
use App\Models\PegawaiExportTask;
use App\Models\RiwayatKenaikanPangkat;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PegawaiController extends Controller
{
    private const REQUIRED_COLUMNS = [
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
        'created_at',
        'updated_at',
    ];

    public function index(Request $request)
    {
        $this->authorize('viewAny', Pegawai::class);

        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'in:10,25,50,100,200'],
            'pangkat_golongan' => ['nullable', 'string', 'max:200'],
            'jenis_pegawai' => ['nullable', 'string', 'max:100'],
            'jenis_kelamin' => ['nullable', 'string', 'max:100'],
            'satker_induk' => ['nullable', 'string', 'max:500'],
            'unit_kerja' => ['nullable', 'string', 'max:500'],
            'jabatan' => ['nullable', 'string', 'max:1000'],
            'source_unit_slug' => ['nullable', 'string', 'max:180'],
            'is_active' => ['nullable', 'in:true,false'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $limit = (int) ($validated['limit'] ?? 10);
        $offset = max(0, ($page - 1) * $limit);
        $statusFilter = $validated['is_active'] ?? null;
        unset($validated['is_active']);

        $baseQuery = $this->pegawaiBaseQuery($validated, $request);
        $allRows = (clone $baseQuery)
            ->select(self::REQUIRED_COLUMNS)
            ->orderBy('nama')
            ->orderBy('nip')
            ->get();

        $rowsWithStatus = $allRows->map(function (Pegawai $row) {
            $computedIsActive = !PegawaiLifecycle::isRetiredByRule($row);
            $row->is_active = $computedIsActive;
            $pns = PnsPangkatGolongan::parts($row->jenis_pegawai, $row->pangkat_golongan);
            $row->setAttribute('pangkat_pns_nama', $pns['pangkat']);
            $row->setAttribute('golongan_pns', $pns['golongan']);

            return $row;
        });

        $active = $rowsWithStatus->where('is_active', true)->count();
        $inactive = $rowsWithStatus->where('is_active', false)->count();

        $filteredRows = $rowsWithStatus;
        if ($statusFilter === 'true') {
            $filteredRows = $filteredRows->where('is_active', true);
        } elseif ($statusFilter === 'false') {
            $filteredRows = $filteredRows->where('is_active', false);
        }

        $total = $filteredRows->count();
        $totalPages = (int) ceil($total / max(1, $limit));
        $rows = $filteredRows
            ->slice($offset, $limit)
            ->values();

        // Response format must match frontend contract exactly.
        return response()->json([
            'data' => $rows->values()->all(),
            'total' => $total,
            'inactive' => $inactive,
            'active' => $active,
            'totalPages' => $totalPages,
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    public function searchSurat(Request $request)
    {
        $this->authorize('viewAny', Pegawai::class);

        if ($this->pegawaiKabupatenLingkupInvalid($request)) {
            return response()->json([]);
        }

        $nip = trim((string) $request->query('nip', ''));
        $nama = trim((string) $request->query('nama', ''));

        $query = Pegawai::query()->select(self::REQUIRED_COLUMNS);

        if (strlen(trim($nip)) >= 4) {
            $query->where('nip', 'like', $nip . '%');
        } elseif (strlen(trim($nama)) >= 4) {
            $query->where(function (Builder $q) use ($nama) {
                $q->where('nama', 'like', '%' . $nama . '%');
            });
        } else {
            return response()->json([]);
        }

        $this->applyWilayahScopeToPegawaiQuery($query, $request);

        $rows = $query->orderBy('nip')->limit(20)->get();

        return response()->json($rows->values()->all());
    }

    public function tempatKerja(Request $request)
    {
        $this->authorize('viewAny', Pegawai::class);

        $validated = $request->validate([
            'nip' => ['nullable', 'string', 'max:18'],
            'satker_induk' => ['nullable', 'string', 'max:500'],
            'unit_kerja' => ['nullable', 'string', 'max:500'],
            'pangkat_golongan' => ['nullable', 'string', 'max:200'],
            'jabatan' => ['nullable', 'string', 'max:1000'],
            'nama' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'in:true,false'],
        ]);

        $query = $this->pegawaiBaseQuery($validated, $request);
        if (isset($validated['nama']) && $validated['nama'] !== '') {
            $query->where('nama', 'like', '%' . $validated['nama'] . '%');
        }
        if (isset($validated['nip']) && $validated['nip'] !== '') {
            $query->where('nip', 'like', '%' . $validated['nip'] . '%');
        }

        $rows = $query
            ->select([
                'nip',
                'nama',
                'pangkat_golongan',
                'satker_induk',
                'unit_kerja',
                'jabatan',
                'is_active',
            ])
            ->orderBy('nip')
            ->get();

        // Compute tempat_kerja in controller layer (no extra queries).
        $payload = $rows->map(function (Pegawai $p) {
            return [
                'nip' => $p->nip,
                'nama' => $p->nama,
                'pangkat_golongan' => $p->pangkat_golongan,
                'satker_induk' => $p->satker_induk,
                'unit_kerja' => $p->unit_kerja,
                'jabatan' => $p->jabatan,
                'is_active' => $p->is_active,
                'tempat_kerja' => ($p->satker_induk ?? '') . ' - ' . ($p->unit_kerja ?? ''),
            ];
        });

        return response()->json($payload->values()->all());
    }

    public function retiredCount(Request $request)
    {
        $this->authorize('viewAny', Pegawai::class);

        if ($this->pegawaiKabupatenLingkupInvalid($request)) {
            return response()->json([
                'retiredCount' => 0,
            ]);
        }

        $retiredQuery = Pegawai::query()->select(self::REQUIRED_COLUMNS);
        $this->applyWilayahScopeToPegawaiQuery($retiredQuery, $request);

        $retiredCount = $retiredQuery
            ->get()
            ->filter(fn (Pegawai $pegawai) => PegawaiLifecycle::isRetiredByRule($pegawai))
            ->count();

        return response()->json([
            'retiredCount' => $retiredCount,
        ]);
    }

    public function sdmOverview(Request $request)
    {
        $this->authorize('viewAny', Pegawai::class);

        if ($this->pegawaiKabupatenLingkupInvalid($request)) {
            return response()->json([
                'summary' => [
                    'total_pegawai' => 0,
                    'total_variasi_jabatan' => 0,
                    'rata_per_jabatan' => 0,
                ],
                'top_jabatan' => [],
                'cluster_ringkasan' => [],
            ]);
        }

        $validated = $request->validate([
            'top' => ['nullable', 'integer', 'min:5', 'max:50'],
            'pangkat_golongan' => ['nullable', 'string', 'max:200'],
            'jenis_pegawai' => ['nullable', 'string', 'max:100'],
            'jenis_kelamin' => ['nullable', 'string', 'max:100'],
            'satker_induk' => ['nullable', 'string', 'max:500'],
            'unit_kerja' => ['nullable', 'string', 'max:500'],
            'jabatan' => ['nullable', 'string', 'max:1000'],
            'source_unit_slug' => ['nullable', 'string', 'max:180'],
            'is_active' => ['nullable', 'in:true,false'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $topLimit = (int) ($validated['top'] ?? 10);
        $statusFilter = $validated['is_active'] ?? null;
        unset($validated['top'], $validated['is_active']);
        $query = $this->pegawaiBaseQuery($validated, $request);
        if ($statusFilter === 'true') {
            $query->where('is_active', true);
        } elseif ($statusFilter === 'false') {
            $query->where('is_active', false);
        }

        $jabatanExpr = "COALESCE(NULLIF(TRIM(jabatan), ''), '(Tidak ada jabatan)')";
        $rankedJabatan = (clone $query)
            ->selectRaw("{$jabatanExpr} as jabatan, COUNT(*) as total")
            ->groupBy(DB::raw($jabatanExpr))
            ->orderByDesc('total')
            ->orderBy('jabatan')
            ->get();

        $totalPegawai = (int) $rankedJabatan->sum(fn ($row) => (int) $row->total);
        $totalVariasi = (int) $rankedJabatan->count();

        $topJabatan = $rankedJabatan
            ->take($topLimit)
            ->values()
            ->map(function ($row) use ($totalPegawai) {
                $jumlah = (int) $row->total;
                return [
                    'jabatan' => (string) $row->jabatan,
                    'total' => $jumlah,
                    'persentase' => $totalPegawai > 0 ? round(($jumlah / $totalPegawai) * 100, 2) : 0.0,
                ];
            })
            ->all();

        $clusterRules = [
            ['label' => 'Guru', 'regex' => '/\bguru\b/i'],
            ['label' => 'Administrasi/Pelaksana', 'regex' => '/(penata|pelaksana|administrasi|pengadministrasi)/i'],
            ['label' => 'Penyuluh', 'regex' => '/penyuluh/i'],
            ['label' => 'Penghulu', 'regex' => '/penghulu/i'],
            ['label' => 'Pengawas', 'regex' => '/pengawas/i'],
            ['label' => 'Analis', 'regex' => '/\banalis\b/i'],
            ['label' => 'Arsiparis', 'regex' => '/arsiparis/i'],
            ['label' => 'Pranata Komputer', 'regex' => '/pranata komputer/i'],
            ['label' => 'Perencana', 'regex' => '/\bperencana\b/i'],
        ];

        $clusterSummary = [];
        foreach ($clusterRules as $rule) {
            $clusterSummary[$rule['label']] = [
                'label' => $rule['label'],
                'total' => 0,
                'variasi_jabatan' => 0,
            ];
        }
        $clusterSummary['Lainnya'] = [
            'label' => 'Lainnya',
            'total' => 0,
            'variasi_jabatan' => 0,
        ];

        foreach ($rankedJabatan as $row) {
            $jabatan = (string) $row->jabatan;
            $jumlah = (int) $row->total;
            $matched = false;

            foreach ($clusterRules as $rule) {
                if (preg_match($rule['regex'], $jabatan) === 1) {
                    $clusterSummary[$rule['label']]['total'] += $jumlah;
                    $clusterSummary[$rule['label']]['variasi_jabatan'] += 1;
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                $clusterSummary['Lainnya']['total'] += $jumlah;
                $clusterSummary['Lainnya']['variasi_jabatan'] += 1;
            }
        }

        $clusterRows = collect(array_values($clusterSummary))
            ->map(function (array $row) use ($totalPegawai) {
                return [
                    ...$row,
                    'persentase' => $totalPegawai > 0
                        ? round(((int) $row['total'] / $totalPegawai) * 100, 2)
                        : 0.0,
                ];
            })
            ->filter(fn (array $row) => (int) $row['total'] > 0)
            ->sortByDesc('total')
            ->values()
            ->all();

        return response()->json([
            'summary' => [
                'total_pegawai' => $totalPegawai,
                'total_variasi_jabatan' => $totalVariasi,
                'rata_per_jabatan' => $totalVariasi > 0 ? round($totalPegawai / $totalVariasi, 2) : 0,
            ],
            'top_jabatan' => $topJabatan,
            'cluster_ringkasan' => $clusterRows,
        ]);
    }

    public function filters(Request $request)
    {
        $this->authorize('viewAny', Pegawai::class);

        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
            'is_active' => ['nullable', 'in:true,false'],
            'source_unit_slug' => ['nullable', 'string', 'max:180'],
            'satker_induk' => ['nullable', 'string', 'max:500'],
            'unit_kerja' => ['nullable', 'string', 'max:500'],
            'pangkat_golongan' => ['nullable', 'string', 'max:200'],
            'jenis_pegawai' => ['nullable', 'string', 'max:100'],
            'jenis_kelamin' => ['nullable', 'string', 'max:100'],
        ]);

        if ($this->pegawaiKabupatenLingkupInvalid($request)) {
            return response()->json([
                'satker_induk' => [],
                'unit_kerja' => [],
                'pangkat_golongan' => [],
                'jabatan' => [],
                'jenis_pegawai' => [],
                'jenis_kelamin' => [],
                'source_unit_slug' => [],
            ]);
        }

        $limit = (int) ($validated['limit'] ?? 50);
        $isActiveParam = $validated['is_active'] ?? null;
        $isActiveBool = $isActiveParam === null ? null : ($isActiveParam === 'true');

        $base = Pegawai::query();
        if ($isActiveBool !== null) {
            $base->where('is_active', $isActiveBool);
        }

        $wilayahSlug = $this->wilayahRestrictionSlug($request);
        if ($wilayahSlug !== null) {
            $base->where('source_unit_slug', $wilayahSlug);
        } elseif (!empty($validated['source_unit_slug'] ?? '')) {
            $base->where('source_unit_slug', 'like', '%' . $validated['source_unit_slug'] . '%');
        }
        if (!empty($validated['satker_induk'] ?? '')) {
            $base->where('satker_induk', 'like', '%' . $validated['satker_induk'] . '%');
        }
        if (!empty($validated['unit_kerja'] ?? '')) {
            $base->where('unit_kerja', 'like', '%' . $validated['unit_kerja'] . '%');
        }
        if (!empty($validated['pangkat_golongan'] ?? '')) {
            $base->where('pangkat_golongan', 'like', '%' . $validated['pangkat_golongan'] . '%');
        }
        if (!empty($validated['jenis_pegawai'] ?? '')) {
            $base->where('jenis_pegawai', 'like', '%' . $validated['jenis_pegawai'] . '%');
        }
        if (!empty($validated['jenis_kelamin'] ?? '')) {
            $base->where('jenis_kelamin', 'like', '%' . $validated['jenis_kelamin'] . '%');
        }

        $payload = [
            'satker_induk' => $this->distinctValues($base, 'satker_induk', $limit),
            'unit_kerja' => $this->distinctValues($base, 'unit_kerja', $limit),
            'pangkat_golongan' => $this->distinctValues($base, 'pangkat_golongan', $limit),
            'jabatan' => $this->distinctValues($base, 'jabatan', $limit),
            'jenis_pegawai' => $this->distinctValues($base, 'jenis_pegawai', $limit),
            'jenis_kelamin' => $this->distinctValues($base, 'jenis_kelamin', $limit),
            'source_unit_slug' => $this->distinctValues($base, 'source_unit_slug', $limit),
        ];

        return response()->json($payload);
    }

    public function filter(Request $request)
    {
        return $this->index($request);
    }

    public function export(Request $request)
    {
        $this->authorize('export', Pegawai::class);

        $validated = $request->validate([
            'format' => ['nullable', 'in:csv,xlsx'],
            'scope' => ['nullable', 'in:page,all'],
            'separator' => ['nullable', 'in:comma,semicolon'],
            'page' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'pangkat_golongan' => ['nullable', 'string', 'max:200'],
            'jenis_pegawai' => ['nullable', 'string', 'max:100'],
            'jenis_kelamin' => ['nullable', 'string', 'max:100'],
            'satker_induk' => ['nullable', 'string', 'max:500'],
            'unit_kerja' => ['nullable', 'string', 'max:500'],
            'jabatan' => ['nullable', 'string', 'max:1000'],
            'source_unit_slug' => ['nullable', 'string', 'max:180'],
            'is_active' => ['nullable', 'in:true,false'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $format = (string) ($validated['format'] ?? 'xlsx');
        $scope = (string) ($validated['scope'] ?? 'page');
        $separator = (string) ($validated['separator'] ?? 'comma');
        $page = (int) ($validated['page'] ?? 1);
        $limit = (int) ($validated['limit'] ?? 10);

        if ($scope === 'all') {
            if ($this->pegawaiKabupatenLingkupInvalid($request)) {
                return response()->json([
                    'message' => 'Lingkup kabupaten memerlukan pemilihan unit kabupaten/kota pada profil user.',
                ], 422);
            }

            $exportFilters = $this->extractExportFilters($validated);
            $restrictSlug = $this->wilayahRestrictionSlug($request);
            if ($restrictSlug !== null) {
                $exportFilters['wilayah_source_unit_slug'] = $restrictSlug;
            }

            $task = PegawaiExportTask::query()->create([
                'id' => (string) Str::uuid(),
                'user_id' => (int) $request->user()->id,
                'status' => 'queued',
                'format' => $format,
                'scope' => 'all',
                'separator' => $separator,
                'filters' => $exportFilters,
            ]);

            dispatch(new GeneratePegawaiExportJob($task->id));

                        $task->refresh();

            $payload = [
                'success' => true,
                'task_id' => $task->id,
                'status' => $task->status,
                'download_url' => $task->status === 'completed'
                    ? "/pegawai/export/{$task->id}/download"
                    : null,
                'error_message' => $task->error_message,
                'message' => $task->status === 'completed'
                    ? 'Export selesai.'
                    : 'Export sedang diproses di background.',
            ];

            return response()->json($payload);
        }

        $query = $this->pegawaiBaseQuery($validated, $request)
            ->select(PegawaiControllerExportColumns::databaseColumns())
            ->orderBy('nip');

        $offset = max(0, ($page - 1) * $limit);
        $query->offset($offset)->limit($limit);

        $rows = $query->get();
        PegawaiLifecycle::attachTmtPensiunForExport($rows);
        $restrictSlug = $this->wilayahRestrictionSlug($request);
        $sourceUnitFilter = (string) ($validated['source_unit_slug'] ?? '');
        $rowCount = $rows->count();

        if ($format === 'csv') {
            $csvSeparator = $separator === 'semicolon' ? ';' : ',';
            $filename = PegawaiExportFilename::pageExportFilename('csv', $restrictSlug, $sourceUnitFilter, $page, $rowCount);
            return $this->streamCsv($rows, $csvSeparator, $filename);
        }

        $filename = PegawaiExportFilename::pageExportFilename('xlsx', $restrictSlug, $sourceUnitFilter, $page, $rowCount);
        return Excel::download(new PegawaiExport($rows, PegawaiControllerExportColumns::ALL), $filename);
    }

    public function exportStatus(Request $request, string $taskId)
    {
        $this->authorize('export', Pegawai::class);

        $task = PegawaiExportTask::query()
            ->where('id', $taskId)
            ->where('user_id', (int) $request->user()->id)
            ->first();

        if (!$task) {
            return response()->json(['message' => 'Task export tidak ditemukan.'], 404);
        }

        return response()->json([
            'success' => true,
            'task_id' => $task->id,
            'status' => $task->status,
            'format' => $task->format,
            'total_rows' => (int) $task->total_rows,
            'error_message' => $task->error_message,
            'download_url' => $task->status === 'completed'
                ? "/pegawai/export/{$task->id}/download"
                : null,
            'started_at' => optional($task->started_at)->toIso8601String(),
            'finished_at' => optional($task->finished_at)->toIso8601String(),
        ]);
    }

    public function exportDownload(Request $request, string $taskId)
    {
        $this->authorize('export', Pegawai::class);

        $task = PegawaiExportTask::query()
            ->where('id', $taskId)
            ->where('user_id', (int) $request->user()->id)
            ->first();

        if (!$task) {
            return response()->json(['message' => 'Task export tidak ditemukan.'], 404);
        }

        if ($task->status !== 'completed' || !$task->file_path || !$task->file_name) {
            return response()->json(['message' => 'File export belum siap.'], 409);
        }

        if (!Storage::disk('public')->exists($task->file_path)) {
            return response()->json(['message' => 'File export tidak ditemukan di storage.'], 404);
        }

        return Storage::disk('public')->download($task->file_path, $task->file_name);
    }

    public function show(Request $request, string $nip)
    {
        $pegawai = Pegawai::query()->where('nip', $nip)->first();
        if (!$pegawai) {
            return response()->json(['message' => 'Pegawai not found'], 404);
        }

        $this->authorize('view', $pegawai);

        $actor = $request->user();
        if ($actor instanceof User && $actor->shouldRestrictToWilayah()) {
            $actor->loadMissing('wilayahUnit');
            if ($actor->wilayahUnit === null) {
                abort(403, 'Akun lingkup kabupaten memerlukan unit wilayah.');
            }
        }

        $restrictSlug = $this->wilayahRestrictionSlug($request);
        if ($restrictSlug !== null && (string) $pegawai->source_unit_slug !== $restrictSlug) {
            abort(403, 'Forbidden');
        }

        $payload = $pegawai->only(self::REQUIRED_COLUMNS);
        $pns = PnsPangkatGolongan::parts($pegawai->jenis_pegawai, $pegawai->pangkat_golongan);
        $payload['pangkat_pns_nama'] = $pns['pangkat'];
        $payload['golongan_pns'] = $pns['golongan'];

        return response()->json([
            'success' => true,
            'data' => $payload,
        ]);
    }

    public function update(Request $request, string $nip)
    {
        $pegawai = Pegawai::query()->where('nip', $nip)->first();
        if (!$pegawai) {
            return response()->json(['message' => 'Pegawai not found'], 404);
        }

        $this->authorize('update', $pegawai);

        $actor = $request->user();
        $fullEdit = $actor !== null && $actor->hasPermission('pegawai.edit_all');

        $allowedKeys = $fullEdit
            ? ['nama', 'nip_lama', 'tempat_tanggal_lahir', 'jenis_kelamin', 'agama', 'jenis_pegawai', 'jabatan', 'unit_kerja', 'satker_induk', 'pangkat_golongan', 'pendidikan_terakhir', 'source_unit_slug', 'is_active']
            : ['nama', 'tempat_tanggal_lahir', 'jenis_kelamin', 'agama', 'jenis_pegawai', 'jabatan', 'unit_kerja', 'satker_induk', 'pangkat_golongan', 'pendidikan_terakhir', 'is_active'];

        $rules = [
            'nama' => ['sometimes', 'required', 'string', 'max:255'],
            'nip_lama' => ['nullable', 'string', 'max:32'],
            'tempat_tanggal_lahir' => ['sometimes', 'required', 'string', 'max:500'],
            'jenis_kelamin' => ['sometimes', 'required', 'string', 'max:100'],
            'agama' => ['sometimes', 'required', 'string', 'max:100'],
            'jenis_pegawai' => ['sometimes', 'required', 'string', 'max:100'],
            'jabatan' => ['sometimes', 'required', 'string', 'max:1000'],
            'unit_kerja' => ['sometimes', 'required', 'string', 'max:500'],
            'satker_induk' => ['sometimes', 'required', 'string', 'max:500'],
            'pangkat_golongan' => ['sometimes', 'required', 'string', 'max:200'],
            'pendidikan_terakhir' => ['sometimes', 'required', 'string', 'max:500'],
            'source_unit_slug' => ['sometimes', 'required', 'string', 'max:180', Rule::exists('source_units', 'slug')],
            'is_active' => ['sometimes', 'boolean'],
        ];

        $validated = $request->validate($rules);

        $toUpdate = array_intersect_key($validated, array_flip($allowedKeys));

        if ($toUpdate === []) {
            return response()->json([
                'success' => true,
                'data' => $pegawai->fresh()?->only(self::REQUIRED_COLUMNS),
            ]);
        }

        $pegawai->fill($toUpdate);
        $pegawai->save();

        return response()->json([
            'success' => true,
            'data' => $pegawai->fresh()?->only(self::REQUIRED_COLUMNS),
        ]);
    }

    public function getRiwayatKenaikanPangkat(Request $request, string $nip)
    {
        $this->authorize('viewAny', Pegawai::class);

        $page = max((int) $request->query('page', 1), 1);
        $limit = (int) $request->query('limit', 50);
        if ($limit < 1) $limit = 50;
        if ($limit > 500) $limit = 500;

        $pegawaiRow = Pegawai::query()->where('nip', $nip)->first(['nip', 'source_unit_slug']);
        if (!$pegawaiRow) {
            return response()->json(['message' => 'Pegawai not found'], 404);
        }

        $actor = $request->user();
        if ($actor instanceof User && $actor->shouldRestrictToWilayah()) {
            $actor->loadMissing('wilayahUnit');
            if ($actor->wilayahUnit === null) {
                abort(403, 'Akun lingkup kabupaten memerlukan unit wilayah.');
            }
        }

        $restrictSlug = $this->wilayahRestrictionSlug($request);
        if ($restrictSlug !== null && (string) $pegawaiRow->source_unit_slug !== $restrictSlug) {
            abort(403, 'Forbidden');
        }

        $query = RiwayatKenaikanPangkat::query()
            ->where('nip', $nip)
            ->orderByRaw('CAST(`no` AS UNSIGNED) ASC')
            ->orderBy('no', 'ASC');

        $total = (clone $query)->count();
        $items = $query
            ->forPage($page, $limit)
            ->get();

        return response()->json([
            'nip' => $nip,
            'data' => $items->values()->all(),
            'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)) ?: 1,
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    /**
     * Build base query for pegawai list/search/tempat-kerja.
     * This function MUST not include pagination and MUST not include select(*).
     */
    private function pegawaiBaseQuery(array $validated, Request $request): Builder
    {
        $query = Pegawai::query();

        if ($this->pegawaiKabupatenLingkupInvalid($request)) {
            return $query->whereRaw('1 = 0');
        }

        $restrictSlug = $this->wilayahRestrictionSlug($request);
        if ($restrictSlug !== null) {
            $query->where('source_unit_slug', $restrictSlug);
        } elseif (!empty($validated['source_unit_slug'] ?? '')) {
            $query->where('source_unit_slug', 'like', '%' . $validated['source_unit_slug'] . '%');
        }

        if (!empty($validated['pangkat_golongan'] ?? '')) {
            $query->where('pangkat_golongan', 'like', '%' . $validated['pangkat_golongan'] . '%');
        }
        if (!empty($validated['jenis_pegawai'] ?? '')) {
            $query->where('jenis_pegawai', 'like', '%' . $validated['jenis_pegawai'] . '%');
        }
        if (!empty($validated['jenis_kelamin'] ?? '')) {
            $query->where('jenis_kelamin', 'like', '%' . $validated['jenis_kelamin'] . '%');
        }
        if (!empty($validated['satker_induk'] ?? '')) {
            $query->where('satker_induk', 'like', '%' . $validated['satker_induk'] . '%');
        }
        if (!empty($validated['unit_kerja'] ?? '')) {
            $query->where('unit_kerja', 'like', '%' . $validated['unit_kerja'] . '%');
        }
        if (!empty($validated['jabatan'] ?? '')) {
            $query->where('jabatan', 'like', '%' . $validated['jabatan'] . '%');
        }

        if (!empty($validated['search'] ?? '')) {
            $search = (string) $validated['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('nama', 'like', '%' . $search . '%')
                    ->orWhere('nip', 'like', '%' . $search . '%')
                    ->orWhere('jabatan', 'like', '%' . $search . '%');
            });
        }

        return $query;
    }

    private function applyWilayahScopeToPegawaiQuery(Builder $query, Request $request): void
    {
        if ($this->pegawaiKabupatenLingkupInvalid($request)) {
            $query->whereRaw('1 = 0');

            return;
        }

        $slug = $this->wilayahRestrictionSlug($request);
        if ($slug !== null) {
            $query->where('source_unit_slug', $slug);
        }
    }

    /**
     * Lingkup kabupaten tanpa unit wilayah = konfigurasi tidak valid; jangan tampilkan data.
     */
    private function pegawaiKabupatenLingkupInvalid(Request $request): bool
    {
        $user = $request->user();

        return $user instanceof User
            && !$user->isSuperAdmin()
            && $user->hasPermission('pegawai.lingkup.kabupaten')
            && $user->wilayah_unit_id === null;
    }

    private function wilayahRestrictionSlug(Request $request): ?string
    {
        $user = $request->user();
        if (!$user instanceof User || !$user->shouldRestrictToWilayah()) {
            return null;
        }
        $user->loadMissing('wilayahUnit');

        return $user->wilayahUnit?->slug;
    }

    /**
     * DISTINCT helper without N+1 loops.
     * Returned array is already filtered for non-null and non-empty values.
     */
    private function distinctValues(Builder $base, string $column, int $limit): array
    {
        $allowedColumns = [
            'satker_induk',
            'unit_kerja',
            'pangkat_golongan',
            'jabatan',
            'jenis_pegawai',
            'jenis_kelamin',
            'source_unit_slug',
        ];
        if (!in_array($column, $allowedColumns, true)) {
            return [];
        }

        return (clone $base)
            ->selectRaw("DISTINCT {$column} as value")
            ->whereNotNull($column)
            ->where($column, '<>', '')
            ->orderBy($column)
            ->limit($limit)
            ->pluck('value')
            ->values()
            ->all();
    }

    private function streamCsv($rows, string $separator, string $filename): StreamedResponse
    {
        $columns = PegawaiControllerExportColumns::ALL;

        return response()->streamDownload(function () use ($rows, $separator, $columns) {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $columns, $separator);

            foreach ($rows as $row) {
                $line = [];
                foreach ($columns as $column) {
                    $line[] = PegawaiSpreadsheetIdentifiers::csvFieldForExcel($column, $row->{$column} ?? null);
                }
                fputcsv($handle, $line, $separator);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function extractExportFilters(array $validated): array
    {
        $allowed = [
            'pangkat_golongan',
            'jenis_pegawai',
            'jenis_kelamin',
            'satker_induk',
            'unit_kerja',
            'jabatan',
            'source_unit_slug',
            'is_active',
            'search',
        ];

        $filters = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $validated)) {
                $filters[$key] = $validated[$key];
            }
        }

        return $filters;
    }
}

