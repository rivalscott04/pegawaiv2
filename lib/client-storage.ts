/** Same-tab storage updates (localStorage does not fire `storage`). */
export const CLIENT_STORAGE_SYNC_EVENT = 'empkanwil-client-storage'

export function notifyClientStorageChanged(): void {
	if (typeof window === 'undefined') return
	window.dispatchEvent(new Event(CLIENT_STORAGE_SYNC_EVENT))
}

export function subscribeClientStorage(onChange: () => void): () => void {
	if (typeof window === 'undefined') return () => {}
	const handler = () => onChange()
	window.addEventListener('storage', handler)
	window.addEventListener(CLIENT_STORAGE_SYNC_EVENT, handler)
	return () => {
		window.removeEventListener('storage', handler)
		window.removeEventListener(CLIENT_STORAGE_SYNC_EVENT, handler)
	}
}

export function readHasAuthToken(): boolean {
	if (typeof window === 'undefined') return false
	return !!(localStorage.getItem('token') || sessionStorage.getItem('token'))
}

export function readAuthRole(): string {
	if (typeof window === 'undefined') return ''
	return localStorage.getItem('role') || sessionStorage.getItem('role') || ''
}

export function readAuthUsername(): string {
	if (typeof window === 'undefined') return ''
	const role = readAuthRole()
	const name = localStorage.getItem('username') || sessionStorage.getItem('username') || ''
	return name || (role ? role.charAt(0).toUpperCase() + role.slice(1) : '')
}

export function readThemePreference(): string {
	if (typeof window === 'undefined') return ''
	return localStorage.getItem('theme') || ''
}
