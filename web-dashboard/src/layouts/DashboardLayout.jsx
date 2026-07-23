import { NavLink, Outlet, useLocation } from 'react-router-dom'
import {
  FaBars,
  FaBell,
  FaBookOpen,
  FaCalendarAlt,
  FaChalkboardTeacher,
  FaClipboardCheck,
  FaFileAlt,
  FaHome,
  FaListUl,
  FaSignOutAlt,
  FaThLarge,
  FaUserCircle,
  FaUserGraduate,
  FaUsers,
} from 'react-icons/fa'
import Swal from 'sweetalert2'
import { authService } from '../services/authService'
import { useAuthStore } from '../stores/authStore'
import { usePengaturanStore } from '../stores/pengaturanStore'
import { useUnitStore } from '../stores/unitStore'

const dataMasterMenus = [
  { to: '/dashboard/students/kelas', label: 'Kelas & Rombel', icon: FaChalkboardTeacher, end: true },
  { to: '/dashboard/academic', label: 'Mata Pelajaran', icon: FaThLarge, end: true },
  { to: '/dashboard/students/unit-pendidikan', label: 'Unit Pendidikan', icon: FaThLarge, end: true },
]

const aktivitasMenus = [
  { to: '/dashboard/attendance', label: 'Absensi', icon: FaClipboardCheck, end: true },
  { to: '/dashboard/tahfizh', label: 'Tahfizh & Mutabaah', icon: FaBookOpen, end: true },
  { to: '/dashboard/students/tugas', label: 'Penugasan Siswa', icon: FaListUl, end: true },
  { to: '/dashboard/academic', label: 'Materi Belajar', icon: FaBookOpen, end: true },
  { to: '/dashboard/students/laporan', label: 'Bank Soal', icon: FaFileAlt, end: true },
  { to: '/dashboard/students/rombel', label: 'Kisi-kisi Ujian', icon: FaFileAlt, end: true },
  { to: '/dashboard/students/catatan', label: 'Catatan Siswa', icon: FaFileAlt, end: true },
]

const laporanMenus = [
  { to: '/dashboard/laporan-siswa', label: 'Laporan & Statistik', icon: FaFileAlt, end: true },
  { to: '/dashboard/laporan-alumni', label: 'Alumni', icon: FaUsers, end: true },
]

const bottomMenus = [
  { to: '/dashboard', label: 'Dashboard', icon: FaHome },
  { to: '/dashboard/students', label: 'Siswa', icon: FaUserGraduate },
  { to: '/dashboard/attendance', label: 'Absensi', icon: FaClipboardCheck },
  { to: '/dashboard/tahfizh', label: 'Tahfizh', icon: FaBookOpen },
  { to: '/dashboard/laporan-siswa', label: 'Laporan', icon: FaFileAlt },
]

