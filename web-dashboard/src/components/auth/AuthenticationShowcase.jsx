import { useState } from 'react'
import { FaMosque } from 'react-icons/fa6'
import { FiLock, FiGrid, FiMaximize2 } from 'react-icons/fi'

import LoginCard from './LoginCard'
import ForgotPasswordCard from './ForgotPasswordCard'
import ResetPasswordCard from './ResetPasswordCard'
import ChangePasswordCard from './ChangePasswordCard'
import UserProfileCard from './UserProfileCard'
import SelectUnitCard from './SelectUnitCard'
import SelectAcademicYearCard from './SelectAcademicYearCard'
import SessionLoginCard from './SessionLoginCard'
import ActivityLoginCard from './ActivityLoginCard'
import TwoFactorAuthCard from './TwoFactorAuthCard'

export default function AuthenticationShowcase() {
  const [activeTab, setActiveTab] = useState(1)
  const [viewMode, setViewMode] = useState('single') // 'single' | 'grid'

  const screens = [
    { id: 1, title: '1. LOGIN', component: LoginCard },
    { id: 2, title: '2. LUPA PASSWORD', component: ForgotPasswordCard },
    { id: 3, title: '3. RESET PASSWORD', component: ResetPasswordCard },
    { id: 4, title: '4. GANTI PASSWORD', component: ChangePasswordCard },
    { id: 5, title: '5. PROFIL USER', component: UserProfileCard },
    { id: 6, title: '6. PILIH UNIT PENDIDIKAN', component: SelectUnitCard },
    { id: 7, title: '7. PILIH TAHUN AJARAN', component: SelectAcademicYearCard },
    { id: 8, title: '8. SESSION LOGIN', component: SessionLoginCard },
    { id: 9, title: '9. ACTIVITY LOGIN', component: ActivityLoginCard },
    { id: 10, title: '10. TWO FACTOR AUTHENTICATION (2FA)', component: TwoFactorAuthCard },
  ]

  const handleNavigate = (pageId) => {
    setActiveTab(pageId)
    setViewMode('single')
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }

  const ActiveComponent = screens.find((s) => s.id === activeTab)?.component || LoginCard

  return (
    <div className="min-h-screen bg-slate-100 text-slate-900 pb-16 font-sans">
      {/* Top Banner Header matching user's image */}
      <header className="bg-white border-b border-emerald-200 shadow-sm sticky top-0 z-50">
        <div className="max-w-7xl mx-auto px-4 py-3 flex flex-col md:flex-row md:items-center justify-between gap-4">
          {/* Logo & School Name */}
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-emerald-800 text-amber-300 flex items-center justify-center shadow-md border border-amber-400/40">
              <FaMosque className="w-5 h-5" />
            </div>
            <div>
              <h1 className="text-sm font-extrabold text-emerald-900 tracking-tight leading-none">
                SDIT DAR EL-IMAN
              </h1>
              <p className="text-[11px] font-medium text-slate-500 mt-0.5">
                Sistem Manajemen Sekolah Islam Terpadu
              </p>
            </div>
          </div>

          {/* Center Title */}
          <div className="text-center">
            <h2 className="text-base md:text-lg font-black text-slate-800 uppercase tracking-wide flex items-center justify-center gap-2">
              <span className="text-amber-500">◆</span>
              <span>1. AUTHENTICATION (10 HALAMAN)</span>
              <span className="text-amber-500">◆</span>
            </h2>
          </div>

          {/* Right ERP Badge & View Switcher */}
          <div className="flex items-center gap-3">
            <button
              onClick={() => setViewMode(viewMode === 'single' ? 'grid' : 'single')}
              className="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs rounded-xl flex items-center gap-1.5 border border-slate-300 transition-all"
            >
              {viewMode === 'single' ? (
                <>
                  <FiGrid className="w-3.5 h-3.5 text-emerald-700" />
                  <span>Mode Grid (10 Screen)</span>
                </>
              ) : (
                <>
                  <FiMaximize2 className="w-3.5 h-3.5 text-emerald-700" />
                  <span>Mode Detail</span>
                </>
              )}
            </button>

            <div className="hidden sm:flex items-center gap-2 bg-emerald-50 border border-emerald-200 px-3 py-1.5 rounded-xl">
              <FiLock className="w-4 h-4 text-emerald-700" />
              <div className="text-right">
                <span className="text-xs font-bold text-emerald-900 block leading-tight">
                  School ERP v1.0
                </span>
                <span className="text-[9px] font-medium text-slate-500 block">
                  Secure • Smart • Integrated
                </span>
              </div>
            </div>
          </div>
        </div>

        {/* Tab Navigation Bar */}
        <div className="bg-emerald-900 text-white overflow-x-auto shadow-inner">
          <div className="max-w-7xl mx-auto px-4 flex gap-1">
            {screens.map((screen) => {
              const isActive = activeTab === screen.id && viewMode === 'single'
              return (
                <button
                  key={screen.id}
                  onClick={() => handleNavigate(screen.id)}
                  className={`py-2.5 px-3 text-[11px] font-bold tracking-wide transition-all whitespace-nowrap border-b-4 ${
                    isActive
                      ? 'bg-emerald-800 text-amber-300 border-amber-400'
                      : 'text-emerald-100/80 hover:text-white hover:bg-emerald-800/50 border-transparent'
                  }`}
                >
                  {screen.title}
                </button>
              )
            })}
          </div>
        </div>
      </header>

      {/* Main Content Area */}
      <main className="max-w-7xl mx-auto px-4 pt-8">
        {viewMode === 'single' ? (
          <div className="space-y-6">
            <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex items-center justify-between">
              <span className="text-xs font-bold text-slate-600">
                Menampilkan Halaman <strong className="text-emerald-800 font-extrabold">{activeTab} dari 10</strong>
              </span>
              <div className="flex gap-2">
                <button
                  disabled={activeTab === 1}
                  onClick={() => handleNavigate(activeTab - 1)}
                  className="px-3 py-1 bg-slate-100 hover:bg-slate-200 disabled:opacity-40 text-xs font-bold rounded-lg text-slate-700"
                >
                  &larr; Sebelumnya
                </button>
                <button
                  disabled={activeTab === 10}
                  onClick={() => handleNavigate(activeTab + 1)}
                  className="px-3 py-1 bg-emerald-800 hover:bg-emerald-900 disabled:opacity-40 text-xs font-bold rounded-lg text-white shadow-xs"
                >
                  Berikutnya &rarr;
                </button>
              </div>
            </div>

            {/* Render Active Component */}
            <div className="transition-all duration-300">
              <ActiveComponent onNavigate={handleNavigate} />
            </div>
          </div>
        ) : (
          /* Grid View Mode displaying all 10 screens like the user's reference image! */
          <div className="space-y-8">
            <div className="text-center max-w-2xl mx-auto bg-amber-50 border border-amber-200 p-3.5 rounded-2xl text-xs text-amber-900 font-medium">
              Mode Grid 10 Halaman Authentication. Klik pada halaman manapun untuk membukanya secara penuh.
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
              {screens.map((screen) => {
                const Comp = screen.component
                return (
                  <div
                    key={screen.id}
                    className="bg-white rounded-2xl shadow-lg border border-slate-200 overflow-hidden group hover:border-emerald-600 transition-all duration-300 flex flex-col"
                  >
                    <div className="bg-slate-900 text-white py-2.5 px-4 flex items-center justify-between">
                      <span className="text-xs font-bold text-amber-400">
                        {screen.title}
                      </span>
                      <button
                        onClick={() => handleNavigate(screen.id)}
                        className="text-[11px] bg-emerald-700 hover:bg-emerald-600 px-2.5 py-1 rounded-lg font-semibold transition-all"
                      >
                        Buka Detail
                      </button>
                    </div>

                    <div className="p-4 bg-slate-50 overflow-auto max-h-[480px]">
                      <div className="transform scale-[0.92] origin-top">
                        <Comp onNavigate={handleNavigate} />
                      </div>
                    </div>
                  </div>
                )
              })}
            </div>
          </div>
        )}
      </main>
    </div>
  )
}
