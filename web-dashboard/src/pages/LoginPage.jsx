import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import Swal from 'sweetalert2'
import { authService } from '../services/authService'
import { useAuthStore } from '../stores/authStore'

export default function LoginPage() {
  const navigate = useNavigate()
  const setSession = useAuthStore((state) => state.setSession)
  const [form, setForm] = useState({
    email: 'superadmin@school-erp.local',
    password: 'Password123!',
    device_name: 'web-dashboard',
  })
  const [loading, setLoading] = useState(false)

  const submitLogin = async (event) => {
    event.preventDefault()
    setLoading(true)

    try {
      const hasilLogin = await authService.login(form)
      setSession({ token: hasilLogin.token, user: hasilLogin.user || null })

      if (!hasilLogin.user) {
        const profil = await authService.profile()
        setSession({ token: hasilLogin.token, user: profil?.data || profil || null })
      }

      await Swal.fire('Berhasil', 'Login berhasil, selamat datang.', 'success')
      navigate('/', { replace: true })
    } catch (error) {
      await Swal.fire(
        'Login gagal',
        error?.response?.data?.message || 'Email atau password tidak valid.',
        'error'
      )
    } finally {
      setLoading(false)
    }
  }

  return (
    <section className="login-shell">
      <div className="login-card">
        <p className="showcase-badge">AKSES DASHBOARD</p>
        <h2>Masuk ke Sistem Informasi Sekolah</h2>
        <p>Gunakan akun admin untuk membuka modul monitoring dan CRUD dashboard.</p>

        <form className="login-form" onSubmit={submitLogin}>
          <label htmlFor="email">Email</label>
          <input
            id="email"
            type="email"
            value={form.email}
            onChange={(event) => setForm((prev) => ({ ...prev, email: event.target.value }))}
            required
          />

          <label htmlFor="password">Password</label>
          <input
            id="password"
            type="password"
            value={form.password}
            onChange={(event) => setForm((prev) => ({ ...prev, password: event.target.value }))}
            required
          />

          <button className="topbar-action" type="submit" disabled={loading}>
            {loading ? 'Memproses...' : 'Masuk'}
          </button>
        </form>

        <div className="login-hint">
          <strong>Akun default seeder:</strong>
          <span>superadmin@school-erp.local / Password123!</span>
        </div>
      </div>
    </section>
  )
}
