export type Employee = {
	NIP: string | null
	NIP_BARU: string
	NAMA_LENGKAP: string
	KODE_PANGKAT: string | null
	GOL_RUANG: string | null
	pangkat_asn: string | null
	TMT_PANGKAT: string | null
	MK_TAHUN: number | null
	MK_BULAN: number | null
	KODE_SATUAN_KERJA: string | null
	SATUAN_KERJA: string | null
	KODE_JABATAN: string | null
	KET_JABATAN: string | null
	TMT_JABATAN: string | null
	NAMA_SEKOLAH: string | null
	KODE_JENJANG_PENDIDIKAN: string | null
	JENJANG_PENDIDIKAN: string | null
	AKTA: string | null
	FAKULTAS_PENDIDIKAN: string | null
	JURUSAN: string | null
	TAHUN_LULUS: number | null
	TGL_LAHIR: string | null
	TEMPAT_LAHIR: string | null
	ISI_UNIT_KERJA: string | null
	kab_kota: string | null
	induk_unit?: string | null
	TMT_PENSIUN: string | null
	tmt_cpns: string | null
}

export type PaginatedEmployees = {
	success: boolean
	data: {
		current_page: number
		data: Employee[]
		from: number | null
		last_page: number
		per_page: number
		to: number | null
		total: number
	}
}

export type Coordinate = {
	id: number
	induk_unit: string
	latitude: number
	longitude: number
	created_at?: string
	updated_at?: string
}

export type HeatmapData = {
	location: string
	induk_unit: string
	/** Selaras Pegawai v2 / heatmap — dipakai filter `source_unit_slug` di daftar pegawai. */
	source_unit_slug?: string | null
	count: number
	aktif?: number
	pensiun?: number
	latitude: number
	longitude: number
}

// Pegawai fields (API v2 - README-API-v2.md / README-PEGAWAI-FIELDS.md)
export type PegawaiV2 = {
	nip: string
	nama: string
	nip_lama: string | null
	tempat_tanggal_lahir: string
	jenis_kelamin: string
	agama: string
	jenis_pegawai: string
	jabatan: string
	unit_kerja: string
	satker_induk: string
	pangkat_golongan: string
	/** Nama pangkat PNS (mis. Penata Muda); hanya untuk PNS. */
	pangkat_pns_nama?: string | null
	/** Golongan/ruang PNS terurai (mis. III/a); jika tidak terurai, teks asli dari pangkat_golongan. */
	golongan_pns?: string | null
	pendidikan_terakhir: string
	source_unit_slug: string
	is_active: boolean
	last_seen_at: string | null
	list_fingerprint: string | null
	detail_fingerprint: string | null
	created_at: string
	updated_at: string
}

export type PegawaiListResponseV2 = {
	data: PegawaiV2[]
	total: number
	inactive: number
	active: number
	totalPages: number
	page: number
	limit: number
}

export type PegawaiFiltersResponseV2 = {
	satker_induk: string[]
	unit_kerja: string[]
	pangkat_golongan: string[]
	jabatan: string[]
	jenis_pegawai: string[]
	source_unit_slug: string[]
	source_units?: Array<{
		value: string
		label: string
	}>
}

export type RiwayatKenaikanPangkatItem = {
	nip: string
	no: string
	pangkat: string | null
	golongan: string | null
	tmt: string | null
	nomor_sk: string | null
	created_at: string | null
	updated_at: string | null
}

export type RiwayatKenaikanPangkatResponse = {
	nip: string
	data: RiwayatKenaikanPangkatItem[]
	total: number
	totalPages: number
	page: number
	limit: number
}

