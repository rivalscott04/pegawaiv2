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

	// 2024-01-15 atau bagian dari datetime ISO
	const iso = raw.match(/(\d{4})-(\d{2})-(\d{2})/)
	if (iso) {
		const year = Number(iso[1])
		const month = Number(iso[2])
		const day = Number(iso[3])
		const d = new Date(year, month - 1, day)
		if (
			!Number.isNaN(d.getTime()) &&
			d.getFullYear() === year &&
			d.getMonth() === month - 1 &&
			d.getDate() === day
		) {
			return d
		}
	}

	// DD/MM/YYYY, DD-MM-YYYY, DD.MM.YYYY (format penulisan umum di Indonesia)
	const match = raw.match(/(\d{1,2})[/.-](\d{1,2})[/.-](\d{2,4})/)
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

/** NIP ASN: 8 digit pertama = YYYYMMDD tanggal lahir */
function extractBirthDateFromNip(nip: string | null | undefined): Date | null {
	const digits = (nip ?? '').replace(/\D/g, '')
	if (digits.length < 8) return null
	const y = Number(digits.slice(0, 4))
	const m = Number(digits.slice(4, 6))
	const d = Number(digits.slice(6, 8))
	if (!y || !m || !d) return null
	const dt = new Date(y, m - 1, d)
	if (
		Number.isNaN(dt.getTime()) ||
		dt.getFullYear() !== y ||
		dt.getMonth() !== m - 1 ||
		dt.getDate() !== d
	) {
		return null
	}
	return dt
}

/** Tanggal lahir: utamakan NIP (8 digit pertama = YYYYMMDD, format ASN); TTL hanya jika NIP tidak valid. */
function resolveBirthDate(pegawai: PegawaiV2): Date | null {
	return extractBirthDateFromNip(pegawai.nip) ?? extractBirthDate(pegawai.tempat_tanggal_lahir)
}

function getRetirementAge(pegawai: PegawaiV2): number {
	const jabatan = normalizeText(pegawai.jabatan)
	const pangkatGolongan = normalizeText(pegawai.pangkat_golongan)

	if (containsAny(jabatan, ['guru'])) {
		return isGolonganIi(pangkatGolongan) ? 58 : 60
	}

	if (containsAny(jabatan, ['penghulu', 'penyuluh', 'pengawas'])) {
		if (containsAny(jabatan, ['madya'])) return 60
		if (containsAny(jabatan, ['muda'])) return 58
		// Ahli Pertama / Penghulu Pertama, dll. — sebelumnya null sehingga grid & export kosong
		if (containsAny(jabatan, ['pertama'])) return 58
		return 58
	}

	// Pelaksana, staff, pengadministrasi umum, anal, dan jabatan lain di luar rule di atas: BUP 58
	return 58
}

export function getRetirementDate(pegawai: PegawaiV2): Date | null {
	const jenisPegawai = normalizeText(pegawai.jenis_pegawai)
	if (!containsAny(jenisPegawai, ['pns', 'pppk'])) return null

	const birthDate = resolveBirthDate(pegawai)
	if (!birthDate) return null

	const retirementAge = getRetirementAge(pegawai)

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
