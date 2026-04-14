"use client"

import { useEffect, useMemo, useState } from 'react'
import { apiFetch, apiFetchBlob, hasPermission } from '@/lib/api'
import { toast } from '@/components/Toaster'
import type { PegawaiFiltersResponseV2, PegawaiListResponseV2, SdmOverviewResponse } from '@/lib/types'
import { useCanExportPegawai } from '@/lib/use-permissions-from-storage'
import { pegawaiExportAllFallbackFilename } from '@/lib/ntb-constants'

function formatNumber(value: number): string {
	return new Intl.NumberFormat('id-ID').format(value)
}

function formatPercent(value: number): string {
	return `${value.toFixed(2).replace('.', ',')}%`
}

function formatWilayahLabel(value: string): string {
	const parts = value
		.replaceAll('_', ' ')
		.trim()
		.split(/\s+/)
		.map((part) => {
			if (!part) return part
			if (part.toLowerCase() === 'kota') return 'Kota'
			if (part.toLowerCase() === 'kabupaten') return 'Kabupaten'
			return part[0].toUpperCase() + part.slice(1).toLowerCase()
		})

	return parts.join(' ')
}

function getChartColor(index: number): string {
	const palette = ['#2563eb', '#16a34a', '#f59e0b', '#dc2626', '#7c3aed', '#0891b2', '#ea580c', '#4f46e5']
	return palette[index % palette.length]
}

