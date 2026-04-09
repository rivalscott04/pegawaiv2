const DEV_API_FALLBACK = 'http://127.0.0.1:8000/api'

function normalizeApiBaseUrl(raw: string | undefined): string {
	const value = (raw || '').trim()

	// Safe default for local development when env is missing.
	if (!value) return DEV_API_FALLBACK

	// Guardrail: prevent accidental self-calls to Next dev server.
	try {
		const url = new URL(value)
		const isLocalhost = url.hostname === 'localhost' || url.hostname === '127.0.0.1'
		if (isLocalhost && url.port === '3000') {
			return DEV_API_FALLBACK
		}
	} catch {
		// Ignore parse errors and keep raw value as-is.
	}

	return value.replace(/\/+$/, '')
}

export const API_BASE_URL = normalizeApiBaseUrl(process.env.NEXT_PUBLIC_API_URL)

export function getApiUrl(path: string): string {
	const normalizedPath = path.startsWith('/') ? path : `/${path}`
	return `${API_BASE_URL}${normalizedPath}`
}
