import { useEffect, useState } from 'react'
import { AlarmClock, CheckCircle2 } from 'lucide-react'
import { useNavigate } from 'react-router-dom'
import { lmsPresensiService } from '../../services/lmsPresensiService'
import { useAuthStore } from '../../stores/authStore'

export default function ActiveScheduleNotice() {
  const navigate = useNavigate()
  const roles = useAuthStore((state) => state.user?.roles || [])
  const [data, setData] = useState(null)
  const eligible = roles.includes('Guru') || roles.includes('Wali Kelas')

  useEffect(() => {
    if (!eligible) return undefined
    let alive = true
    const load = () => lmsPresensiService.getActiveSchedules()
      .then((response) => alive && setData(response?.data || null))
      .catch(() => alive && setData(null))
    load()
    const timer = window.setInterval(load, 60_000)
    return () => {
      alive = false
      window.clearInterval(timer)
    }
  }, [eligible])

  if (!eligible || !data?.schedules?.length) return null

  return (
    <section className="mb-5 space-y-3" aria-label="Jadwal pelajaran aktif">
      {data.schedules.map((schedule) => {
        const done = ['final', 'locked'].includes(schedule.attendance_status)
        const className = schedule.kelas?.nama_kelas || schedule.school_class?.name || 'Kelas'
        return (
          <article key={schedule.id} className="flex flex-col gap-4 rounded-[18px] border border-emerald-200 bg-emerald-50 p-4 shadow-sm md:flex-row md:items-center md:justify-between dark:border-emerald-800 dark:bg-emerald-950/30">
            <div className="flex gap-3">
              <div className="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-[#0E5C44] text-white">
                {done ? <CheckCircle2 size={21} /> : <AlarmClock size={21} />}
              </div>
              <div>
                <p className="text-xs font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">
                  {done ? 'Presensi sudah diambil' : 'Jam pelajaran sedang aktif'}
                </p>
                <h2 className="font-extrabold text-slate-900 dark:text-white">
                  {schedule.subject?.name || 'Mata Pelajaran'} · {className}
                </h2>
                <p className="text-xs text-slate-600 dark:text-slate-300">
                  {String(schedule.time_start).slice(0, 5)}–{String(schedule.time_end).slice(0, 5)}
                  {schedule.requires_substitute_reason ? ' · Akses wali kelas/pengganti' : ' · Jadwal mengajar Anda'}
                </p>
              </div>
            </div>
            <button
              type="button"
              disabled={done}
              onClick={() => navigate(`/absensi/presensi?active_schedule=${schedule.id}&attendance_date=${data.date}`)}
              className="rounded-xl bg-[#0E5C44] px-5 py-3 text-sm font-bold text-white shadow-md disabled:cursor-not-allowed disabled:bg-slate-400"
            >
              {done ? 'Sudah Final' : schedule.attendance_status === 'draft' ? 'Lanjutkan Absen' : 'Ambil Absen'}
            </button>
          </article>
        )
      })}
    </section>
  )
}
