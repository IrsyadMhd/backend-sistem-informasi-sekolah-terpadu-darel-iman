import { useNavigate } from 'react-router-dom'
import Swal from 'sweetalert2'
import { FaMosque } from 'react-icons/fa6'
import { usePengaturanStore } from '../stores/pengaturanStore'

import LoginCard from '../components/auth/LoginCard'

export default function LoginPage() {
  const navigate = useNavigate()
  const pengaturan = usePengaturanStore((state) => state.pengaturan)

  const namaSekolah = pengaturan?.namaSekolah || 'YAYASAN DAR EL - IMAN'

  const handleLoginSuccess = () => {
    Swal.fire({
      title: 'Login Berhasil!',
      text: `Selamat datang di Sistem Manajemen ${namaSekolah}.`,
      icon: 'success',
      confirmButtonColor: '#065f46',
      timer: 1800,
      showConfirmButton: false,
    }).then(() => {
      navigate('/dashboard', { replace: true })
    })
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-100 via-emerald-50/30 to-slate-100 flex flex-col font-sans relative">
      {/* Background pattern */}
      <div
        className="absolute inset-0 pointer-events-none"
        style={{
          backgroundImage: `radial-gradient(circle at 15% 10%, rgba(16,185,129,0.10) 0%, transparent 40%), radial-gradient(circle at 85% 90%, rgba(208,139,47,0.10) 0%, transparent 40%)`,
        }}
      />

      {/* Top Info Bar */}
      <div className="relative z-10 border-b border-emerald-200/60 bg-white/80 backdrop-blur-sm shadow-xs">
        <div className="max-w-5xl mx-auto px-4 py-2.5 flex items-center gap-2">
          {pengaturan?.logoUrl ? (
            <img
              src={pengaturan.logoUrl}
              alt="Logo"
              className="w-6 h-6 rounded object-contain"
            />
          ) : (
            <div className="w-6 h-6 rounded-lg bg-emerald-800 text-amber-300 flex items-center justify-center">
              <FaMosque className="w-3.5 h-3.5" />
            </div>
          )}
          <span className="text-xs font-bold text-emerald-900 tracking-tight">
            {namaSekolah}
          </span>
          <span className="hidden sm:block text-xs text-slate-400">•</span>
          <span className="hidden sm:block text-xs text-slate-500 font-medium">
            Sistem Manajemen Sekolah Islam Terpadu
          </span>
        </div>
      </div>

      {/* Main Login Area */}
      <div className="relative z-10 flex-1 flex items-center justify-center px-4 py-12">
        <div className="w-full max-w-md">
          <LoginCard onLoginSuccess={handleLoginSuccess} />
        </div>
      </div>

      {/* Footer */}
      <div className="relative z-10 text-center py-4 text-[11px] text-slate-400 border-t border-slate-200/60 bg-white/60">
        © {new Date().getFullYear()} {namaSekolah} — Sistem Manajemen Sekolah Islam Terpadu. All rights reserved. <span className="font-mono text-emerald-700 font-medium ml-1">Ver 1.0.0</span>
      </div>
    </div>
  )
}
