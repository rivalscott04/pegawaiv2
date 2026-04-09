'use client'

import Link from 'next/link'

export default function Footer() {
	return (
		<footer className="footer bg-base-200/80 border-t border-base-300 text-base-content/80 px-4 py-8 sm:px-6 sm:footer-horizontal">
			<aside>
				<p className="font-semibold text-base-content">SDM & Hukum</p>
				<p className="max-w-md text-sm leading-relaxed">
					Sistem Informasi SDM & Hukum Kantor Wilayah Kementerian Agama Provinsi Nusa Tenggara Barat
				</p>
				<p className="text-xs opacity-70 pt-2">© 2026 Kanwil Kemenag Provinsi NTB</p>
			</aside>
			<nav>
				<h6 className="footer-title opacity-90">Menu</h6>
				<Link href="/" className="link link-hover">
					Beranda
				</Link>
				<Link href="/employees" className="link link-hover">
					Data pegawai
				</Link>
				<Link href="/heatmap" className="link link-hover">
					Sebaran pegawai
				</Link>
			</nav>
		</footer>
	)
}
