import { useEffect, useMemo, useState } from 'react'
import { AlertCircle, BookOpenCheck, CalendarDays, CheckCircle2, Clock3, FilePlus2, HeartPulse, Save, ShieldCheck, UserCheck, Users, XCircle } from 'lucide-react'
import Swal from 'sweetalert2'
import { useSearchParams } from 'react-router-dom'
import { lmsPresensiService } from '../services/lmsPresensiService'
import { useAuthStore } from '../stores/authStore'
import { AttendanceCapturePanel, AttendanceMethodSelector } from '../components/attendance/AttendanceCapturePanels'

const today = new Date().toISOString().slice(0, 10)
const statuses = [
  ['belum_diverifikasi', 'Belum Diverifikasi'],
  ['hadir', 'Hadir'],
  ['terlambat', 'Terlambat'],
  ['izin', 'Izin'],
  ['sakit', 'Sakit'],
  ['alpa', 'Alpha'],
  ['dispensasi', 'Dispensasi'],
]

const unwrapPage = (response) => response?.data?.data || response?.data || []

function Metric({ icon: Icon, label, value, tone = 'emerald' }) {
  const tones = {
    emerald: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300',
    amber: 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300',
    blue: 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300',
    violet: 'bg-violet-50 text-violet-700 dark:bg-violet-950/40 dark:text-violet-300',
    rose: 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300',
  }
  return (
    <article className="rounded-[18px] border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg dark:border-slate-700 dark:bg-[#1B2433]">
      <div className={`mb-3 grid h-10 w-10 place-items-center rounded-xl ${tones[tone] || tones.emerald}`}>
        <Icon size={20} />
      </div>
      <p className="text-2xl font-extrabold text-slate-900 dark:text-white">{value ?? 0}</p>
      <p className="text-xs font-semibold text-slate-500 dark:text-slate-400">{label}</p>
    </article>
  )
}

