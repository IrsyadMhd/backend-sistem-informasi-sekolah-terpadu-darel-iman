import { useState } from 'react'
import { FiCalendar, FiCheckCircle } from 'react-icons/fi'

export default function SelectAcademicYearCard({ onNavigate }) {
  const [year, setYear] = useState('2024/2025')
  const [semester, setSemester] = useState('Genap')

  const handleContinue = () => {
    if (onNavigate) onNavigate(8) // Navigate to Session Login or Dashboard
  }

  return (
    <div className="w-full max-w-4xl mx-auto bg-white rounded-2xl shadow-xl overflow-hidden border border-emerald-100 p-6 lg:p-8 space-y-6">
      {/* Header */}
      <div>
        <h2 className="text-2xl font-bold text-slate-800">Pilih Tahun Ajaran</h2>
        <p className="text-sm text-slate-500 mt-1">
          Tentukan tahun ajaran dan semester aktif.
        </p>
      </div>

      {/* Inputs Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
          <label className="block text-xs font-semibold text-slate-700 mb-1.5">
            Tahun Ajaran
          </label>
          <select
            value={year}
            onChange={(e) => setYear(e.target.value)}
            className="w-full px-3.5 py-2.5 bg-white text-slate-800 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:border-transparent transition-all shadow-sm"
          >
            <option value="2024/2025">2024/2025</option>
            <option value="2023/2024">2023/2024</option>
            <option value="2025/2026">2025/2026</option>
          </select>
        </div>

        <div>
          <label className="block text-xs font-semibold text-slate-700 mb-1.5">
            Semester
          </label>
          <select
            value={semester}
            onChange={(e) => setSemester(e.target.value)}
            className="w-full px-3.5 py-2.5 bg-white text-slate-800 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:border-transparent transition-all shadow-sm"
          >
            <option value="Genap">Genap</option>
            <option value="Ganjil">Ganjil</option>
          </select>
        </div>
      </div>

      {/* Summary Card */}
      <div className="bg-gradient-to-br from-emerald-50/80 to-teal-50/40 rounded-2xl p-5 border border-emerald-100/90 shadow-sm space-y-4">
        <h3 className="text-xs font-bold text-emerald-900 uppercase tracking-wider flex items-center gap-2">
          <FiCalendar className="w-4 h-4 text-emerald-700" />
          <span>Informasi Tahun Ajaran Aktif</span>
        </h3>

        <div className="grid grid-cols-2 md:grid-cols-4 gap-4 bg-white/80 backdrop-blur-sm rounded-xl p-4 border border-emerald-100/60 shadow-xs">
          <div>
            <span className="text-[11px] text-slate-400 block font-medium">Tahun Ajaran</span>
            <span className="text-sm font-bold text-slate-800">{year}</span>
          </div>

          <div>
            <span className="text-[11px] text-slate-400 block font-medium">Semester</span>
            <span className="text-sm font-bold text-slate-800">{semester}</span>
          </div>

          <div>
            <span className="text-[11px] text-slate-400 block font-medium">Periode</span>
            <span className="text-sm font-bold text-slate-800">
              {semester === 'Genap' ? 'Januari 2025 - Juni 2025' : 'Juli 2024 - Desember 2024'}
            </span>
          </div>

          <div>
            <span className="text-[11px] text-slate-400 block font-medium mb-0.5">Status</span>
            <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
              <FiCheckCircle className="w-3 h-3 text-emerald-600" />
              <span>Aktif</span>
            </span>
          </div>
        </div>
      </div>

      {/* Action Button */}
      <div className="flex justify-end pt-4 border-t border-slate-100">
        <button
          type="button"
          onClick={handleContinue}
          className="py-2.5 px-6 bg-emerald-800 hover:bg-emerald-900 active:bg-emerald-950 text-white font-semibold text-sm rounded-xl shadow-md shadow-emerald-800/20 hover:shadow-lg transition-all"
        >
          Lanjutkan
        </button>
      </div>
    </div>
  )
}
