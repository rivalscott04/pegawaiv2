<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Pegawai;
use App\Support\EmployeeIndukUnit;
use App\Support\PegawaiLifecycle;
use App\Support\PegawaiWilayah;
use App\Http\Requests\EmployeeIndexRequest;
use App\Http\Requests\EmployeeByLocationRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class EmployeeController extends Controller
{
	/**
	 * Public index: sama seperti index tapi tanpa auth/policy.
	 * HATI-HATI: endpoint ini terbuka untuk umum, jadi gunakan dengan bijak.
	 */
	public function publicIndex(EmployeeIndexRequest $request)
	{
		$validated = $request->validated();
		
		$perPageRaw = $validated['per_page'] ?? '15';
		$perPage = 15;
		if (is_string($perPageRaw) && strtolower($perPageRaw) === 'all') {
			// Untuk endpoint publik, jangan izinkan 'all' untuk menghindari beban berat
			$perPage = 200;
		} else {
			$perPage = (int) $perPageRaw;
			$allowed = [10, 25, 50, 100, 200];
			if (!in_array($perPage, $allowed, true)) {
				$perPage = 15;
			}
		}
		
		// Enforce maximum limit yang lebih ketat untuk publik
		$perPage = min($perPage, 200); // Maksimum 200 records per page untuk public
		$search = $validated['search'] ?? '';
		$induk = $validated['induk'] ?? '';
		$jabatan = $validated['jabatan'] ?? '';
		$kodeJabatan = $validated['kode_jabatan'] ?? '';
		$status = $validated['status'] ?? ''; // 'aktif' or 'pensiun'
		$golongan = $validated['golongan'] ?? '';

		$query = Employee::query();
		if ($search !== '') {
			$query->where(function ($q) use ($search) {
				$q->where('NAMA_LENGKAP', 'like', "%$search%")
					->orWhere('SATUAN_KERJA', 'like', "%$search%")
					->orWhere('KET_JABATAN', 'like', "%$search%")
					->orWhere('NIP_BARU', 'like', "%$search%")
					->orWhere('KODE_JABATAN', 'like', "%$search%")
					->orWhere('KODE_SATUAN_KERJA', 'like', "%$search%");
			});
		}

		// Filter by status (aktif/pensiun)
		if ($status === 'aktif') {
			$query->where(function ($q) {
				$q->whereNull('TMT_PENSIUN')
					->orWhere('TMT_PENSIUN', '>', now()->toDateString());
			});
		} elseif ($status === 'pensiun') {
			$query->whereNotNull('TMT_PENSIUN')
				->where('TMT_PENSIUN', '<=', now()->toDateString());
		}

		// Filter by golongan (with mapping: non-standard -> PPPK)
		if ($golongan !== '') {
			if ($golongan === 'PPPK') {
				$standarPNS = ['I/a', 'I/b', 'I/c', 'I/d', 'II/a', 'II/b', 'II/c', 'II/d', 'III/a', 'III/b', 'III/c', 'III/d', 'IV/a', 'IV/b', 'IV/c', 'IV/d'];
				$query->whereNotIn('GOL_RUANG', $standarPNS)
					->whereNotNull('GOL_RUANG')
					->where('GOL_RUANG', '!=', '');
			} else {
				$query->where('GOL_RUANG', $golongan);
			}
		}

		if ($induk !== '' || $kodeJabatan !== '' || $jabatan !== '') {
			$pageNum = max(1, (int) ($validated['page'] ?? 1));
			$filtered = collect();
			$query->chunk(1000, function ($chunk) use (&$filtered, $induk, $kodeJabatan, $jabatan, $status, $golongan) {
				foreach ($chunk as $e) {
					if ($induk !== '') {
						$computed = EmployeeIndukUnit::compute($e->SATUAN_KERJA, $e->kab_kota, $e->KET_JABATAN ?? null);
						if ($computed !== $induk) {
							continue;
						}
					}
					if ($kodeJabatan !== '') {
						if ($e->KODE_JABATAN !== $kodeJabatan) {
							continue;
						}
					} elseif ($jabatan !== '') {
						if ($e->KET_JABATAN !== $jabatan) {
							continue;
						}
					}
					if ($golongan !== '') {
						if ($golongan === 'PPPK') {
							if ($this->isStandardPNSGolongan($e->GOL_RUANG)) {
								continue;
							}
							if (empty(trim($e->GOL_RUANG ?? ''))) {
								continue;
							}
						} else {
							if ($e->GOL_RUANG !== $golongan) {
								continue;
							}
						}
					}
					if ($status === 'aktif' || $status === 'pensiun') {
						$today = now()->toDateString();
						if ($e->TMT_PENSIUN === null) {
							if ($status !== 'aktif') continue;
						} elseif ($status === 'aktif') {
							if ($e->TMT_PENSIUN <= $today) continue;
						} else {
							if ($e->TMT_PENSIUN > $today) continue;
						}
					}
					$filtered->push($e);
				}
			});

			$kanwilName = 'Kantor Wilayah Kementerian Agama Provinsi Nusa Tenggara Barat';
			$filtered->transform(function ($e) {
				$e->induk_unit = EmployeeIndukUnit::compute($e->SATUAN_KERJA, $e->kab_kota, $e->KET_JABATAN ?? null);
				$e->GOL_RUANG = $this->normalizeGolongan($e->GOL_RUANG);
				return $e;
			})->sortBy(function ($e) use ($kanwilName) {
				$induk = $e->induk_unit ?? '';
				if ($induk === $kanwilName) {
					return '0_' . strtolower($e->NAMA_LENGKAP ?? '');
				}
				return '1_' . strtolower($induk) . '_' . strtolower($e->NAMA_LENGKAP ?? '');
			})->values();
			
			$totalCount = $filtered->count();
			$items = $filtered->slice(($pageNum - 1) * $perPage, $perPage)->values();
			return response()->json([
				'success' => true,
				'data' => [
					'data' => $items,
					'total' => $totalCount,
					'per_page' => $perPage,
					'current_page' => $pageNum,
				],
			]);
		}

		if ($kodeJabatan !== '') {
			$query->where('KODE_JABATAN', $kodeJabatan);
		} elseif ($jabatan !== '') {
			$query->where('KET_JABATAN', $jabatan);
		}
	
		$all = collect();
		$query->chunk(1000, function ($chunk) use (&$all) {
			$all = $all->merge($chunk);
		});
	
		$all->transform(function ($e) {
			$e->induk_unit = EmployeeIndukUnit::compute($e->SATUAN_KERJA, $e->kab_kota, $e->KET_JABATAN ?? null);
			$e->GOL_RUANG = $this->normalizeGolongan($e->GOL_RUANG);
			return $e;
		});
	
		$kanwilName = 'Kantor Wilayah Kementerian Agama Provinsi Nusa Tenggara Barat';
		$sorted = $all->sortBy(function ($e) use ($kanwilName) {
			$induk = $e->induk_unit ?? '';
			if ($induk === $kanwilName) {
				return '0_' . strtolower($e->NAMA_LENGKAP ?? '');
			}
			return '1_' . strtolower($induk) . '_' . strtolower($e->NAMA_LENGKAP ?? '');
		})->values();
	
		$pageNum = max(1, (int) ($validated['page'] ?? 1));
		$totalCount = $sorted->count();
		$paginatedData = $sorted->slice(($pageNum - 1) * $perPage, $perPage)->values();
	
		return response()->json([
			'success' => true,
			'data' => [
				'data' => $paginatedData,
				'total' => $totalCount,
				'per_page' => $perPage,
				'current_page' => $pageNum,
				'last_page' => (int) ceil($totalCount / $perPage),
				'from' => $totalCount > 0 ? (($pageNum - 1) * $perPage) + 1 : 0,
				'to' => min($pageNum * $perPage, $totalCount),
			],
		]);
	}

	/**
	 * Public endpoint untuk mendapatkan semua data pegawai tanpa pagination
	 * Maksimal 5000 records untuk menghindari beban berat pada server
	 */
	public function publicAll(Request $request)
	{
		$query = Employee::query();
		
		// Optional filters
		$search = $request->query('search', '');
		$status = $request->query('status', '');
		
		if ($search !== '') {
			$query->where(function ($q) use ($search) {
				$q->where('NAMA_LENGKAP', 'like', "%$search%")
					->orWhere('SATUAN_KERJA', 'like', "%$search%")
					->orWhere('KET_JABATAN', 'like', "%$search%")
					->orWhere('NIP_BARU', 'like', "%$search%");
			});
		}

		if ($status === 'aktif') {
			$query->where(function ($q) {
				$q->whereNull('TMT_PENSIUN')
					->orWhere('TMT_PENSIUN', '>', now()->toDateString());
			});
		} elseif ($status === 'pensiun') {
			$query->whereNotNull('TMT_PENSIUN')
				->where('TMT_PENSIUN', '<=', now()->toDateString());
		}

		// Get all data dengan chunking untuk menghindari memory issue
		$all = collect();
		$query->chunk(1000, function ($chunk) use (&$all) {
			$all = $all->merge($chunk);
		});

		// Compute induk_unit dan normalize golongan untuk semua records
		$all->transform(function ($e) {
			$e->induk_unit = EmployeeIndukUnit::compute($e->SATUAN_KERJA, $e->kab_kota, $e->KET_JABATAN ?? null);
			$e->GOL_RUANG = $this->normalizeGolongan($e->GOL_RUANG);
			return $e;
		});

		// Sort: Kanwil first, then alphabetically
		$kanwilName = 'Kantor Wilayah Kementerian Agama Provinsi Nusa Tenggara Barat';
		$sorted = $all->sortBy(function ($e) use ($kanwilName) {
			$induk = $e->induk_unit ?? '';
			if ($induk === $kanwilName) {
				return '0_' . strtolower($e->NAMA_LENGKAP ?? '');
			}
			return '1_' . strtolower($induk) . '_' . strtolower($e->NAMA_LENGKAP ?? '');
		})->values();

		// Limit maksimal 5000 records untuk public endpoint
		$totalCount = $sorted->count();
		$limitedData = $sorted->take(5000)->values();

		return response()->json([
			'success' => true,
			'data' => $limitedData,
			'total' => $totalCount,
			'returned' => $limitedData->count(),
			'message' => $totalCount > 5000 ? 'Data dibatasi maksimal 5000 records. Gunakan endpoint /api/public/employees dengan pagination untuk data lengkap.' : null,
		]);
	}

	public function index(EmployeeIndexRequest $request)
	{
		$this->authorize('viewAny', Employee::class);

		$validated = $request->validated();
		
		$perPageRaw = $validated['per_page'] ?? '15';
		$perPage = 15;
		if (is_string($perPageRaw) && strtolower($perPageRaw) === 'all') {
			// Don't allow 'all' in production
			if (app()->environment('production')) {
				$perPage = 1500; // Max in production
			} else {
				$perPage = 10000; // Max in development
			}
		} else {
			$perPage = (int) $perPageRaw;
			$allowed = [10, 25, 50, 100, 200, 1500];
			if (!in_array($perPage, $allowed, true)) {
				$perPage = 15;
			}
		}
		
		// Enforce maximum limit
		$perPage = min($perPage, 1500); // Maximum 1500 records per page
		$search = $validated['search'] ?? '';
		$induk = $validated['induk'] ?? '';
		$jabatan = $validated['jabatan'] ?? '';
		$kodeJabatan = $validated['kode_jabatan'] ?? '';
		$status = $validated['status'] ?? ''; // 'aktif' or 'pensiun'
		$golongan = $validated['golongan'] ?? '';

		$query = Employee::query();
		if ($search !== '') {
			$query->where(function ($q) use ($search) {
				$q->where('NAMA_LENGKAP', 'like', "%$search%")
					->orWhere('SATUAN_KERJA', 'like', "%$search%")
					->orWhere('KET_JABATAN', 'like', "%$search%")
					->orWhere('NIP_BARU', 'like', "%$search%")
					->orWhere('KODE_JABATAN', 'like', "%$search%")
					->orWhere('KODE_SATUAN_KERJA', 'like', "%$search%");
			});
		}

		// Filter by status (aktif/pensiun)
		// Aktif: TMT_PENSIUN null atau TMT_PENSIUN > hari ini (belum sampai tanggal pensiun)
		// Pensiun: TMT_PENSIUN tidak null dan TMT_PENSIUN <= hari ini (sudah lewat atau sama dengan tanggal pensiun)
		if ($status === 'aktif') {
			$query->where(function ($q) {
				$q->whereNull('TMT_PENSIUN')
					->orWhere('TMT_PENSIUN', '>', now()->toDateString());
			});
		} elseif ($status === 'pensiun') {
			$query->whereNotNull('TMT_PENSIUN')
				->where('TMT_PENSIUN', '<=', now()->toDateString());
		}

		// Filter by golongan (with mapping: non-standard -> PPPK)
		if ($golongan !== '') {
			if ($golongan === 'PPPK') {
				// Filter untuk PPPK: semua golongan yang bukan standar PNS
				$standarPNS = ['I/a', 'I/b', 'I/c', 'I/d', 'II/a', 'II/b', 'II/c', 'II/d', 'III/a', 'III/b', 'III/c', 'III/d', 'IV/a', 'IV/b', 'IV/c', 'IV/d'];
				$query->whereNotIn('GOL_RUANG', $standarPNS)
					->whereNotNull('GOL_RUANG')
					->where('GOL_RUANG', '!=', '');
			} else {
				// Filter untuk golongan standar PNS
				$query->where('GOL_RUANG', $golongan);
			}
		}

		if ($induk !== '' || $kodeJabatan !== '' || $jabatan !== '') {
		// Manual filter by canonical induk with computed mapping, then manual paginate
		$pageNum = max(1, (int) ($validated['page'] ?? 1));
			// Use chunking to avoid memory exhaustion for large datasets
			$filtered = collect();
			$query->chunk(1000, function ($chunk) use (&$filtered, $induk, $kodeJabatan, $jabatan, $status, $golongan) {
				foreach ($chunk as $e) {
					// Filter by induk
					if ($induk !== '') {
						$computed = EmployeeIndukUnit::compute($e->SATUAN_KERJA, $e->kab_kota, $e->KET_JABATAN ?? null);
						if ($computed !== $induk) {
							continue;
						}
					}
					// Filter by kode_jabatan or jabatan
					if ($kodeJabatan !== '') {
						if ($e->KODE_JABATAN !== $kodeJabatan) {
							continue;
						}
					} elseif ($jabatan !== '') {
						if ($e->KET_JABATAN !== $jabatan) {
							continue;
						}
					}
					// Filter by golongan (with mapping: non-standard -> PPPK)
					if ($golongan !== '') {
						if ($golongan === 'PPPK') {
							// Untuk PPPK: semua yang bukan standar PNS
							if ($this->isStandardPNSGolongan($e->GOL_RUANG)) {
								continue;
							}
							// Skip jika GOL_RUANG null atau kosong
							if (empty(trim($e->GOL_RUANG ?? ''))) {
								continue;
							}
						} else {
							// Untuk golongan standar PNS
							if ($e->GOL_RUANG !== $golongan) {
								continue;
							}
						}
					}
					// Filter by status
					if ($status === 'aktif' || $status === 'pensiun') {
						$today = now()->toDateString();
						if ($e->TMT_PENSIUN === null) {
							if ($status !== 'aktif') continue;
						} elseif ($status === 'aktif') {
							if ($e->TMT_PENSIUN <= $today) continue;
						} else {
							if ($e->TMT_PENSIUN > $today) continue;
						}
					}
					$filtered->push($e);
				}
			});
			// Sort ALL filtered data first (before pagination) by induk_unit (Kanwil first)
			$kanwilName = 'Kantor Wilayah Kementerian Agama Provinsi Nusa Tenggara Barat';
			$filtered->transform(function ($e) {
				$e->induk_unit = EmployeeIndukUnit::compute($e->SATUAN_KERJA, $e->kab_kota, $e->KET_JABATAN ?? null);
				// Normalize golongan untuk display: non-standar -> PPPK
				$e->GOL_RUANG = $this->normalizeGolongan($e->GOL_RUANG);
				return $e;
			})->sortBy(function ($e) use ($kanwilName) {
				// Sort: Kanwil first, then alphabetically by induk_unit, then by NAMA_LENGKAP
				$induk = $e->induk_unit ?? '';
				if ($induk === $kanwilName) {
					return '0_' . strtolower($e->NAMA_LENGKAP ?? '');
				}
				return '1_' . strtolower($induk) . '_' . strtolower($e->NAMA_LENGKAP ?? '');
			})->values();
			
			$totalCount = $filtered->count();
			// Apply pagination with max limit
			$items = $filtered->slice(($pageNum - 1) * $perPage, $perPage)->values();
			return response()->json([
				'success' => true,
				'data' => [
					'data' => $items,
					'total' => $totalCount,
					'per_page' => $perPage,
					'current_page' => $pageNum,
				],
			]);
		}

	if ($kodeJabatan !== '') {
		$query->where('KODE_JABATAN', $kodeJabatan);
	} elseif ($jabatan !== '') {
		$query->where('KET_JABATAN', $jabatan);
	}
	
	// Get data with chunking, compute induk_unit, sort, then paginate manually
	$all = collect();
	$query->chunk(1000, function ($chunk) use (&$all) {
		$all = $all->merge($chunk);
	});
	
	// Compute induk_unit for all records and normalize golongan
	$all->transform(function ($e) {
		$e->induk_unit = EmployeeIndukUnit::compute($e->SATUAN_KERJA, $e->kab_kota, $e->KET_JABATAN ?? null);
		// Normalize golongan untuk display: non-standar -> PPPK
		$e->GOL_RUANG = $this->normalizeGolongan($e->GOL_RUANG);
		return $e;
	});
	
	// Sort: Kanwil (Kantor Wilayah) first, then alphabetically by induk_unit, then by NAMA_LENGKAP
	$kanwilName = 'Kantor Wilayah Kementerian Agama Provinsi Nusa Tenggara Barat';
	$sorted = $all->sortBy(function ($e) use ($kanwilName) {
		$induk = $e->induk_unit ?? '';
		if ($induk === $kanwilName) {
			return '0_' . strtolower($e->NAMA_LENGKAP ?? '');
		}
		return '1_' . strtolower($induk) . '_' . strtolower($e->NAMA_LENGKAP ?? '');
	})->values();
	
	// Manual pagination after sorting
	$pageNum = max(1, (int) ($validated['page'] ?? 1));
	$totalCount = $sorted->count();
	$paginatedData = $sorted->slice(($pageNum - 1) * $perPage, $perPage)->values();
	
	return response()->json([
		'success' => true,
		'data' => [
			'data' => $paginatedData,
			'total' => $totalCount,
			'per_page' => $perPage,
			'current_page' => $pageNum,
			'last_page' => (int) ceil($totalCount / $perPage),
			'from' => $totalCount > 0 ? (($pageNum - 1) * $perPage) + 1 : 0,
			'to' => min($pageNum * $perPage, $totalCount),
		],
	]);
	}

	public function show(Employee $employee)
	{
		$this->authorize('view', $employee);
        $employee->induk_unit = EmployeeIndukUnit::compute($employee->SATUAN_KERJA, $employee->kab_kota, $employee->KET_JABATAN ?? null);
		// Normalize golongan untuk display: non-standar -> PPPK
		$employee->GOL_RUANG = $this->normalizeGolongan($employee->GOL_RUANG);
		return response()->json(['success' => true, 'data' => $employee]);
	}

	public function store(Request $request)
	{
		$this->authorize('create', Employee::class);

		$data = $this->validateData($request, true);
		$employee = Employee::create($data);
		return response()->json(['success' => true, 'data' => $employee], 201);
	}

	public function update(Request $request, Employee $employee)
	{
		$this->authorize('update', $employee);

		$actor = $request->user();
		$actor->loadMissing('role');

		// Full edit: superadmin atau pegawai.edit_all; terbatas: pegawai.edit
		if ($actor->hasPermission('pegawai.edit_all')) {
			$fillable = ['NIP','NIP_BARU','NAMA_LENGKAP','KODE_PANGKAT','GOL_RUANG','pangkat_asn','TMT_PANGKAT','MK_TAHUN','MK_BULAN','KODE_SATUAN_KERJA','SATUAN_KERJA','KODE_JABATAN','KET_JABATAN','TMT_JABATAN','NAMA_SEKOLAH','KODE_JENJANG_PENDIDIKAN','JENJANG_PENDIDIKAN','AKTA','FAKULTAS_PENDIDIKAN','JURUSAN','TAHUN_LULUS','TGL_LAHIR','TEMPAT_LAHIR','ISI_UNIT_KERJA','kab_kota','TMT_PENSIUN','tmt_cpns'];
		} elseif ($actor->hasPermission('pegawai.edit')) {
			$fillable = ['NIP','NAMA_LENGKAP','KODE_PANGKAT','GOL_RUANG','pangkat_asn','TMT_PANGKAT','MK_TAHUN','MK_BULAN','KODE_SATUAN_KERJA','SATUAN_KERJA','KODE_JABATAN','KET_JABATAN','TMT_JABATAN','NAMA_SEKOLAH','KODE_JENJANG_PENDIDIKAN','JENJANG_PENDIDIKAN','AKTA','FAKULTAS_PENDIDIKAN','JURUSAN','TAHUN_LULUS','TGL_LAHIR','TEMPAT_LAHIR','ISI_UNIT_KERJA','kab_kota','TMT_PENSIUN','tmt_cpns'];
		} else {
			abort(403, 'Forbidden');
		}

		$data = $request->only($fillable);
		$employee->fill($data);
		$employee->save();

		return response()->json(['success' => true, 'data' => $employee]);
	}

	public function destroy(Employee $employee)
	{
		$this->authorize('delete', $employee);
		$employee->delete();
		return response()->json(['success' => true]);
	}

	/**
	 * Return distinct induk unit names computed across all employees.
	 */
	public function indukUnits(Request $request)
	{
		$this->authorize('viewAny', Employee::class);
		// NTB has exactly 10 kab/kota + 1 Kanwil; return canonical list
		return response()->json(['success' => true, 'data' => $this->canonicalIndukList()]);
	}

	/**
	 * Return statistics: total, aktif, pensiun
	 */
	public function statistics(Request $request)
	{
		$this->authorize('viewAny', Employee::class);
		
		// Aktif: TMT_PENSIUN null atau TMT_PENSIUN > hari ini (belum sampai tanggal pensiun)
		// Pensiun: TMT_PENSIUN tidak null dan TMT_PENSIUN <= hari ini (sudah lewat atau sama dengan tanggal pensiun)
		$total = Employee::count();
		
		// Count aktif: TMT_PENSIUN is null OR > today
		$aktif = Employee::where(function ($q) {
			$q->whereNull('TMT_PENSIUN')
				->orWhere('TMT_PENSIUN', '>', now()->toDateString());
		})->count();
		
		// Count pensiun: TMT_PENSIUN is not null AND <= today
		$pensiun = Employee::whereNotNull('TMT_PENSIUN')
			->where('TMT_PENSIUN', '<=', now()->toDateString())
			->count();

		return response()->json([
			'success' => true,
			'data' => [
				'total' => $total,
				'aktif' => $aktif,
				'pensiun' => $pensiun,
			],
		]);
	}

	/**
	 * Get heatmap data - count employees per location with coordinates
	 */
	public function heatmap(Request $request)
	{
		$this->authorize('viewAny', Pegawai::class);

		$actor = $request->user();
		$wilayahCanonical = null;
		if ($actor instanceof \App\Models\User && $actor->shouldRestrictToWilayah()) {
			$actor->loadMissing('wilayahUnit');
			$wilayahCanonical = PegawaiWilayah::canonicalIndukFromSourceUnitSlug($actor->wilayahUnit?->slug);
		}

		$type = $request->query('type', 'kabupaten'); // 'kabupaten' or 'kanwil'
		$includeInactive = $request->query('include_inactive', 'false') === 'true';

		// Get canonical list
		$indukUnits = $this->canonicalIndukList();

		if ($wilayahCanonical !== null) {
			$indukUnits = array_values(array_filter($indukUnits, fn ($u) => $u === $wilayahCanonical));
		} else {
			// Filter by type
			if ($type === 'kanwil') {
				$indukUnits = array_filter($indukUnits, function ($unit) {
					return str_contains($unit, 'Kantor Wilayah');
				});
			} else {
				$indukUnits = array_filter($indukUnits, function ($unit) {
					return !str_contains($unit, 'Kantor Wilayah');
				});
			}

			$indukUnits = array_values($indukUnits);
		}

		// Pegawai v2: aktif/pensiun mengikuti PegawaiController (PegawaiLifecycle), bukan kolom is_active mentah.
		$query = Pegawai::query();

		// Get employee counts per induk unit with breakdown aktif/pensiun using chunking
		$employeeCounts = [];

		foreach ($indukUnits as $indukUnit) {
			$stats = ['total' => 0, 'aktif' => 0, 'pensiun' => 0];
			$employeesQuery = clone $query;
			$employeesQuery->chunk(1000, function ($chunk) use (&$stats, $indukUnit, $includeInactive) {
				foreach ($chunk as $emp) {
					if ($this->resolvePegawaiHeatmapInduk($emp) !== $indukUnit) {
						continue;
					}
					$retired = PegawaiLifecycle::isRetiredByRule($emp);
					if (!$includeInactive && $retired) {
						continue;
					}
					$stats['total']++;
					if ($retired) {
						$stats['pensiun']++;
					} else {
						$stats['aktif']++;
					}
				}
			});

			$employeeCounts[$indukUnit] = $stats;
		}

		// Koordinat: baca semua baris (tabel kecil) lalu cocokkan dengan trim — whereIn gagal jika DB punya spasi berlebih
		$coordinates = \App\Models\Coordinate::query()->get();
		$coordByInduk = [];
		foreach ($coordinates as $c) {
			$coordByInduk[trim((string) $c->induk_unit)] = $c;
		}

		// Build response data
		$data = [];
		foreach ($indukUnits as $indukUnit) {
			$coord = $coordByInduk[trim($indukUnit)] ?? null;
			if ($coord) {
				// Extract location name (simplified)
				$locationName = str_replace('Kantor Kementerian Agama ', '', $indukUnit);
				$locationName = str_replace('Kantor Wilayah Kementerian Agama Provinsi ', '', $locationName);

				$stats = $employeeCounts[$indukUnit] ?? ['total' => 0, 'aktif' => 0, 'pensiun' => 0];
				$data[] = [
					'location' => $locationName,
					'induk_unit' => $indukUnit,
					'source_unit_slug' => PegawaiWilayah::sourceUnitSlugFromCanonicalInduk($indukUnit),
					'count' => $stats['total'],
					'aktif' => $stats['aktif'],
					'pensiun' => $stats['pensiun'],
					'latitude' => (float) $coord->latitude,
					'longitude' => (float) $coord->longitude,
				];
			}
		}

		return response()->json([
			'success' => true,
			'data' => $data,
		]);
	}

	/**
	 * Get employees by location (induk_unit) with statistics
	 * Security: Uses same authorization as index (viewAny policy)
	 * Performance: Uses chunking to avoid N+1 queries and memory issues
	 */
	public function byLocation(EmployeeByLocationRequest $request)
	{
		$this->authorize('viewAny', Employee::class);

		$validated = $request->validated();
		$indukUnit = $validated['induk_unit'];
		$locationName = $validated['location'] ?? '';
		$search = $validated['search'] ?? '';
		$status = $validated['status'] ?? '';
		$perPage = (int) $validated['per_page'];
		$pageNum = max(1, (int) $validated['page']);

		// Validate induk_unit is in canonical list (security)
		$canonicalIndukUnits = $this->canonicalIndukList();
		if (!in_array($indukUnit, $canonicalIndukUnits, true)) {
			return response()->json([
				'success' => false,
				'message' => 'Invalid induk_unit',
			], 400);
		}

		// Build base query
		$query = Employee::query();

		// Apply search filter
		if ($search !== '') {
			$query->where(function ($q) use ($search) {
				$q->where('NAMA_LENGKAP', 'like', "%$search%")
					->orWhere('SATUAN_KERJA', 'like', "%$search%")
					->orWhere('KET_JABATAN', 'like', "%$search%")
					->orWhere('NIP_BARU', 'like', "%$search%");
			});
		}

		// Apply status filter
		if ($status === 'aktif') {
			$query->where(function ($q) {
				$q->whereNull('TMT_PENSIUN')
					->orWhere('TMT_PENSIUN', '>', now()->toDateString());
			});
		} elseif ($status === 'pensiun') {
			$query->whereNotNull('TMT_PENSIUN')
				->where('TMT_PENSIUN', '<=', now()->toDateString());
		}

		// Use chunking to filter by computed induk_unit and calculate statistics in one pass
		$filtered = collect();
		$stats = ['total' => 0, 'aktif' => 0, 'pensiun' => 0];
		$today = now()->toDateString();

		$query->chunk(1000, function ($chunk) use (&$filtered, &$stats, $indukUnit, $today) {
			foreach ($chunk as $e) {
				// Compute induk_unit and check match
				$computedInduk = EmployeeIndukUnit::compute($e->SATUAN_KERJA, $e->kab_kota, $e->KET_JABATAN ?? null);
				if ($computedInduk !== $indukUnit) {
					continue;
				}

				// Add computed field
				$e->induk_unit = $computedInduk;
				// Normalize golongan untuk display: non-standar -> PPPK
				$e->GOL_RUANG = $this->normalizeGolongan($e->GOL_RUANG);

				// Calculate statistics in same pass (avoid N+1)
				$stats['total']++;
				if ($e->TMT_PENSIUN === null || $e->TMT_PENSIUN > $today) {
					$stats['aktif']++;
				} else {
					$stats['pensiun']++;
				}

				$filtered->push($e);
			}
		});

		// Sort: by NAMA_LENGKAP
		$sorted = $filtered->sortBy(function ($e) {
			return strtolower($e->NAMA_LENGKAP ?? '');
		})->values();

		// Manual pagination
		$totalCount = $sorted->count();
		$paginatedData = $sorted->slice(($pageNum - 1) * $perPage, $perPage)->values();

		// If location name not provided, extract from induk_unit
		if ($locationName === '') {
			$locationName = str_replace('Kantor Kementerian Agama ', '', $indukUnit);
			$locationName = str_replace('Kantor Wilayah Kementerian Agama Provinsi ', '', $locationName);
		}

		return response()->json([
			'success' => true,
			'data' => [
				'location' => $locationName,
				'induk_unit' => $indukUnit,
				'statistics' => $stats,
				'employees' => [
					'data' => $paginatedData,
					'total' => $totalCount,
					'per_page' => $perPage,
					'current_page' => $pageNum,
					'last_page' => (int) ceil($totalCount / $perPage),
					'from' => $totalCount > 0 ? (($pageNum - 1) * $perPage) + 1 : 0,
					'to' => min($pageNum * $perPage, $totalCount),
				],
			],
		]);
	}

	/**
	 * Normalize golongan: standar PNS tetap, non-standar jadi PPPK
	 */
	private function normalizeGolongan(?string $golongan): string
	{
		if ($golongan === null || trim($golongan) === '') {
			return '';
		}
		$gol = trim($golongan);
		// Golongan standar PNS
		$standarPNS = ['I/a', 'I/b', 'I/c', 'I/d', 'II/a', 'II/b', 'II/c', 'II/d', 'III/a', 'III/b', 'III/c', 'III/d', 'IV/a', 'IV/b', 'IV/c', 'IV/d'];
		if (in_array($gol, $standarPNS, true)) {
			return $gol;
		}
		// Semua golongan non-standar di-mapping ke PPPK
		return 'PPPK';
	}

	/**
	 * Check if golongan is standard PNS
	 */
	private function isStandardPNSGolongan(?string $golongan): bool
	{
		if ($golongan === null || trim($golongan) === '') {
			return false;
		}
		$gol = trim($golongan);
		$standarPNS = ['I/a', 'I/b', 'I/c', 'I/d', 'II/a', 'II/b', 'II/c', 'II/d', 'III/a', 'III/b', 'III/c', 'III/d', 'IV/a', 'IV/b', 'IV/c', 'IV/d'];
		return in_array($gol, $standarPNS, true);
	}

	/**
	 * Induk wilayah untuk heatmap (pegawai v2): utamakan source_unit_slug (satu sumber dengan API / list pegawai), lalu heuristik teks.
	 * satker_induk tidak dipakai sebagai hint kab/kota — di DB itu organisasi induk Kanwil, bukan lokasi penugasan.
	 */
	private function resolvePegawaiHeatmapInduk(Pegawai $emp): string
	{
		$fromSlug = PegawaiWilayah::canonicalIndukFromSourceUnitSlug($emp->source_unit_slug);
		if ($fromSlug !== null) {
			return $fromSlug;
		}

		return EmployeeIndukUnit::compute($emp->unit_kerja, null, $emp->jabatan);
	}

	/**
	 * Canonical list of induk units for NTB.
	 */
	private function canonicalIndukList(): array
	{
		return [
			'Kantor Wilayah Kementerian Agama Provinsi Nusa Tenggara Barat',
			'Kantor Kementerian Agama Kota Mataram',
			'Kantor Kementerian Agama Kota Bima',
			'Kantor Kementerian Agama Kabupaten Lombok Barat',
			'Kantor Kementerian Agama Kabupaten Lombok Tengah',
			'Kantor Kementerian Agama Kabupaten Lombok Timur',
			'Kantor Kementerian Agama Kabupaten Lombok Utara',
			'Kantor Kementerian Agama Kabupaten Sumbawa',
			'Kantor Kementerian Agama Kabupaten Sumbawa Barat',
			'Kantor Kementerian Agama Kabupaten Dompu',
			'Kantor Kementerian Agama Kabupaten Bima',
		];
	}

	/**
	 * Return distinct values for a whitelisted column.
	 */
	public function distinct(Request $request)
	{
		$this->authorize('viewAny', Employee::class);
		$column = $request->query('column', '');
		$whitelist = [
			'KET_JABATAN',
			'pangkat_asn',
			'GOL_RUANG',
			'SATUAN_KERJA',
			'kab_kota',
		];
		if (!in_array($column, $whitelist, true)) {
			return response()->json(['success' => false, 'data' => []]);
		}
		$values = Employee::query()
			->select($column)
			->whereNotNull($column)
			->distinct()
			->orderBy($column)
			->pluck($column)
			->filter(function ($v) { return trim((string)$v) !== ''; })
			->values()
			->all();
		return response()->json(['success' => true, 'data' => $values]);
	}

	/**
	 * Return distinct job options as code-name pairs.
	 */
	public function jabatanOptions(Request $request)
	{
		$this->authorize('viewAny', Employee::class);
		$rows = Employee::query()
			->select(['KODE_JABATAN','KET_JABATAN'])
			->whereNotNull('KODE_JABATAN')
			->orderBy('KODE_JABATAN')
			->get();
		$map = [];
		foreach ($rows as $r) {
			$code = trim((string)$r->KODE_JABATAN);
			if ($code === '') continue;
			if (!isset($map[$code])) {
				$name = trim((string)$r->KET_JABATAN) !== '' ? (string)$r->KET_JABATAN : $code;
				$map[$code] = $name;
			}
		}
		$opts = [];
		foreach ($map as $code => $name) {
			$opts[] = ['code' => $code, 'name' => $name];
		}
		return response()->json(['success' => true, 'data' => $opts]);
	}

	private function validateData(Request $request, bool $isCreate = false): array
	{
		$rules = [
			'NIP' => ['nullable','string','max:50'],
			'NIP_BARU' => [$isCreate ? 'required' : 'sometimes','string','max:50'],
			'NAMA_LENGKAP' => ['nullable','string','max:255'],
			'KODE_PANGKAT' => ['nullable','string','max:20'],
			'GOL_RUANG' => ['nullable','string','max:20'],
			'pangkat_asn' => ['nullable','string','max:100'],
			'TMT_PANGKAT' => ['nullable','date'],
			'MK_TAHUN' => ['nullable','integer'],
			'MK_BULAN' => ['nullable','integer'],
			'KODE_SATUAN_KERJA' => ['nullable','string','max:50'],
			'SATUAN_KERJA' => ['nullable','string','max:255'],
			'KODE_JABATAN' => ['nullable','string','max:50'],
			'KET_JABATAN' => ['nullable','string','max:255'],
			'TMT_JABATAN' => ['nullable','date'],
			'NAMA_SEKOLAH' => ['nullable','string','max:255'],
			'KODE_JENJANG_PENDIDIKAN' => ['nullable','string','max:50'],
			'JENJANG_PENDIDIKAN' => ['nullable','string','max:100'],
			'AKTA' => ['nullable','string','max:100'],
			'FAKULTAS_PENDIDIKAN' => ['nullable','string','max:255'],
			'JURUSAN' => ['nullable','string','max:255'],
			'TAHUN_LULUS' => ['nullable','integer'],
			'TGL_LAHIR' => ['nullable','date'],
			'TEMPAT_LAHIR' => ['nullable','string','max:255'],
			'ISI_UNIT_KERJA' => ['nullable','string'],
			'kab_kota' => ['nullable','string','max:100'],
			'TMT_PENSIUN' => ['nullable','date'],
			'tmt_cpns' => ['nullable','date'],
		];

		return $request->validate($rules);
	}

}
