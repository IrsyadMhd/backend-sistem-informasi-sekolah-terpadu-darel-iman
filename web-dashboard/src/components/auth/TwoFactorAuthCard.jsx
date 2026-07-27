import { useState, useRef } from 'react'
import { FiSmartphone, FiInfo, FiExternalLink, FiLock, FiCheck } from 'react-icons/fi'
import { BsShieldCheck } from 'react-icons/bs'

export default function TwoFactorAuthCard() {
  const [activeTab, setActiveTab] = useState('Ringkasan')
  const [otp, setOtp] = useState(['1', '2', '3', '4', '5', '6'])
  const [verified, setVerified] = useState(false)
  const inputRefs = useRef([])

  const handleOtpChange = (index, value) => {
    if (value.length > 1) return
    const newOtp = [...otp]
    newOtp[index] = value
    setOtp(newOtp)

    if (value && index < 5) {
      inputRefs.current[index + 1]?.focus()
    }
  }

  const handleKeyDown = (index, e) => {
    if (e.key === 'Backspace' && !otp[index] && index > 0) {
      inputRefs.current[index - 1]?.focus()
    }
  }

  const handleVerify = () => {
    setVerified(true)
    setTimeout(() => setVerified(false), 2500)
  }

  const tabs = ['Ringkasan', 'Setup Authenticator', 'Recovery Code', 'Trusted Device', 'Pengaturan']

  return (
    <div className="w-full max-w-6xl mx-auto bg-white rounded-2xl shadow-xl overflow-hidden border border-emerald-100 p-6 lg:p-8 space-y-6">
      {/* Tabs */}
      <div className="flex overflow-x-auto border-b border-slate-200 gap-1 pb-1">
        {tabs.map((tab) => (
          <button
            key={tab}
            onClick={() => setActiveTab(tab)}
            className={`py-2 px-4 text-xs font-bold rounded-t-xl transition-all whitespace-nowrap ${
              activeTab === tab
                ? 'bg-emerald-50 text-emerald-800 border-b-2 border-emerald-700'
                : 'text-slate-500 hover:text-slate-800 hover:bg-slate-50'
            }`}
          >
            {tab}
          </button>
        ))}
      </div>

      {/* 3 Columns Grid */}
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-stretch">
        {/* Card 1: Status 2FA */}
        <div className="lg:col-span-3 bg-slate-50/80 p-6 rounded-2xl border border-slate-200/80 flex flex-col items-center justify-between text-center">
          <div className="space-y-3">
            <span className="text-xs font-bold text-slate-500 uppercase tracking-wider block">
              Status 2FA
            </span>
            <div className="w-20 h-20 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center mx-auto shadow-inner border border-emerald-200">
              <BsShieldCheck className="w-10 h-10" />
            </div>
            <div>
              <h4 className="text-base font-extrabold text-emerald-800">2FA Aktif</h4>
              <p className="text-xs text-slate-500 mt-1 max-w-[180px] mx-auto">
                Akun Anda dilindungi dengan Two Factor Authentication.
              </p>
            </div>
          </div>

          <button
            type="button"
            className="w-full py-2 px-3 mt-6 bg-white hover:bg-red-50 text-red-600 border border-red-200 hover:border-red-300 rounded-xl text-xs font-semibold transition-all shadow-xs"
          >
            Nonaktifkan 2FA
          </button>
        </div>

        {/* Card 2: Metode 2FA & Verifikasi */}
        <div className="lg:col-span-5 bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs flex flex-col justify-between space-y-5">
          <div className="space-y-4">
            <div className="border-b border-slate-100 pb-3">
              <span className="text-xs font-bold text-slate-400 uppercase tracking-wider block">
                Metode 2FA
              </span>
              <div className="flex items-center gap-2 mt-1">
                <FiSmartphone className="w-5 h-5 text-emerald-700" />
                <h4 className="text-sm font-bold text-slate-800">
                  Authenticator App (Google Authenticator)
                </h4>
              </div>
              <p className="text-[11px] text-slate-400 mt-1">
                Dibuat Pada: 15 Mei 2024 09:15
              </p>
            </div>

            <div>
              <h4 className="text-sm font-bold text-slate-800">Verifikasi 2FA</h4>
              <p className="text-xs text-slate-500 mt-0.5">
                Masukkan kode 6 digit dari aplikasi authenticator Anda.
              </p>
            </div>

            {verified && (
              <div className="bg-emerald-50 border border-emerald-200 rounded-xl p-2.5 text-emerald-800 text-xs font-semibold flex items-center gap-2">
                <FiCheck className="w-4 h-4 text-emerald-600 shrink-0" />
                <span>Kode 2FA berhasil diverifikasi!</span>
              </div>
            )}

            {/* OTP Input Boxes */}
            <div className="flex justify-between gap-2 my-2">
              {otp.map((digit, index) => (
                <input
                  key={index}
                  ref={(el) => (inputRefs.current[index] = el)}
                  type="text"
                  maxLength={1}
                  value={digit}
                  onChange={(e) => handleOtpChange(index, e.target.value)}
                  onKeyDown={(e) => handleKeyDown(index, e)}
                  className="w-10 h-12 text-center text-lg font-bold text-slate-800 bg-slate-50 rounded-xl border border-slate-200 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:border-transparent transition-all shadow-xs"
                />
              ))}
            </div>
          </div>

          <button
            type="button"
            onClick={handleVerify}
            className="w-full py-2.5 px-4 bg-emerald-800 hover:bg-emerald-900 text-white font-semibold text-xs rounded-xl shadow-md shadow-emerald-800/20 hover:shadow-lg transition-all"
          >
            Verifikasi
          </button>
        </div>

        {/* Card 3: Informasi */}
        <div className="lg:col-span-4 bg-gradient-to-br from-emerald-50/50 to-teal-50/30 p-6 rounded-2xl border border-emerald-100 shadow-xs flex flex-col justify-between space-y-4">
          <div className="space-y-4">
            <h4 className="text-xs font-bold text-emerald-900 uppercase tracking-wider flex items-center gap-1.5">
              <FiInfo className="w-4 h-4 text-emerald-700" />
              <span>Informasi</span>
            </h4>

            <ul className="space-y-3 text-xs text-slate-700">
              <li className="flex items-start gap-2.5">
                <FiLock className="w-4 h-4 text-emerald-700 shrink-0 mt-0.5" />
                <span>2FA menambahkan lapisan keamanan ekstra untuk akun Anda.</span>
              </li>

              <li className="flex items-start gap-2.5">
                <BsShieldCheck className="w-4 h-4 text-emerald-700 shrink-0 mt-0.5" />
                <span>
                  Anda akan diminta kode verifikasi saat login dari perangkat baru.
                </span>
              </li>

              <li className="flex items-start gap-2.5">
                <FiInfo className="w-4 h-4 text-emerald-700 shrink-0 mt-0.5" />
                <span>Simpan recovery code di tempat aman.</span>
              </li>
            </ul>
          </div>

          <div className="pt-3 border-t border-emerald-200/60">
            <button
              type="button"
              className="inline-flex items-center gap-2 text-xs font-semibold text-emerald-800 hover:text-emerald-950 transition-colors"
            >
              <span>Pelajari Lebih Lanjut</span>
              <FiExternalLink className="w-3.5 h-3.5 text-emerald-700" />
            </button>
          </div>
        </div>
      </div>
    </div>
  )
}
