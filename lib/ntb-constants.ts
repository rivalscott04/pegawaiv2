/** Selaras dengan App\Support\PegawaiWilayah::canonicalKanwilIndukName() */
export const CANONICAL_KANWIL_INDUK_NAME =
	'Kantor Wilayah Kementerian Agama Provinsi Nusa Tenggara Barat'

/** Selaras dengan PegawaiWilayah::kanwilSourceUnitSlug() — baris Kantor Wilayah di pegawai v2. */
export const KANWIL_SOURCE_UNIT_SLUG = 'kanwil_kementerian_agama_provinsi_nusa_tenggara_barat'

/** Mirror App\Support\PegawaiWilayah::sourceUnitSlugToCanonicalIndukMap() */
export const SOURCE_UNIT_SLUG_TO_CANONICAL_INDUK: Record<string, string> = {
	kantor_kementerian_agama_kabupaten_bima: 'Kantor Kementerian Agama Kabupaten Bima',
	kantor_kementerian_agama_kabupaten_dompu: 'Kantor Kementerian Agama Kabupaten Dompu',
	kantor_kementerian_agama_kabupaten_lombok_barat: 'Kantor Kementerian Agama Kabupaten Lombok Barat',
	kantor_kementerian_agama_kabupaten_lombok_tengah: 'Kantor Kementerian Agama Kabupaten Lombok Tengah',
	kantor_kementerian_agama_kabupaten_lombok_timur: 'Kantor Kementerian Agama Kabupaten Lombok Timur',
	kantor_kementerian_agama_kabupaten_lombok_utara: 'Kantor Kementerian Agama Kabupaten Lombok Utara',
	kantor_kementerian_agama_kabupaten_sumbawa: 'Kantor Kementerian Agama Kabupaten Sumbawa',
	kantor_kementerian_agama_kabupaten_sumbawa_barat: 'Kantor Kementerian Agama Kabupaten Sumbawa Barat',
	kantor_kementerian_agama_kota_bima: 'Kantor Kementerian Agama Kota Bima',
	kantor_kementerian_agama_kota_mataram: 'Kantor Kementerian Agama Kota Mataram',
	kanwil_kementerian_agama_provinsi_nusa_tenggara_barat: CANONICAL_KANWIL_INDUK_NAME,
}

export function canonicalIndukFromSourceUnitSlug(slug: string): string | null {
	const key = slug.trim()
	if (SOURCE_UNIT_SLUG_TO_CANONICAL_INDUK[key]) {
		return SOURCE_UNIT_SLUG_TO_CANONICAL_INDUK[key]
	}
	const lower = key.toLowerCase()
	for (const [k, canonical] of Object.entries(SOURCE_UNIT_SLUG_TO_CANONICAL_INDUK)) {
		if (k.toLowerCase() === lower) {
			return canonical
		}
	}
	return null
}

export function sourceUnitSlugFromCanonicalInduk(canonicalInduk: string): string | null {
	const needle = canonicalInduk.trim()
	if (!needle) return null
	for (const [slug, canonical] of Object.entries(SOURCE_UNIT_SLUG_TO_CANONICAL_INDUK)) {
		if (canonical === needle) {
			return slug
		}
	}
	return null
}

/** Judul singkat seperti kolom `location` di API heatmap. */
export function locationTitleFromCanonicalInduk(canonical: string): string {
	let s = canonical
	s = s.replace(/^Kantor Kementerian Agama\s+/, '')
	s = s.replace(/^Kantor Wilayah Kementerian Agama Provinsi\s+/, '')
	return s
}

/** Segmen URL `/employees/location/[slug]` — slug source_unit, bukan teks induk kanonik. */
export function isLikelySourceUnitPathSlug(s: string): boolean {
	if (!s || s.includes(' ')) return false
	return /^[a-z0-9_]+$/.test(s)
}

export function resolveLocationPathSegment(pathSegment: string): {
	sourceUnitSlug: string | null
	fallbackSatkerInduk: string | null
} {
	const decoded = pathSegment.trim()
	if (!decoded) {
		return { sourceUnitSlug: null, fallbackSatkerInduk: null }
	}
	if (isLikelySourceUnitPathSlug(decoded)) {
		return { sourceUnitSlug: decoded, fallbackSatkerInduk: null }
	}
	const slug = sourceUnitSlugFromCanonicalInduk(decoded)
	if (slug) {
		return { sourceUnitSlug: slug, fallbackSatkerInduk: null }
	}
	return { sourceUnitSlug: null, fallbackSatkerInduk: decoded }
}
