"use client"

import { useEffect, useMemo, useState } from 'react'
import { useRouter } from 'next/navigation'
import { apiFetch, canManageUsers } from '@/lib/api'
import { toast } from '@/components/Toaster'
import { confirm } from '@/components/Confirm'

type Role = { id: number; name: string }
type PermissionRow = { id: number; key: string; label: string; group: string | null; sort_order: number }
type WilayahRow = { id: number; slug: string; name: string; kind: string }
type UserRow = {
	id: number
	name: string
	email: string
	role_id: number | null
	wilayah_unit_id?: number | null
	role?: Role | null
	permissions?: { id: number; key: string; label: string }[]
	wilayah_unit?: WilayahRow | null
}
type Paginated<T> = { success: boolean; data: { current_page: number; data: T[]; last_page: number; per_page: number; total: number } }

export default function AdminPage() {
	const router = useRouter()

	const [loading, setLoading] = useState(false)
	const [users, setUsers] = useState<UserRow[]>([])
	const [roles, setRoles] = useState<Role[]>([])
	const [permissionCatalog, setPermissionCatalog] = useState<PermissionRow[]>([])
	const [wilayahList, setWilayahList] = useState<WilayahRow[]>([])
	const [search, setSearch] = useState('')
	const [page, setPage] = useState(1)
	const [lastPage, setLastPage] = useState(1)

	const [editing, setEditing] = useState<UserRow | null>(null)
	const [userFormTab, setUserFormTab] = useState<'account' | 'permissions'>('account')
	/** Buka/tutup accordion izin per nama grup (bukan satu state global). */
	const [permGroupOpen, setPermGroupOpen] = useState<Record<string, boolean>>({})
	const [form, setForm] = useState<{
		name: string
		email: string
		password: string
		role_id: number | ''
		wilayah_unit_id: number | ''
		permissionIds: number[]
	}>({ name: '', email: '', password: '', role_id: '', wilayah_unit_id: '', permissionIds: [] })

	const permissionGroups = useMemo(() => {
		const map = new Map<string, PermissionRow[]>()
		for (const p of permissionCatalog) {
			const g = p.group?.trim() || 'Umum'
			if (!map.has(g)) map.set(g, [])
			map.get(g)!.push(p)
		}
		for (const arr of map.values()) {
			arr.sort((a, b) => a.sort_order - b.sort_order || a.label.localeCompare(b.label))
		}
		return Array.from(map.entries()).sort(([a], [b]) => a.localeCompare(b))
	}, [permissionCatalog])

	useEffect(() => {
		if (!canManageUsers()) {
			router.replace('/')
			return
		}
		void fetchRoles()
		void fetchPermissionCatalog()
		void fetchWilayah()
		void fetchUsers(1, '')
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [router])

	async function fetchUsers(p = 1, q = search) {
		setLoading(true)
		try {
			const res = await apiFetch<Paginated<UserRow>>(`/users?per_page=10&page=${p}&search=${encodeURIComponent(q)}`)
			setUsers(res.data.data)
			setPage(res.data.current_page)
			setLastPage(res.data.last_page)
		} finally {
			setLoading(false)
		}
	}

	async function fetchRoles() {
		const res = await apiFetch<{ success: boolean; data: Role[] }>(`/users/roles`)
		setRoles(res.data)
	}

	async function fetchPermissionCatalog() {
		const res = await apiFetch<{ success: boolean; data: PermissionRow[] }>(`/users/permissions`)
		setPermissionCatalog(res.data)
	}

	async function fetchWilayah() {
		const res = await apiFetch<{ success: boolean; data: WilayahRow[] }>(`/users/wilayah-units`)
		setWilayahList(res.data)
	}

	function initialPermGroupOpen(selectedIds: Set<number>): Record<string, boolean> {
		const next: Record<string, boolean> = {}
		permissionGroups.forEach(([name, perms], i) => {
			const hasSelected = perms.some((p) => selectedIds.has(p.id))
			next[name] = i === 0 || hasSelected
		})
		return next
	}

	function openCreate() {
		setEditing(null)
		setUserFormTab('account')
		setForm({
			name: '',
			email: '',
			password: '',
			role_id: roles[0]?.id ?? '',
			wilayah_unit_id: '',
			permissionIds: [],
		})
		setPermGroupOpen(initialPermGroupOpen(new Set()))
		;(document.getElementById('user_modal') as HTMLDialogElement)?.showModal()
	}

	function openEdit(u: UserRow) {
		setEditing(u)
		setUserFormTab('account')
		const ids = new Set((u.permissions ?? []).map((p) => p.id))
		setForm({
			name: u.name,
			email: u.email,
			password: '',
			role_id: u.role_id ?? '',
			wilayah_unit_id: u.wilayah_unit_id ?? '',
			permissionIds: [...ids],
		})
		setPermGroupOpen(initialPermGroupOpen(ids))
		;(document.getElementById('user_modal') as HTMLDialogElement)?.showModal()
	}

	function togglePermission(id: number) {
		setForm((prev) => {
			const has = prev.permissionIds.includes(id)
			return {
				...prev,
				permissionIds: has ? prev.permissionIds.filter((x) => x !== id) : [...prev.permissionIds, id],
			}
		})
	}

	function togglePermGroup(name: string) {
		setPermGroupOpen((prev) => ({ ...prev, [name]: !prev[name] }))
	}

	async function submitForm() {
		try {
			if (!form.name.trim()) {
				toast('Nama wajib diisi', 'error')
				return
			}
			if (!form.email.trim()) {
				toast('Email wajib diisi', 'error')
				return
			}
			if (!form.role_id) {
				toast('Role wajib dipilih', 'error')
				return
			}

			if (!editing && (!form.password || form.password.length < 6)) {
				toast('Password wajib diisi minimal 6 karakter', 'error')
				return
			}
			if (editing && form.password && form.password.length < 6) {
				toast('Password minimal 6 karakter', 'error')
				return
			}

			const payload: Record<string, unknown> = {
				name: form.name.trim(),
				email: form.email.trim(),
				role_id: form.role_id,
				permission_ids: form.permissionIds,
				wilayah_unit_id: form.wilayah_unit_id === '' ? null : form.wilayah_unit_id,
			}

			if (editing) {
				if (form.password && form.password.trim()) {
					payload.password = form.password
				}
				await apiFetch(`/users/${editing.id}`, {
					method: 'PUT',
					body: JSON.stringify(payload),
					headers: { 'Content-Type': 'application/json' },
				})
				toast('User berhasil diupdate', 'success')
			} else {
				payload.password = form.password
				await apiFetch(`/users`, {
					method: 'POST',
					body: JSON.stringify(payload),
					headers: { 'Content-Type': 'application/json' },
				})
				toast('User berhasil dibuat', 'success')
			}
			;(document.getElementById('user_modal') as HTMLDialogElement)?.close()
			setForm({ name: '', email: '', password: '', role_id: '', wilayah_unit_id: '', permissionIds: [] })
			await fetchUsers(page)
		} catch (e: unknown) {
			const errorMsg = e instanceof Error ? e.message : 'Gagal menyimpan user'
			toast(errorMsg, 'error')
		}
	}

	async function removeUser(u: UserRow) {
		const ok = await confirm({
			title: 'Hapus User',
			message: `Hapus user ${u.name}?`,
			confirmText: 'Hapus',
			cancelText: 'Batal',
		})
		if (!ok) return
		try {
			await apiFetch(`/users/${u.id}`, { method: 'DELETE' })
			toast('User berhasil dihapus', 'success')
			await fetchUsers(page)
		} catch {
			toast('Gagal menghapus user', 'error')
		}
	}

	return (
		<div className="p-4 md:p-8">
			<div className="flex items-center justify-between mb-6">
				<h1 className="text-2xl font-semibold">Management User</h1>
				<a className="btn" href="/employees">
					Kembali
				</a>
			</div>

			<div className="card bg-base-200/40">
				<div className="card-body">
					<div className="flex flex-col sm:flex-row gap-2 sm:items-center sm:justify-between">
						<label className="input max-w-sm">
							<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className="w-5 h-5 opacity-70">
								<path
									fillRule="evenodd"
									d="M10.5 3.75a6.75 6.75 0 015.364 10.83l3.278 3.278a.75.75 0 11-1.06 1.06l-3.279-3.278A6.75 6.75 0 1110.5 3.75zm0 1.5a5.25 5.25 0 100 10.5 5.25 5.25 0 000-10.5z"
									clipRule="evenodd"
								/>
							</svg>
							<input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Cari nama/email" />
							<button type="button" className="btn btn-sm" onClick={() => fetchUsers(1, search)}>
								Cari
							</button>
						</label>
						<button type="button" className="btn btn-primary" onClick={openCreate}>
							Tambah User
						</button>
					</div>

					<div className="overflow-x-auto mt-4">
						<table className="table table-sm">
							<thead>
								<tr>
									<th>Nama</th>
									<th>Email</th>
									<th>Role</th>
									<th>Wilayah</th>
									<th>Izin</th>
									<th className="w-40"></th>
								</tr>
							</thead>
							<tbody>
								{loading ? (
									<tr>
										<td colSpan={6}>
											<div className="skeleton h-6 w-full" />
										</td>
									</tr>
								) : users.length === 0 ? (
									<tr>
										<td colSpan={6} className="text-center opacity-70">
											Tidak ada data
										</td>
									</tr>
								) : (
									users.map((u) => (
										<tr key={u.id}>
											<td>{u.name}</td>
											<td>{u.email}</td>
											<td className="capitalize">{u.role?.name || '-'}</td>
											<td className="text-sm opacity-90">{u.wilayah_unit?.name ?? '—'}</td>
											<td className="text-sm">{(u.permissions ?? []).length} checklist</td>
											<td className="text-right">
												<div className="join join-horizontal justify-end">
													<button type="button" className="btn btn-xs join-item" onClick={() => openEdit(u)}>
														Edit
													</button>
													<button type="button" className="btn btn-xs btn-error join-item" onClick={() => removeUser(u)}>
														Hapus
													</button>
												</div>
											</td>
										</tr>
									))
								)}
							</tbody>
						</table>
					</div>

					<div className="join mt-4 justify-center">
						<button type="button" disabled={page <= 1} className="btn btn-sm join-item" onClick={() => fetchUsers(page - 1)}>
							«
						</button>
						<button type="button" className="btn btn-sm join-item">
							Hal {page} / {lastPage}
						</button>
						<button type="button" disabled={page >= lastPage} className="btn btn-sm join-item" onClick={() => fetchUsers(page + 1)}>
							»
						</button>
					</div>

					<p className="text-sm opacity-70 mt-4">
						Role: <span className="font-medium">superadmin</span>, <span className="font-medium">admin_kanwil</span>,{' '}
						<span className="font-medium">admin_kab</span>, <span className="font-medium">user</span>. Di grup «pegawai», pilih lingkup kabupaten atau Kanwil lalu
						izin aksi (lihat, export, edit, dll.). Lingkup kabupaten harus dipasangkan dengan kolom wilayah pada user.
					</p>
				</div>
			</div>

			<dialog id="user_modal" className="modal">
				<div className="modal-box max-w-xl md:max-w-2xl max-h-[90vh] flex flex-col gap-0 p-6">
					<h3 className="font-bold text-lg shrink-0">{editing ? 'Edit user' : 'Tambah user baru'}</h3>
					<p className="text-sm opacity-70 shrink-0 mt-1">
						{editing
							? 'Ubah data login, wilayah, lalu sesuaikan izin di tab berikutnya.'
							: 'Isi data akun dulu, lalu pilih izin di tab «Hak akses». Admin tidak perlu mencentang izin.'}
					</p>

					<div role="tablist" className="tabs tabs-box w-full shrink-0 mt-4">
						<button
							type="button"
							role="tab"
							className={`tab flex-1 sm:flex-none ${userFormTab === 'account' ? 'tab-active' : ''}`}
							aria-selected={userFormTab === 'account'}
							onClick={() => setUserFormTab('account')}
						>
							1. Data akun &amp; wilayah
						</button>
						<button
							type="button"
							role="tab"
							className={`tab flex-1 sm:flex-none ${userFormTab === 'permissions' ? 'tab-active' : ''}`}
							aria-selected={userFormTab === 'permissions'}
							onClick={() => setUserFormTab('permissions')}
						>
							2. Hak akses
							{form.permissionIds.length > 0 ? (
								<span className="badge badge-sm badge-primary ml-1">{form.permissionIds.length}</span>
							) : null}
						</button>
					</div>

					<div className="flex-1 min-h-0 overflow-y-auto mt-4 pr-1 space-y-3">
						{userFormTab === 'account' ? (
							<div className="space-y-3">
								<label className="input">
									<span className="label">Nama</span>
									<input value={form.name} onChange={(e) => setForm((v) => ({ ...v, name: e.target.value }))} />
								</label>
								<label className="input">
									<span className="label">Email</span>
									<input type="email" value={form.email} onChange={(e) => setForm((v) => ({ ...v, email: e.target.value }))} />
								</label>
								{editing ? (
									<div className="space-y-1">
										<div className="label">
											Password baru <span className="opacity-60 font-normal">— opsional</span>
										</div>
										<p className="text-xs opacity-70 -mt-1 mb-1">Kosongkan jika password tidak diganti.</p>
										<input
											className="input w-full"
											type="password"
											value={form.password}
											onChange={(e) => setForm((v) => ({ ...v, password: e.target.value }))}
											placeholder="Kosongkan jika tidak diubah"
											autoComplete="new-password"
										/>
									</div>
								) : (
									<div className="space-y-1">
										<div className="label">Password</div>
										<p className="text-xs opacity-70 -mt-1 mb-1">Wajib untuk user baru (minimal 6 karakter).</p>
										<input
											className="input w-full"
											type="password"
											value={form.password}
											onChange={(e) => setForm((v) => ({ ...v, password: e.target.value }))}
											placeholder="Minimal 6 karakter"
											required
											autoComplete="new-password"
										/>
									</div>
								)}
								<label className="input">
									<span className="label">Role</span>
									<select
										className="select"
										value={form.role_id}
										onChange={(e) => setForm((v) => ({ ...v, role_id: e.target.value ? Number(e.target.value) : '' }))}
									>
										{roles.map((r) => (
											<option key={r.id} value={r.id}>
												{r.name}
											</option>
										))}
									</select>
								</label>
								<div className="form-control">
									<span className="label-text font-medium">Unit kabupaten/kota (wilayah)</span>
									<select
										className="select select-bordered w-full mt-1"
										value={form.wilayah_unit_id === '' ? '' : String(form.wilayah_unit_id)}
										onChange={(e) =>
											setForm((v) => ({
												...v,
												wilayah_unit_id: e.target.value === '' ? '' : Number(e.target.value),
											}))
										}
									>
										<option value="">— Kosongkan jika lingkup Kanwil —</option>
										{wilayahList.map((w) => (
											<option key={w.id} value={w.id}>
												{w.name} ({w.kind})
											</option>
										))}
									</select>
									<p className="text-xs opacity-70 mt-2">
										Wajib diisi bila user memiliki izin «Lingkup pegawai: satu kabupaten/kota». Untuk lingkup Kanwil pilih kosong di sini.
									</p>
								</div>
							</div>
						) : (
							<div>
								<div className="alert alert-soft mb-3 py-2">
									<span className="text-sm">
										Untuk modul pegawai: pilih dulu salah satu «Lingkup pegawai» (kabupaten vs Kanwil), baru izin lihat/edit/export. Klik judul grup untuk buka/tutup daftar.
									</span>
								</div>
								<div className="flex flex-col gap-2">
									{permissionGroups.map(([groupName, perms]) => {
										const selectedInGroup = perms.filter((p) => form.permissionIds.includes(p.id)).length
										const isOpen = permGroupOpen[groupName] ?? false
										return (
											<div
												key={groupName}
												className={`collapse collapse-arrow bg-base-200/80 rounded-box border border-base-300 ${isOpen ? 'collapse-open' : ''}`}
											>
												<div
													role="button"
													tabIndex={0}
													className="collapse-title text-sm font-medium pr-10 cursor-pointer select-none"
													aria-expanded={isOpen}
													onClick={() => togglePermGroup(groupName)}
													onKeyDown={(e) => {
														if (e.key === 'Enter' || e.key === ' ') {
															e.preventDefault()
															togglePermGroup(groupName)
														}
													}}
												>
													<span className="capitalize">{groupName}</span>
													<span className="text-xs font-normal opacity-60 ml-2">
														{selectedInGroup}/{perms.length} dipilih
													</span>
												</div>
												<div className="collapse-content">
													<ul className="flex flex-col gap-1 pt-0">
														{perms.map((p) => (
															<li key={p.id}>
																<label className="label cursor-pointer justify-start gap-3 py-1.5 min-h-0">
																	<input
																		type="checkbox"
																		className="checkbox checkbox-sm checkbox-primary"
																		checked={form.permissionIds.includes(p.id)}
																		onChange={() => togglePermission(p.id)}
																	/>
																	<div className="flex flex-col gap-0">
																		<span className="label-text text-sm leading-tight">{p.label}</span>
																		<span className="text-[10px] opacity-50 font-mono leading-tight">{p.key}</span>
																	</div>
																</label>
															</li>
														))}
													</ul>
												</div>
											</div>
										)
									})}
								</div>
							</div>
						)}
					</div>

					<div className="shrink-0 mt-4 pt-3 border-t border-base-300 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
						<form method="dialog" className="order-last sm:order-0">
							<button type="submit" className="btn btn-ghost w-full sm:w-auto">
								Batal
							</button>
						</form>
						<div className="flex flex-col gap-2 sm:flex-row sm:justify-end sm:flex-1">
							{userFormTab === 'account' ? (
								<button type="button" className="btn btn-outline btn-primary flex-1 sm:flex-none" onClick={() => setUserFormTab('permissions')}>
									Lanjut ke hak akses →
								</button>
							) : (
								<button type="button" className="btn btn-outline flex-1 sm:flex-none" onClick={() => setUserFormTab('account')}>
									← Kembali ke data akun
								</button>
							)}
							<button
								type="button"
								className="btn btn-primary flex-1 sm:flex-none"
								onClick={submitForm}
								disabled={!editing && form.permissionIds.length === 0}
								title={!editing && form.permissionIds.length === 0 ? 'Lengkapi hak akses dulu sebelum menyimpan.' : undefined}
							>
								Simpan
							</button>
						</div>
					</div>
				</div>
			</dialog>
		</div>
	)
}
