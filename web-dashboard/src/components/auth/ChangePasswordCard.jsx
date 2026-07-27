import { useState } from 'react'
import { FiLock, FiEye, FiEyeOff, FiClock, FiCheckCircle } from 'react-icons/fi'

export default function ChangePasswordCard() {
  const [form, setForm] = useState({
    oldPassword: '',
    newPassword: '',
    confirmPassword: '',
  })
  const [showOld, setShowOld] = useState(false)
  const [showNew, setShowNew] = useState(false)
  const [showConfirm, setShowConfirm] = useState(false)
  const [updated, setUpdated] = useState(false)

  const handleSubmit = (e) => {
    e.preventDefault()
    if (form.newPassword !== form.confirmPassword) {
      alert('Konfirmasi password baru tidak cocok!')
      return
    }
    setUpdated(true)
    setTimeout(() => {
      setUpdated(false)
      setForm({ oldPassword: '', newPassword: '', confirmPassword: '' })
    }, 2000)
  }

  return (
    <div className="w-full bg-slate-50/60 rounded-2xl p-4 lg:p-6">
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
        {/* Left Form: Ubah Password */}
        <div className="lg:col-span-8 bg-white p-6 rounded-2xl shadow-sm border border-slate-200/80">
          <div className="border-b border-slate-100 pb-4 mb-6">
            <h2 className="text-xl font-bold text-slate-800">Ubah Password</h2>
            <p className="text-xs text-slate-500 mt-1">
              Pastikan password baru Anda kuat dan mudah diingat.
            </p>
          </div>

          {updated && (
            <div className="mb-5 bg-emerald-50 border border-emerald-200 rounded-xl p-3.5 text-emerald-800 text-xs font-semibold flex items-center gap-2">
              <FiCheckCircle className="w-4 h-4 text-emerald-600 shrink-0" />
              <span>Password Anda telah berhasil diperbarui!</span>
            </div>
          )}

          <form onSubmit={handleSubmit} className="space-y-4">
            {/* Password Lama */}
            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1">
                Password Lama
              </label>
              <div className="relative">
                <input
                  type={showOld ? 'text' : 'password'}
                  value={form.oldPassword}
                  onChange={(e) => setForm({ ...form, oldPassword: e.target.value })}
                  placeholder="••••••••••••"
                  className="w-full px-3.5 py-2.5 bg-white text-slate-800 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:border-transparent transition-all shadow-sm pr-10"
                  required
                />
                <button
                  type="button"
                  onClick={() => setShowOld(!showOld)}
                  className="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600"
                >
                  {showOld ? <FiEyeOff className="w-4 h-4" /> : <FiEye className="w-4 h-4" />}
                </button>
              </div>
            </div>

            {/* Password Baru */}
            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1">
                Password Baru
              </label>
              <div className="relative">
                <input
                  type={showNew ? 'text' : 'password'}
                  value={form.newPassword}
                  onChange={(e) => setForm({ ...form, newPassword: e.target.value })}
                  placeholder="••••••••••••"
                  className="w-full px-3.5 py-2.5 bg-white text-slate-800 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:border-transparent transition-all shadow-sm pr-10"
                  required
                />
                <button
                  type="button"
                  onClick={() => setShowNew(!showNew)}
                  className="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600"
                >
                  {showNew ? <FiEyeOff className="w-4 h-4" /> : <FiEye className="w-4 h-4" />}
                </button>
              </div>
            </div>

            {/* Konfirmasi Password Baru */}
            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1">
                Konfirmasi Password Baru
              </label>
              <div className="relative">
                <input
                  type={showConfirm ? 'text' : 'password'}
                  value={form.confirmPassword}
                  onChange={(e) => setForm({ ...form, confirmPassword: e.target.value })}
                  placeholder="••••••••••••"
                  className="w-full px-3.5 py-2.5 bg-white text-slate-800 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:border-transparent transition-all shadow-sm pr-10"
                  required
                />
                <button
                  type="button"
                  onClick={() => setShowConfirm(!showConfirm)}
                  className="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600"
                >
                  {showConfirm ? <FiEyeOff className="w-4 h-4" /> : <FiEye className="w-4 h-4" />}
                </button>
              </div>
            </div>

            <div className="pt-2">
              <button
                type="submit"
                className="py-2.5 px-6 bg-emerald-800 hover:bg-emerald-900 text-white font-semibold text-sm rounded-xl shadow-md shadow-emerald-800/20 hover:shadow-lg transition-all"
              >
                Update Password
              </button>
            </div>
          </form>
        </div>

        {/* Right Card: Riwayat Perubahan */}
        <div className="lg:col-span-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-200/80 flex flex-col justify-between">
          <div>
            <h3 className="text-sm font-bold text-slate-800 border-b border-slate-100 pb-3 mb-4 flex items-center gap-2">
              <FiClock className="text-emerald-700" />
              <span>Riwayat Perubahan</span>
            </h3>

            <div className="space-y-4">
              <div className="flex items-start gap-3 text-xs border-l-2 border-emerald-600 pl-3 py-1">
                <div className="space-y-0.5">
                  <p className="font-mono text-slate-700 tracking-wider">••••••••</p>
                  <p className="text-[11px] text-slate-400">28 Mei 2024 10:32</p>
                </div>
              </div>

              <div className="flex items-start gap-3 text-xs border-l-2 border-slate-200 pl-3 py-1">
                <div className="space-y-0.5">
                  <p className="font-mono text-slate-500 tracking-wider">••••••••</p>
                  <p className="text-[11px] text-slate-400">15 Apr 2024 09:15</p>
                </div>
              </div>

              <div className="flex items-start gap-3 text-xs border-l-2 border-slate-200 pl-3 py-1">
                <div className="space-y-0.5">
                  <p className="font-mono text-slate-500 tracking-wider">••••••••</p>
                  <p className="text-[11px] text-slate-400">10 Mar 2024 14:22</p>
                </div>
              </div>
            </div>
          </div>

          <div className="mt-6 pt-4 border-t border-slate-100 text-[11px] text-slate-500 bg-emerald-50/50 rounded-xl p-3 border border-emerald-100">
            <span className="font-medium text-slate-700 block">Informasi Keamanan:</span>
            Password terakhir diubah pada <strong>28 Mei 2024 10:32</strong>.
          </div>
        </div>
      </div>
    </div>
  )
}
