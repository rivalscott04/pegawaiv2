'use client'

import { usePathname } from 'next/navigation'
import { useSyncExternalStore } from 'react'
import { canEditEmployees, canExportPegawai } from '@/lib/api'
import { subscribeClientStorage } from '@/lib/client-storage'

/** Permission flags from storage; SSR snapshot is false so markup matches before hydrate. */
export function useCanExportPegawai(): boolean {
	const pathname = usePathname()
	return useSyncExternalStore(
		subscribeClientStorage,
		() => {
			void pathname
			return canExportPegawai()
		},
		() => false
	)
}

export function useCanEditEmployees(): boolean {
	const pathname = usePathname()
	return useSyncExternalStore(
		subscribeClientStorage,
		() => {
			void pathname
			return canEditEmployees()
		},
		() => false
	)
}
