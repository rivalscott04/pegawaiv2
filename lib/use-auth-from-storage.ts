'use client'

import { usePathname } from 'next/navigation'
import { useSyncExternalStore } from 'react'
import {
	readAuthRole,
	readAuthUsername,
	readHasAuthToken,
	readThemePreference,
	subscribeClientStorage,
} from '@/lib/client-storage'

/** Reads auth-related keys from storage; re-reads on route changes and `notifyClientStorageChanged()`. */
export function useAuthFromStorage() {
	const pathname = usePathname()

	const isLoggedIn = useSyncExternalStore(
		subscribeClientStorage,
		() => {
			void pathname
			return readHasAuthToken()
		},
		() => false
	)

	const role = useSyncExternalStore(
		subscribeClientStorage,
		() => {
			void pathname
			return readAuthRole()
		},
		() => ''
	)

	const username = useSyncExternalStore(
		subscribeClientStorage,
		() => {
			void pathname
			return readAuthUsername()
		},
		() => ''
	)

	const currentTheme = useSyncExternalStore(
		subscribeClientStorage,
		() => {
			void pathname
			return readThemePreference()
		},
		() => ''
	)

	return { isLoggedIn, role, username, currentTheme }
}
