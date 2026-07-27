import { useState, useEffect } from 'react'
import { useSearchParams } from 'react-router-dom'
import {
  FiUser,
  FiLock,
  FiShield,
  FiMonitor,
  FiActivity,
  FiGrid,
  FiCalendar,
} from 'react-icons/fi'

import UserProfileCard from '../components/auth/UserProfileCard'
import ChangePasswordCard from '../components/auth/ChangePasswordCard'
import TwoFactorAuthCard from '../components/auth/TwoFactorAuthCard'
import SessionLoginCard from '../components/auth/SessionLoginCard'
import ActivityLoginCard from '../components/auth/ActivityLoginCard'
import SelectUnitCard from '../components/auth/SelectUnitCard'
import SelectAcademicYearCard from '../components/auth/SelectAcademicYearCard'

const VALID_TABS = ['profil', 'ganti-password', '2fa', 'session-login', 'activity-login', 'unit-tahun']

export default function UserProfileManagementPage() {
  const [searchParams] = useSearchParams()
  const tabFromUrl = searchParams.get('tab')
  const [activeTab, setActiveTab] = useState(
    VALID_TABS.includes(tabFromUrl) ? tabFromUrl : 'profil'
  )

  // Sync tab when URL param changes
  useEffect(() => {
    if (tabFromUrl && VALID_TABS.includes(tabFromUrl)) {
      setActiveTab(tabFromUrl)
    }
  }, [tabFromUrl])

  const tabs = [
    { id: 'profil', label: 'Profil User', icon: FiUser },
    { id: 'ganti-password', label: 'Ganti Password', icon: FiLock },
    { id: '2fa', label: 'Keamanan 2FA', icon: FiShield },
    { id: 'session-login', label: 'Session Login', icon: FiMonitor },
    { id: 'activity-login', label: 'Activity Login', icon: FiActivity },
    { id: 'unit-tahun', label: 'Unit & Tahun Ajaran', icon: FiGrid },
  ]

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-black text-slate-800 tracking-tight">
            Profil & Keamanan Akun
          </h1>
          <p className="text-xs text-slate-500 mt-1">
            Kelola data diri, ganti password, keamanan 2FA, session login, dan aktivitas akun Anda.
          </p>
        </div>
        <div className="flex items-center gap-2 px-3 py-1.5 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-800 text-xs font-semibold self-start md:self-auto">
          <span className="w-2 h-2 rounded-full bg-emerald-600 animate-pulse" />
          <span>Status Akun: Super Admin (Aktif)</span>
        </div>
      </div>

      {/* Tabs Bar */}
      <div className="bg-white p-2 rounded-2xl border border-slate-200/80 shadow-xs overflow-x-auto flex gap-1">
        {tabs.map((tab) => {
          const IconComp = tab.icon
          const isActive = activeTab === tab.id
          return (
            <button
              key={tab.id}
              onClick={() => setActiveTab(tab.id)}
              className={`flex items-center gap-2 py-2.5 px-4 rounded-xl text-xs font-bold transition-all whitespace-nowrap ${
                isActive
                  ? 'bg-emerald-800 text-white shadow-md shadow-emerald-800/20'
                  : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'
              }`}
            >
              <IconComp className={`w-4 h-4 ${isActive ? 'text-amber-300' : 'text-slate-400'}`} />
              <span>{tab.label}</span>
            </button>
          )
        })}
      </div>

      {/* Tab Content */}
      <div className="transition-all duration-200">
        {activeTab === 'profil' && <UserProfileCard />}
        {activeTab === 'ganti-password' && <ChangePasswordCard />}
        {activeTab === '2fa' && <TwoFactorAuthCard />}
        {activeTab === 'session-login' && <SessionLoginCard />}
        {activeTab === 'activity-login' && <ActivityLoginCard />}
        {activeTab === 'unit-tahun' && (
          <div className="space-y-8">
            <SelectUnitCard />
            <SelectAcademicYearCard />
          </div>
        )}
      </div>
    </div>
  )
}
