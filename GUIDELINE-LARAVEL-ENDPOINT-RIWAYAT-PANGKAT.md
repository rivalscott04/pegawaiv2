# Guideline Laravel - Endpoint Riwayat Kenaikan Pangkat

Dokumen ini khusus untuk AI agent yang akan membuat endpoint **riwayat kenaikan pangkat** berdasarkan tabel DB yang sudah ada:
- `pegawai`
- `riwayat_kenaikan_pangkat`

Tujuan:
- Ambil riwayat kenaikan pangkat berdasarkan `nip`
- Response jelas untuk frontend
- Struktur implementasi rapi (Model, Controller, Route, Resource)

---

## 1) Struktur tabel yang dipakai

### Tabel `pegawai`
- PK: `nip` (`CHAR(18)`)

### Tabel `riwayat_kenaikan_pangkat`
- PK gabungan: `nip`, `no`
- Kolom utama:
  - `nip` (`CHAR(18)`)
  - `no` (`VARCHAR(16)`) -> urutan riwayat
  - `pangkat` (`VARCHAR(255)`)
  - `golongan` (`VARCHAR(50)`)
  - `tmt` (`DATETIME`, nullable)
  - `nomor_sk` (`VARCHAR(100)`)
  - `created_at`, `updated_at`

---

## 2) Endpoint yang harus dibuat

### Endpoint utama
`GET /api/pegawai/{nip}/riwayat-kenaikan-pangkat`

### Query params (opsional)
- `page` (default: `1`)
- `limit` (default: `50`, max: `500`)

### Perilaku
1. Cek dulu apakah pegawai dengan `nip` itu ada di tabel `pegawai`
   - jika tidak ada -> `404`
2. Jika ada, ambil data dari `riwayat_kenaikan_pangkat` by `nip`
3. Urutkan berdasarkan `no` numerik ASC
4. Kembalikan response terstruktur + pagination metadata

---

## 3) Implementasi Laravel

## 3.1 Model

### `app/Models/Pegawai.php`
Pastikan model `Pegawai`:
- `$table = 'pegawai'`
- `$primaryKey = 'nip'`
- `$incrementing = false`
- `$keyType = 'string'`

Tambahkan relasi:
```php
public function riwayatKenaikanPangkat()
{
    return $this->hasMany(RiwayatKenaikanPangkat::class, 'nip', 'nip');
}
```

### `app/Models/RiwayatKenaikanPangkat.php`
Contoh:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiwayatKenaikanPangkat extends Model
{
    protected $table = 'riwayat_kenaikan_pangkat';
    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = null; // PK komposit (nip,no), tidak native di Eloquent

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

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'nip', 'nip');
    }
}
```

---

## 3.2 Controller

Buat method di `app/Http/Controllers/PegawaiController.php`:

```php
public function getRiwayatKenaikanPangkat(Request $request, string $nip)
{
    $page = max((int) $request->query('page', 1), 1);
    $limit = (int) $request->query('limit', 50);
    if ($limit < 1) $limit = 50;
    if ($limit > 500) $limit = 500;

    $pegawai = Pegawai::query()->where('nip', $nip)->first();
    if (!$pegawai) {
        return response()->json(['message' => 'Pegawai not found'], 404);
    }

    $query = RiwayatKenaikanPangkat::query()
        ->where('nip', $nip)
        ->orderByRaw('CAST(`no` AS UNSIGNED) ASC')
        ->orderBy('no', 'ASC');

    $total = (clone $query)->count();
    $items = $query->forPage($page, $limit)->get();

    return response()->json([
        'nip' => $nip,
        'data' => $items,
        'total' => $total,
        'totalPages' => (int) ceil($total / $limit) ?: 1,
        'page' => $page,
        'limit' => $limit,
    ]);
}
```

---

## 3.3 Route

Tambahkan di `routes/api.php`:

```php
Route::get('/pegawai/{nip}/riwayat-kenaikan-pangkat', [PegawaiController::class, 'getRiwayatKenaikanPangkat']);
```

---

## 4) Format response yang diharapkan

## 4.1 Success `200`
```json
{
  "nip": "196806071991032003",
  "data": [
    {
      "nip": "196806071991032003",
      "no": "1",
      "pangkat": "Pengatur Muda",
      "golongan": "II/a",
      "tmt": "1991-03-01T00:00:00.000000Z",
      "nomor_sk": "W.X/1-b/1117/2000",
      "created_at": "2026-04-02T07:16:13.000000Z",
      "updated_at": "2026-04-02T07:16:13.000000Z"
    }
  ],
  "total": 15,
  "totalPages": 1,
  "page": 1,
  "limit": 50
}
```

## 4.2 Pegawai tidak ditemukan `404`
```json
{
  "message": "Pegawai not found"
}
```

## 4.3 Error internal `500`
```json
{
  "message": "Failed to fetch riwayat kenaikan pangkat"
}
```

---

## 5) Contoh test curl

```bash
curl -s "http://localhost:8000/api/pegawai/196806071991032003/riwayat-kenaikan-pangkat?page=1&limit=10"
```

---

## 6) Checklist untuk AI agent

1. Model `RiwayatKenaikanPangkat` dibuat.
2. Relasi `Pegawai -> hasMany(RiwayatKenaikanPangkat)` dibuat.
3. Method controller `getRiwayatKenaikanPangkat()` dibuat.
4. Route API dipasang.
5. Validasi `nip` not found -> 404.
6. Sorting `no` numerik ASC.
7. Pagination metadata (`total`, `totalPages`, `page`, `limit`) ada.
8. Test curl berhasil.

