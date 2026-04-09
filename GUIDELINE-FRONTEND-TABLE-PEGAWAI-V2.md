# Frontend Guideline - Tabel Pegawai (API v2)

Tujuan dokumen ini: memastikan UI tabel `PegawaiV2` terlihat rapi di frontend, sesuai respons API v2 (`pangkat_golongan`, `satker_induk`, `jenis_kelamin`, `agama`, `is_active`, `tempat_tanggal_lahir`).

Dokumen ini fokus ke page:
- `empkanwil/app/employees/EmployeesPageV2.tsx`
- `empkanwil/app/employees/location/[slug]/EmployeeLocationPageV2.tsx`

## Masalah yang harus dibenahi (sesuai laporan)

1. `NIP` dan `Nama` terpotong/overflow dan terlalu lebar karena isi + ikon.
2. `NIP` dan `Nama` di tabel masih menampilkan ikon copy (tidak diinginkan).
3. Kolom `Jabatan` perlu wrap, tapi lebar kolomnya harus ditambah sedikit agar tidak tampak “sempit”.
4. `Tempat/Tgl Lahir` tampil jelek/kurang readable.
5. `Status` (Aktif/Nonaktif) kontrasnya berantakan karena styling badge (terutama varian `badge-soft`).

## Guideline perubahan kode (spesifik)

### A) File: `empkanwil/app/employees/EmployeesPageV2.tsx`

1. Hapus ikon copy di kolom tabel
   - Pada render khusus `c.key === 'nip'`, hapus `<CopyButton value={row.nip} ... />`.
   - Pada render khusus `c.key === 'nama'`, hapus `<CopyButton value={row.nama} ... />`.
   - Copy boleh tetap ada di modal, tapi tidak tampil di row tabel.

2. Hapus badge `is_active` yang ditaruh di kolom `nip`
   - Saat ini status badge disisipkan di dalam cell `nip`.
   - Hapus badge itu agar badge status tampil hanya di kolom `is_active` (key `is_active`).
   - Dampak: mengurangi clutter dan mencegah masalah warna/kontras.

3. Perapihan lebar kolom `NIP` dan `Nama`
   - Sesuaikan `thClass` untuk menghindari kolom lain jadi terlalu sempit.
   - Saran:
     - `nip`: `min-w-[9rem]` s/d `min-w-[11rem]` (18 digit monospaced)
     - `nama`: `min-w-[10rem]` s/d `min-w-[12rem]`
   - Pastikan cell untuk NIP tidak overflow:
     - `whitespace-nowrap overflow-hidden text-ellipsis`
     - tetap gunakan `font-mono`.

4. Perlebar dan rapikan wrap kolom `Jabatan`
   - Naikkan `min-w` untuk header `jabatan` (contoh: dari `min-w-[14rem]` ke `min-w-[16rem]` atau `min-w-[18rem]`).
   - Ubah wrapping class untuk `jabatan` menjadi:
     - `whitespace-normal break-words`
   - Hindari wrapping yang terlalu agresif (mis. break per karakter).

5. Format ulang `tempat_tanggal_lahir` di tabel
   - Gunakan helper `formatTempatTanggalLahir` yang sudah ada.
   - Pastikan class wrap:
     - `whitespace-normal break-words`

6. Perbaiki styling status badge
   - Pastikan di tabel tidak memakai varian `badge-soft` untuk status.
   - Gunakan class dari `fmtStatus` untuk key `is_active`, misalnya:
     - Aktif: `badge badge-success badge-sm`
     - Nonaktif: `badge badge-error badge-sm`
   - Hapus penggunaan `badge-success badge-soft ...` / `badge-error badge-soft ...` di area tabel.

### B) File: `empkanwil/app/employees/location/[slug]/EmployeeLocationPageV2.tsx`

Checklist:
1. Pastikan tidak ada ikon copy di row tabel untuk `nip` dan `nama`.
2. Pastikan status badge menggunakan `fmtStatus` (tanpa `badge-soft`).
3. Pastikan `tempat_tanggal_lahir` (jika ditampilkan) memakai format helper bila perlu.

## Acceptance Criteria (bisa dicek visual)

1. Kolom `NIP` tidak overflow dan tidak mendorong `Jabatan` jadi terlalu sempit.
2. Tidak ada icon copy di cell tabel `NIP` dan `Nama`.
3. `Jabatan` wrap rapi (per kata), bukan per karakter.
4. `Tempat/Tgl Lahir` tampil lebih readable (hasil formatting helper).
5. `Status` (Aktif/Nonaktif) terlihat jelas dengan kontras teks yang benar.

