# Guideline Refactor Backend (Laravel) - Pegawai v2

Dokumen ini merangkum “apa yang harus diubah” saat kamu refactor backend agar mengikuti skema database terbaru untuk tabel `pegawai` (sesuai `hrms_full_dump_mysql.sql`) dan kontrak response yang dipakai frontend.

Context: proyek ini awalnya memakai field lama (`golongan`, `induk_unit`, `tmt_pensiun`). Versi refactor v2 **menggantinya** dengan field skema baru (`pangkat_golongan`, `satker_induk`, `is_active/last_seen_at`).

---

## 1) Schema yang dipakai (kolom `pegawai`)

Kolom wajib (NOT NULL) pada tabel `pegawai`:
- `nip` CHAR(18) PK
- `nama` VARCHAR(255)
- `tempat_tanggal_lahir` VARCHAR(500)
- `jenis_kelamin` VARCHAR(100)
- `agama` VARCHAR(100)
- `jenis_pegawai` VARCHAR(100)
- `jabatan` VARCHAR(1000)
- `unit_kerja` VARCHAR(500)
- `satker_induk` VARCHAR(500)
- `pangkat_golongan` VARCHAR(200)
- `pendidikan_terakhir` VARCHAR(500)
- `source_unit_slug` VARCHAR(180)
- `is_active` TINYINT(1) (default 1)
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP

Kolom opsional (NULL/nullable):
- `nip_lama` VARCHAR(32) NULL
- `last_seen_at` DATETIME NULL
- `list_fingerprint` CHAR(64) NULL
- `detail_fingerprint` CHAR(64) NULL

---

## 2) Eloquent Model (`Pegawai`)

### 2.1 Nama model & konfigurasi PK
Karena PK adalah `nip` (string), set:
- `$table = 'pegawai'`
- `$primaryKey = 'nip'`
- `$incrementing = false`
- `$keyType = 'string'`

### 2.2 Field yang diizinkan (fillable)
Di `Pegawai` model, set `fillable` minimal:
- `nip`
- `nama`
- `nip_lama`
- `tempat_tanggal_lahir`
- `jenis_kelamin`
- `agama`
- `jenis_pegawai`
- `jabatan`
- `unit_kerja`
- `satker_induk`
- `pangkat_golongan`
- `pendidikan_terakhir`
- `source_unit_slug`
- `is_active`
- `last_seen_at`
- `list_fingerprint`
- `detail_fingerprint`

### 2.3 Timestamp
Tabel punya kolom `created_at` & `updated_at`, tetapi DB dump mengatur `updated_at` via `ON UPDATE CURRENT_TIMESTAMP`.
Saran implementasi aman:
- `$timestamps = false` (supaya Eloquent tidak mengubah value timestamps saat update yang tidak kamu maksud)
- saat create/update, rely pada default DB (atau set manual bila memang diperlukan).

---

## 3) Refactor Controller: apa saja yang dipakai frontend

Frontend menggunakan endpoint berikut (semua terkait `pegawai`):
- `GET /pegawai` (list + pagination)
- `GET /pegawai/filter` (alias list yang sama)
- `GET /pegawai/search-surat` (search untuk input surat)
- `GET /pegawai/tempat-kerja` (response ringkas + field `tempat_kerja`)
- `GET /pegawai/retired-count` (jumlah pegawai `is_active=false`)
- `GET /pegawai/filters` (endpoint baru: dropdown distinct)

> Catatan: field lama `golongan`, `induk_unit`, `tmt_pensiun` **tidak lagi** dipakai. Frontend harus berpindah ke:
> - `pangkat_golongan`
> - `satker_induk`
> - `is_active/last_seen_at`

---

## 4) Kontrak Query & Response (implementasi detail)

### 4.1 `GET /pegawai` dan `GET /pegawai/filter`

Query params opsional:
- `page` (default `1`)
- `limit` (default `10`)
- `pangkat_golongan` (partial match)
- `satker_induk` (partial match)
- `unit_kerja` (partial match)
- `jabatan` (partial match)
- `source_unit_slug` (partial match)
- `is_active` (`true|false`)
- `search` (partial match untuk `nama` atau `nip`)

Implementasi filter (Laravel):
- gunakan `where('kolom', 'like', "%value%")` untuk partial
- `search`:
  - jika `search` ada: `where(function($q){ $q->where('nama','like',"%search%")->orWhere('nip','like',"%search%"); })`

Response (200) yang diharapkan FE:
```json
{
  "data": [ /* array Pegawai */ ],
  "total": 0,
  "inactive": 0,
  "active": 0,
  "totalPages": 0,
  "page": 1,
  "limit": 10
}
```

Aturan hitung `active/inactive`:
- jika `is_active` diset di query:
  - `active` atau `inactive` akan mengarah ke hasil yang sesuai filter itu
- jika `is_active` tidak diset:
  - `active` = count dengan `is_active=true`
  - `inactive` = count dengan `is_active=false`

