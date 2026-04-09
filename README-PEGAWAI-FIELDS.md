# Pegawai Fields (Frontend Sync - Lengkap)

Dokumen ini merangkum field yang tersedia pada response pegawai dari endpoint:
- `GET /api/pegawai` dan `GET /api/pegawai/filter` (response: `data` berisi array objek pegawai lengkap)
- `GET /api/pegawai/search-surat` (response: array objek pegawai yang sama)
- `GET /api/pegawai/tempat-kerja` (response: array objek ringkas untuk FE)
- `GET /api/pegawai/retired-count` (response: jumlah)
- `GET /api/pegawai/filters` (endpoint baru untuk dropdown FE)

Sumber kolom: tabel `pegawai` pada `hrms_full_dump_mysql.sql` (skema terbaru).

## 1) Mapping Field -> Tabel `pegawai` (MySQL)

| Field | MySQL type (skema) | Nullable | Yang diterima FE / Response JSON |
|---|---|---|---|
| `nip` | `CHAR(18)` | No | string (primary key) |
| `nama` | `VARCHAR(255)` | No | string (bisa mengandung karakter backtick dari hasil scraping) |
| `nip_lama` | `VARCHAR(32)` | Yes | `string` atau `null` |
| `tempat_tanggal_lahir` | `VARCHAR(500)` | No | string |
| `jenis_kelamin` | `VARCHAR(100)` | No | string |
| `agama` | `VARCHAR(100)` | No | string |
| `jenis_pegawai` | `VARCHAR(100)` | No | string (contoh: `PPPK`, `PNS`, `CPNS`) |
| `jabatan` | `VARCHAR(1000)` | No | string |
| `unit_kerja` | `VARCHAR(500)` | No | string |
| `satker_induk` | `VARCHAR(500)` | No | string |
| `pangkat_golongan` | `VARCHAR(200)` | No | string |
| `pendidikan_terakhir` | `VARCHAR(500)` | No | string |
| `source_unit_slug` | `VARCHAR(180)` | No | string (slug unit sumber scrape) |
| `is_active` | `TINYINT(1)` | No | boolean (`true`/`false`) |
| `last_seen_at` | `DATETIME` | Yes | string ISO atau `null` (tergantung value di DB) |
| `list_fingerprint` | `CHAR(64)` | Yes | string atau `null` |
| `detail_fingerprint` | `CHAR(64)` | Yes | string atau `null` |
| `created_at` | `TIMESTAMP` | No | string ISO |
| `updated_at` | `TIMESTAMP` | No | string ISO |

## 2) Catatan kompatibilitas (field lama)

- Field lama: `golongan`, `induk_unit`, `tmt_pensiun` **sudah tidak dipakai** pada response pegawai versi baru.
- Mapping versi lama ke versi baru:
  - `golongan` -> `pangkat_golongan`
  - `induk_unit` -> `satker_induk`
  - “retired logic” -> berdasarkan `is_active=false` (bukan `tmt_pensiun`)

## 3) TypeScript Interface (disarankan untuk FE)

```typescript
export interface Pegawai {
  nip: string;
  nama: string;
  nip_lama: string | null;

  tempat_tanggal_lahir: string;
  jenis_kelamin: string;
  agama: string;
  jenis_pegawai: string;
  jabatan: string;

  unit_kerja: string;
  satker_induk: string;

  pangkat_golongan: string;
  pendidikan_terakhir: string;
  source_unit_slug: string;

  is_active: boolean;
  last_seen_at: string | null;
  list_fingerprint: string | null;
  detail_fingerprint: string | null;

  created_at: string;
  updated_at: string;
}
```

## 4) Kontrak Response per Endpoint

### 4.1 `GET /api/pegawai` dan `GET /api/pegawai/filter`

Query params (opsional):
- `page` (default `1`)
- `limit` (default `10`)
- `pangkat_golongan` (partial match)
- `satker_induk` (partial match)
- `unit_kerja` (partial match)
- `jabatan` (partial match)
- `source_unit_slug` (partial match)
- `is_active` (`true|false`)
- `search` (partial match untuk `nama` atau `nip`)

Response (200):
```json
{
  "data": [ { "nip": "...", "nama": "...", "...": "..." } ],
  "total": 7297,
  "inactive": 0,
  "active": 7297,
  "totalPages": 3649,
  "page": 1,
  "limit": 10
}
```

### 4.2 `GET /api/pegawai/search-surat?nip=...` (atau `nama=...`)

Behavior:
- `nip`: match `startsWith` (minimal panjang query tertentu di controller)
- `nama`: match `substring` (minimal panjang query tertentu)
- Output dibatasi `limit=20`

Response (200): array `Pegawai` (bentuk objek pegawai lengkap).

### 4.3 `GET /api/pegawai/tempat-kerja`

Query params (opsional):
- `nip`
- `satker_induk`
- `unit_kerja`
- `pangkat_golongan`
- `jabatan`
- `nama`
- `is_active` (`true|false`)

Response (200): array objek ringkas untuk FE:
```json
[
  {
    "nip": "197410182022211003",
    "nama": "...",
    "pangkat_golongan": "IX",
    "satker_induk": "Kanwil ...",
    "unit_kerja": "MIN ...",
    "jabatan": "...",
    "is_active": true,
    "tempat_kerja": "satker_induk - unit_kerja"
  }
]
```

### 4.4 `GET /api/pegawai/retired-count`

Response (200):
```json
{ "retiredCount": 0 }
```

Definisi:
- `retiredCount` = jumlah pegawai dengan `is_active = false`.

### 4.5 `GET /api/pegawai/filters` (Endpoint Baru)

Dipakai frontend untuk membangun dropdown filter dinamis (ambil nilai `DISTINCT` dari kolom-kolom tertentu).

Query params (opsional):
- `limit` (default `50`)
- `is_active` (`true|false`)
- `source_unit_slug` (partial)
- `satker_induk` (partial)
- `unit_kerja` (partial)
- `pangkat_golongan` (partial)

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

