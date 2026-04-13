'use client'

import React, { useEffect, useMemo, useRef, useState } from 'react'
import type {
	PegawaiListResponseV2,
	PegawaiFiltersResponseV2,
	PegawaiV2,
	RiwayatKenaikanPangkatItem,
	RiwayatKenaikanPangkatResponse,
} from '@/lib/types'
import { apiFetch, apiFetchBlob } from '@/lib/api'
import { useCanEditEmployees, useCanExportPegawai } from '@/lib/use-permissions-from-storage'
import { info } from '@/components/Info'
import { formatDateFriendlyId, getAkanPensiunDate } from '@/lib/pension'

type DetailTab = 'profile' | 'riwayat'
type StatusFilter = '' | 'true' | 'false'
type SourceUnitOption = { value: string; label: string }
type HierarchyOption = {
	key: string
	value: string
	label: string
	filterType: 'source_unit_slug'
}

function normalizeFilterText(value: string): string {
	return value
		.normalize('NFKD')
		.replace(/[^\w\s]/g, ' ')
		.replace(/\s+/g, ' ')
		.trim()
		.toLowerCase()
}

function formatSourceUnitSlug(value: string): string {
	return value
		.replaceAll('_', ' ')
		.split(' ')
		.map((part) => (part ? part[0].toUpperCase() + part.slice(1) : part))
		.join(' ')
}

function formatDate(value: string | null | undefined): string {
	if (!value) return '-'
	const d = new Date(value)
	if (Number.isNaN(d.getTime())) return value
	return d.toLocaleDateString('id-ID', {
		day: '2-digit',
		month: 'short',
		year: 'numeric',
	})
}

function formatTempatTanggalLahir(value: string | null | undefined): string {
	if (!value) return '-'
	const raw = value.trim()
	if (!raw) return '-'

	const match = raw.match(/^(.*?)[,\s]+(\d{1,2}[\/-]\d{1,2}[\/-]\d{2,4})$/)
	if (!match) return raw

	const place = match[1].trim().replace(/,\s*$/, '')
	const datePart = match[2]
	const [dd, mm, yyyy] = datePart.split(/[\/-]/)
	const day = Number(dd)
	const month = Number(mm)
	const year = Number(yyyy)

	if (!day || !month || !year) return raw
	const fullYear = year < 100 ? 2000 + year : year
	const date = new Date(fullYear, month - 1, day)
	if (Number.isNaN(date.getTime())) return raw

	const readableDate = date.toLocaleDateString('id-ID', {
		day: '2-digit',
		month: 'short',
		year: 'numeric',
	})

	return place ? `${place}, ${readableDate}` : readableDate
}

function CopyButton({ value, label }: { value: string; label: string }) {
	return (
		<button
			type="button"
			className="btn btn-ghost btn-xs shrink-0 opacity-70 hover:opacity-100 focus:opacity-100"
			title={`Salin ${label}`}
			aria-label={`Salin ${label}`}
			onClick={async () => {
				try {
					await navigator.clipboard?.writeText?.(value)
					info(`${label} berhasil disalin`, 'Info')
				} catch {}
			}}
		>
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
				<path d="M16 1H4c-1.103 0-2 .897-2 2v12h2V3h12V1z" />
				<path d="M19 5H8c-1.103 0-2 .897-2 2v13c0 1.103.897 2 2 2h11c1.103 0 2-.897 2-2V7c0-1.103-.897-2-2-2zm0 15H8V7h11v13z" />
			</svg>
		</button>
	)
}

function Field({
	label,
	value,
	mono,
}: {
	label: string
	value: React.ReactNode
	mono?: boolean
}) {
	return (
		<div className="min-w-0">
			<div className="text-xs opacity-70">{label}</div>
			<div className={mono ? 'font-mono break-all' : 'whitespace-normal wrap-break-word'}>{value ?? '-'}</div>
		</div>
	)
}

function RetirementBadge({ isActive }: { isActive: boolean }) {
	if (isActive) {
		return <span className="badge badge-success badge-sm whitespace-nowrap">Aktif</span>
	}
	return <span className="badge badge-warning badge-sm whitespace-nowrap">Pensiun</span>
}