> Best practice Laravel: lakukan 2 count query (active/inactive) untuk akurasi, walau lebih berat daripada 1 query.

---

### 4.2 `GET /pegawai/search-surat?nip=...` (atau `nama=...`)

Aturan:
- kalau `nip` ada dan panjang >= 4:
  - match `startsWith` (`nip LIKE "{nip}%"`)
- else kalau `nama` ada dan panjang >= 4:
  - match `substring` (`nama LIKE "%{nama}%"`)
- jika terlalu pendek:
  - return array kosong
- selalu batasi output `limit=20`

Response (200):
- array Pegawai (objek pegawai lengkap sesuai kontrak field)

---

### 4.3 `GET /pegawai/tempat-kerja`

Query params opsional:
- `nip`
- `satker_induk`
- `unit_kerja`
- `pangkat_golongan`
- `jabatan`
- `nama`
- `is_active` (`true|false`)

Response (200): array ringkas:
```json
[
  {
    "nip": "...",
    "nama": "...",
    "pangkat_golongan": "...",
    "satker_induk": "...",
    "unit_kerja": "...",
    "jabatan": "...",
    "is_active": true,
    "tempat_kerja": "satker_induk - unit_kerja"
  }
]
```

Implementasi:
- query hanya select kolom yang dibutuhkan
- `tempat_kerja` dihitung di layer controller/resource.

---

### 4.4 `GET /pegawai/retired-count`

Response:
```json
{ "retiredCount": 0 }
```

Definisi retired pada v2:
- `retiredCount` = `COUNT(*) WHERE is_active = false`

> Jangan pakai `tmt_pensiun` lagi.

---

### 4.5 `GET /pegawai/filters` (endpoint baru untuk FE)

Tujuan:
- FE butuh dropdown yang berisi `distinct` dari kolom tertentu.

Query params (opsional):
- `limit` default 50
- `is_active`
- `source_unit_slug` partial
- `satker_induk` partial
- `unit_kerja` partial
- `pangkat_golongan` partial

Response (200):
```json
{
  "satker_induk": ["..."],
  "unit_kerja": ["..."],
  "pangkat_golongan": ["..."],
  "jabatan": ["..."],
  "jenis_pegawai": ["..."],
  "source_unit_slug": ["..."]
}
```

Implementasi Laravel yang disarankan:
- gunakan `selectRaw("DISTINCT kolom as value")`
- `where` untuk optional `is_active` dan optional partial filters
- buang nilai null/empty (`value <> ''`)
- `orderBy(kolom)`
- `limit($limit)`

---

## 5) Sanitasi data `nama` setelah seed (penting)

Dari hasil dump/scrape, kadang tersimpan karakter backtick di awal/akhir `nama` (contoh: `` `HARIADI`, S.Ag ``).

Guideline:
- setelah import SQL dump ke DB, jalankan sanitasi:
  - hapus backtick di boundary (awal/akhir string)
  - ganti pola:
    - '`,': ','  (backtick sebelum koma)
    - '`.' : '.'  (backtick sebelum titik)

Contoh SQL sanitasi (konsep):
- `UPDATE pegawai SET nama = TRIM(BOTH '`' FROM nama)`
- `UPDATE pegawai SET nama = REPLACE(nama, '`,', ',')`
- `UPDATE pegawai SET nama = REPLACE(nama, '`.','.')`

Kenapa ini penting:
- FE menampilkan `nama` mentah; tanpa sanitasi, UI jadi ada karakter aneh.

---

## 6) Auth/Login (Laravel-side checklist)

Endpoint auth (di backend Node kamu) butuh tabel `users` berisi data admin.
Di Laravel, pastikan:
- migration users sesuai model:
  - `username`, `password` (hash bcrypt), `role`, `created_at`, `updated_at`
- admin user diset saat seeding (mis. `admin/admin123`)
- JWT config konsisten dengan env secret yang dipakai frontend.

Kalau login error:
- cek ada user di DB (COUNT)
- cek password hash valid
- cek JWT secret sama

---

## 7) Checklist implementasi refactor (urut pengerjaan)

1. Buat migration + Eloquent model mengikuti skema `pegawai` v2
2. Update controller list/filter/search/tempat-kerja/retired-count sesuai kontrak field baru
3. Tambahkan endpoint `filters` (distinct dropdown)
4. Pastikan response JSON struktur dan key sama persis dengan kontrak (`data`, `total`, `inactive`, `active`, dst.)
5. Jalankan sanitasi `nama` setelah seed/import
6. Seed admin user (untuk login FE)
7. Test dengan query:
   - `GET /pegawai?limit=2&page=1`
   - `GET /pegawai/filter?pangkat_golongan=IX&is_active=true&limit=2&page=1`
   - `GET /pegawai/tempat-kerja?satker_induk=...&unit_kerja=...&is_active=true`
   - `GET /pegawai/filters?is_active=true&limit=50`

