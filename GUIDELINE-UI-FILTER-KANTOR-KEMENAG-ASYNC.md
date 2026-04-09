# Guideline AI Agent - UI Hierarki Kantor + Filter Async (Tanpa Reload)

Dokumen ini untuk AI agent agar frontend menampilkan struktur organisasi dengan bahasa awam, serta filter dropdown yang load async tanpa reload halaman.

## 1) Problem yang diselesaikan

Field DB/API saat ini:
- `source_unit_slug` (teknis, tidak user-friendly)
- `satker_induk` (kanwil/provinsi)
- `unit_kerja`

Kebutuhan UI:
1. User tidak melihat istilah `source_unit_slug`.
2. UI menampilkan hierarki yang mudah dipahami:
   - **Induk Tertinggi:** Kanwil (Provinsi)
   - **Induk:** Kantor Kemenag (Kab/Kota)
   - **Unit Kerja**
3. Dropdown filter bisa dipakai untuk filter data secara async (tanpa full page reload).

---

## 2) Naming UI yang wajib dipakai

Gunakan label berikut di frontend:

- `satker_induk` -> **Kanwil (Provinsi)**
- `source_unit_slug` -> **Kantor Kemenag (Kab/Kota)**
- `unit_kerja` -> **Unit Kerja**

Jangan tampilkan istilah `slug` ke user.

---

## 3) Mapping value filter (UI -> API)

Walau label UI ramah user, value API tetap memakai key backend:

- Filter UI **Kanwil (Provinsi)** -> query `satker_induk=...`
- Filter UI **Kantor Kemenag (Kab/Kota)** -> query `source_unit_slug=...`
- Filter UI **Unit Kerja** -> query `unit_kerja=...`
- Filter UI **Status** -> query `is_active=true|false`
- Filter UI **Pangkat/Golongan** -> query `pangkat_golongan=...`

---

## 4) Kontrak endpoint backend (minimum)

## 4.1 Endpoint data list
`GET /api/pegawai`

Harus support query:
- `page`, `limit`
- `search`
- `satker_induk`
- `source_unit_slug`
- `unit_kerja`
- `pangkat_golongan`
- `is_active`

## 4.2 Endpoint options dropdown (async)
`GET /api/pegawai/filters`

Disarankan support query:
- `limit` (default 50)
- `satker_induk` (untuk cascading filter)
- `source_unit_slug` (untuk narrow unit kerja)
- `unit_kerja`
- `pangkat_golongan`
- `is_active`

Response minimal:
```json
{
  "satker_induk": ["Kanwil Kementerian Agama Provinsi ..."],
  "source_unit_slug": ["kantor_kementerian_agama_kabupaten_lombok_timur"],
  "unit_kerja": ["MTsN 1 Lombok Timur"],
  "pangkat_golongan": ["IX"],
  "jabatan": ["Guru ..."],
  "jenis_pegawai": ["PNS", "PPPK"]
}
```

---

## 5) UX rule untuk dropdown label/value

Karena `source_unit_slug` tidak ramah user:

### Opsi A (minimal, cepat)
- UI menampilkan hasil transform slug jadi title:
  - `kantor_kementerian_agama_kabupaten_lombok_timur`
  - -> `Kantor Kementerian Agama Kabupaten Lombok Timur`
- Value yang dikirim tetap slug asli.

### Opsi B (lebih rapi, disarankan)
- Backend kirim pair untuk source unit:
  - `value`: slug
  - `label`: nama manusiawi
- Frontend langsung render label.

Contoh response yang disarankan:
```json
{
  "source_units": [
    {
      "value": "kantor_kementerian_agama_kabupaten_lombok_timur",
      "label": "Kantor Kementerian Agama Kabupaten Lombok Timur"
    }
  ]
}
```

---

## 6) Flow async tanpa reload halaman

Implementasi frontend wajib:

1. Saat halaman dibuka:
   - fetch `GET /api/pegawai/filters?limit=100` untuk isi dropdown awal
   - fetch `GET /api/pegawai?page=1&limit=...`

2. Saat user ubah filter:
   - update state lokal filter
   - trigger fetch list baru (`/api/pegawai?...`) via `useEffect` / query hook
   - **tidak** melakukan `window.location.reload()`

3. Cascading dropdown:
   - saat `Kanwil` dipilih, reload opsi `Kantor Kemenag`:
     - `GET /api/pegawai/filters?satker_induk=...`
   - saat `Kantor Kemenag` dipilih, reload opsi `Unit Kerja`:
     - `GET /api/pegawai/filters?satker_induk=...&source_unit_slug=...`

4. Saat filter berubah:
   - reset pagination ke page 1
   - fetch data list terbaru

5. Debounce search:
   - 300-500ms agar tidak spam request

---

## 7) Pseudocode frontend (React)

```ts
const [filters, setFilters] = useState({
  satker_induk: '',
  source_unit_slug: '',
  unit_kerja: '',
  pangkat_golongan: '',
  is_active: '',
  search: '',
  page: 1,
  limit: 25
})

useEffect(() => {
  fetchPegawaiList(filters) // async, update table
}, [filters])

useEffect(() => {
  fetchDropdownOptions({
    satker_induk: filters.satker_induk,
    source_unit_slug: filters.source_unit_slug,
    is_active: filters.is_active
  }) // async, update dropdown options
}, [filters.satker_induk, filters.source_unit_slug, filters.is_active])
```

---

## 8) Acceptance criteria

1. UI tidak menampilkan kata `source_unit_slug`.
2. Ada 3 filter hierarki:
   - Kanwil (Provinsi)
   - Kantor Kemenag (Kab/Kota)
   - Unit Kerja
3. Memilih filter langsung update tabel tanpa reload halaman.
4. Dropdown options berubah async sesuai parent filter (cascading).
5. Query API yang dikirim tetap memakai key backend asli (`source_unit_slug`, dll).

