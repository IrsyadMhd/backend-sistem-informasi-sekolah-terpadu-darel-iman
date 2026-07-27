import { useState } from 'react'
import { FiSearch, FiCheck } from 'react-icons/fi'
import { FaGraduationCap, FaSchool, FaBookQuran, FaBuildingColumns } from 'react-icons/fa6'

export default function SelectUnitCard({ onNavigate }) {
  const [selectedUnit, setSelectedUnit] = useState('sdit')
  const [search, setSearch] = useState('')

  const units = [
    {
      id: 'tkit',
      name: 'TKIT',
      fullName: 'TKIT Dar El-Iman',
      subtext: 'Dar El-Iman',
      icon: FaSchool,
    },
    {
      id: 'sdit',
      name: 'SDIT',
      fullName: 'SDIT Dar El-Iman',
      subtext: 'Dar El-Iman',
      icon: FaGraduationCap,
    },
    {
      id: 'smpit',
      name: 'SMPIT',
      fullName: 'SMPIT Dar El-Iman',
      subtext: 'Dar El-Iman',
      icon: FaBuildingColumns,
    },
    {
      id: 'smait',
      name: 'SMAIT',
      fullName: 'SMAIT Dar El-Iman',
      subtext: 'Dar El-Iman',
      icon: FaBuildingColumns,
    },
    {
      id: 'ponpes',
      name: 'Pondok Pesantren',
      fullName: 'Pondok Pesantren',
      subtext: 'Dar El-Iman',
      icon: FaBookQuran,
    },
    {
      id: 'mahad',
      name: 'Ma\'had',
      fullName: 'Ma\'had',
      subtext: 'Dar El-Iman',
      icon: FaBuildingColumns,
    },
  ]

  const filtered = units.filter(
    (u) =>
      u.name.toLowerCase().includes(search.toLowerCase()) ||
      u.fullName.toLowerCase().includes(search.toLowerCase())
  )

  const handleSelect = () => {
    if (onNavigate) onNavigate(7) // Navigate to Pilih Tahun Ajaran
  }

  return (
    <div className="w-full max-w-4xl mx-auto bg-white rounded-2xl shadow-xl overflow-hidden border border-emerald-100 p-6 lg:p-8 space-y-6">
      {/* Header */}
      <div>
        <h2 className="text-2xl font-bold text-slate-800">Pilih Unit Pendidikan</h2>
        <p className="text-sm text-slate-500 mt-1">
          Pilih unit pendidikan yang akan Anda kelola.
        </p>
      </div>

      {/* Search Input */}
      <div className="relative max-w-md">
        <div className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
          <FiSearch className="w-4 h-4" />
        </div>
        <input
          type="text"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder="Cari unit pendidikan..."
          className="w-full pl-10 pr-4 py-2.5 bg-slate-50 text-slate-800 text-sm rounded-xl border border-slate-200 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:border-transparent transition-all shadow-sm"
        />
      </div>

      {/* Units Grid */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        {filtered.map((unit) => {
          const isSelected = selectedUnit === unit.id
          const IconComp = unit.icon

          return (
            <div
              key={unit.id}
              onClick={() => setSelectedUnit(unit.id)}
              className={`relative cursor-pointer p-5 rounded-2xl border-2 transition-all duration-200 flex items-center gap-4 ${
                isSelected
                  ? 'border-emerald-700 bg-emerald-50/40 shadow-md ring-2 ring-emerald-600/20'
                  : 'border-slate-200 bg-white hover:border-emerald-300 hover:bg-slate-50/50 shadow-sm'
              }`}
            >
              {/* Icon Badge */}
              <div
                className={`w-12 h-12 rounded-xl flex items-center justify-center text-xl shrink-0 transition-colors ${
                  isSelected
                    ? 'bg-emerald-700 text-amber-300 shadow-md shadow-emerald-700/20'
                    : 'bg-emerald-100/70 text-emerald-800'
                }`}
              >
                <IconComp />
              </div>

              {/* Text */}
              <div className="flex-1 min-w-0">
                <h4 className="text-base font-bold text-slate-800 truncate">
                  {unit.name}
                </h4>
                <p className="text-xs text-slate-500">{unit.subtext}</p>
              </div>

              {/* Selection Checkmark */}
              {isSelected && (
                <div className="w-6 h-6 rounded-full bg-emerald-700 text-white flex items-center justify-center shrink-0 shadow-sm">
                  <FiCheck className="w-3.5 h-3.5 stroke-[3]" />
                </div>
              )}
            </div>
          )
        })}
      </div>

      {/* Action Button */}
      <div className="flex justify-end pt-4 border-t border-slate-100">
        <button
          type="button"
          onClick={handleSelect}
          className="py-2.5 px-6 bg-emerald-800 hover:bg-emerald-900 active:bg-emerald-950 text-white font-semibold text-sm rounded-xl shadow-md shadow-emerald-800/20 hover:shadow-lg transition-all"
        >
          Pilih Unit Ini
        </button>
      </div>
    </div>
  )
}