export default function DataInformasiPage() {
	const [payload, setPayload] = useState<SdmOverviewResponse | null>(null)
	const [statusSummary, setStatusSummary] = useState<{ active: number; inactive: number } | null>(null)
	const [loading, setLoading] = useState(true)
	const [exporting, setExporting] = useState(false)
	const [search, setSearch] = useState('')
	const [jenisKelamin, setJenisKelamin] = useState('')
	const [jenisPegawai, setJenisPegawai] = useState('')
	const [jabatan, setJabatan] = useState('')
	const [sourceUnitSlug, setSourceUnitSlug] = useState('')
	const [statusFilter, setStatusFilter] = useState<'true' | 'false' | ''>('true')
	const [filterOptions, setFilterOptions] = useState<PegawaiFiltersResponseV2 | null>(null)

	const canView = hasPermission('pegawai.view')
	const canExport = useCanExportPegawai()

	const filterQuery = useMemo(() => {
		const params = new URLSearchParams()
		params.set('top', '10')
		if (search) params.set('search', search)
		if (jenisKelamin) params.set('jenis_kelamin', jenisKelamin)
		if (jenisPegawai) params.set('jenis_pegawai', jenisPegawai)
		if (jabatan) params.set('jabatan', jabatan)
		if (sourceUnitSlug) params.set('source_unit_slug', sourceUnitSlug)
		if (statusFilter) params.set('is_active', statusFilter)
		return params.toString()
	}, [search, jenisKelamin, jenisPegawai, jabatan, sourceUnitSlug, statusFilter])

	const statsQuery = useMemo(() => {
		const params = new URLSearchParams()
		params.set('page', '1')
		params.set('limit', '10')
		if (search) params.set('search', search)
		if (jenisKelamin) params.set('jenis_kelamin', jenisKelamin)
		if (jenisPegawai) params.set('jenis_pegawai', jenisPegawai)
		if (jabatan) params.set('jabatan', jabatan)
		if (sourceUnitSlug) params.set('source_unit_slug', sourceUnitSlug)
		return params.toString()
	}, [search, jenisKelamin, jenisPegawai, jabatan, sourceUnitSlug])

	function getFilenameFromDisposition(disposition: string | null, fallback: string): string {
		if (!disposition) return fallback
		const utf8Match = disposition.match(/filename\*=UTF-8''([^;]+)/i)
		if (utf8Match?.[1]) return decodeURIComponent(utf8Match[1])
		const normalMatch = disposition.match(/filename="?([^"]+)"?/i)
		return normalMatch?.[1] || fallback
	}

	async function exportDetail(format: 'csv' | 'xlsx', separator: 'comma' | 'semicolon' = 'comma') {
		try {
			setExporting(true)
			const params = new URLSearchParams()
			params.set('format', format)
			params.set('scope', 'all')
			if (format === 'csv') params.set('separator', separator)
			if (search) params.set('search', search)
			if (jenisKelamin) params.set('jenis_kelamin', jenisKelamin)
			if (jenisPegawai) params.set('jenis_pegawai', jenisPegawai)
			if (jabatan) params.set('jabatan', jabatan)
			if (sourceUnitSlug) params.set('source_unit_slug', sourceUnitSlug)
			if (statusFilter) params.set('is_active', statusFilter)

			const task = await apiFetch<{
				success: boolean
				task_id: string
				status: 'queued' | 'processing' | 'completed' | 'failed'
				download_url?: string | null
				error_message?: string | null
			}>(`/pegawai/export?${params.toString()}`)

			let status = task.status
			let downloadUrl = task.download_url ?? null
			const taskId = task.task_id
			while ((status === 'queued' || status === 'processing') && !downloadUrl) {
				await new Promise((resolve) => setTimeout(resolve, 2000))
				const check = await apiFetch<{
					success: boolean
					status: 'queued' | 'processing' | 'completed' | 'failed'
					download_url: string | null
					error_message: string | null
				}>(`/pegawai/export/${encodeURIComponent(taskId)}/status`)
				status = check.status
				downloadUrl = check.download_url
				if (status === 'failed') throw new Error(check.error_message || 'Export gagal diproses.')
			}

			if (!downloadUrl) throw new Error('File export belum tersedia.')
			const { blob, response } = await apiFetchBlob(downloadUrl)
			const fallbackFileName = pegawaiExportAllFallbackFilename(format, sourceUnitSlug || null)
			const fileName = getFilenameFromDisposition(response.headers.get('content-disposition'), fallbackFileName)
			const url = URL.createObjectURL(blob)
			const anchor = document.createElement('a')
			anchor.href = url
			anchor.download = fileName
			anchor.click()
			URL.revokeObjectURL(url)
			toast('Export detail berhasil diunduh', 'success')
		} catch (error) {
			console.error(error)
			toast('Gagal export data detail', 'error')
		} finally {
			setExporting(false)
		}
	}

	useEffect(() => {
		if (!canView) {
			setLoading(false)
			return
		}

		let isMounted = true
		const load = async () => {
			try {
				const [response, filters, stats] = await Promise.all([
					apiFetch<SdmOverviewResponse>(`/pegawai/sdm-overview?${filterQuery}`),
					apiFetch<PegawaiFiltersResponseV2>('/pegawai/filters?limit=100'),
					apiFetch<PegawaiListResponseV2>(`/pegawai?${statsQuery}`),
				])
				if (isMounted) {
					setPayload(response)
					setFilterOptions(filters)
					setStatusSummary({
						active: stats?.active ?? 0,
						inactive: stats?.inactive ?? 0,
					})
				}
			} catch (error) {
				console.error(error)
				toast('Gagal memuat laporan SDM', 'error')
			} finally {
				if (isMounted) {
					setLoading(false)
				}
			}
		}

		void load()
		return () => {
			isMounted = false
		}
	}, [canView, filterQuery, statsQuery])

	const topCluster = useMemo(() => payload?.cluster_ringkasan?.[0] ?? null, [payload])
	const statusChart = useMemo(() => {
		const active = statusSummary?.active ?? 0
		const inactive = statusSummary?.inactive ?? 0
		const total = active + inactive
		if (total <= 0) return { activePct: 0, inactivePct: 0, total: 0 }
		return {
			activePct: Number(((active / total) * 100).toFixed(2)),
			inactivePct: Number(((inactive / total) * 100).toFixed(2)),
			total,
		}
	}, [statusSummary])
	const donutGradient = useMemo(() => {
		if (!payload?.cluster_ringkasan?.length) return 'conic-gradient(#94a3b8 0 100%)'
		let current = 0
		const slices = payload.cluster_ringkasan.map((item, index) => {
			const start = current
			const end = Math.min(100, current + item.persentase)
			current = end
			return `${getChartColor(index)} ${start}% ${end}%`
		})
		if (current < 100) {
			slices.push(`#cbd5e1 ${current}% 100%`)
		}
		return `conic-gradient(${slices.join(', ')})`
	}, [payload])

	if (!canView) {
		return (
			<div className="p-4 md:p-8">
				<div className="alert alert-warning">
					<span>Anda tidak memiliki akses untuk melihat laporan SDM.</span>
				</div>
			</div>
		)
	}

	return (
		<div className="p-4 md:p-8 space-y-4 md:space-y-6">
			<div className="flex flex-col gap-2">
				<h1 className="text-2xl font-semibold">Data & Informasi SDM</h1>
				<p className="text-sm opacity-70">
					Ringkasan pemetaan SDM sesuai hak akses akun. Admin kabupaten hanya melihat wilayahnya sendiri.
				</p>
			</div>

			<div className="card bg-base-200 border border-base-300">
				<div className="card-body p-4">
					<div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
						<label className="input w-full">
							<span className="label text-xs">Cari</span>
							<input
								value={search}
								onChange={(event) => setSearch(event.target.value)}
								placeholder="Nama, NIP, jabatan"
							/>
						</label>
						<select className="select w-full" value={jenisKelamin} onChange={(event) => setJenisKelamin(event.target.value)}>
							<option value="">Semua Jenis Kelamin</option>
							{(filterOptions?.jenis_kelamin ?? []).map((value) => (
								<option key={value} value={value}>{value}</option>
							))}
						</select>
						<select className="select w-full" value={jenisPegawai} onChange={(event) => setJenisPegawai(event.target.value)}>
							<option value="">Semua Jenis Pegawai</option>
							{(filterOptions?.jenis_pegawai ?? []).map((value) => (
								<option key={value} value={value}>{value}</option>
							))}
						</select>
						<select className="select w-full" value={jabatan} onChange={(event) => setJabatan(event.target.value)}>
							<option value="">Semua Jabatan</option>
							{(filterOptions?.jabatan ?? []).slice(0, 100).map((value) => (
								<option key={value} value={value}>{value}</option>
							))}
						</select>
						<select className="select w-full" value={sourceUnitSlug} onChange={(event) => setSourceUnitSlug(event.target.value)}>
							<option value="">Semua Wilayah</option>
							{(filterOptions?.source_units ?? []).map((value) => (
								<option key={value.value} value={value.value}>{value.label}</option>
							))}
							{!filterOptions?.source_units?.length
								? (filterOptions?.source_unit_slug ?? []).map((value) => (
									<option key={value} value={value}>{formatWilayahLabel(value)}</option>
								))
								: null}
						</select>
						<select className="select w-full" value={statusFilter} onChange={(event) => setStatusFilter(event.target.value as 'true' | 'false' | '')}>
							<option value="">Semua Status</option>
							<option value="true">Aktif</option>
							<option value="false">Nonaktif</option>
						</select>
					</div>
					<div className="flex flex-wrap gap-2 justify-end mt-3">
						<button
							type="button"
							className="btn btn-sm"
							onClick={() => {
								setSearch('')
								setJenisKelamin('')
								setJenisPegawai('')
								setJabatan('')
								setSourceUnitSlug('')
								setStatusFilter('true')
							}}
						>
							Reset Filter
						</button>
						{canExport ? (
							<div className="dropdown dropdown-end">
								<button type="button" tabIndex={0} role="button" className="btn btn-sm btn-primary" disabled={exporting}>
									{exporting ? <span className="loading loading-dots loading-sm" /> : 'Export Data Detail'}
								</button>
								<ul className="dropdown-content menu mt-1 w-64 rounded-box bg-base-100 p-2 shadow-xl border border-base-300 z-40">
									<li><button onClick={() => exportDetail('xlsx')} disabled={exporting}>Excel (.xlsx)</button></li>
									<li><button onClick={() => exportDetail('csv', 'comma')} disabled={exporting}>CSV (Koma ,)</button></li>
									<li><button onClick={() => exportDetail('csv', 'semicolon')} disabled={exporting}>CSV (Titik Koma ;)</button></li>
								</ul>
							</div>
						) : null}
					</div>
				</div>
			</div>

			{loading ? (
				<div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
					<div className="skeleton h-28 w-full" />
					<div className="skeleton h-28 w-full" />
					<div className="skeleton h-28 w-full" />
					<div className="skeleton h-28 w-full" />
				</div>
			) : null}

			{!loading && payload ? (
				<>
					<div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
						<div className="card bg-base-200 border border-base-300">
							<div className="card-body p-4">
								<p className="text-xs uppercase opacity-70">Total Pegawai</p>
								<p className="text-3xl font-semibold">{formatNumber(payload.summary.total_pegawai)}</p>
							</div>
						</div>
						<div className="card bg-base-200 border border-base-300">
							<div className="card-body p-4">
								<p className="text-xs uppercase opacity-70">Variasi Jabatan</p>
								<p className="text-3xl font-semibold">{formatNumber(payload.summary.total_variasi_jabatan)}</p>
							</div>
						</div>
						<div className="card bg-base-200 border border-base-300">
							<div className="card-body p-4">
								<p className="text-xs uppercase opacity-70">Rata-rata per Jabatan</p>
								<p className="text-3xl font-semibold">{payload.summary.rata_per_jabatan.toFixed(2).replace('.', ',')}</p>
							</div>
						</div>
						<div className="card bg-base-200 border border-base-300">
							<div className="card-body p-4">
								<p className="text-xs uppercase opacity-70">Klaster Terbesar</p>
								<p className="text-xl font-semibold">{topCluster?.label ?? '-'}</p>
								<p className="text-sm opacity-70">
									{topCluster ? `${formatNumber(topCluster.total)} pegawai (${formatPercent(topCluster.persentase)})` : '-'}
								</p>
							</div>
						</div>
					</div>

					<div className="grid grid-cols-1 xl:grid-cols-2 gap-4">
						<div className="card bg-base-200 border border-base-300">
							<div className="card-body p-4">
								<h2 className="card-title text-lg">Chart Status Pegawai</h2>
								<div className="space-y-3">
									<div className="w-full h-4 rounded-full bg-base-300 overflow-hidden flex">
										<div
											className="h-4 bg-success"
											style={{ width: `${statusChart.activePct}%` }}
											title={`Aktif ${formatPercent(statusChart.activePct)}`}
										></div>
										<div
											className="h-4 bg-warning"
											style={{ width: `${statusChart.inactivePct}%` }}
											title={`Nonaktif ${formatPercent(statusChart.inactivePct)}`}
										></div>
									</div>
									<div className="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
										<div className="card bg-base-100 border border-base-300">
											<div className="card-body p-3">
												<p className="text-xs opacity-70">Pegawai Aktif</p>
												<p className="text-lg font-semibold text-success">{formatNumber(statusSummary?.active ?? 0)}</p>
												<p className="text-xs opacity-70">{formatPercent(statusChart.activePct)}</p>
											</div>
										</div>
										<div className="card bg-base-100 border border-base-300">
											<div className="card-body p-3">
												<p className="text-xs opacity-70">Pegawai Nonaktif</p>
												<p className="text-lg font-semibold text-warning">{formatNumber(statusSummary?.inactive ?? 0)}</p>
												<p className="text-xs opacity-70">{formatPercent(statusChart.inactivePct)}</p>
											</div>
										</div>
									</div>
									<details className="collapse collapse-arrow bg-base-100 border border-base-300">
										<summary className="collapse-title py-3 min-h-0 text-sm font-medium">
											Lihat Detail Top Jabatan (opsional)
										</summary>
										<div className="collapse-content text-sm pt-1">
											<ul className="space-y-2">
												{payload.top_jabatan.map((item, index) => (
													<li key={item.jabatan} className="flex items-start justify-between gap-3">
														<span className="line-clamp-2">{index + 1}. {item.jabatan}</span>
														<span className="badge badge-ghost shrink-0">{formatNumber(item.total)}</span>
													</li>
												))}
											</ul>
										</div>
									</details>
								</div>
							</div>
						</div>

						<div className="card bg-base-200 border border-base-300">
							<div className="card-body p-4">
								<h2 className="card-title text-lg">Chart Komposisi Klaster SDM</h2>
								<div className="grid grid-cols-1 md:grid-cols-[220px_1fr] gap-4 items-center">
									<div className="flex justify-center">
										<div className="relative w-44 h-44">
											<div
												className="w-44 h-44 rounded-full border border-base-300"
												style={{ background: donutGradient }}
											></div>
											<div className="absolute inset-6 rounded-full bg-base-200 border border-base-300 flex items-center justify-center text-center p-2">
												<div>
													<p className="text-xs opacity-70">Total Pegawai</p>
													<p className="text-xl font-bold">{formatNumber(payload.summary.total_pegawai)}</p>
												</div>
											</div>
										</div>
									</div>
									<div className="space-y-2">
										{payload.cluster_ringkasan.map((item, index) => (
											<div key={item.label} className="flex items-center justify-between gap-3 text-sm">
												<div className="flex items-center gap-2 min-w-0">
													<span
														className="inline-block w-3 h-3 rounded-full shrink-0"
														style={{ backgroundColor: getChartColor(index) }}
													></span>
													<span className="truncate">{item.label}</span>
												</div>
												<div className="text-right shrink-0">
													<div className="font-medium">{formatPercent(item.persentase)}</div>
													<div className="text-xs opacity-70">{formatNumber(item.total)} pegawai</div>
												</div>
											</div>
										))}
									</div>
								</div>
							</div>
						</div>
					</div>
				</>
			) : null}
		</div>
	)
}
