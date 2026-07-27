import { useState } from 'react'
import { FiMail, FiArrowLeft, FiLock, FiCheck } from 'react-icons/fi'

export default function ForgotPasswordCard({ onNavigate }) {
  const [email, setEmail] = useState('')
  const [submitted, setSubmitted] = useState(false)

  const handleSubmit = (e) => {
    e.preventDefault()
    setSubmitted(true)
    setTimeout(() => {
      if (onNavigate) onNavigate(3) // Navigate to Reset Password
    }, 1000)
  }

  return (
    <div className="w-full max-w-4xl mx-auto bg-white rounded-2xl shadow-xl overflow-hidden border border-emerald-100 p-8 lg:p-10">
      {/* Stepper */}
      <div className="flex items-center justify-center max-w-md mx-auto mb-10">
        <div className="flex items-center text-xs font-semibold">
          <div className="w-8 h-8 rounded-full bg-emerald-700 text-white flex items-center justify-center font-bold shadow-md shadow-emerald-700/30 ring-4 ring-emerald-100">
            1
          </div>
          <span className="ml-2 text-emerald-800 font-bold">Email</span>
        </div>

        <div className="w-16 h-0.5 bg-slate-200 mx-3"></div>

        <div className="flex items-center text-xs font-medium text-slate-400">
          <div className="w-8 h-8 rounded-full bg-slate-100 border border-slate-300 flex items-center justify-center">
            2
          </div>
          <span className="ml-2">Verifikasi</span>
        </div>

        <div className="w-16 h-0.5 bg-slate-200 mx-3"></div>

        <div className="flex items-center text-xs font-medium text-slate-400">
          <div className="w-8 h-8 rounded-full bg-slate-100 border border-slate-300 flex items-center justify-center">
            3
          </div>
          <span className="ml-2">Selesai</span>
        </div>
      </div>

      {/* Main Content Grid */}
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
        {/* Form Column */}
        <div className="lg:col-span-7 space-y-5">
          <div>
            <h2 className="text-2xl font-bold text-slate-800">Lupa Password</h2>
            <p className="text-sm text-slate-500 mt-1">
              Masukkan email Anda, kami akan mengirimkan kode verifikasi.
            </p>
          </div>

          {submitted ? (
            <div className="bg-emerald-50 border border-emerald-200 rounded-xl p-4 text-emerald-800 text-sm flex items-center gap-3">
              <FiCheck className="w-5 h-5 text-emerald-600 shrink-0" />
              <span>
                Kode verifikasi telah dikirim ke email <strong>{email}</strong>. Pengalihan...
              </span>
            </div>
          ) : (
            <form onSubmit={handleSubmit} className="space-y-4">
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                  Email
                </label>
                <div className="relative">
                  <div className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <FiMail className="w-4 h-4" />
                  </div>
                  <input
                    type="email"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    placeholder="Masukkan email Anda"
                    className="w-full pl-10 pr-4 py-2.5 bg-white text-slate-800 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:border-transparent transition-all shadow-sm"
                    required
                  />
                </div>
              </div>

              <button
                type="submit"
                className="w-full py-2.5 px-4 bg-emerald-800 hover:bg-emerald-900 text-white font-semibold text-sm rounded-xl shadow-md shadow-emerald-800/20 hover:shadow-lg transition-all"
              >
                Kirim Kode Verifikasi
              </button>
            </form>
          )}

          <div className="pt-2">
            <button
              type="button"
              onClick={() => onNavigate && onNavigate(1)}
              className="inline-flex items-center gap-2 text-xs font-semibold text-emerald-700 hover:text-emerald-900 transition-colors"
            >
              <FiArrowLeft className="w-4 h-4" />
              <span>Kembali ke halaman login</span>
            </button>
          </div>
        </div>

        {/* Illustration Column */}
        <div className="lg:col-span-5 flex justify-center">
          <div className="w-60 h-60 bg-gradient-to-tr from-emerald-50 to-teal-50 rounded-full border border-emerald-100 p-6 flex flex-col items-center justify-center relative shadow-inner">
            <div className="w-24 h-20 bg-white rounded-2xl shadow-xl border border-emerald-100 flex flex-col items-center justify-center relative group">
              <div className="w-10 h-10 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center mb-1">
                <FiLock className="w-5 h-5" />
              </div>
              <div className="w-12 h-1.5 bg-emerald-200 rounded-full" />
            </div>
            <div className="absolute top-8 right-8 w-8 h-8 rounded-full bg-emerald-700 text-amber-300 flex items-center justify-center text-xs font-bold shadow-md">
              <FiMail />
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}
