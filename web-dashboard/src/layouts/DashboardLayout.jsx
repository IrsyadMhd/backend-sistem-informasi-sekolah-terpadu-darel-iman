import { NavLink, Outlet } from 'react-router-dom'
import {
  FaBars,
  FaBell,
  FaBookOpen,
  FaBullhorn,
  FaCalendarAlt,
  FaChartLine,
  FaChalkboardTeacher,
  FaCog,
  FaClipboardCheck,
  FaClipboardList,
  FaHome,
  FaMosque,
  FaUserCircle,
  FaUserGraduate,
} from 'react-icons/fa'
import Swal from 'sweetalert2'
import { authService } from '../services/authService'
import { useAuthStore } from '../stores/authStore'
import { dashboardUnits, useUnitStore } from '../stores/unitStore'

const menus = [
  { to: '/', label: 'Dashboard', icon: FaHome },
  { to: '/attendance', label: 'Monitoring Kehadiran', icon: FaClipboardCheck },
  { to: '/attendance', label: 'Monitoring Divisi', icon: FaClipboardList },
  { to: '/academic', label: 'Laporan Bulanan', icon: FaBookOpen },
  { to: '/students', label: 'Prestasi Siswa', icon: FaChartLine },
  { to: '/tahfizh', label: 'Tahfizh', icon: FaMosque },
  { to: '/tahfizh', label: 'Mutabaah & Ibadah', icon: FaBell },
  { to: '/students', label: 'Data Siswa', icon: FaUserGraduate },
  { label: 'Guru & Staf', icon: FaChalkboardTeacher, static: true },
  { to: '/notifications', label: 'Pengumuman', icon: FaBullhorn },
  { label: 'Pengaturan', icon: FaCog, static: true },
]

export default function DashboardLayout() {
  const user = useAuthStore((state) => state.user)
  const clearSession = useAuthStore((state) => state.clearSession)
  const activeUnit = useUnitStore((state) => state.activeUnit)
  const setActiveUnit = useUnitStore((state) => state.setActiveUnit)
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
    <div className="app-shell">
      <aside className="sidebar">
        <div className="sidebar-brand">
          <div className="sidebar-logo-wrap">
            <div className="sidebar-logo">SDIT</div>
          </div>
          <div>
            <p className="brand-kicker">SDIT DAR EL-IMAN</p>
            <h1 className="brand-title">Sekolah Islam Terpadu</h1>
          </div>
        </div>

        <p className="sidebar-section-title">Menu Utama</p>
        <nav className="menu-list">
          {menus.map(({ to, label, icon: Icon, static: isStatic }) => (
            isStatic ? (
              <div key={label} className="menu-item menu-item-static">
                <Icon />
                <span>{label}</span>
              </div>
            ) : (
              <NavLink key={`${to}-${label}`} to={to} className={({ isActive }) => `menu-item ${isActive ? 'active' : ''}`}>
                <Icon />
                <span>{label}</span>
              </NavLink>
            )
          ))}
        </nav>

        <div className="sidebar-access-panel">
          <p>Pilih Unit</p>
          <strong>Unit Aktif: {activeUnit}</strong>
          <div className="unit-switcher">
            {dashboardUnits.map((unit) => (
              <button
                key={unit}
                type="button"
                className={`unit-chip ${activeUnit === unit ? 'active' : ''}`}
                onClick={() => setActiveUnit(unit)}
              >
                {unit}
              </button>
            ))}
          </div>
        </div>

        <div className="sidebar-quote-panel">
          <p>Visi Kami</p>
          <strong>Membentuk Generasi Islami, Berprestasi, dan Berakhlak Mulia</strong>
        </div>
      </aside>

      <main className="main-panel">
        <header className="topbar">
          <div className="topbar-left-wrap">
            <button type="button" className="topbar-menu-btn" aria-label="Buka menu">
              <FaBars />
            </button>
            <div>
              <p className="topbar-label">SDIT DAR EL-IMAN</p>
              <h2>Dashboard Monitoring Kepala Sekolah</h2>
              <small className="topbar-user">Unit Aktif {activeUnit} - Sekolah Islam Terpadu</small>
            </div>
          </div>

          <div className="topbar-right-wrap">
            <div className="topbar-date-chip">
              <FaCalendarAlt />
              <span>{tanggalTampil}</span>
            </div>

            <div className="topbar-notif-dot">
              <FaBell />
              <small>3</small>
            </div>

            <div className="topbar-profile-wrap">
              <FaUserCircle className="topbar-profile-icon" />
              <div>
                <p className="topbar-profile-name">{namaTampil}</p>
                <small className="topbar-profile-role">{roleTampil}</small>
              </div>
            </div>

            <button className="topbar-action" type="button" onClick={logout}>
              Logout
            </button>
          </div>
        </header>
        <Outlet />
      </main>
    </div>
  )
}
