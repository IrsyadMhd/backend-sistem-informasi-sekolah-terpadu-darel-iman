import { useState } from 'react'
import { FiMonitor, FiSmartphone, FiGlobe, FiLogOut, FiShield, FiCheckCircle } from 'react-icons/fi'

export default function SessionLoginCard() {
  const [sessions, setSessions] = useState([
    {
      id: 1,
      device: 'Windows PC',
      isCurrent: true,
      browser: 'Chrome 125',
      location: 'Padang, Indonesia',
      ip: '103.123.45.67',
      loginTime: '20 Mei 2024 10:22:45',
      icon: FiMonitor,
    },
    {
      id: 2,
      device: 'MacBook Pro',
      isCurrent: false,
      browser: 'Safari 17',
      location: 'Padang, Indonesia',
      ip: '103.123.45.68',
      loginTime: '19 Mei 2024 21:15:09',
      icon: FiMonitor,
    },
    {
      id: 3,
      device: 'iPhone 13',
      isCurrent: false,
      browser: 'Safari iOS 17',
      location: 'Padang, Indonesia',
      ip: '103.123.45.69',
      loginTime: '18 Mei 2024 08:22:11',
      icon: FiSmartphone,
    },
    {
      id: 4,
      device: 'Android Phone',
      isCurrent: false,
      browser: 'Chrome Mobile',
      location: 'Padang, Indonesia',
      ip: '103.123.45.72',
      loginTime: '17 Mei 2024 16:40:33',
      icon: FiSmartphone,
    },
  ])

  const handleLogoutSession = (id) => {
    setSessions(sessions.filter((s) => s.id !== id))
  }

  return (
    <div className="w-full max-w-5xl mx-auto bg-white rounded-2xl shadow-xl overflow-hidden border border-emerald-100 p-6 lg:p-8 space-y-6">
      {/* Header */}
      <div>
        <h2 className="text-2xl font-bold text-slate-800 flex items-center gap-2">
          <FiShield className="text-emerald-700" />
          <span>Perangkat yang Sedang Login</span>
        </h2>
        <p className="text-sm text-slate-500 mt-1">
          Kelola perangkat yang saat ini login ke akun Anda.
        </p>
      </div>

      {/* Table */}
      <div className="overflow-x-auto rounded-xl border border-slate-200 shadow-xs">
        <table className="w-full text-left border-collapse min-w-[700px]">
          <thead>
            <tr className="bg-slate-50 text-slate-700 text-xs font-bold uppercase tracking-wider border-b border-slate-200">
              <th className="py-3 px-4">Perangkat</th>
              <th className="py-3 px-4">Browser</th>
              <th className="py-3 px-4">Lokasi</th>
              <th className="py-3 px-4">IP Address</th>
              <th className="py-3 px-4">Waktu Login</th>
              <th className="py-3 px-4 text-center">Aksi</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100 text-xs text-slate-700 font-medium">
            {sessions.map((session) => {
              const IconComp = session.icon
              return (
                <tr key={session.id} className="hover:bg-slate-50/70 transition-colors">
                  <td className="py-3.5 px-4">
                    <div className="flex items-center gap-2.5">
                      <div className="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-800 flex items-center justify-center shrink-0">
                        <IconComp className="w-4 h-4" />
                      </div>
                      <div>
                        <span className="font-bold text-slate-800 block">
                          {session.device}
                        </span>
                        {session.isCurrent && (
                          <span className="text-[10px] text-emerald-700 font-semibold bg-emerald-100 px-1.5 py-0.5 rounded">
                            (Perangkat Ini)
                          </span>
                        )}
                      </div>
                    </div>
                  </td>

                  <td className="py-3.5 px-4 text-slate-600">{session.browser}</td>

                  <td className="py-3.5 px-4 text-slate-600">
                    <span className="flex items-center gap-1">
                      <FiGlobe className="text-slate-400 w-3 h-3" />
                      {session.location}
                    </span>
                  </td>

                  <td className="py-3.5 px-4 font-mono text-slate-600">{session.ip}</td>

                  <td className="py-3.5 px-4 text-slate-500 whitespace-nowrap">
                    {session.loginTime}
                  </td>

                  <td className="py-3.5 px-4 text-center">
                    {session.isCurrent ? (
                      <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                        <FiCheckCircle className="w-3 h-3" />
                        Aktif
                      </span>
                    ) : (
                      <button
                        type="button"
                        onClick={() => handleLogoutSession(session.id)}
                        className="inline-flex items-center gap-1 py-1 px-3 bg-white hover:bg-red-50 text-red-600 border border-red-200 hover:border-red-300 rounded-lg text-xs font-semibold transition-all shadow-xs"
                      >
                        <FiLogOut className="w-3 h-3" />
                        Logout
                      </button>
                    )}
                  </td>
                </tr>
              )
            })}
          </tbody>
        </table>
      </div>

      {/* Warning Footer */}
      <div className="bg-amber-50 border border-amber-200 rounded-xl p-3.5 text-xs text-amber-900 flex items-center gap-2">
        <FiShield className="w-4 h-4 text-amber-600 shrink-0" />
        <span>
          Jika Anda melihat aktivitas yang tidak dikenal, segera logout dari perangkat tersebut dan ubah password Anda.
        </span>
      </div>
    </div>
  )
}
