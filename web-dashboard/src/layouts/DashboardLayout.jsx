import { useState, useRef, useEffect } from 'react'
import { NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom'
import {
  LayoutDashboard,
  Database,
  BookOpen,
  BookMarked,
  FileText,
  Settings,
  ChevronDown,
  ChevronRight,
  ChevronLeft,
  Menu,
  X,
  Search,
  Bell,
  User,
  Layers,
  Plus,
  LogOut,
  Calendar,
  Sparkles,
  Sun,
  Moon,
  CalendarCheck,
  Wallet,
  HelpCircle,
  Target,
} from 'lucide-react'
import Swal from 'sweetalert2'
import { authService } from '../services/authService'
import { useAuthStore } from '../stores/authStore'
import { usePengaturanStore } from '../stores/pengaturanStore'
import { useUnitStore } from '../stores/unitStore'
import { Drawer } from '../components/ui/drawer'
import { FAB } from '../components/ui/fab'

export default function DashboardLayout() {
  const location = useLocation()
  const navigate = useNavigate()
  const user = useAuthStore((state) => state.user)
  const clearSession = useAuthStore((state) => state.clearSession)
  const activeUnit = useUnitStore((state) => state.activeUnit)
  const setActiveUnit = useUnitStore((state) => state.setActiveUnit)
  const pengaturan = usePengaturanStore((state) => state.pengaturan)
  const namaSekolah = pengaturan?.namaSekolah || 'YAYASAN DAR EL - IMAN'

  const [collapsed, setCollapsed] = useState(false)
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false)
  const [openSection, setOpenSection] = useState('master-data')
  const [profileDropdownOpen, setProfileDropdownOpen] = useState(false)
  const [unitDropdownOpen, setUnitDropdownOpen] = useState(false)
  const [themeMenuOpen, setThemeMenuOpen] = useState(false)
  const [notificationsOpen, setNotificationsOpen] = useState(false)
  const [searchQuery, setSearchQuery] = useState('')
  const [isDarkMode, setIsDarkMode] = useState(() => {
    const saved = localStorage.getItem('theme')
    if (saved === 'light') return false
    if (saved === 'dark') return true
    return false // Default to Light Mode
  })

  const profileDropdownRef = useRef(null)
  const unitDropdownRef = useRef(null)
  const themeDropdownRef = useRef(null)

  // Sync dark mode class on html & body
  useEffect(() => {
    if (isDarkMode) {
      document.documentElement.classList.add('dark')
      document.body.classList.add('dark')
      localStorage.setItem('theme', 'dark')
    } else {
      document.documentElement.classList.remove('dark')
      document.body.classList.remove('dark')
      localStorage.setItem('theme', 'light')
    }
  }, [isDarkMode])

  // Close dropdowns when clicking outside
  useEffect(() => {
    const handler = (e) => {
      if (profileDropdownRef.current && !profileDropdownRef.current.contains(e.target)) {
        setProfileDropdownOpen(false)
      }
      if (unitDropdownRef.current && !unitDropdownRef.current.contains(e.target)) {
        setUnitDropdownOpen(false)
      }
      if (themeDropdownRef.current && !themeDropdownRef.current.contains(e.target)) {
        setThemeMenuOpen(false)
      }
    }
    document.addEventListener('mousedown', handler)
    return () => document.removeEventListener('mousedown', handler)
  }, [])

  const namaTampil = user?.name || 'Ketua Yayasan'
  const roleTampil = 'Super Admin Yayasan'
  const tanggalTampil = 'Senin, 27 Juli 2026 / 1 Muharram 1448 H'

  const toggleSection = (sectionKey) => {
    setOpenSection((prev) => (prev === sectionKey ? '' : sectionKey))
  }

  const logout = async () => {
    try {
      await authService.logout()
    } catch {
      // Abaikan error API
    }
    clearSession()
    await Swal.fire({
      icon: 'success',
      title: 'Sampai Jumpa',
      text: 'Anda telah berhasil keluar dari Sistem Manajemen Sekolah.',
      confirmButtonColor: '#0F5132',
    })
    window.location.href = '/masuk'
  }

  const daftarUnitOptions = [
    { id: 'SEMUA', name: 'Semua Unit Pendidikan' },
    { id: 'TK', name: 'TK Islam Terpadu' },
    { id: 'SD', name: 'SD Islam Terpadu' },
    { id: 'SMP', name: 'SMP Islam Terpadu' },
    { id: 'SMA', name: 'SMA Islam Terpadu' },
    { id: 'PONPES', name: 'Pondok Pesantren' },
  ]

  const sidebarMenu = [
    {
      key: 'dashboard',
      label: 'Dashboard',
      icon: LayoutDashboard,
      to: '/dashboard',
    },
    {
      key: 'master-data',
      label: 'MASTER DATA',
      icon: Database,
      submenus: [
        { to: '/dashboard/students/unit-pendidikan', label: 'Unit Pendidikan' },
        { to: '/dashboard/master-jenis-unit', label: 'Jenis Unit' },
        { to: '/dashboard/master-tahun-ajaran', label: 'Tahun Ajaran' },
        { to: '/dashboard/master-subjects', label: 'Mata Pelajaran' },
        { to: '/dashboard/master-jabatan', label: 'Jabatan' },
        { to: '/dashboard/employees', label: 'Pegawai' },
        { to: '/dashboard/students', label: 'Siswa' },
      ],
    },
    {
      key: 'akademik',
      label: 'AKADEMIK',
      icon: BookOpen,
      submenus: [
        { to: '/dashboard/master-tahun-ajaran', label: 'Tahun Ajaran' },
        { to: '/dashboard/master-modul-semester', label: 'Semester' },
        { to: '/dashboard/master-kurikulum', label: 'Kurikulum' },
        { to: '/dashboard/students/rombel', label: 'Kelas & Rombel' },
        { to: '/dashboard/master-subjects', label: 'Mata Pelajaran' },
        { to: '/dashboard/master-capaian-pembelajaran', label: 'Capaian Pembelajaran (CP)' },
        { to: '/dashboard/master-tujuan-pembelajaran', label: 'Tujuan Pembelajaran (TP)' },
        { to: '/dashboard/lms/modul-ajar', label: 'Modul Ajar (RPP)' },
        { to: '/dashboard/lms/materi-pembelajaran', label: 'Materi Pembelajaran' },
        { to: '/dashboard/lms/media-pembelajaran', label: 'Media Pembelajaran' },
        { to: '/dashboard/lms/referensi-pembelajaran', label: 'Referensi Pembelajaran' },
        { to: '/dashboard/lms/aktivitas-belajar', label: 'Aktivitas Belajar' },
        { to: '/dashboard/lms/diskusi-kelas', label: 'Diskusi Kelas' },
        { to: '/dashboard/lms/penugasan', label: 'Penugasan' },
        { to: '/dashboard/lms/pengumpulan-tugas', label: 'Pengumpulan Tugas' },
        { to: '/dashboard/lms/presensi-pembelajaran', label: 'Presensi Pembelajaran' },
        { to: '/dashboard/lms/kisi-kisi', label: 'Kisi-kisi Ujian' },
        { to: '/dashboard/lms/bank-soal', label: 'Bank Soal' },
        { to: '/dashboard/lms/ujian-online', label: 'Ujian Online (CBT)' },
        { to: '/dashboard/lms/penilaian', label: 'Penilaian LMS' },
        { to: '/dashboard/lms/rapor', label: 'Rapor Digital & PDF' },
      ],
    },
    {
      key: 'absensi',
      label: 'ABSENSI',
      icon: CalendarCheck,
      submenus: [
        { to: '/dashboard/attendance', label: 'Presensi Guru' },
        { to: '/dashboard/attendance?view=siswa', label: 'Presensi Siswa' },
        { to: '/dashboard/lms/presensi-pembelajaran', label: 'Presensi Pembelajaran (LMS)' },
        { to: '/dashboard/laporan-absensi', label: 'Rekap Presensi' },
      ],
    },
    {
      key: 'tahfidz',
      label: 'TAHFIDZ',
      icon: BookMarked,
      submenus: [
        { to: '/dashboard/tahfizh', label: 'Hafalan' },
        { to: '/dashboard/laporan-tahfizh', label: 'Mutabaah' },
        { to: '/dashboard/tahfizh?tab=murajaah', label: 'Murajaah' },
      ],
    },
    {
      key: 'laporan',
      label: 'LAPORAN',
      icon: FileText,
      submenus: [
        { to: '/dashboard/laporan-siswa', label: 'Laporan Siswa' },
        { to: '/dashboard/laporan-akademik', label: 'Laporan Akademik' },
        { to: '/dashboard/laporan-tahfizh', label: 'Laporan Tahfizh' },
      ],
    },
    {
      key: 'pengaturan',
      label: 'PENGATURAN',
      icon: Settings,
      submenus: [
        { to: '/dashboard/pengaturan', label: 'Profil Sekolah' },
        { to: '/dashboard/hak-akses', label: 'Hak Akses' },
        { to: '/dashboard/pengaturan?tab=unit', label: 'Pengaturan Unit' },
      ],
    },
  ]

  const isSubActive = (to) => {
    if (to === '/dashboard/students') {
      return location.pathname === '/dashboard/students' || location.pathname === '/dashboard/students/'
    }
    return location.pathname.startsWith(to) && to !== '/dashboard'
  }

  const notifikasiItems = [
    { id: 1, title: 'Setoran Tahfizh Baru', desc: 'Siswa Ahmad Faiq menyelesaikan Surah Al-Mulk', time: '10 min yang lalu', unread: true },
    { id: 2, title: 'Laporan Kehadiran Guru', desc: 'Rekap kehadiran bulan Juli telah difinalisasi', time: '1 jam yang lalu', unread: true },
    { id: 3, title: 'Jadwal Rapat Kurikulum', desc: 'Undangan rapat evaluasi Semester Ganjil 2026/2027', time: '3 jam yang lalu', unread: false },
  ]

  return (
    <div className="min-h-screen bg-slate-50/80 text-slate-800 flex flex-col font-sans antialiased dark:bg-slate-950 dark:text-slate-100">
      {/* Mobile Overlay */}
      {mobileMenuOpen && (
        <div
          className="fixed inset-0 z-40 bg-slate-950/60 backdrop-blur-xs md:hidden"
          onClick={() => setMobileMenuOpen(false)}
        />
      )}

      <div className="flex flex-1 min-h-screen">
        {/* Left Sidebar (Sticky & Collapsible) */}
        <aside
          className={`fixed inset-y-0 left-0 z-50 flex flex-col justify-between border-r border-[#1E8E5A]/20 bg-gradient-to-b from-[#0E5C44] via-[#0b4d39] to-[#083a2b] text-slate-100 transition-all duration-300 ease-in-out md:sticky md:top-0 md:h-screen ${collapsed ? 'w-20' : 'w-64'
            } ${mobileMenuOpen ? 'translate-x-0 w-64' : '-translate-x-full md:translate-x-0'}`}
        >
          {/* Header Sidebar: Logo & Collapsible Toggle */}
          <div className="p-4 border-b border-white/10">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-3 overflow-hidden">
                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-[#3FBF75] to-[#1E8E5A] text-white shadow-md">
                  <Sparkles className="h-5 w-5 stroke-[2]" />
                </div>
                {!collapsed && (
                  <div className="min-w-0">
                    <h1 className="text-xs font-black tracking-wider text-white uppercase truncate font-sans">
                      {namaSekolah}
                    </h1>
                    <p className="text-[10px] font-bold text-[#3FBF75] tracking-widest">SEKOLAH TERPADU</p>
                  </div>
                )}
              </div>

              {/* Desktop Toggle Button */}
              <button
                type="button"
                onClick={() => setCollapsed(!collapsed)}
                className="hidden md:flex h-7 w-7 items-center justify-center rounded-xl bg-white/10 text-emerald-100 hover:bg-white/20 hover:text-white transition-all btn-master"
              >
                {collapsed ? <ChevronRight className="h-4 w-4" /> : <ChevronLeft className="h-4 w-4" />}
              </button>

              {/* Mobile Close Button */}
              <button
                type="button"
                onClick={() => setMobileMenuOpen(false)}
                className="md:hidden text-emerald-200 hover:text-white p-1"
              >
                <X className="h-5 w-5" />
              </button>
            </div>
          </div>

          {/* Navigation Items */}
          <nav className="flex-1 space-y-1 overflow-y-auto p-3 text-xs custom-scrollbar">
            {sidebarMenu.map((item) => {
              const Icon = item.icon
              if (!item.submenus) {
                const isActive = location.pathname === item.to
                return (
                  <NavLink
                    key={item.key}
                    to={item.to}
                    onClick={() => setMobileMenuOpen(false)}
                    className={`group relative flex items-center gap-3 rounded-xl px-3 py-2.5 font-bold transition-all duration-200 ${isActive
                      ? 'bg-white text-[#0E5C44] shadow-md dark:bg-[#10B981] dark:text-[#0d1514]'
                      : 'text-emerald-100/70 hover:bg-white/10 hover:text-white'
                      }`}
                    title={collapsed ? item.label : undefined}
                  >
                    <div className={`flex h-6 w-6 shrink-0 items-center justify-center rounded-lg ${isActive ? 'bg-[#0E5C44]/10 text-[#0E5C44] dark:bg-slate-900/30 dark:text-slate-900' : 'text-emerald-300'}`}>
                      <Icon className="h-4 w-4 stroke-[2]" />
                    </div>
                    {!collapsed && <span>{item.label}</span>}
                  </NavLink>
                )
              }

              const isOpen = openSection === item.key
              const hasActiveChild = item.submenus.some((sub) => isSubActive(sub.to))

              return (
                <div key={item.key} className="space-y-1">
                  <button
                    type="button"
                    onClick={() => {
                      if (collapsed) setCollapsed(false)
                      toggleSection(item.key)
                    }}
                    className={`flex w-full items-center justify-between rounded-xl px-3 py-2.5 text-xs font-bold transition-all duration-200 ${hasActiveChild || isOpen
                      ? 'bg-white/15 text-white shadow-xs'
                      : 'text-emerald-100/70 hover:bg-white/10 hover:text-white'
                      }`}
                    title={collapsed ? item.label : undefined}
                  >
                    <div className="flex items-center gap-3 min-w-0">
                      <Icon className="h-4 w-4 shrink-0 text-[#3FBF75] stroke-[1.8]" />
                      {!collapsed && <span className="truncate">{item.label}</span>}
                    </div>
                    {!collapsed && (
                      <span className="text-[10px]">
                        {isOpen ? <ChevronDown className="h-3.5 w-3.5" /> : <ChevronRight className="h-3.5 w-3.5" />}
                      </span>
                    )}
                  </button>

                  {/* Submenu Accordion */}
                  {!collapsed && isOpen && (
                    <div className="ml-5 space-y-1 border-l-2 border-[#3FBF75]/40 pl-3 pt-1 animate-[masterDropdownSlide_0.2s_ease-out]">
                      {item.submenus.map((sub) => {
                        const active = isSubActive(sub.to)
                        return (
                          <NavLink
                            key={sub.label}
                            to={sub.to}
                            onClick={() => setMobileMenuOpen(false)}
                            className={`block rounded-lg px-2.5 py-1.5 text-xs transition-all duration-150 ${active
                              ? 'bg-[#3FBF75] text-slate-900 font-bold shadow-xs'
                              : 'text-emerald-100/80 hover:bg-white/10 hover:text-white'
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

          {/* User Status Bar & Help Link at Sidebar Bottom */}
          <div className="p-3.5 border-t border-white/10 bg-[#083a2b]/80 space-y-2.5">
            <div className="flex items-center gap-3">
              <div className="relative">
                <div className="h-8 w-8 shrink-0 rounded-full bg-gradient-to-tr from-[#0E5C44] to-[#3FBF75] text-white flex items-center justify-center font-bold text-xs shadow-md border border-white/20">
                  {user?.avatar ? (
                    <img src={user.avatar} alt="Avatar" className="h-full w-full rounded-full object-cover" />
                  ) : (
                    namaTampil.charAt(0)
                  )}
                </div>
                <span className="absolute bottom-0 right-0 h-2.5 w-2.5 rounded-full bg-emerald-400 border-2 border-[#083a2b]" />
              </div>
              {!collapsed && (
                <div className="min-w-0 flex-1">
                  <p className="truncate text-xs font-bold text-white leading-tight">{namaTampil}</p>
                  <div className="flex items-center gap-1.5 mt-0.5">
                    <p className="truncate text-[10px] font-medium text-[#3FBF75]">{roleTampil}</p>
                    <span className="text-[9px] text-emerald-300 font-semibold">• Online</span>
                  </div>
                </div>
              )}
            </div>

            {!collapsed && (
              <button
                type="button"
                onClick={() => navigate('/dashboard/pengaturan')}
                className="w-full flex items-center gap-2 px-2.5 py-1.5 rounded-xl bg-white/10 hover:bg-white/20 text-emerald-100 text-[11px] font-semibold transition"
              >
                <HelpCircle className="h-3.5 w-3.5 text-[#3FBF75]" />
                <span>Bantuan & Panduan</span>
              </button>
            )}
          </div>
        </aside>

        {/* Main Workspace Area */}
        <div className="flex-1 flex flex-col min-w-0">
          {/* Topbar Navbar (Sticky Header) */}
          <header className="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-slate-200/80 bg-white/90 px-4 md:px-8 backdrop-blur-md shadow-2xs dark:border-slate-800/80 dark:bg-slate-900/90">
            {/* Left Controls: Mobile Toggle, Unit Switcher Dropdown, Search */}
            <div className="flex items-center gap-3 flex-1 max-w-xl">
              <button
                type="button"
                onClick={() => setMobileMenuOpen(true)}
                className="rounded-xl border border-slate-200 p-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900 md:hidden dark:border-slate-800 dark:text-slate-300"
              >
                <Menu className="h-5 w-5" />
              </button>

              {/* Active Unit Dropdown Switcher */}
              <div className="relative" ref={unitDropdownRef}>
                <button
                  type="button"
                  onClick={() => setUnitDropdownOpen(!unitDropdownOpen)}
                  className="flex items-center gap-2 rounded-xl border border-slate-200/80 bg-slate-50/80 px-3 py-1.5 text-xs font-bold text-slate-800 hover:bg-slate-100 hover:border-[#0E5C44]/30 transition-all dark:border-slate-800 dark:bg-slate-800/60 dark:text-slate-200 btn-master"
                >
                  <Layers className="h-4 w-4 text-[#0E5C44] dark:text-[#3FBF75] stroke-[2]" />
                  <span className="hidden sm:inline text-slate-500 font-medium">Unit:</span>
                  <span className="font-extrabold text-[#0E5C44] dark:text-[#3FBF75]">{activeUnit || 'Semua Unit'}</span>
                  <ChevronDown className="h-3.5 w-3.5 text-slate-400" />
                </button>

                {unitDropdownOpen && (
                  <div className="absolute left-0 top-full mt-2 w-56 rounded-[18px] bg-white p-1.5 shadow-2xl border border-slate-200/80 z-50 animate-[masterDropdownSlide_0.2s_ease-out] dark:bg-[#1B2433] dark:border-slate-800">
                    <p className="px-3 py-1.5 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">
                      Pilih Unit Pendidikan
                    </p>
                    {daftarUnitOptions.map((unit) => (
                      <button
                        key={unit.id}
                        type="button"
                        onClick={() => {
                          setActiveUnit(unit.id)
                          setUnitDropdownOpen(false)
                        }}
                        className={`w-full text-left flex items-center justify-between px-3 py-2 rounded-xl text-xs font-semibold transition-all ${activeUnit === unit.id
                          ? 'bg-[#0E5C44]/10 text-[#0E5C44] font-bold dark:bg-[#3FBF75]/20 dark:text-[#3FBF75]'
                          : 'text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800/60'
                          }`}
                      >
                        <span>{unit.name}</span>
                        {activeUnit === unit.id && <span className="h-2 w-2 rounded-full bg-[#0E5C44] dark:bg-[#3FBF75]" />}
                      </button>
                    ))}
                  </div>
                )}
              </div>

              {/* Global Search Bar (Expand Width on Focus) */}
              <div className="relative hidden md:flex items-center flex-1 max-w-xs transition-all duration-300 focus-within:max-w-md">
                <Search className="absolute left-3 text-slate-400 h-4 w-4" />
                <input
                  type="text"
                  placeholder="Cari siswa, guru, kelas..."
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  className="w-full rounded-xl border border-slate-200/80 bg-slate-50/80 pl-9 pr-8 py-1.5 text-xs font-medium placeholder-slate-400 focus:bg-white focus:border-[#0E5C44] focus:outline-none focus:ring-2 focus:ring-[#0E5C44]/30 transition-all duration-300 dark:border-slate-800 dark:bg-slate-800/60 dark:text-slate-100 dark:placeholder-slate-500 dark:focus:bg-[#111827] dark:focus:border-[#3FBF75]"
                />
                <span className="absolute right-3 rounded-md bg-slate-200/60 px-1.5 py-0.5 text-[9px] font-bold text-slate-500 dark:bg-slate-700 dark:text-slate-300">
                  Ctrl + K
                </span>
              </div>
            </div>

            {/* Right Controls: Date, Notifications, Profile Avatar */}
            <div className="flex items-center gap-3">
              {/* Realtime Date Display */}
              <div className="hidden lg:flex items-center gap-2 rounded-xl bg-slate-100/70 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:bg-slate-800/60 dark:text-slate-200">
                <Calendar className="h-3.5 w-3.5 text-[#0E5C44] dark:text-[#3FBF75]" />
                <span>{tanggalTampil}</span>
              </div>

              {/* Notification Drawer Trigger */}
              <button
                type="button"
                onClick={() => setNotificationsOpen(true)}
                className="relative rounded-xl border border-slate-200/80 bg-slate-50/80 p-2 text-slate-600 hover:bg-slate-100 hover:text-[#0E5C44] transition-all dark:border-slate-800 dark:bg-slate-800/60 dark:text-slate-300 dark:hover:bg-slate-800 btn-master"
                title="Notifikasi Sistem"
              >
                <Bell className="h-4 w-4" />
                <span className="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-rose-500 text-[9px] font-black text-white shadow-xs">
                  {notifikasiItems.filter((n) => n.unread).length}
                </span>
              </button>

              {/* Mode Tampilan Switcher (Light / Dark Mode) */}
              <div className="relative" ref={themeDropdownRef}>
                <button
                  type="button"
                  onClick={() => setThemeMenuOpen(!themeMenuOpen)}
                  className="flex items-center gap-2 rounded-xl border border-slate-200/80 bg-slate-50/80 px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-100 dark:border-slate-800 dark:bg-slate-800/60 dark:text-slate-200 transition-all btn-master"
                  title="Pilih Mode Tampilan"
                >
                  {isDarkMode ? <Moon className="h-4 w-4 text-emerald-400" /> : <Sun className="h-4 w-4 text-amber-500" />}
                  <span className="hidden sm:inline font-semibold">{isDarkMode ? 'Night Mode' : 'Light Mode'}</span>
                  <ChevronDown className="h-3.5 w-3.5 text-slate-400" />
                </button>

                {themeMenuOpen && (
                  <div className="absolute right-0 top-full mt-2 w-48 rounded-[18px] bg-white p-1.5 shadow-2xl border border-slate-200/80 z-50 animate-[masterDropdownSlide_0.2s_ease-out] dark:bg-[#13221f] dark:border-slate-800">
                    <p className="px-3 py-1.5 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">
                      Mode Tampilan
                    </p>

                    <button
                      type="button"
                      onClick={() => {
                        setIsDarkMode(false)
                        setThemeMenuOpen(false)
                      }}
                      className={`w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs font-semibold transition ${!isDarkMode
                        ? 'bg-[#0E5C44]/10 text-[#0E5C44] font-bold dark:bg-emerald-500/20 dark:text-emerald-300'
                        : 'text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60'
                        }`}
                    >
                      <div className="flex items-center gap-2">
                        <Sun className="h-4 w-4 text-amber-500" />
                        <span>Light Mode</span>
                      </div>
                      {!isDarkMode && <span className="h-2 w-2 rounded-full bg-[#0E5C44] dark:bg-emerald-400" />}
                    </button>

                    <button
                      type="button"
                      onClick={() => {
                        setIsDarkMode(true)
                        setThemeMenuOpen(false)
                      }}
                      className={`w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs font-semibold transition ${isDarkMode
                        ? 'bg-[#0E5C44]/10 text-[#0E5C44] font-bold dark:bg-emerald-500/20 dark:text-emerald-300'
                        : 'text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60'
                        }`}
                    >
                      <div className="flex items-center gap-2">
                        <Moon className="h-4 w-4 text-emerald-400" />
                        <span>Night Mode</span>
                      </div>
                      {isDarkMode && <span className="h-2 w-2 rounded-full bg-[#0E5C44] dark:bg-emerald-400" />}
                    </button>
                  </div>
                )}
              </div>

              {/* User Profile Avatar Dropdown */}
              <div className="relative" ref={profileDropdownRef}>
                <button
                  type="button"
                  onClick={() => setProfileDropdownOpen(!profileDropdownOpen)}
                  className="flex items-center gap-2.5 rounded-xl border border-slate-200/80 bg-white px-2.5 py-1 text-xs font-semibold text-slate-800 hover:bg-slate-50 transition-all shadow-xs dark:border-slate-800 dark:bg-[#111827] dark:text-slate-200 btn-master"
                >
                  <div className="h-7 w-7 rounded-full bg-[#0E5C44] text-white flex items-center justify-center font-bold text-xs shadow-sm border border-[#3FBF75]/30">
                    {namaTampil.charAt(0)}
                  </div>
                  <div className="hidden sm:block text-left">
                    <p className="text-xs font-bold text-slate-900 dark:text-white leading-none">{namaTampil}</p>
                    <p className="text-[10px] text-slate-400 mt-0.5 font-semibold">{roleTampil}</p>
                  </div>
                  <ChevronDown className="h-3.5 w-3.5 text-slate-400" />
                </button>

                {/* Profile Dropdown Popup */}
                {profileDropdownOpen && (
                  <div className="absolute right-0 top-full mt-2 w-56 rounded-[18px] bg-white border border-slate-200/80 shadow-2xl overflow-hidden z-50 animate-[masterDropdownSlide_0.2s_ease-out] dark:bg-[#1B2433] dark:border-slate-800">
                    <div className="px-4 py-3 bg-slate-50 border-b border-slate-100 dark:bg-slate-800/50 dark:border-slate-800">
                      <p className="text-xs font-bold text-slate-900 dark:text-white">{namaTampil}</p>
                      <p className="text-[10px] text-slate-500 truncate mt-0.5 dark:text-slate-400">admin@dareliman.sch.id</p>
                    </div>

                    <div className="p-1.5 space-y-0.5">
                      <button
                        onClick={() => {
                          navigate('/dashboard/pengaturan')
                          setProfileDropdownOpen(false)
                        }}
                        className="w-full text-left flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-semibold text-slate-700 hover:bg-slate-100 hover:text-slate-900 transition dark:text-slate-300 dark:hover:bg-slate-800"
                      >
                        <User className="h-4 w-4 text-[#0E5C44] dark:text-[#3FBF75]" />
                        <span>Profil & Akun</span>
                      </button>
                      <button
                        onClick={() => {
                          navigate('/dashboard/pengaturan?tab=keamanan')
                          setProfileDropdownOpen(false)
                        }}
                        className="w-full text-left flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-semibold text-slate-700 hover:bg-slate-100 hover:text-slate-900 transition dark:text-slate-300 dark:hover:bg-slate-800"
                      >
                        <Settings className="h-4 w-4 text-slate-500" />
                        <span>Ganti Password</span>
                      </button>
                    </div>

                    <div className="border-t border-slate-100 p-1.5 dark:border-slate-800">
                      <button
                        onClick={() => {
                          setProfileDropdownOpen(false)
                          logout()
                        }}
                        className="w-full text-left flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-semibold text-rose-600 hover:bg-rose-50 transition dark:text-rose-400 dark:hover:bg-rose-950/40"
                      >
                        <LogOut className="h-4 w-4" />
                        <span>Keluar Sistem</span>
                      </button>
                    </div>
                  </div>
                )}
              </div>
            </div>
          </header>

          {/* Main Page Workspace */}
          <main className="flex-1 p-6 md:p-8 space-y-8 max-w-7xl w-full mx-auto pb-24 md:pb-12">
            <Outlet />
          </main>
        </div>
      </div>

      {/* Notifications Drawer */}
      <Drawer
        isOpen={notificationsOpen}
        onClose={() => setNotificationsOpen(false)}
        title="Pemberitahuan & Activity Log"
        position="right"
      >
        <div className="space-y-3">
          {notifikasiItems.map((item) => (
            <div
              key={item.id}
              className={`p-4 rounded-2xl border transition ${item.unread
                ? 'bg-emerald-50/50 border-emerald-200/80 dark:bg-emerald-950/30 dark:border-emerald-800/80'
                : 'bg-white border-slate-200/80 dark:bg-slate-900 dark:border-slate-800/80'
                }`}
            >
              <div className="flex items-center justify-between">
                <h5 className="text-xs font-bold text-slate-900 dark:text-white">{item.title}</h5>
                {item.unread && <span className="h-2 w-2 rounded-full bg-emerald-600" />}
              </div>
              <p className="text-xs text-slate-600 mt-1 dark:text-slate-300">{item.desc}</p>
              <p className="text-[10px] text-slate-400 mt-2 font-medium">{item.time}</p>
            </div>
          ))}
        </div>
      </Drawer>

      {/* Mobile Bottom Navigation (Responsive Mobile View <= 768px) */}
      <nav className="fixed bottom-0 inset-x-0 z-40 flex items-center justify-around border-t border-slate-200/80 bg-white/95 px-2 py-2 backdrop-blur-md md:hidden shadow-lg dark:border-slate-800 dark:bg-slate-900/95">
        <NavLink
          to="/dashboard"
          className={({ isActive }) =>
            `flex flex-col items-center gap-1 text-[10px] font-semibold transition ${isActive ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400'
            }`
          }
        >
          <LayoutDashboard className="h-5 w-5" />
          <span>Dashboard</span>
        </NavLink>

        <NavLink
          to="/dashboard/students"
          className={({ isActive }) =>
            `flex flex-col items-center gap-1 text-[10px] font-semibold transition ${isActive ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400'
            }`
          }
        >
          <Database className="h-5 w-5" />
          <span>Data Siswa</span>
        </NavLink>

        {/* Action Center Trigger */}
        <button
          type="button"
          onClick={() => navigate('/dashboard/students?action=add')}
          className="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-600 text-white shadow-md"
        >
          <Plus className="h-5 w-5" />
        </button>

        <button
          type="button"
          onClick={() => setNotificationsOpen(true)}
          className="flex flex-col items-center gap-1 text-[10px] font-semibold text-slate-400"
        >
          <Bell className="h-5 w-5" />
          <span>Notifikasi</span>
        </button>

        <NavLink
          to="/dashboard/pengaturan"
          className={({ isActive }) =>
            `flex flex-col items-center gap-1 text-[10px] font-semibold transition ${isActive ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400'
            }`
          }
        >
          <User className="h-5 w-5" />
          <span>Profil</span>
        </NavLink>
      </nav>

      {/* Floating Action Button (FAB) for Mobile Quick Add */}
      <FAB onClick={() => navigate('/dashboard/students?action=add')} label="Tambah Siswa" />
    </div>
  )
}
