import { useState } from 'react'
import { FiLock, FiEye, FiEyeOff, FiShield, FiCheck } from 'react-icons/fi'

export default function ResetPasswordCard({ onNavigate }) {
  const [password, setPassword] = useState('')
  const [confirmPassword, setConfirmPassword] = useState('')
  const [showPassword, setShowPassword] = useState(false)
  const [showConfirm, setShowConfirm] = useState(false)
  const [success, setSuccess] = useState(false)

  // Calculate password strength
  const getStrength = (pass) => {
    if (!pass) return { level: 0, label: 'Kosong', color: 'bg-slate-200' }
    if (pass.length < 6) return { level: 1, label: 'Lemah', color: 'bg-red-500' }
    if (pass.length < 10) return { level: 2, label: 'Sedang', color: 'bg-amber-500' }
    return { level: 3, label: 'Kuat', color: 'bg-emerald-600' }
  }

  const strength = getStrength(password)

  const handleSubmit = (e) => {
    e.preventDefault()
    if (password !== confirmPassword) {
      alert('Konfirmasi password tidak cocok!')
      return
    }
    setSuccess(true)
    setTimeout(() => {
      if (onNavigate) onNavigate(1) // Navigate back to Login
    }, 1200)
  }

  return (
    <div className="w-full max-w-4xl mx-auto bg-white rounded-2xl shadow-xl overflow-hidden border border-emerald-100 p-8 lg:p-10">
      {/* Stepper */}
      <div className="flex items-center justify-center max-w-md mx-auto mb-10">
        <div className="flex items-center text-xs font-semibold text-emerald-800">
          <div className="w-8 h-8 rounded-full bg-emerald-100 text-emerald-800 border border-emerald-300 flex items-center justify-center font-bold">
            <FiCheck className="w-4 h-4 text-emerald-700" />
          </div>
          <span className="ml-2 font-medium">Verifikasi</span>
        </div>

        <div className="w-16 h-0.5 bg-emerald-600 mx-3"></div>

        <div className="flex items-center text-xs font-semibold">
          <div className="w-8 h-8 rounded-full bg-emerald-700 text-white flex items-center justify-center font-bold shadow-md shadow-emerald-700/30 ring-4 ring-emerald-100">
            2
          </div>
          <span className="ml-2 text-emerald-800 font-bold">Reset Password</span>
        </div>

        <div className="w-16 h-0.5 bg-slate-200 mx-3"></div>

        <div className="flex items-center text-xs font-medium text-slate-400">
          <div className="w-8 h-8 rounded-full bg-slate-100 border border-slate-300 flex items-center justify-center">
            3
          </div>
          <span className="ml-2">Selesai</span>
        </div>
      </div>

      {/* Main Grid */}
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
        {/* Form */}
        <div className="lg:col-span-7 space-y-5">
          <div>
            <h2 className="text-2xl font-bold text-slate-800">Reset Password</h2>
            <p className="text-sm text-slate-500 mt-1">
              Buat password baru untuk akun Anda.
            </p>
          </div>

          {success ? (
            <div className="bg-emerald-50 border border-emerald-200 rounded-xl p-4 text-emerald-800 text-sm flex items-center gap-3">
              <FiCheck className="w-5 h-5 text-emerald-600 shrink-0" />
              <span>
                Password berhasil diperbarui! Mengalihkan ke halaman login...
              </span>
            </div>
          ) : (
            <form onSubmit={handleSubmit} className="space-y-4">
              {/* Password Baru */}
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                  Password Baru
                </label>
                <div className="relative">
                  <input
                    type={showPassword ? 'text' : 'password'}
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    placeholder="••••••••••••"
                    className="w-full px-4 py-2.5 bg-white text-slate-800 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:border-transparent transition-all shadow-sm pr-10"
                    required
                  />
                  <button
                    type="button"
                    onClick={() => setShowPassword(!showPassword)}
                    className="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600"
                  >
                    {showPassword ? (
                      <FiEyeOff className="w-4 h-4" />
                    ) : (
                      <FiEye className="w-4 h-4" />
                    )}
                  </button>
                </div>
              </div>

              {/* Konfirmasi Password */}
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                  Konfirmasi Password
                </label>
                <div className="relative">
                  <input
                    type={showConfirm ? 'text' : 'password'}
                    value={confirmPassword}
                    onChange={(e) => setConfirmPassword(e.target.value)}
                    placeholder="••••••••••••"
                    className="w-full px-4 py-2.5 bg-white text-slate-800 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:border-transparent transition-all shadow-sm pr-10"
                    required
                  />
                  <button
                    type="button"
                    onClick={() => setShowConfirm(!showConfirm)}
                    className="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600"
                  >
                    {showConfirm ? (
                      <FiEyeOff className="w-4 h-4" />
                    ) : (
                      <FiEye className="w-4 h-4" />
                    )}
                  </button>
                </div>
              </div>

              {/* Strength Meter */}
              <div className="space-y-1.5">
                <div className="flex justify-between items-center text-xs">
                  <span className="text-slate-600 font-medium">
                    Kekuatan Password
                  </span>
                  <span className="font-bold text-emerald-700">
                    {strength.label}
                  </span>
                </div>
                <div className="grid grid-cols-3 gap-1.5 h-2">
                  <div
                    className={`h-full rounded-full transition-all ${
                      strength.level >= 1 ? strength.color : 'bg-slate-200'
                    }`}
                  />
                  <div
                    className={`h-full rounded-full transition-all ${
                      strength.level >= 2 ? strength.color : 'bg-slate-200'
                    }`}
                  />
                  <div
                    className={`h-full rounded-full transition-all ${
                      strength.level >= 3 ? strength.color : 'bg-slate-200'
                    }`}
                  />
                </div>
              </div>

              {/* Submit */}
              <button
                type="submit"
                className="w-full py-2.5 px-4 bg-emerald-800 hover:bg-emerald-900 text-white font-semibold text-sm rounded-xl shadow-md shadow-emerald-800/20 hover:shadow-lg transition-all"
              >
                Reset Password
              </button>
            </form>
          )}
        </div>

        {/* Illustration */}
        <div className="lg:col-span-5 flex justify-center">
          <div className="w-56 h-56 bg-emerald-50/70 rounded-full border border-emerald-100 flex flex-col items-center justify-center relative shadow-inner p-4">
            <div className="w-24 h-28 bg-emerald-800 rounded-2xl shadow-xl text-white flex flex-col items-center justify-center relative border-2 border-emerald-600">
              <FiShield className="w-12 h-12 text-amber-400" />
              <FiLock className="w-5 h-5 absolute bottom-4 text-emerald-200" />
            </div>
            <div className="mt-3 flex gap-1">
              <span className="w-2 h-2 rounded-full bg-emerald-600" />
              <span className="w-2 h-2 rounded-full bg-emerald-600" />
              <span className="w-2 h-2 rounded-full bg-amber-400" />
              <span className="w-2 h-2 rounded-full bg-emerald-600" />
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}
