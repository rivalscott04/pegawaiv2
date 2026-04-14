<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CoordinateController;
use App\Http\Controllers\PegawaiController;

// Login endpoint dengan rate limiting ketat
Route::post('/auth/login', [AuthController::class, 'login'])
	->middleware('throttle:5,1'); // 5 attempts per minute

// Public endpoints (tidak perlu auth)
Route::get('/public/employees', [EmployeeController::class, 'publicIndex'])
	->middleware('throttle:30,1'); // batasi 30 request per menit per IP
Route::get('/public/employees/all', [EmployeeController::class, 'publicAll'])
	->middleware('throttle:10,1'); // batasi lebih ketat karena return semua data

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
	Route::get('/auth/me', [AuthController::class, 'me']);
	Route::post('/auth/logout', [AuthController::class, 'logout']);
	Route::post('/auth/refresh', [AuthController::class, 'refresh']);

	// API Pegawai (v2 - skema tabel `pegawai`)
	Route::get('/pegawai', [PegawaiController::class, 'index']);
	Route::get('/pegawai/filter', [PegawaiController::class, 'filter']);
	Route::get('/pegawai/search-surat', [PegawaiController::class, 'searchSurat']);
	Route::get('/pegawai/tempat-kerja', [PegawaiController::class, 'tempatKerja']);
	Route::get('/pegawai/retired-count', [PegawaiController::class, 'retiredCount']);
	Route::get('/pegawai/filters', [PegawaiController::class, 'filters']);
	Route::get('/pegawai/sdm-overview', [PegawaiController::class, 'sdmOverview']);
	Route::get('/pegawai/export', [PegawaiController::class, 'export']);
	Route::get('/pegawai/export/{taskId}/status', [PegawaiController::class, 'exportStatus']);
	Route::get('/pegawai/export/{taskId}/download', [PegawaiController::class, 'exportDownload']);
	Route::get('/pegawai/{nip}', [PegawaiController::class, 'show']);
	Route::get('/pegawai/{nip}/riwayat-kenaikan-pangkat', [PegawaiController::class, 'getRiwayatKenaikanPangkat']);

	Route::get('/employees', [EmployeeController::class, 'index']);
	Route::get('/employees/induk-units', [EmployeeController::class, 'indukUnits']);
	Route::get('/employees/statistics', [EmployeeController::class, 'statistics']);
	Route::get('/employees/heatmap', [EmployeeController::class, 'heatmap']);
	Route::get('/employees/by-location', [EmployeeController::class, 'byLocation']);
	Route::get('/employees/distinct', [EmployeeController::class, 'distinct']);
	Route::get('/employees/jabatan-options', [EmployeeController::class, 'jabatanOptions']);
	Route::get('/employees/{employee:NIP_BARU}', [EmployeeController::class, 'show']);

	Route::middleware('permission:pegawai.create')->group(function () {
		Route::post('/employees', [EmployeeController::class, 'store'])
			->middleware('throttle:10,1');
	});

	Route::middleware('permission:pegawai.delete')->group(function () {
		Route::delete('/employees/{employee:NIP_BARU}', [EmployeeController::class, 'destroy'])
			->middleware('throttle:10,1');
	});

	Route::middleware('permission:users.manage')->group(function () {
		Route::get('/users', [UserController::class, 'index']);
		Route::get('/users/roles', [UserController::class, 'roles']);
		Route::get('/users/permissions', [UserController::class, 'permissions']);
		Route::get('/users/wilayah-units', [UserController::class, 'wilayahUnits']);
		Route::post('/users', [UserController::class, 'store']);
		Route::put('/users/{user}', [UserController::class, 'update']);
		Route::delete('/users/{user}', [UserController::class, 'destroy']);
	});

	Route::middleware('permission:coordinates.manage')->group(function () {
		Route::get('/coordinates', [CoordinateController::class, 'index']);
		Route::get('/coordinates/{coordinate}', [CoordinateController::class, 'show']);
		Route::post('/coordinates', [CoordinateController::class, 'store'])
			->middleware('throttle:10,1');
		Route::put('/coordinates/{coordinate}', [CoordinateController::class, 'update'])
			->middleware('throttle:10,1');
		Route::delete('/coordinates/{coordinate}', [CoordinateController::class, 'destroy'])
			->middleware('throttle:10,1');
	});

	Route::middleware('permission:pegawai.edit|pegawai.edit_all')->group(function () {
		Route::put('/pegawai/{nip}', [PegawaiController::class, 'update'])
			->middleware('throttle:100,1');
	});
});