export default function EmployeesPageV2() {
	const canExport = useCanExportPegawai()
	const canEdit = useCanEditEmployees()

	const [items, setItems] = useState<PegawaiV2[]>([])
	const [loading, setLoading] = useState(false)

	const [page, setPage] = useState(1)
	const [limit, setLimit] = useState(10)
	const [totalPages, setTotalPages] = useState(1)
	const [total, setTotal] = useState(0)

	const [statistics, setStatistics] = useState<{ active: number; inactive: number }>({ active: 0, inactive: 0 })
	const [exporting, setExporting] = useState(false)

	const [searchInput, setSearchInput] = useState('')
	const [search, setSearch] = useState('')
	const [satkerInduk, setSatkerInduk] = useState('')
	const [sourceUnitSlug, setSourceUnitSlug] = useState('')
	const [unitKerja, setUnitKerja] = useState('')
	const [jenisPegawai, setJenisPegawai] = useState('')
	const [pangkatGolongan, setPangkatGolongan] = useState('')
	const [statusFilter, setStatusFilter] = useState<StatusFilter>('true')

	const [sourceUnitOptions, setSourceUnitOptions] = useState<SourceUnitOption[]>([])
	const [unitKerjaOptions, setUnitKerjaOptions] = useState<string[]>([])
	const [jenisPegawaiOptions, setJenisPegawaiOptions] = useState<string[]>([])
	const [pangkatOptions, setPangkatOptions] = useState<string[]>([])

	const [selected, setSelected] = useState<PegawaiV2 | null>(null)
	const [detailTab, setDetailTab] = useState<DetailTab>('profile')
	const detailModalRef = useRef<HTMLDialogElement | null>(null)
	const [riwayatByNip, setRiwayatByNip] = useState<Record<string, RiwayatKenaikanPangkatItem[]>>({})
	const [riwayatLoadingNip, setRiwayatLoadingNip] = useState<string | null>(null)
	const [riwayatErrorNip, setRiwayatErrorNip] = useState<string | null>(null)
	const [hiddenColumnKeys, setHiddenColumnKeys] = useState<string[]>([])

	async function loadFilters(params?: {
		satker_induk?: string
		source_unit_slug?: string
		jenis_pegawai?: string
	}) {
		try {
			const baseQs = new URLSearchParams()
			baseQs.set('limit', '100')

			// Keep top-level dropdown options complete, regardless of active filters.
			const topLevelData = await apiFetch<PegawaiFiltersResponseV2>(`/pegawai/filters?${baseQs.toString()}`)
			setJenisPegawaiOptions(Array.isArray(topLevelData?.jenis_pegawai) ? topLevelData.jenis_pegawai : [])
			setPangkatOptions(Array.isArray(topLevelData?.pangkat_golongan) ? topLevelData.pangkat_golongan : [])

			const sourceUnitsFromPair = Array.isArray(topLevelData?.source_units)
				? topLevelData.source_units
					.filter((item) => item && typeof item.value === 'string' && typeof item.label === 'string')
					.map((item) => ({ value: item.value, label: item.label }))
				: []
			const sourceUnitsFromSlug = Array.isArray(topLevelData?.source_unit_slug)
				? topLevelData.source_unit_slug
					.filter((slug): slug is string => typeof slug === 'string' && slug.trim().length > 0)
					.map((slug) => ({ value: slug, label: formatSourceUnitSlug(slug) }))
				: []
			setSourceUnitOptions(sourceUnitsFromPair.length > 0 ? sourceUnitsFromPair : sourceUnitsFromSlug)

			// Unit Kerja stays cascading (depends on selected hierarchy).
			const unitQs = new URLSearchParams()
			unitQs.set('limit', '100')
			if (params?.satker_induk) unitQs.set('satker_induk', params.satker_induk)
			if (params?.source_unit_slug) unitQs.set('source_unit_slug', params.source_unit_slug)
			if (params?.jenis_pegawai) unitQs.set('jenis_pegawai', params.jenis_pegawai)

			const unitData = await apiFetch<PegawaiFiltersResponseV2>(`/pegawai/filters?${unitQs.toString()}`)
			setUnitKerjaOptions(Array.isArray(unitData?.unit_kerja) ? unitData.unit_kerja : [])
		} catch (e) {
			// Silent: filter dropdowns can still work with empty options.
			if (process.env.NODE_ENV === 'development') console.error('loadFilters failed', e)
		}
	}

	const selectedHierarchy = sourceUnitSlug ? `source_unit_slug:${sourceUnitSlug}` : ''
	const isKanwilFilterSelected = sourceUnitSlug.trim().toLowerCase().startsWith('kanwil_')
	const selectedSourceUnitLabel = useMemo(
		() => sourceUnitOptions.find((option) => option.value === sourceUnitSlug)?.label ?? '',
		[sourceUnitOptions, sourceUnitSlug]
	)
	const isParentUnitKerjaSelected = useMemo(() => {
		if (!unitKerja || !selectedSourceUnitLabel) return false
		return normalizeFilterText(unitKerja) === normalizeFilterText(selectedSourceUnitLabel)
	}, [unitKerja, selectedSourceUnitLabel])
	const effectiveUnitKerja = isParentUnitKerjaSelected ? '' : unitKerja

	const queryString = useMemo(() => {
		const params = new URLSearchParams()
		params.set('page', String(page))
		params.set('limit', String(limit))
		if (search) params.set('search', search)
		if (satkerInduk) params.set('satker_induk', satkerInduk)
		if (sourceUnitSlug) params.set('source_unit_slug', sourceUnitSlug)
		if (effectiveUnitKerja) params.set('unit_kerja', effectiveUnitKerja)
		if (jenisPegawai) params.set('jenis_pegawai', jenisPegawai)
		if (pangkatGolongan) params.set('pangkat_golongan', pangkatGolongan)
		if (statusFilter) params.set('is_active', statusFilter)
		return params.toString()
	}, [page, limit, search, satkerInduk, sourceUnitSlug, effectiveUnitKerja, jenisPegawai, pangkatGolongan, statusFilter])

	const paginationItems = useMemo<(number | 'ellipsis-left' | 'ellipsis-right')[]>(() => {
		if (totalPages <= 7) return Array.from({ length: totalPages }, (_, i) => i + 1)

		const items: (number | 'ellipsis-left' | 'ellipsis-right')[] = [1]
		const start = Math.max(2, page - 1)
		let end = Math.min(totalPages - 1, page + 1)

		if (page <= 4) end = 4
		// Jangan paksa start ke totalPages-3: itu membuat halaman (page-1) hilang dari tombol
		// (mis. di 726 tidak bisa klik 725).

		if (start > 2) items.push('ellipsis-left')
		for (let p = start; p <= end; p += 1) items.push(p)
		if (end < totalPages - 1) items.push('ellipsis-right')
		items.push(totalPages)

		return items
	}, [page, totalPages])

	const hierarchyOptions = useMemo<HierarchyOption[]>(() => {
		const options: HierarchyOption[] = [
			...sourceUnitOptions
				.map((option) => ({
					key: `source_unit_slug:${option.value}`,
					value: option.value,
					label: option.label,
					filterType: 'source_unit_slug' as const,
				})),
		]

		const seen = new Set<string>()
		return options.filter((option) => {
			if (seen.has(option.key)) return false
			seen.add(option.key)
			return true
		})
	}, [sourceUnitOptions])

	async function load() {
		setLoading(true)
		try {
			const json = await apiFetch<PegawaiListResponseV2>(`/pegawai?${queryString}`)
			setItems(json?.data ?? [])
			setTotal(json?.total ?? 0)
			setTotalPages(json?.totalPages ?? 1)
			setPage(json?.page ?? page)
			setLimit(json?.limit ?? limit)
			setStatistics({
				active: json?.active ?? 0,
				inactive: json?.inactive ?? 0,
			})
		} catch (e) {
			console.error('load pegawai v2 failed', e)
			setItems([])
		} finally {
			setLoading(false)
		}
	}

	// Initial load
	useEffect(() => {
		setSatkerInduk('')
		loadFilters()
		load()
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [])

	useEffect(() => {
		const id = window.setTimeout(() => setSearch(searchInput), 400)
		return () => window.clearTimeout(id)
	}, [searchInput])

	useEffect(() => {
		loadFilters({
			satker_induk: satkerInduk || undefined,
			source_unit_slug: sourceUnitSlug || undefined,
			jenis_pegawai: jenisPegawai || undefined,
		})
	}, [satkerInduk, sourceUnitSlug, jenisPegawai])

	// Reload when query changes
	useEffect(() => {
		load()
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [queryString])

	const columns = useMemo(() => {
		const baseColumns = [
			{ key: 'nip' as const, label: 'NIP', render: (r: PegawaiV2) => r.nip || '-' },
			{ key: 'nama' as const, label: 'Nama', render: (r: PegawaiV2) => r.nama || '-' },
			{ key: 'akan_pensiun' as const, label: 'Akan Pensiun', render: (r: PegawaiV2) => formatDateFriendlyId(getAkanPensiunDate(r)) },
			{ key: 'jenis_pegawai' as const, label: 'Jenis Pegawai', render: (r: PegawaiV2) => r.jenis_pegawai || '-' },
			{ key: 'unit_kerja' as const, label: 'Unit Kerja', render: (r: PegawaiV2) => r.unit_kerja || '-' },
			{ key: 'satker_induk' as const, label: 'Kanwil (Provinsi)', render: (r: PegawaiV2) => r.satker_induk || '-' },
			{ key: 'jabatan' as const, label: 'Jabatan', render: (r: PegawaiV2) => r.jabatan || '-' },
			{ key: 'pangkat_golongan' as const, label: 'Pangkat/Golongan', render: (r: PegawaiV2) => r.pangkat_golongan || '-' },
		]

		if (isKanwilFilterSelected) return baseColumns

		return [
			baseColumns[0],
			baseColumns[1],
			baseColumns[2],
			baseColumns[3],
			{
				key: 'source_unit_slug' as const,
				label: 'Kantor Kemenag (Kab/Kota)',
				render: (r: PegawaiV2) => (r.source_unit_slug ? formatSourceUnitSlug(r.source_unit_slug) : '-'),
			},
			baseColumns[4],
			baseColumns[5],
			baseColumns[6],
		]
	}, [isKanwilFilterSelected])

	const visibleColumns = useMemo(
		() => columns.filter((column) => !hiddenColumnKeys.includes(String(column.key))),
		[columns, hiddenColumnKeys]
	)

	useEffect(() => {
		try {
			const raw = localStorage.getItem('pegawai_v2_hidden_columns')
			if (!raw) return
			const parsed = JSON.parse(raw)
			if (Array.isArray(parsed)) {
				setHiddenColumnKeys(parsed.filter((item) => typeof item === 'string'))
			}
		} catch {}
	}, [])

	useEffect(() => {
		try {
			localStorage.setItem('pegawai_v2_hidden_columns', JSON.stringify(hiddenColumnKeys))
		} catch {}
	}, [hiddenColumnKeys])

	function toggleColumnVisibility(columnKey: string) {
		setHiddenColumnKeys((current) =>
			current.includes(columnKey) ? current.filter((key) => key !== columnKey) : [...current, columnKey]
		)
	}

	function openDetail(row: PegawaiV2) {
		setSelected(row)
		setDetailTab('profile')
		setTimeout(() => detailModalRef.current?.showModal(), 0)
	}

	async function loadRiwayatKenaikanPangkat(nip: string) {
		if (!nip || riwayatByNip[nip]) return
		setRiwayatLoadingNip(nip)
		setRiwayatErrorNip(null)
		try {
			const res = await apiFetch<RiwayatKenaikanPangkatResponse>(`/pegawai/${encodeURIComponent(nip)}/riwayat-kenaikan-pangkat?limit=500&page=1`)
			setRiwayatByNip((prev) => ({ ...prev, [nip]: Array.isArray(res?.data) ? res.data : [] }))
		} catch (e) {
			console.error('load riwayat kenaikan pangkat failed', e)
			setRiwayatErrorNip(nip)
		} finally {
			setRiwayatLoadingNip((curr) => (curr === nip ? null : curr))
		}
	}

	useEffect(() => {
		if (detailTab !== 'riwayat') return
		if (!selected?.nip) return
		void loadRiwayatKenaikanPangkat(selected.nip)
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [detailTab, selected?.nip])

	const selectedRiwayat = useMemo(() => {
		if (!selected?.nip) return []
		return riwayatByNip[selected.nip] ?? []
	}, [riwayatByNip, selected?.nip])

	const latestRiwayat = useMemo(() => {
		if (selectedRiwayat.length === 0) return null
		return [...selectedRiwayat].sort((a, b) => {
			const da = a.tmt ? new Date(a.tmt).getTime() : 0
			const db = b.tmt ? new Date(b.tmt).getTime() : 0
			return db - da
		})[0]
	}, [selectedRiwayat])

	const buildBaseFilterParams = () => {
		const params = new URLSearchParams()
		if (search) params.set('search', search)
		if (satkerInduk) params.set('satker_induk', satkerInduk)
		if (sourceUnitSlug) params.set('source_unit_slug', sourceUnitSlug)
		if (effectiveUnitKerja) params.set('unit_kerja', effectiveUnitKerja)
		if (jenisPegawai) params.set('jenis_pegawai', jenisPegawai)
		if (pangkatGolongan) params.set('pangkat_golongan', pangkatGolongan)
		if (statusFilter) params.set('is_active', statusFilter)
		return params
	}

	function getFilenameFromDisposition(disposition: string | null, fallback: string): string {
		if (!disposition) return fallback
		const utf8Match = disposition.match(/filename\*=UTF-8''([^;]+)/i)
		if (utf8Match?.[1]) return decodeURIComponent(utf8Match[1])
		const normalMatch = disposition.match(/filename="?([^"]+)"?/i)
		return normalMatch?.[1] || fallback
	}

	async function exportFromBackend(format: 'csv' | 'xlsx', scope: 'page' | 'all', separator: 'comma' | 'semicolon' = 'comma') {
		try {
			setExporting(true)
			if (scope === 'all') info('Export semua data diproses di background. Mohon tunggu sebentar.', 'Export')

			const params = buildBaseFilterParams()
			params.set('format', format)
			params.set('scope', scope)
			if (scope === 'page') {
				params.set('page', String(page))
				params.set('limit', String(limit))
			}
			if (format === 'csv') {
				params.set('separator', separator)
			}

			if (scope === 'page') {
				const { blob, response } = await apiFetchBlob(`/pegawai/export?${params.toString()}`)
				const fallbackFileName = `pegawai_export_${scope}.${format}`
				const fileName = getFilenameFromDisposition(response.headers.get('content-disposition'), fallbackFileName)

				const url = URL.createObjectURL(blob)
				const anchor = document.createElement('a')
				anchor.href = url
				anchor.download = fileName
				anchor.click()
				URL.revokeObjectURL(url)
				info('Export berhasil', 'Export Berhasil')
				return
			}

			const task = await apiFetch<{
				success: boolean
				task_id: string
				status: 'queued' | 'processing' | 'completed' | 'failed'
				download_url?: string | null
				error_message?: string | null
			}>(`/pegawai/export?${params.toString()}`)
			const taskId = task?.task_id
			if (!taskId) {
				throw new Error('Task export tidak valid.')
			}

			async function downloadExportFile(downloadUrl: string) {
				const { blob, response } = await apiFetchBlob(downloadUrl)
				const fallbackFileName = `pegawai_export_all.${format}`
				const fileName = getFilenameFromDisposition(response.headers.get('content-disposition'), fallbackFileName)
				const url = URL.createObjectURL(blob)
				const anchor = document.createElement('a')
				anchor.href = url
				anchor.download = fileName
				anchor.click()
				URL.revokeObjectURL(url)
				info('Export semua data selesai dan berhasil diunduh', 'Export Berhasil')
			}

			if (task.status === 'failed') {
				throw new Error(task.error_message || 'Export gagal diproses.')
			}
			if (task.status === 'completed' && task.download_url) {
				await downloadExportFile(task.download_url)
				return
			}

			let status: 'queued' | 'processing' | 'completed' | 'failed' = task.status || 'queued'
			while (status === 'queued' || status === 'processing') {
				await new Promise((resolve) => setTimeout(resolve, 2000))
				const check = await apiFetch<{
					success: boolean
					status: 'queued' | 'processing' | 'completed' | 'failed'
					download_url: string | null
					error_message: string | null
				}>(`/pegawai/export/${encodeURIComponent(taskId)}/status`)

				status = check.status
				if (status === 'failed') {
					throw new Error(check.error_message || 'Export gagal diproses.')
				}

				if (status === 'completed' && check.download_url) {
					await downloadExportFile(check.download_url)
					return
				}
			}
		} catch (error) {
			console.error('export failed', error)
			info('Gagal export data. Silakan coba lagi.', 'Export Gagal')
		} finally {
			setExporting(false)
		}
	}

	return (
		<div className="p-2 sm:p-4 md:p-8 overflow-x-hidden max-w-full">
			<div className="mb-4 space-y-2">
				<h1 className="text-2xl font-semibold">Data Pegawai (API v2)</h1>

				<div className="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
					<div className="card bg-base-300 border border-base-content/20 shadow-lg">
						<div className="card-body py-4">
							<h3 className="text-sm text-base-content/70">Total Pegawai</h3>
							<p className="text-2xl font-bold text-base-content">{total.toLocaleString('id-ID')}</p>
						</div>
					</div>
					<div className="card bg-base-300 border border-success shadow-lg">
						<div className="card-body py-4">
							<h3 className="text-sm text-base-content/70">Aktif</h3>
							<p className="text-2xl font-bold text-success">{statistics.active.toLocaleString('id-ID')}</p>
						</div>
					</div>
					<div className="card bg-base-300 border border-warning shadow-lg">
						<div className="card-body py-4">
							<h3 className="text-sm text-base-content/70">Nonaktif</h3>
							<p className="text-2xl font-bold text-warning">{statistics.inactive.toLocaleString('id-ID')}</p>
						</div>
					</div>
				</div>

				<div className="flex items-center gap-2 flex-wrap">
					<span className="text-sm opacity-70">Jumlah Data</span>
					<select className="select select-sm w-24" value={String(limit)} onChange={(e) => {
						setLimit(parseInt(e.target.value, 10))
						setPage(1)
					}}>
						<option value="10">10</option>
						<option value="25">25</option>
						<option value="50">50</option>
						<option value="100">100</option>
						<option value="200">200</option>
					</select>

					<label className="input input-bordered w-full max-w-sm">
						<input
							placeholder="Cari nama, NIP, atau jabatan"
							value={searchInput}
							onChange={(e) => {
								setSearchInput(e.target.value)
								setPage(1)
							}}
						/>
					</label>

					<select
						className="select select-sm w-full max-w-xs"
						value={selectedHierarchy}
						onChange={(e) => {
							const selected = e.target.value
							if (!selected) {
								setSatkerInduk('')
								setSourceUnitSlug('')
								setUnitKerja('')
								setPage(1)
								return
							}

							const [type, ...rawParts] = selected.split(':')
							const rawValue = rawParts.join(':')

							if (type === 'source_unit_slug') {
								setSatkerInduk('')
								setSourceUnitSlug(rawValue)
							}

							setUnitKerja('')
							setPage(1)
						}}
					>
						<option value="">Semua Kanwil / Kantor Kemenag (Kab/Kota)</option>
						{hierarchyOptions.map((option) => (
							<option key={option.key} value={option.key}>
								{option.label}
							</option>
						))}
					</select>

					<select
						className="select select-sm w-full max-w-xs"
						value={unitKerja}
						onChange={(e) => {
							const nextUnitKerja = e.target.value
							if (
								nextUnitKerja &&
								selectedSourceUnitLabel &&
								normalizeFilterText(nextUnitKerja) === normalizeFilterText(selectedSourceUnitLabel)
							) {
								// Selecting parent office in child-unit filter means "show all descendants".
								setUnitKerja('')
								setPage(1)
								return
							}
							setUnitKerja(nextUnitKerja)
							setPage(1)
						}}
					>
						<option value="">Semua Unit Kerja (Turunan)</option>
						{unitKerjaOptions.map((v) => (
							<option key={v} value={v}>{v}</option>
						))}
					</select>

					<select className="select select-sm w-full max-w-xs" value={jenisPegawai} onChange={(e) => { setJenisPegawai(e.target.value); setPage(1) }}>
						<option value="">Semua Jenis Pegawai</option>
						{jenisPegawaiOptions.map((v) => (
							<option key={v} value={v}>{v}</option>
						))}
					</select>

					<select className="select select-sm w-full max-w-xs" value={pangkatGolongan} onChange={(e) => { setPangkatGolongan(e.target.value); setPage(1) }}>
						<option value="">Semua Pangkat/Golongan</option>
						{pangkatOptions.map((v) => (
							<option key={v} value={v}>{v}</option>
						))}
					</select>

					<select
						className="select select-sm w-full max-w-xs"
						value={statusFilter}
						onChange={(e) => { setStatusFilter(e.target.value as StatusFilter); setPage(1) }}
					>
						<option value="">Semua Status</option>
						<option value="true">Aktif</option>
						<option value="false">Nonaktif</option>
					</select>

					<button
						type="button"
						className="btn btn-sm"
						onClick={() => {
							setSearchInput('')
							setSearch('')
							setSatkerInduk('')
							setSourceUnitSlug('')
							setUnitKerja('')
							setJenisPegawai('')
							setPangkatGolongan('')
							setStatusFilter('true')
							setPage(1)
						}}
					>
						Reset
					</button>
					<details className="dropdown dropdown-end">
						<summary className="btn btn-sm m-0">Kolom</summary>
						<ul className="dropdown-content menu mt-1 w-72 rounded-box bg-base-100 p-2 shadow-xl z-50 border border-base-300">
							{columns.map((column) => {
								const key = String(column.key)
								const isVisible = !hiddenColumnKeys.includes(key)
								return (
									<li key={key}>
										<label className="label cursor-pointer justify-start gap-3 px-2">
											<input
												type="checkbox"
												className="checkbox checkbox-sm"
												checked={isVisible}
												onChange={() => toggleColumnVisibility(key)}
											/>
											<span className="label-text">{column.label}</span>
										</label>
									</li>
								)
							})}
						</ul>
					</details>
					{canExport && (
						<div className="dropdown dropdown-end">
							<button
								type="button"
								tabIndex={0}
								role="button"
								className="btn btn-sm btn-primary whitespace-nowrap"
								aria-haspopup="menu"
								disabled={exporting}
							>
								{exporting ? <span className="loading loading-dots loading-sm" /> : 'Export'}
							</button>
							<ul className="dropdown-content menu mt-1 w-72 rounded-box bg-[#001b3d] p-3 text-white shadow-xl z-50" role="menu">
								<li className="menu-title px-1 pb-1 text-white/55">
									<span>Halaman Saat Ini ({items.length} data)</span>
								</li>
								<li><button disabled={exporting} onClick={() => exportFromBackend('csv', 'page', 'comma')}>CSV (Koma ,)</button></li>
								<li><button disabled={exporting} onClick={() => exportFromBackend('csv', 'page', 'semicolon')}>CSV (Titik Koma ;)</button></li>
								<li><button disabled={exporting} onClick={() => exportFromBackend('xlsx', 'page')}>Excel (.xlsx)</button></li>
								<li className="my-2 h-px bg-white/20" />
								<li className="menu-title px-1 pb-1 text-white/55">
									<span>Semua Data (Tanpa Paginasi)</span>
								</li>
								<li><button disabled={exporting} onClick={() => exportFromBackend('csv', 'all', 'comma')}>CSV (Koma ,)</button></li>
								<li><button disabled={exporting} onClick={() => exportFromBackend('csv', 'all', 'semicolon')}>CSV (Titik Koma ;)</button></li>
								<li><button disabled={exporting} onClick={() => exportFromBackend('xlsx', 'all')}>Excel (.xlsx)</button></li>
							</ul>
						</div>
					)}
				</div>
			</div>

			<div className="hidden md:block overflow-x-auto rounded-box border border-base-300">
				<table className="table w-full">
					<thead>
						<tr>
							{visibleColumns.map((c) => {
								const thClass =
									c.key === 'nip'
										? 'min-w-[14rem]'
										: c.key === 'nama'
											? 'min-w-[12rem]'
											: c.key === 'unit_kerja'
												? 'min-w-[11rem]'
												: c.key === 'source_unit_slug'
													? 'min-w-[14rem]'
												: c.key === 'satker_induk'
													? 'min-w-[12rem]'
												: c.key === 'jabatan'
													? 'min-w-[14rem]'
													: c.key === 'pangkat_golongan'
														? 'min-w-[10rem]'
															: c.key === 'akan_pensiun'
																? 'min-w-[9rem]'
														: undefined
								return (
									<th key={c.key} className={thClass}>
										{c.label}
									</th>
								)
							})}
							<th className="w-28 text-center">Aksi</th>
						</tr>
					</thead>
					<tbody>
						{loading ? (
							<tr>
								<td colSpan={visibleColumns.length + 1}>
									<span className="loading loading-bars" />
								</td>
							</tr>
						) : (
							items.map((row) => (
								<tr key={row.nip}>
									{visibleColumns.map((c) => {
										const cellClass =
											c.key === 'nip'
												? 'font-mono whitespace-nowrap'
												: c.key === 'nama'
													? 'whitespace-normal wrap-break-word'
													: c.key === 'unit_kerja'
														? 'whitespace-normal wrap-break-word'
														: c.key === 'source_unit_slug'
															? 'whitespace-normal wrap-break-word'
														: c.key === 'satker_induk'
															? 'whitespace-normal wrap-break-word'
														: c.key === 'jabatan'
															? 'whitespace-normal wrap-break-word'
															: c.key === 'pangkat_golongan'
																? 'whitespace-nowrap'
																: c.key === 'akan_pensiun'
																	? 'whitespace-nowrap'
																: 'whitespace-normal wrap-break-word'
										const content = c.render(row)

										if (c.key === 'nip') {
											return (
												<td key={c.key}>
													<div className="flex items-center gap-2">
														<span className={cellClass}>{content}</span>
														<CopyButton value={row.nip} label="NIP" />
													</div>
												</td>
											)
										}

										if (c.key === 'nama') {
											return (
												<td key={c.key}>
													<div className="flex items-center gap-2">
														<button
															type="button"
															className="text-primary hover:opacity-90 text-left font-semibold"
															onClick={() => openDetail(row)}
														>
															<div className={cellClass}>{content}</div>
														</button>
										<RetirementBadge isActive={row.is_active} />
														<CopyButton value={row.nama} label="Nama" />
													</div>
												</td>
											)
										}

										return (
											<td key={c.key}>
												<div className={cellClass}>{content}</div>
											</td>
										)
									})}
									<td className="text-right">
										<div className="join join-horizontal justify-end">
											<button className="btn btn-sm btn-primary join-item" onClick={() => openDetail(row)}>
												Detail
											</button>
											{canEdit && (
												<a className="btn btn-sm btn-secondary join-item" href={`/employees/${row.nip}/edit`}>
													Edit
												</a>
											)}
										</div>
									</td>
								</tr>
							))
						)}
					</tbody>
				</table>
			</div>

			<div className="md:hidden space-y-3">
				{loading ? (
					<div className="card bg-base-100 border border-base-300">
						<div className="card-body py-6 flex items-center justify-center">
							<span className="loading loading-bars" />
						</div>
					</div>
				) : items.length === 0 ? (
					<div className="card bg-base-100 border border-base-300">
						<div className="card-body py-6 text-center text-sm text-base-content/70">
							Tidak ada data
						</div>
					</div>
				) : (
					items.map((row) => (
						<div key={row.nip} className="card bg-base-100 border border-base-300 shadow-sm">
							<div className="card-body p-4 space-y-4">
								<div className="space-y-3">
									<div className="flex items-start justify-between gap-3">
										<div className="min-w-0">
											<div className="text-xs opacity-70">NIP</div>
											<div className="mt-1 flex items-center gap-2">
												<span className="font-mono break-all">{row.nip || '-'}</span>
												<CopyButton value={row.nip} label="NIP" />
											</div>
										</div>
									</div>

									<div className="min-w-0">
										<div className="text-xs opacity-70">Nama</div>
										<div className="mt-1 flex items-start gap-2">
											<div className="font-semibold whitespace-normal wrap-break-word">{row.nama || '-'}</div>
										<RetirementBadge isActive={row.is_active} />
											<CopyButton value={row.nama} label="Nama" />
										</div>
									</div>
								</div>

								<details className="collapse collapse-arrow bg-base-200/60 border border-base-300">
									<summary className="collapse-title py-3 min-h-0 font-medium">Detail Lainnya</summary>
									<div className="collapse-content pt-1 pb-0 space-y-3">
										{visibleColumns
											.filter((c) => c.key !== 'nip' && c.key !== 'nama')
											.map((c) => (
												<div key={c.key} className="space-y-1">
													<div className="text-xs opacity-70">{c.label}</div>
													<div className="text-sm whitespace-normal wrap-break-word">{c.render(row)}</div>
												</div>
											))}
									</div>
								</details>

								<div className="flex justify-end gap-2 pt-1">
									<button className="btn btn-sm btn-primary" onClick={() => openDetail(row)}>
										Detail
									</button>
									{canEdit && (
										<a className="btn btn-sm btn-secondary" href={`/employees/${row.nip}/edit`}>
											Edit
										</a>
									)}
								</div>
							</div>
						</div>
					))
				)}
			</div>

			<div className="mt-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2">
				<div className="text-sm opacity-70">
					{total > 0 ? `Menampilkan halaman ${page} dari ${totalPages} (total ${total.toLocaleString('id-ID')})` : 'Tidak ada data'}
				</div>
				{totalPages > 1 && (
					<div className="join">
						<button
							className="btn join-item"
							disabled={page <= 1}
							onClick={() => setPage((p) => Math.max(1, p - 1))}
						>
							«
						</button>
						{paginationItems.map((item, idx) =>
							typeof item === 'number' ? (
								<button
									key={item}
									className={`btn join-item ${item === page ? 'btn-active' : ''}`}
									onClick={() => setPage(item)}
									aria-current={item === page ? 'page' : undefined}
								>
									{item}
								</button>
							) : (
								<button key={`${item}-${idx}`} className="btn join-item btn-disabled" disabled>
									...
								</button>
							)
						)}
						<button
							className="btn join-item"
							disabled={page >= totalPages}
							onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
						>
							»
						</button>
					</div>
				)}
			</div>

			{/* Detail Modal */}
			<dialog id="pegawai_modal_v2" ref={detailModalRef} className="modal">
				<div className="modal-box max-w-full w-full sm:w-auto sm:max-w-5xl p-4 sm:p-6 space-y-4">
					<div className="flex items-start justify-between gap-4">
						<div className="space-y-2">
							<p className="text-xs uppercase tracking-wide text-base-content/70">Detail Pegawai</p>
							{selected && (
								<>
									<div className="flex flex-wrap items-center gap-2">
										<h3 className="font-bold text-lg">{selected.nama || '-'}</h3>
										<RetirementBadge isActive={selected.is_active} />
									</div>
									<div className="flex items-center gap-2 text-sm text-base-content/70">
										<span>
											NIP: <span className="font-mono">{selected.nip || '-'}</span>
										</span>
										{selected.nip ? <CopyButton value={selected.nip} label="NIP" /> : null}
									</div>
								</>
							)}
						</div>
						<form method="dialog" className="shrink-0">
							<button className="btn btn-ghost btn-sm" type="submit">Tutup</button>
						</form>
					</div>

					{selected && (
						<div className="space-y-4">
							<div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
								<div className="card bg-base-200 border border-base-300">
									<div className="card-body py-3 px-4">
										<p className="text-xs text-base-content/70">Jabatan Saat Ini</p>
										<p className="text-sm font-medium whitespace-normal wrap-break-word">{selected.jabatan || '-'}</p>
									</div>
								</div>
								<div className="card bg-base-200 border border-base-300">
									<div className="card-body py-3 px-4">
										<p className="text-xs text-base-content/70">Unit Kerja</p>
										<p className="text-sm font-medium whitespace-normal wrap-break-word">{selected.unit_kerja || '-'}</p>
									</div>
								</div>
								<div className="card bg-base-200 border border-base-300">
									<div className="card-body py-3 px-4">
										<p className="text-xs text-base-content/70">Pangkat/Golongan</p>
										<p className="text-sm font-medium whitespace-normal wrap-break-word">{selected.pangkat_golongan || '-'}</p>
									</div>
								</div>
							</div>

							<div className="tabs tabs-border">
								<button
									type="button"
									className={`tab ${detailTab === 'profile' ? 'tab-active font-semibold text-primary [--tab-border-color:var(--color-primary)]' : 'text-base-content/70'}`}
									onClick={() => setDetailTab('profile')}
								>
									Profil
								</button>
								<button
									type="button"
									className={`tab ${detailTab === 'riwayat' ? 'tab-active font-semibold text-primary [--tab-border-color:var(--color-primary)]' : 'text-base-content/70'}`}
									onClick={() => setDetailTab('riwayat')}
								>
									Riwayat Pangkat
								</button>
							</div>

							{detailTab === 'profile' && (
								<div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
									<div className="card bg-base-200 border border-base-300">
										<div className="card-body p-4">
											<h4 className="card-title text-sm">Data Pribadi</h4>
											<div className="grid grid-cols-1 gap-3 text-sm">
												<Field label="NIP" value={selected.nip || '-'} mono />
												<Field label="Nama" value={selected.nama || '-'} />
												<Field label="Tempat/Tgl Lahir" value={formatTempatTanggalLahir(selected.tempat_tanggal_lahir)} />
												<Field label="Jenis Kelamin" value={selected.jenis_kelamin ?? '-'} />
												<Field label="Agama" value={selected.agama ?? '-'} />
											</div>
										</div>
									</div>

									<div className="card bg-base-200 border border-base-300">
										<div className="card-body p-4">
											<h4 className="card-title text-sm">Data Kepegawaian</h4>
											<div className="grid grid-cols-1 gap-3 text-sm">
												<Field label="Jenis Pegawai" value={selected.jenis_pegawai ?? '-'} />
												<div className="flex items-start gap-2">
													<div className="min-w-0 flex-1">
														<Field label="Pangkat/Golongan" value={selected.pangkat_golongan ?? '-'} />
													</div>
													{selected.pangkat_golongan ? (
														<CopyButton value={selected.pangkat_golongan} label="Pangkat/Golongan" />
													) : null}
												</div>
												<Field label="Jabatan" value={selected.jabatan ?? '-'} />
												<Field label="Unit Kerja" value={selected.unit_kerja ?? '-'} />
												<Field
													label="Kantor Kemenag (Kab/Kota)"
													value={selected.source_unit_slug ? formatSourceUnitSlug(selected.source_unit_slug) : '-'}
												/>
												<Field label="Kanwil (Provinsi)" value={selected.satker_induk ?? '-'} />
											</div>
										</div>
									</div>
								</div>
							)}

							{detailTab === 'riwayat' && (
								<div className="space-y-4">
									<div className="stats stats-vertical sm:stats-horizontal shadow border border-base-300 bg-base-200 w-full">
										<div className="stat py-3">
											<div className="stat-title text-xs">Total Riwayat</div>
											<div className="stat-value text-2xl">{selectedRiwayat.length}</div>
										</div>
										<div className="stat py-3">
											<div className="stat-title text-xs">Pangkat Terakhir</div>
											<div className="stat-value text-base whitespace-normal wrap-break-word">
												{latestRiwayat ? `${latestRiwayat.pangkat || '-'} (${latestRiwayat.golongan || '-'})` : '-'}
											</div>
											<div className="stat-desc">TMT: {latestRiwayat ? formatDate(latestRiwayat.tmt) : '-'}</div>
										</div>
									</div>

									{riwayatLoadingNip === selected.nip ? (
										<div className="flex items-center gap-3 py-4 text-sm text-base-content/70">
											<span className="loading loading-bars loading-sm" />
											<span>Memuat data riwayat pangkat...</span>
										</div>
									) : riwayatErrorNip === selected.nip ? (
										<div className="alert alert-error alert-soft">
											<span>Gagal memuat riwayat kenaikan pangkat.</span>
										</div>
									) : selectedRiwayat.length === 0 ? (
										<div className="alert alert-info alert-soft">
											<span>Riwayat kenaikan pangkat tidak tersedia.</span>
										</div>
									) : (
										<div className="overflow-x-auto rounded-box border border-base-300">
											<table className="table table-sm table-zebra">
												<thead>
													<tr>
														<th className="w-16">No</th>
														<th className="min-w-48">Pangkat</th>
														<th>Golongan</th>
														<th className="min-w-36">TMT</th>
														<th className="min-w-56">Nomor SK</th>
													</tr>
												</thead>
												<tbody>
													{selectedRiwayat.map((row, idx) => (
														<tr key={`${row.nip}-${row.no}-${idx}`}>
															<td className="whitespace-nowrap">{row.no || '-'}</td>
															<td className="whitespace-normal wrap-break-word">{row.pangkat || '-'}</td>
															<td className="whitespace-nowrap">
																<span className="badge badge-ghost badge-sm">{row.golongan || '-'}</span>
															</td>
															<td className="whitespace-nowrap">{formatDate(row.tmt)}</td>
															<td className="whitespace-normal wrap-break-word">{row.nomor_sk || '-'}</td>
														</tr>
													))}
												</tbody>
											</table>
										</div>
									)}
								</div>
							)}
						</div>
					)}
				</div>

				<form method="dialog" className="modal-backdrop">
					<button type="submit">Close</button>
				</form>
			</dialog>
		</div>
	)
}

