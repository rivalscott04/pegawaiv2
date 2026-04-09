# API Pegawai - Response Contract (v2)

Dokumen ini menjelaskan bentuk response yang diharapkan frontend untuk endpoint `pegawai` setelah repo mengikuti skema database terbaru (`hrms_full_dump_mysql.sql`).

Base URL:
`http://localhost:3000/api`

Catatan penting:
- `retiredCount` pada endpoint `/api/pegawai/retired-count` menghitung pegawai yang **inactive** (`is_active = false`).
- Field utama yang dipakai frontend:
  - `pangkat_golongan`, `satker_induk`, `unit_kerja`, `jabatan`, `jenis_pegawai`, `source_unit_slug`
  - flag `is_active`

## 1) GET `/pegawai`

### Query
- `page` (integer, default: `1`)
- `limit` (integer, default: `10`)
- `pangkat_golongan` (string, partial match)
- `satker_induk` (string, partial match)
- `unit_kerja` (string, partial match)
- `jabatan` (string, partial match)
- `source_unit_slug` (string, partial match)
- `is_active` (`true|false`)
- `search` (string, partial match untuk `nama` atau `nip`)

### Response (200)
```json
{
  "data": [
    {
      "nip": "197410182022211003",
      "nama": "`HARIADI`, S.Ag",
      "nip_lama": "230055943",
      "tempat_tanggal_lahir": "LOMBOK TIMUR 18/10/1974",
      "jenis_kelamin": "Laki-Laki",
      "agama": "Islam",
      "jenis_pegawai": "PPPK",
      "jabatan": "Guru Kelas ...",
      "unit_kerja": "MIN Lombok Timur",
      "satker_induk": "Kanwil Kementerian Agama Provinsi Nusa Tenggara Barat",
      "pangkat_golongan": "IX",
      "pendidikan_terakhir": "S-1/...",
      "source_unit_slug": "kantor_kementerian_agama_kabupaten_lombok_timur",
      "is_active": true,
      "last_seen_at": "2026-04-02T07:16:13.000Z",
      "list_fingerprint": null,
      "detail_fingerprint": "aa55d11b...",
      "created_at": "2026-04-02T07:16:13.000Z",
      "updated_at": "2026-04-02T07:16:13.000Z"
    }
  ],
  "total": 7297,
  "inactive": 0,
  "active": 7297,
  "totalPages": 3649,
  "page": 1,
  "limit": 2
}
```

## 2) GET `/pegawai/filter`

`/filter` adalah alias dari `GET /pegawai` (response format sama).

## 3) GET `/pegawai/search-surat?nip=...`

### Response (200)
Array pegawai (maksimal `20` data):
```json
[
  {
    "nip": "197410182022211003",
    "nama": "...",
    "unit_kerja": "...",
    "jabatan": "...",
    "satker_induk": "...",
    "pangkat_golongan": "...",
    "is_active": true,
    "last_seen_at": "..."
  }
]
```

## 4) GET `/pegawai/tempat-kerja`

Dipakai untuk kebutuhan FE menampilkan daftar tempat kerja berdasarkan kombinasi `satker_induk` dan `unit_kerja`.

### Query
- `satker_induk` (optional, partial match)
- `unit_kerja` (optional, partial match)
- `pangkat_golongan` (optional, partial match)
- `jabatan` (optional, partial match)
- `nama` (optional, partial match)
- `nip` (optional, partial match)
- `is_active` (optional, `true|false`)

### Response (200)
Array:
```json
[
  {
    "nip": "197410182022211003",
    "nama": "...",
    "pangkat_golongan": "IX",
    "satker_induk": "Kanwil ...",
    "unit_kerja": "MIN Lombok Timur",
    "jabatan": "...",
    "is_active": true,
    "tempat_kerja": "Kanwil ... - MIN Lombok Timur"
  }
]
```

## 5) GET `/pegawai/retired-count`

Mengembalikan jumlah pegawai yang `is_active=false`.

### Response (200)
```json
{ "retiredCount": 0 }
```

## 6) GET `/pegawai/filters` (Endpoint Baru untuk Frontend)

Dipakai untuk membuat dropdown/filter list secara dinamis (ambil nilai distinct dari tabel `pegawai`).

### Query (opsional)
- `limit` (integer, default `50`)
- `is_active` (`true|false`)
- `source_unit_slug` (partial match)
- `satker_induk` (partial match)
- `unit_kerja` (partial match)
- `pangkat_golongan` (partial match)

### Response (200)
```json
{
  "satker_induk": ["Kanwil ...", "..."],
  "unit_kerja": ["MIN Lombok Timur", "..."],
  "pangkat_golongan": ["IX", "V", "..."],
  "jabatan": ["Guru ...", "..."],
  "jenis_pegawai": ["PPPK", "PNS", "..."],
  "source_unit_slug": ["kantor_kementerian_...", "..."]
}
```

## Error
- `400` untuk request body invalid (khusus endpoint `POST /pegawai`)
- `500` untuk error internal