export default function DashboardLayout() {
  const location = useLocation()
  const user = useAuthStore((state) => state.user)
  const clearSession = useAuthStore((state) => state.clearSession)
  const activeUnit = useUnitStore((state) => state.activeUnit)
  const pengaturan = usePengaturanStore((state) => state.pengaturan)
  const namaTampil = user?.name || 'H. Ahmad Fauzi'
  const roleTampil = 'Kepala Sekolah'
  const tanggalTampil = new Intl.DateTimeFormat('id-ID', {
    weekday: 'long',
    day: '2-digit',
    month: 'long',
    year: 'numeric',
  }).format(new Date())

  const logout = async () => {
    try {
      await authService.logout()
    } catch {
      // Abaikan error logout API agar user tetap bisa keluar di sisi client.
    }

    clearSession()
    await Swal.fire('Sampai jumpa', 'Anda berhasil keluar dari sistem.', 'success')
    window.location.href = '/masuk'
  }

  return (
    <div className="grid min-h-screen md:grid-cols-[248px_1fr]">
      <aside className="hidden flex-col gap-2 overflow-hidden border-r border-white/10 bg-gradient-to-b from-emerald-800 via-emerald-900 to-emerald-950 p-3 text-white md:flex">
        <div className="flex items-center gap-3 border-b border-white/20 px-2 pb-3">
          <div className="h-14 w-14 rounded-2xl bg-gradient-to-br from-slate-100 to-emerald-100 p-1">
            {pengaturan.logoUrl ? (
              <img src={pengaturan.logoUrl} alt="Logo sekolah" className="h-full w-full rounded-xl object-cover" />
            ) : (
              <div className="grid h-full w-full place-content-center rounded-xl bg-white text-[11px] font-extrabold text-emerald-800">{pengaturan.logoTeks}</div>
            )}
          </div>
          <div>
            <p className="m-0 text-[10px] uppercase tracking-[0.08em] text-amber-200">{pengaturan.namaSekolah}</p>
            <h1 className="mt-1 text-sm font-semibold">Sekolah Islam Terpadu</h1>
          </div>
        </div>

        <nav className="grid gap-1">
          <NavLink
            to="/dashboard"
            end
            className={({ isActive }) => `flex items-center gap-2 rounded-lg px-3 py-2 text-sm transition ${isActive ? 'bg-white/20 text-white' : 'text-emerald-100 hover:bg-white/10 hover:text-white'}`}
          >
            <FaHome />
            <span>Dashboard</span>
          </NavLink>

          <p className="mt-2 px-2 text-[11px] uppercase tracking-[0.08em] text-emerald-100/80">Data Master</p>

          <div className="mt-1">
            <NavLink
              to="/dashboard/students"
              className={`flex items-center gap-2 rounded-lg px-3 py-2 text-sm transition ${
                location.pathname.startsWith('/dashboard/students') ? 'bg-white/20 text-white' : 'text-emerald-100 hover:bg-white/10 hover:text-white'
              }`}
            >
              <FaUserGraduate />
              <span>Siswa</span>
            </NavLink>
            <div className="mt-1 ml-7 grid gap-1 border-l border-white/20 pl-2">
              {[
                { to: '/dashboard/students', label: 'Data Seluruh Siswa', end: true },
                { to: '/dashboard/students/input', label: 'Tambah Siswa' },
                { to: '/dashboard/students/laporan', label: 'Import Siswa' },
              ].map((sub) => (
                <NavLink
                  key={sub.to}
                  to={sub.to}
                  end={sub.end}
                  className={({ isActive }) =>
                    `rounded-md px-2 py-1.5 text-xs transition ${
                      isActive ? 'bg-white/20 text-white' : 'text-emerald-100/90 hover:bg-white/10 hover:text-white'
                    }`
                  }
                >
                  {sub.label}
                </NavLink>
              ))}
            </div>
          </div>

          {dataMasterMenus.map(({ to, label, icon: Icon, end }) => (
            <NavLink
              key={`${to}-${label}`}
              to={to}
              end={end}
              className={({ isActive }) => `flex items-center gap-2 rounded-lg px-3 py-2 text-sm transition ${isActive ? 'bg-white/20 text-white' : 'text-emerald-100 hover:bg-white/10 hover:text-white'}`}
            >
              <Icon />
              <span>{label}</span>
            </NavLink>
          ))}

          <p className="mt-2 px-2 text-[11px] uppercase tracking-[0.08em] text-emerald-100/80">Aktivitas</p>
          {aktivitasMenus.map(({ to, label, icon: Icon, end }) => (
            <NavLink
              key={`${to}-${label}`}
              to={to}
              end={end}
              className={({ isActive }) => `flex items-center gap-2 rounded-lg px-3 py-2 text-sm transition ${isActive ? 'bg-white/20 text-white' : 'text-emerald-100 hover:bg-white/10 hover:text-white'}`}
            >
              <Icon />
              <span>{label}</span>
            </NavLink>
          ))}

          <p className="mt-2 px-2 text-[11px] uppercase tracking-[0.08em] text-emerald-100/80">Laporan</p>
          {laporanMenus.map(({ to, label, icon: Icon, end }) => (
            <NavLink
              key={`${to}-${label}`}
              to={to}
              end={end}
              className={({ isActive }) => `flex items-center gap-2 rounded-lg px-3 py-2 text-sm transition ${isActive ? 'bg-white/20 text-white' : 'text-emerald-100 hover:bg-white/10 hover:text-white'}`}
            >
              <Icon />
              <span>{label}</span>
            </NavLink>
          ))}
        </nav>
      </aside>

      <main className="px-3 pb-24 pt-3 md:px-4 md:pb-4">
        <header className="flex min-h-[72px] items-center justify-between rounded-2xl border border-amber-200 bg-amber-50/70 px-4 py-3">
          <div className="flex items-center gap-3">
            <button type="button" className="grid h-10 w-10 place-content-center rounded-xl border border-amber-200 bg-white text-emerald-900 md:hidden" aria-label="Buka menu">
              <FaBars />
            </button>
            <div>
              <p className="text-[11px] uppercase tracking-[0.08em] text-emerald-800">{pengaturan.namaSekolah}</p>
              <h2 className="text-base font-semibold text-emerald-950">{pengaturan.namaDashboard}</h2>
              <small className="text-xs text-emerald-800/80">Unit Aktif {activeUnit} - Sekolah Islam Terpadu</small>
            </div>
          </div>

          <div className="hidden items-center gap-2 md:flex">
            <div className="flex items-center gap-2 rounded-lg border border-amber-200 bg-white px-3 py-2 text-xs text-emerald-900">
              <FaCalendarAlt />
              <span>{tanggalTampil}</span>
            </div>

            <div className="relative grid h-9 w-9 place-content-center rounded-lg border border-amber-200 bg-white text-emerald-900">
              <FaBell />
              <small className="absolute -right-1 -top-1 grid h-4 w-4 place-content-center rounded-full bg-red-500 text-[10px] font-bold text-white">3</small>
            </div>

            <div className="flex items-center gap-2 rounded-lg border border-amber-200 bg-white px-2 py-1">
              <FaUserCircle className="text-2xl text-emerald-900" />
              <div>
                <p className="text-xs font-semibold text-emerald-900">{namaTampil}</p>
                <small className="text-[11px] text-emerald-800/70">{roleTampil}</small>
              </div>
            </div>

            <button
              className="group relative inline-flex h-10 w-10 items-center justify-center rounded-xl border border-rose-200 bg-rose-50 text-rose-600 shadow-sm transition hover:-translate-y-0.5 hover:bg-rose-100 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-300"
              type="button"
              onClick={logout}
              title="Logout"
              aria-label="Logout"
            >
              <FaSignOutAlt />
              <span className="pointer-events-none absolute -bottom-9 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-[11px] font-semibold text-white opacity-0 shadow transition group-hover:opacity-100 group-focus-within:opacity-100">
                Logout
              </span>
            </button>
          </div>
        </header>
        <Outlet />
      </main>

      <nav className="fixed bottom-0 left-0 right-0 z-50 grid grid-cols-5 gap-1 border-t border-white/20 bg-emerald-950/95 p-2 md:hidden" aria-label="Navigasi bawah">
        {bottomMenus.map(({ to, label, icon: Icon }) => (
          <NavLink
            key={`bottom-${to}-${label}`}
            to={to}
            title={label}
            aria-label={label}
            className={({ isActive }) => `grid justify-items-center gap-1 rounded-lg px-1 py-2 text-[11px] transition ${isActive ? 'bg-white/20 text-white' : 'text-emerald-100 hover:bg-white/10'}`}
          >
            <Icon className="text-base" />
            <span>{label}</span>
          </NavLink>
        ))}
      </nav>
    </div>
  )
}
