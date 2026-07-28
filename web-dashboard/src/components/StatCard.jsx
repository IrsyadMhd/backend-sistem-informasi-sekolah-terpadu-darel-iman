import PropTypes from 'prop-types'
import { useNavigate } from 'react-router-dom'
import {
  GraduationCap,
  CheckCircle2,
  Target,
  Award,
  Activity,
  Users,
  Building2,
  BookOpen,
  UserCheck,
  HeartHandshake,
  School,
  DollarSign,
  Layers,
  Sparkles,
} from 'lucide-react'

const ikonStat = {
  'Total Unit': Building2,
  'Unit Pendidikan': Building2,
  'Total Guru': UserCheck,
  'Guru': UserCheck,
  'Total Pegawai': Users,
  'Pegawai': Users,
  'Total Siswa': GraduationCap,
  'Siswa': GraduationCap,
  'Orang Tua': HeartHandshake,
  'Alumni': Sparkles,
  'Total Kelas': School,
  'Kelas': School,
  'Rombel': Layers,
  'Kehadiran Hari Ini': CheckCircle2,
  'Target Tahfizh Sekolah': Target,
  'Prestasi Siswa': Award,
  'Mutabaah Yaumiyah': Activity,
  'Kurikulum & Mapel': BookOpen,
  'Keuangan': DollarSign,
}

const colorMap = {
  'Unit Pendidikan': { bg: 'bg-emerald-500/10 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400' },
  'Guru': { bg: 'bg-blue-500/10 text-blue-600 dark:bg-blue-500/20 dark:text-blue-400' },
  'Pegawai': { bg: 'bg-purple-500/10 text-purple-600 dark:bg-purple-500/20 dark:text-purple-400' },
  'Siswa': { bg: 'bg-cyan-500/10 text-cyan-600 dark:bg-cyan-500/20 dark:text-cyan-400' },
  'Orang Tua': { bg: 'bg-rose-500/10 text-rose-600 dark:bg-rose-500/20 dark:text-rose-400' },
  'Alumni': { bg: 'bg-amber-500/10 text-amber-600 dark:bg-amber-500/20 dark:text-amber-400' },
  'Kelas': { bg: 'bg-violet-500/10 text-violet-600 dark:bg-violet-500/20 dark:text-violet-400' },
  'Rombel': { bg: 'bg-indigo-500/10 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-400' },
}

export default function StatCard({
  title,
  value,
  subtitle,
  trend,
  trendType = 'up',
  trendText = 'dari bulan lalu',
  onClickTo,
  tooltipText,
}) {
  const navigate = useNavigate()
  const Icon = ikonStat[title] || Target
  const colorStyle = colorMap[title] || { bg: 'bg-[#0E5C44]/10 text-[#0E5C44] dark:bg-[#3FBF75]/20 dark:text-[#3FBF75]' }

  const handleClick = () => {
    if (onClickTo) {
      navigate(onClickTo)
    }
  }

  return (
    <div
      onClick={handleClick}
      title={tooltipText || `${title}: ${value}`}
      className={`group relative overflow-hidden rounded-[18px] border border-slate-200/80 bg-white p-4 shadow-xs transition-all duration-200 dark:border-slate-800/80 dark:bg-[#13221f] ${
        onClickTo ? 'cursor-pointer hover:-translate-y-1 hover:border-emerald-500/40 hover:shadow-md' : ''
      }`}
    >
      <div className="flex items-center justify-between gap-2">
        <div className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ${colorStyle.bg} transition-all duration-200 group-hover:scale-105`}>
          <Icon className="h-5 w-5 stroke-[2]" />
        </div>
        <span className="text-xs font-semibold text-slate-500 dark:text-slate-400 truncate">
          {title}
        </span>
      </div>

      <div className="mt-3">
        <h3 className="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white font-sans">
          {value}
        </h3>
      </div>

      <div className="mt-2 flex items-center gap-1.5 text-xs font-semibold">
        {trend && (
          <span
            className={`inline-flex items-center gap-0.5 font-bold ${
              trendType === 'up'
                ? 'text-emerald-600 dark:text-emerald-400'
                : 'text-rose-600 dark:text-rose-400'
            }`}
          >
            {trendType === 'up' ? '↑' : '↓'} {trend}
          </span>
        )}
        <span className="text-[11px] text-slate-400 font-normal truncate">
          {trendText}
        </span>
      </div>
    </div>
  )
}

StatCard.propTypes = {
  title: PropTypes.string.isRequired,
  value: PropTypes.oneOfType([PropTypes.string, PropTypes.number]).isRequired,
  subtitle: PropTypes.string,
  trend: PropTypes.string,
  trendType: PropTypes.oneOf(['up', 'down', 'neutral']),
  trendText: PropTypes.string,
  onClickTo: PropTypes.string,
  tooltipText: PropTypes.string,
}


StatCard.defaultProps = {
  subtitle: '',
  trend: '',
  trendType: 'up',
  onClickTo: '',
  tooltipText: '',
}
