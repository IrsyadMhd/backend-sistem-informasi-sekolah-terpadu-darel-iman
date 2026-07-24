import { useState } from 'react'
import { NavLink, Outlet, useLocation } from 'react-router-dom'
import {
  FaBars,
  FaBell,
  FaBook,
  FaCalendarAlt,
  FaChevronDown,
  FaChevronRight,
  FaClipboardCheck,
  FaCog,
  FaDatabase,
  FaFileAlt,
  FaHome,
  FaMoneyBillWave,
  FaQuran,
  FaSignOutAlt,
  FaTimes,
  FaUserCircle,
} from 'react-icons/fa'
import Swal from 'sweetalert2'
import { authService } from '../services/authService'
import { useAuthStore } from '../stores/authStore'
import { usePengaturanStore } from '../stores/pengaturanStore'
import { useUnitStore } from '../stores/unitStore'

export default function DashboardLayout() {
  const location = useLocation()
  const user = useAuthStore((state) => state.user)
  const clearSession = useAuthStore((state) => state.clearSession)
  const activeUnit = useUnitStore((state) => state.activeUnit)
  const pengaturan = usePengaturanStore((state) => state.pengaturan)

  const [mobileMenuOpen, setMobileMenuOpen] = useState(false)
  const [openSection, setOpenSection] = useState('master-data')

  const namaTampil = user?.name || 'Kepala Sekolah'
  const roleTampil = 'Administrator'
  const tanggalTampil = new Intl.DateTimeFormat('id-ID', {
    weekday: 'long',
    day: '2-digit',
    month: 'long',
    year: 'numeric',
  }).format(new Date())

  const toggleSection = (sectionKey) => {
    setOpenSection((prev) => (prev === sectionKey ? '' : sectionKey))
  }

  const logout = async () => {
    try {
      await authService.logout()
    } catch {
      // Abaikan error API agar session lokal tetap bisa dibersihkan
    }
    clearSession()
    await Swal.fire('Sampai jumpa', 'Anda berhasil keluar dari sistem.', 'success')
    window.location.href = '/masuk'
  }

  const sidebarMenu = [
    {
      key: 'dashboard',
      label: 'Dashboard',
      icon: FaHome,
      to: '/dashboard',
    },
    {
      key: 'master-data',
      label: 'Master Data',
      icon: FaDatabase,
      submenus: [
        { to: '/dashboard/students/unit-pendidikan', label: 'Unit Pendidikan' },
        { to: '/dashboard/students', label: 'Siswa' },
        { to: '/dashboard/academic', label: 'Guru' },
        { to: '/dashboard/students/kelas', label: 'Kelas & Rombel' },
        { to: '/dashboard/parents', label: 'Orang Tua / Wali' },
      ],
    },
    {
      key: 'akademik',
      label: 'Akademik',
      icon: FaBook,
      submenus: [
        { to: '/dashboard/academic', label: 'Mata Pelajaran' },
        { to: '/dashboard/students/rombel', label: 'Jadwal Pelajaran' },
        { to: '/dashboard/laporan-akademik', label: 'Kurikulum & Capaian' },
        { to: '/dashboard/laporan-alumni', label: 'Modul & Prestasi' },
      ],
    },
    {
      key: 'absensi',
      label: 'Absensi',
      icon: FaClipboardCheck,
      submenus: [
        { to: '/dashboard/attendance', label: 'Absensi Siswa' },
        { to: '/dashboard/laporan-absensi', label: 'Laporan Absensi' },
      ],
    },
    {
      key: 'tahfizh',
      label: 'Tahfizh & Mutabaah',
      icon: FaQuran,
      submenus: [
        { to: '/dashboard/tahfizh', label: 'Setoran Hafalan' },
        { to: '/dashboard/laporan-tahfizh', label: 'Laporan Mutabaah' },
      ],
    },
    {
      key: 'keuangan',
      label: 'Keuangan',
      icon: FaMoneyBillWave,
      submenus: [
        { to: '/dashboard/students', label: 'SPP & Tagihan' },
        { to: '/dashboard/laporan-siswa', label: 'Laporan Keuangan' },
      ],
    },
    {
      key: 'laporan',
      label: 'Laporan',
      icon: FaFileAlt,
      submenus: [
        { to: '/dashboard/laporan-siswa', label: 'Laporan Siswa' },
        { to: '/dashboard/laporan-akademik', label: 'Laporan Akademik' },
        { to: '/dashboard/laporan-alumni', label: 'Laporan Alumni' },
      ],
    },
    {
      key: 'pengaturan',
      label: 'Pengaturan',
      icon: FaCog,
      submenus: [
        { to: '/dashboard/pengaturan', label: 'Profil Sekolah' },
        { to: '/dashboard/notifications', label: 'Notifikasi & User' },
      ],
    },
  ]

  const isSubActive = (to) => {
    if (to === '/dashboard/students') {
      return location.pathname === '/dashboard/students' || location.pathname === '/dashboard/students/'
    }
    return location.pathname.startsWith(to) && to !== '/dashboard'
  }

  return (
    <div className="grid min-h-screen md:grid-cols-[240px_1fr] bg-slate-100 font-sans">
      {/* Mobile Drawer Overlay */}
      {mobileMenuOpen && (
        <div
          className="fixed inset-0 z-40 bg-black/60 backdrop-blur-sm md:hidden"
          onClick={() => setMobileMenuOpen(false)}
        />
      )}

      {/* Sidebar Navigation */}
      <aside
        className={`fixed inset-y-0 left-0 z-50 flex w-[240px] flex-col justify-between border-r border-emerald-900/40 bg-[#064e3b] p-3 text-white transition-transform duration-300 md:static md:translate-x-0 ${
          mobileMenuOpen ? 'translate-x-0' : '-translate-x-full'
        }`}
      >
        <div className="space-y-4">
          {/* Header Brand */}
          <div className="flex items-center justify-between border-b border-emerald-700/50 pb-3">
            <div className="flex items-center gap-2.5">
              <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-600 text-white shadow-md">
                <FaQuran className="text-lg" />
              </div>
              <div>
                <h1 className="text-sm font-bold tracking-tight text-white leading-tight">Dar El-Iman</h1>
                <p className="text-[10px] font-medium text-emerald-200/80 uppercase tracking-widest">Sistem Manajemen Sekolah</p>
              </div>
            </div>
            <button
              type="button"
              onClick={() => setMobileMenuOpen(false)}
              className="text-emerald-300 hover:text-white md:hidden"
            >
              <FaTimes />
            </button>
          </div>

          {/* Navigation Accordion */}
          <nav className="space-y-1 overflow-y-auto max-h-[calc(100vh-140px)] pr-1 text-xs">
            {sidebarMenu.map((item) => {
              const Icon = item.icon
              if (!item.submenus) {
                const isActive = location.pathname === item.to
                return (
                  <NavLink
                    key={item.key}
                    to={item.to}
                    onClick={() => setMobileMenuOpen(false)}
                    className={`flex items-center gap-3 rounded-lg px-3 py-2.5 font-medium transition ${
                      isActive
                        ? 'bg-emerald-600 text-white font-semibold shadow-sm'
                        : 'text-emerald-100/90 hover:bg-emerald-800/60 hover:text-white'
                    }`}
                  >
                    <Icon className="text-sm" />
                    <span>{item.label}</span>
                  </NavLink>
                )
              }

              const isOpen = openSection === item.key
              const hasActiveChild = item.submenus.some((sub) => isSubActive(sub.to))

              return (
                <div key={item.key} className="space-y-1">
                  <button
                    type="button"
                    onClick={() => toggleSection(item.key)}
                    className={`flex w-full items-center justify-between rounded-lg px-3 py-2.5 text-xs font-medium transition ${
                      hasActiveChild || isOpen
                        ? 'text-white font-semibold'
                        : 'text-emerald-100/90 hover:bg-emerald-800/60 hover:text-white'
                    }`}
                  >
                    <div className="flex items-center gap-3">
                      <Icon className="text-sm" />
                      <span>{item.label}</span>
                    </div>
                    {isOpen ? <FaChevronDown className="text-[10px]" /> : <FaChevronRight className="text-[10px]" />}
                  </button>

                  {isOpen && (
                    <div className="ml-5 space-y-1 border-l border-emerald-700/60 pl-2.5">
                      {item.submenus.map((sub) => {
                        const active = isSubActive(sub.to)
                        return (
                          <NavLink
                            key={sub.label}
                            to={sub.to}
                            onClick={() => setMobileMenuOpen(false)}
                            className={`block rounded-md px-2.5 py-1.5 transition text-xs ${
                              active
                                ? 'bg-emerald-600 text-white font-semibold shadow-sm'
                                : 'text-emerald-200/90 hover:bg-emerald-800/40 hover:text-white'
                            }`}
                          >
                            {sub.label}
                          </NavLink>
                        )
                      })}
                    </div>
                  )}
                </div>
              )
            })}
          </nav>
        </div>

        {/* Profile Card Footer */}
        <div className="mt-4 border-t border-emerald-700/50 pt-3">
          <div className="flex items-center gap-2.5 px-1 py-1">
            <div className="h-9 w-9 overflow-hidden rounded-full border border-emerald-400/50 bg-emerald-800 flex items-center justify-center">
              <FaUserCircle className="text-2xl text-emerald-200" />
            </div>
            <div className="min-w-0 flex-1">
              <p className="truncate text-xs font-semibold text-white">{namaTampil}</p>
              <p className="truncate text-[10px] text-emerald-200/80">{roleTampil}</p>
            </div>
            <button
              type="button"
              onClick={logout}
              title="Logout"
              className="rounded-lg p-1.5 text-emerald-200 hover:bg-rose-900/60 hover:text-rose-200 transition"
            >
              <FaSignOutAlt className="text-xs" />
            </button>
          </div>
        </div>
      </aside>

      {/* Main Content Area */}
      <div className="flex min-w-0 flex-col">
        {/* Top Navbar */}
        <header className="flex h-14 items-center justify-between border-b border-slate-200 bg-white px-4 shadow-sm md:px-6">
          <div className="flex items-center gap-3">
            <button
              type="button"
              onClick={() => setMobileMenuOpen(true)}
              className="rounded-lg border border-slate-200 p-2 text-slate-600 hover:bg-slate-50 md:hidden"
            >
              <FaBars />
            </button>
            <div className="hidden sm:block">
              <span className="text-xs font-medium text-slate-500">Unit Aktif: </span>
              <span className="text-xs font-bold text-emerald-800">{activeUnit || 'Semua Unit Dar El-Iman'}</span>
            </div>
          </div>

          <div className="flex items-center gap-3">
            <div className="hidden items-center gap-2 text-xs font-medium text-slate-600 sm:flex">
              <FaCalendarAlt className="text-emerald-700" />
              <span>{tanggalTampil}</span>
            </div>

            <div className="relative">
              <button
                type="button"
                className="relative rounded-full border border-slate-200 bg-slate-50 p-2 text-slate-600 hover:bg-slate-100"
              >
                <FaBell className="text-sm" />
                <span className="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-emerald-600 text-[9px] font-bold text-white">
                  3
                </span>
              </button>
            </div>
          </div>
        </header>

        {/* Dynamic Page Content */}
        <main className="flex-1 p-3 md:p-6">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
