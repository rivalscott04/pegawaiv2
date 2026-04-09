'use client'

import { usePathname } from 'next/navigation'
import Navbar from '@/components/Navbar'
import Sidebar from '@/components/Sidebar'
import Footer from '@/components/Footer'

export default function LayoutWrapper({ children }: { children: React.ReactNode }) {
  const pathname = usePathname()
  
  // Hide sidebar on landing page (/) and login page (/auth/login)
  const hideSidebar = pathname === '/' || pathname === '/auth/login'
  
  if (hideSidebar) {
    // Layout tanpa sidebar untuk landing page dan login
    return (
      <div className="flex min-h-dvh flex-col">
        <Navbar />
        <main className="flex-1 px-4 py-4">{children}</main>
        <Footer />
      </div>
    )
  }
  
  // Layout dengan sidebar untuk halaman lainnya
  return (
    <div className="drawer lg:drawer-open min-h-dvh">
      <input id="app-drawer" type="checkbox" className="drawer-toggle" />
      <div className="drawer-content flex min-h-dvh flex-col">
        <Navbar />
        <main className="flex-1 px-4 lg:pl-6 py-4">{children}</main>
        <Footer />
      </div>
      <Sidebar />
    </div>
  )
}