function TeacherWorkspace({ activeScheduleId = '', activeDate = '' }) {
  const activeLogin = Boolean(activeScheduleId)
  const [date, setDate] = useState(activeDate || today)
  const [schedules, setSchedules] = useState([])
  const [scheduleId, setScheduleId] = useState('')
  const [students, setStudents] = useState([])
  const [meeting, setMeeting] = useState(1)
  const [topic, setTopic] = useState('')
  const [notes, setNotes] = useState('')
  const [session, setSession] = useState(null)
  const [busy, setBusy] = useState(false)
  const [method, setMethod] = useState('manual')
  const [substituteReason, setSubstituteReason] = useState('')

  useEffect(() => {
    const request = activeLogin
      ? lmsPresensiService.getActiveSchedules()
      : lmsPresensiService.getMySchedules(date)
    request.then((res) => {
      const list = activeLogin ? (res?.data?.schedules || []) : (res?.data || [])
      setSchedules(list)
      setScheduleId(activeLogin ? activeScheduleId : ((current) =>
        current && list.some((item) => item.id === current) ? current : (list[0]?.id || '')
      ))
    }).catch(() => setSchedules([]))
  }, [activeLogin, activeScheduleId, date])

  useEffect(() => {
    if (!scheduleId) return setStudents([])
    const activeSchedule = schedules.find((item) => item.id === scheduleId)
    Promise.all([
      lmsPresensiService.getScheduleStudents(scheduleId, date, activeLogin ? 'active_login' : null),
      activeSchedule?.attendance_session_id
        ? lmsPresensiService.getSession(activeSchedule.attendance_session_id)
        : Promise.resolve(null),
    ]).then(([res, existing]) => {
      const existingSession = existing?.data || null
      const attendanceByStudent = new Map(
        (existingSession?.attendances || []).map((item) => [item.siswa_id, item])
      )
      setSession(existingSession)
      setStudents((res?.data || []).map((student) => {
        const recorded = attendanceByStudent.get(student.id)
        return {
        ...student,
        status: recorded?.status_hadir || student.recommended_status || 'belum_diverifikasi',
        arrival_time: recorded?.arrival_time?.slice(0, 5) || '',
        notes: recorded?.keterangan || '',
        verification_status: recorded?.verification_status || (student.recommendation_verified ? 'verified' : 'unverified'),
        recorded_method: recorded?.recorded_method || (student.recommendation_verified ? 'manual' : null),
      }}))
    }).catch(() => setStudents([]))
  }, [activeLogin, scheduleId, date, schedules])

  const selected = schedules.find((item) => item.id === scheduleId)
  const updateStudent = (id, values) => setStudents((list) => list.map((student) => student.id === id ? { ...student, ...values } : student))
  const markAllPresent = () => setStudents((list) => list.map((student) =>
    student.verification_status === 'verified' && ['izin', 'sakit', 'dispensasi'].includes(student.status)
      ? student : { ...student, status: 'hadir', recorded_method: 'manual' }
  ))
  const applyScan = (result) => updateStudent(result.student.id, {
    status: result.attendance_status,
    arrival_time: new Date(result.recorded_at).toTimeString().slice(0, 5),
    recorded_method: method === 'qr' ? 'qr_code' : method === 'face' ? 'face_recognition' : method,
    verification_status: method === 'face' ? 'pending' : 'verified',
  })

  const save = async (finalize = false) => {
    setBusy(true)
    try {
      const result = await lmsPresensiService.saveDraft({
        schedule_id: scheduleId, attendance_date: date, meeting_number: Number(meeting),
        topic, meeting_notes: notes,
        attendance_context: activeLogin ? 'active_login' : undefined,
        substitute_reason: selected?.requires_substitute_reason ? substituteReason : undefined,
        items: students.map((student) => ({
          student_id: student.id, status: student.status,
          arrival_time: student.arrival_time || null, notes: student.notes || null,
          recorded_method: student.recorded_method || null,
        })),
      })
      const saved = result.data
      setSession(saved)
      if (finalize) {
        await lmsPresensiService.finalize(saved.id)
        setSession({ ...saved, status: 'final' })
      }
      Swal.fire({ icon: 'success', title: finalize ? 'Presensi difinalisasi' : 'Draft tersimpan', confirmColor: '#0E5C44' })
    } catch (error) {
      Swal.fire({ icon: 'error', title: 'Presensi belum tersimpan', text: error.response?.data?.message || 'Periksa kembali data presensi.', confirmColor: '#0E5C44' })
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="space-y-5">
      <div className="grid gap-4 rounded-[18px] border border-slate-200 bg-white p-5 md:grid-cols-3 dark:border-slate-700 dark:bg-[#1B2433]">
        <label className="text-xs font-bold text-slate-600 dark:text-slate-300">Tanggal
          <input disabled={activeLogin} type="date" value={date} onChange={(event) => setDate(event.target.value)} className="mt-2 w-full rounded-xl border border-slate-200 bg-transparent p-3 outline-none focus:ring-2 focus:ring-[#3FBF75] disabled:bg-slate-100" />
        </label>
        <label className="text-xs font-bold text-slate-600 md:col-span-2 dark:text-slate-300">Jadwal Pelajaran
          <select disabled={activeLogin} value={scheduleId} onChange={(event) => setScheduleId(event.target.value)} className="mt-2 w-full rounded-xl border border-slate-200 bg-transparent p-3 outline-none focus:ring-2 focus:ring-[#3FBF75] disabled:bg-slate-100">
            <option value="">Tidak ada jadwal</option>
            {schedules.map((item) => <option key={item.id} value={item.id}>{item.subject?.name} · {item.kelas?.nama_kelas} · {item.time_start?.slice(0, 5)}–{item.time_end?.slice(0, 5)}</option>)}
          </select>
        </label>
        {selected && <div className="md:col-span-3 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200">
          <b>{selected.subject?.name}</b> · {selected.kelas?.nama_kelas} · {selected.employee?.nama_lengkap} · {selected.time_start?.slice(0, 5)}–{selected.time_end?.slice(0, 5)}
        </div>}
        {selected?.requires_substitute_reason && (
          <label className="text-xs font-bold text-amber-700 md:col-span-3 dark:text-amber-300">
            Alasan mengambil presensi sebagai wali kelas/pengganti
            <textarea required value={substituteReason} onChange={(event) => setSubstituteReason(event.target.value)} className="mt-2 w-full rounded-xl border border-amber-300 bg-amber-50 p-3 text-slate-900 dark:bg-amber-950/20 dark:text-white" rows="2" placeholder="Contoh: Guru mata pelajaran berhalangan hadir." />
          </label>
        )}
        <label className="text-xs font-bold text-slate-600 dark:text-slate-300">Pertemuan ke-
          <input type="number" min="1" value={meeting} onChange={(event) => setMeeting(event.target.value)} className="mt-2 w-full rounded-xl border border-slate-200 bg-transparent p-3" />
        </label>
        <label className="text-xs font-bold text-slate-600 md:col-span-2 dark:text-slate-300">Topik
          <input value={topic} onChange={(event) => setTopic(event.target.value)} className="mt-2 w-full rounded-xl border border-slate-200 bg-transparent p-3" placeholder="Topik pembelajaran" />
        </label>
        <label className="text-xs font-bold text-slate-600 md:col-span-3 dark:text-slate-300">Catatan
          <textarea value={notes} onChange={(event) => setNotes(event.target.value)} className="mt-2 w-full rounded-xl border border-slate-200 bg-transparent p-3" rows="2" />
        </label>
      </div>

      <section className="space-y-4 rounded-[18px] border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-[#1B2433]">
        <div><h2 className="font-extrabold text-slate-900 dark:text-white">Metode Presensi</h2><p className="text-xs text-slate-500">Semua metode masuk ke draft yang sama dan dapat diperiksa sebelum finalisasi.</p></div>
        <AttendanceMethodSelector value={method} onChange={setMethod} />
        <AttendanceCapturePanel method={method} session={session} onRecorded={applyScan} />
      </section>

      <div className="overflow-hidden rounded-[18px] border border-slate-200 bg-white dark:border-slate-700 dark:bg-[#1B2433]">
        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 p-4 dark:border-slate-700">
          <div><h2 className="font-extrabold text-slate-900 dark:text-white">Daftar Siswa</h2><p className="text-xs text-slate-500">{students.length} siswa aktif</p></div>
          <button onClick={markAllPresent} className="rounded-xl bg-emerald-50 px-4 py-2 text-sm font-bold text-[#0E5C44] hover:-translate-y-0.5">Tandai Semua Hadir</button>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full min-w-[850px] text-left text-sm">
            <thead className="sticky top-0 bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-900"><tr><th className="p-4">Siswa</th><th>Status</th><th>Jam Hadir</th><th>Metode</th><th>Catatan</th><th>Verifikasi</th></tr></thead>
            <tbody>{students.map((student) => <tr key={student.id} className="border-t border-slate-100 hover:bg-emerald-50/40 dark:border-slate-800 dark:hover:bg-emerald-950/20">
              <td className="p-4"><b className="text-slate-900 dark:text-white">{student.full_name}</b><p className="text-xs text-slate-500">{student.nis || student.nisn}</p></td>
              <td><select value={student.status} onChange={(event) => updateStudent(student.id, { status: event.target.value, recorded_method: 'manual' })} className="rounded-lg border border-slate-200 bg-transparent p-2">{statuses.map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></td>
              <td><input type="time" value={student.arrival_time} onChange={(event) => updateStudent(student.id, { arrival_time: event.target.value })} className="rounded-lg border border-slate-200 bg-transparent p-2" /></td>
              <td><span className="rounded-full bg-emerald-50 px-2 py-1 text-xs font-bold text-[#0E5C44]">{student.recorded_method || 'belum'}</span></td>
              <td><input value={student.notes} onChange={(event) => updateStudent(student.id, { notes: event.target.value })} className="rounded-lg border border-slate-200 bg-transparent p-2" /></td>
              <td><span className="rounded-full bg-slate-100 px-2 py-1 text-xs font-bold text-slate-600">{student.verification_status === 'verified' ? 'Terverifikasi' : 'Belum'}</span></td>
            </tr>)}</tbody>
          </table>
        </div>
        {!students.length && <div className="p-12 text-center text-sm text-slate-500">Pilih jadwal yang memiliki siswa aktif.</div>}
        <div className="sticky bottom-0 flex justify-end gap-3 border-t border-slate-200 bg-white/95 p-4 backdrop-blur dark:border-slate-700 dark:bg-[#1B2433]/95">
          <button disabled={busy || !students.length} onClick={() => save(false)} className="flex items-center gap-2 rounded-xl border border-[#0E5C44] px-5 py-3 font-bold text-[#0E5C44] disabled:opacity-50"><Save size={17} /> Simpan Draft</button>
          <button disabled={busy || !students.length || session?.status === 'final'} onClick={() => save(true)} className="flex items-center gap-2 rounded-xl bg-[#0E5C44] px-5 py-3 font-bold text-white shadow-lg transition hover:scale-[1.03] disabled:opacity-50"><ShieldCheck size={17} /> Finalisasi</button>
        </div>
      </div>
    </div>
  )
}

function StudentWorkspace() {
  const [attendance, setAttendance] = useState([])
  const [permissions, setPermissions] = useState([])
  const [form, setForm] = useState({ start_date: today, end_date: today, type: 'izin', reason: '' })
  const load = () => Promise.all([lmsPresensiService.getMyAttendance(), lmsPresensiService.getPermissionRequests()])
    .then(([mine, requests]) => { setAttendance(unwrapPage(mine)); setPermissions(unwrapPage(requests)) })
  useEffect(() => { load().catch(() => {}) }, [])
  const totals = useMemo(() => Object.fromEntries(statuses.map(([status]) => [status, attendance.filter((item) => item.status_hadir === status).length])), [attendance])
  const submit = async (event) => {
    event.preventDefault()
    await lmsPresensiService.submitPermissionRequest({ ...form, status: 'submitted' })
    setForm({ start_date: today, end_date: today, type: 'izin', reason: '' })
    load()
  }
  return <div className="space-y-5">
    <div className="grid grid-cols-2 gap-3 md:grid-cols-5"><Metric icon={CheckCircle2} label="Hadir" value={totals.hadir} /><Metric icon={Clock3} label="Terlambat" value={totals.terlambat} tone="amber" /><Metric icon={FilePlus2} label="Izin" value={totals.izin} tone="blue" /><Metric icon={HeartPulse} label="Sakit" value={totals.sakit} tone="violet" /><Metric icon={XCircle} label="Alpha" value={totals.alpa} tone="rose" /></div>
    <div className="grid gap-5 lg:grid-cols-[1fr_1.5fr]">
      <form onSubmit={submit} className="space-y-4 rounded-[18px] border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-[#1B2433]">
        <div><h2 className="font-extrabold dark:text-white">Ajukan Izin / Sakit</h2><p className="text-xs text-slate-500">Pengajuan menunggu verifikasi wali kelas.</p></div>
        <select value={form.type} onChange={(e) => setForm({ ...form, type: e.target.value })} className="w-full rounded-xl border border-slate-200 bg-transparent p-3"><option value="izin">Izin</option><option value="sakit">Sakit</option></select>
        <div className="grid grid-cols-2 gap-3"><input type="date" value={form.start_date} onChange={(e) => setForm({ ...form, start_date: e.target.value })} className="rounded-xl border border-slate-200 bg-transparent p-3" /><input type="date" value={form.end_date} onChange={(e) => setForm({ ...form, end_date: e.target.value })} className="rounded-xl border border-slate-200 bg-transparent p-3" /></div>
        <textarea required value={form.reason} onChange={(e) => setForm({ ...form, reason: e.target.value })} placeholder="Alasan pengajuan" className="w-full rounded-xl border border-slate-200 bg-transparent p-3" rows="4" />
        <button className="w-full rounded-xl bg-[#0E5C44] p-3 font-bold text-white">Kirim Pengajuan</button>
      </form>
      <section className="rounded-[18px] border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-[#1B2433]"><h2 className="mb-4 font-extrabold dark:text-white">Riwayat Kehadiran</h2><div className="space-y-3">{attendance.slice(0, 12).map((item) => <div key={item.id} className="flex items-center justify-between rounded-xl border border-slate-100 p-3 dark:border-slate-700"><div><b className="text-sm dark:text-white">{item.jadwal_pelajaran?.subject?.name || 'Mata Pelajaran'}</b><p className="text-xs text-slate-500">{item.tanggal}</p></div><span className="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-[#0E5C44]">{item.status_label || item.status_hadir}</span></div>)}</div></section>
    </div>
    <section className="rounded-[18px] border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-[#1B2433]"><h2 className="mb-4 font-extrabold dark:text-white">Status Pengajuan</h2><div className="grid gap-3 md:grid-cols-3">{permissions.map((item) => <div key={item.id} className="rounded-xl bg-slate-50 p-4 dark:bg-slate-900"><b className="capitalize dark:text-white">{item.type}</b><p className="text-xs text-slate-500">{item.start_date} – {item.end_date}</p><span className="mt-2 inline-block rounded-full bg-white px-2 py-1 text-xs font-bold text-[#0E5C44]">{item.status}</span></div>)}</div></section>
  </div>
}

function HomeroomWorkspace() {
  const [dashboard, setDashboard] = useState({})
  const [permissions, setPermissions] = useState([])
  const load = () => Promise.all([lmsPresensiService.getHomeroomDashboard(), lmsPresensiService.getHomeroomPermissions()])
    .then(([summary, requests]) => { setDashboard(summary.data || {}); setPermissions(unwrapPage(requests)) })
  useEffect(() => { load().catch(() => {}) }, [])
  const review = async (id, status) => { await lmsPresensiService.reviewPermission(id, { status }); load() }
  return <div className="space-y-5">
    <div className="grid grid-cols-2 gap-3 md:grid-cols-4"><Metric icon={Users} label="Total Siswa" value={dashboard.total_students} /><Metric icon={UserCheck} label="Hadir Hari Ini" value={dashboard.present} /><Metric icon={Clock3} label="Terlambat" value={dashboard.late} tone="amber" /><Metric icon={AlertCircle} label="Tindak Lanjut Aktif" value={dashboard.open_follow_ups} tone="rose" /></div>
    <section className="rounded-[18px] border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-[#1B2433]"><h2 className="font-extrabold dark:text-white">Verifikasi Izin & Sakit</h2><p className="mb-4 text-xs text-slate-500">Persetujuan menjadi rekomendasi presensi, bukan menimpa presensi final.</p><div className="space-y-3">{permissions.map((item) => <div key={item.id} className="flex flex-col justify-between gap-3 rounded-xl border border-slate-100 p-4 md:flex-row md:items-center dark:border-slate-700"><div><b className="dark:text-white">{item.student?.full_name}</b><p className="text-xs text-slate-500">{item.type} · {item.start_date}–{item.end_date} · {item.reason}</p></div><div className="flex gap-2"><button onClick={() => review(item.id, 'rejected')} className="rounded-lg bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700">Tolak</button><button onClick={() => review(item.id, 'approved')} className="rounded-lg bg-emerald-50 px-3 py-2 text-xs font-bold text-[#0E5C44]">Setujui</button></div></div>)}</div></section>
  </div>
}

export default function AttendanceWorkspacePage() {
  const [searchParams] = useSearchParams()
  const user = useAuthStore((state) => state.user)
  const roles = user?.roles || []
  const isStudent = roles.includes('Siswa')
  const isHomeroom = roles.includes('Wali Kelas')
  const activeScheduleId = searchParams.get('active_schedule') || ''
  const activeDate = searchParams.get('attendance_date') || ''
  return <div className="space-y-6 pb-16">
    <header className="relative overflow-hidden rounded-[18px] bg-gradient-to-br from-[#0E5C44] via-[#1E8E5A] to-[#3FBF75] p-6 text-white shadow-xl">
      <div className="relative z-10"><p className="mb-2 flex items-center gap-2 text-xs font-bold uppercase tracking-widest text-emerald-100"><CalendarDays size={16} /> Modul Absensi</p><h1 className="text-2xl font-extrabold md:text-3xl">{isStudent ? 'Kehadiran Saya' : isHomeroom ? 'Monitoring Wali Kelas' : 'Presensi Pembelajaran'}</h1><p className="mt-2 max-w-2xl text-sm text-emerald-50">{isStudent ? 'Pantau riwayat kehadiran dan ajukan izin atau sakit.' : isHomeroom ? 'Pantau kehadiran rombel, verifikasi pengajuan, dan kelola tindak lanjut.' : 'Isi presensi berdasarkan jadwal mengajar, simpan draft, lalu finalisasi.'}</p></div>
      <BookOpenCheck className="absolute -bottom-8 right-8 h-40 w-40 text-white/10" />
    </header>
    {isStudent
      ? <StudentWorkspace />
      : activeScheduleId
        ? <TeacherWorkspace activeScheduleId={activeScheduleId} activeDate={activeDate} />
        : isHomeroom
          ? <HomeroomWorkspace />
          : <TeacherWorkspace />}
  </div>
}
