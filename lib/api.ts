import { getApiUrl } from '@/lib/api-base-url'

// Helper to get token from either localStorage or sessionStorage
function getToken(): string | null {
	if (typeof window === 'undefined') return null
	return localStorage.getItem('token') || sessionStorage.getItem('token')
}

// Helper to get role from either localStorage or sessionStorage
export function getRole(): string {
	if (typeof window === 'undefined') return ''
	return localStorage.getItem('role') || sessionStorage.getItem('role') || ''
}

const PERMISSIONS_STORAGE_KEY = 'permissions'

/** Daftar permission key dari API (/auth/login, /auth/me). Admin = ['*']. */
export function getPermissions(): string[] {
	if (typeof window === 'undefined') return []
	try {
		const raw =
			localStorage.getItem(PERMISSIONS_STORAGE_KEY) ||
			sessionStorage.getItem(PERMISSIONS_STORAGE_KEY)
		if (!raw) return []
		const parsed = JSON.parse(raw) as unknown
		if (!Array.isArray(parsed)) return []
		return parsed.filter((x): x is string => typeof x === 'string')
	} catch {
		return []
	}
}

export function hasPermission(key: string): boolean {
	const perms = getPermissions()
	if (perms.includes('*')) return true
	return perms.includes(key)
}

export function canManageUsers(): boolean {
	return hasPermission('users.manage')
}

export function canEditEmployees(): boolean {
	return hasPermission('pegawai.edit') || hasPermission('pegawai.edit_all')
}

export function canExportPegawai(): boolean {
	return hasPermission('pegawai.export')
}

// Helper to clear auth data from both storages
function clearAuthData() {
	if (typeof window === 'undefined') return
	localStorage.removeItem('token')
	localStorage.removeItem('role')
	localStorage.removeItem('username')
	localStorage.removeItem(PERMISSIONS_STORAGE_KEY)
	sessionStorage.removeItem('token')
	sessionStorage.removeItem('role')
	sessionStorage.removeItem('username')
	sessionStorage.removeItem(PERMISSIONS_STORAGE_KEY)
}

export async function apiFetch<T = unknown>(path: string, init?: RequestInit): Promise<T> {
	const token = getToken()
	const headers: HeadersInit = {
		Accept: 'application/json',
		...(init?.headers || {}),
		Authorization: token ? `Bearer ${token}` : '',
	}
	
	try {
		const res = await fetch(getApiUrl(path),
			{ ...init, headers, cache: 'no-store' });
			
		if (!res.ok) {
			// Redirect to login if unauthorized
			if (res.status === 401 && typeof window !== 'undefined') {
				clearAuthData()
				window.location.href = '/auth/login'
				throw new Error('Unauthorized')
			}
			
			// Try to get JSON error response
			let errorMessage = `Request failed with status ${res.status}`;
			try {
				const json = await res.json();
				errorMessage = json?.message || json?.error || errorMessage;
				
				// Special handling for 429 errors
				if (res.status === 429) {
					errorMessage = 'Terlalu banyak permintaan. Silakan tunggu beberapa saat dan coba lagi.';
				}
				
				// Include validation errors if available
				if (json?.errors) {
					const errorDetails = Object.values(json.errors).flat().join(', ');
					errorMessage = errorDetails ? `${errorMessage}: ${errorDetails}` : errorMessage;
				}
			} catch {
				// If not JSON, provide user-friendly message for 429
				if (res.status === 429) {
					errorMessage = 'Terlalu banyak permintaan. Silakan tunggu beberapa saat dan coba lagi.';
				} else if (process.env.NODE_ENV === 'development') {
					const text = await res.text();
					errorMessage = text || errorMessage;
				}
			}
			
			throw new Error(errorMessage);
		}
		
		return res.json();
	} catch (error) {
		// Log error for debugging (in development only)
		if (process.env.NODE_ENV === 'development') {
			console.error('API Error:', error);
		}
		throw error;
	}
}

export async function apiFetchBlob(path: string, init?: RequestInit): Promise<{ blob: Blob; response: Response }> {
	const token = getToken()
	const headers: HeadersInit = {
		Accept: 'application/json',
		...(init?.headers || {}),
		Authorization: token ? `Bearer ${token}` : '',
	}

	const res = await fetch(getApiUrl(path), { ...init, headers, cache: 'no-store' })
	if (!res.ok) {
		if (res.status === 401 && typeof window !== 'undefined') {
			clearAuthData()
			window.location.href = '/auth/login'
			throw new Error('Unauthorized')
		}

		let errorMessage = `Request failed with status ${res.status}`
		try {
			const json = await res.json()
			errorMessage = json?.message || json?.error || errorMessage
			if (res.status === 429) {
				errorMessage = 'Terlalu banyak permintaan. Silakan tunggu beberapa saat dan coba lagi.'
			}
		} catch {
			if (res.status === 429) {
				errorMessage = 'Terlalu banyak permintaan. Silakan tunggu beberapa saat dan coba lagi.'
			}
		}
		throw new Error(errorMessage)
	}

	return { blob: await res.blob(), response: res }
}
