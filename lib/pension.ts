import type { PegawaiV2 } from '@/lib/types'

function normalizeText(value: string | null | undefined): string {
	return (value ?? '').trim().toLowerCase()
}

function containsAny(haystack: string, needles: string[]): boolean {
	return needles.some((needle) => needle && haystack.includes(needle))
}

function isGolonganIi(pangkatGolongan: string): boolean {
	if (!pangkatGolongan) return false
	return /(^|[^a-z0-9])ii([\/\.\s-]|$)/i.test(pangkatGolongan)
}

function extractBirthDate(tempatTanggalLahir: string | null | undefined): Date | null {
	const raw = (tempatTanggalLahir ?? '').trim()
	if (!raw) return null

	const match = raw.match(/(\d{1,2})[\/-](\d{1,2})[\/-](\d{2,4})/)
	if (!match) return null

	const day = Number(match[1])
	const month = Number(match[2])
	let year = Number(match[3])
	if (!day || !month || !year) return null
	if (year < 100) year += 1900

	const d = new Date(year, month - 1, day)
	if (
		Number.isNaN(d.getTime()) ||
		d.getFullYear() !== year ||
		d.getMonth() !== month - 1 ||
		d.getDate() !== day
	) {
		return null
	}
	return d
}

function getRetirementAge(pegawai: PegawaiV2): number | null {
	const jabatan = normalizeText(pegawai.jabatan)
	const pangkatGolongan = normalizeText(pegawai.pangkat_golongan)

	if (containsAny(jabatan, ['guru'])) {
		return isGolonganIi(pangkatGolongan) ? 58 : 60
	}

	if (containsAny(jabatan, ['penghulu', 'penyuluh', 'pengawas'])) {
		if (containsAny(jabatan, ['madya'])) return 60
		if (containsAny(jabatan, ['muda'])) return 58
		return null
	}

	if (containsAny(jabatan, ['pelaksana'])) {
		return 58
	}

	return null
}

export function getRetirementDate(pegawai: PegawaiV2): Date | null {
	const jenisPegawai = normalizeText(pegawai.jenis_pegawai)
	if (!containsAny(jenisPegawai, ['pns'])) return null

	const birthDate = extractBirthDate(pegawai.tempat_tanggal_lahir)
	if (!birthDate) return null

	const retirementAge = getRetirementAge(pegawai)
	if (retirementAge === null) return null

	return new Date(birthDate.getFullYear() + retirementAge, birthDate.getMonth(), birthDate.getDate())
}

export function getAkanPensiunDate(pegawai: PegawaiV2): Date | null {
	const retirementDate = getRetirementDate(pegawai)
	if (!retirementDate) return null
	return new Date(retirementDate.getFullYear(), retirementDate.getMonth() + 1, 1)
}

export function formatDateFriendlyId(value: Date | null): string {
	if (!value || Number.isNaN(value.getTime())) return '-'
	return value.toLocaleDateString('id-ID', {
		day: 'numeric',
		month: 'long',
		year: 'numeric',
	})
}
