import { useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import Swal from 'sweetalert2'
import {
  CalendarDays, CheckCircle2, ChevronLeft, ChevronRight, Clock3,
  Edit2, GraduationCap, Plus, Search, Trash2, Users, X,
} from 'lucide-react'
import { scheduleService } from '../services/scheduleService'

const emptyForm = {
  kelas_id: '', employee_id: '', subject_id: '', academic_year_id: '',
  semester_id: '', day_of_week: 1, time_start: '07:00', time_end: '08:00',
  week_type: 'all', is_active: true,
}

const pickError = (error, fallback) => {
  const errors = error.response?.data?.errors
  return errors ? Object.values(errors).flat()[0] : (error.response?.data?.message || fallback)
}

const time = (value) => value?.slice(0, 5) || '-'
const subjectName = (item) => item?.nama_mapel || item?.name || 'Mata Pelajaran'

function Metric({ icon: Icon, label, value }) {
  return (
    <article className="rounded-[18px] border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-[#1B2433]">
      <div className="mb-3 grid h-10 w-10 place-items-center rounded-xl bg-emerald-50 text-[#0E5C44] dark:bg-emerald-950/40 dark:text-emerald-300"><Icon size={20} /></div>
      <p className="text-2xl font-extrabold text-slate-900 dark:text-white">{value || 0}</p>
      <p className="text-xs font-semibold text-slate-500">{label}</p>
    </article>
  )
}

export default function MasterSchedulePage() {
  const queryClient = useQueryClient()
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [day, setDay] = useState('')
  const [teacher, setTeacher] = useState('')
  const [status, setStatus] = useState('')
  const [modal, setModal] = useState(false)
  const [editing, setEditing] = useState(null)
  const [form, setForm] = useState(emptyForm)

  const { data: options = {} } = useQuery({
    queryKey: ['schedule-options'],
    queryFn: scheduleService.getOptions,
  })

  const { data: response = {}, isLoading } = useQuery({
    queryKey: ['schedules', page, search, day, teacher, status],
    queryFn: () => scheduleService.getDaftar({
      page, per_page: 15, search, day_of_week: day,
      employee_id: teacher, is_active: status,
    }),
  })

  const items = response.data || []
  const meta = response.meta || {}
  const stats = response.statistik || {}
  const semesters = useMemo(
    () => (options.semester || []).filter((item) => !form.academic_year_id || item.academic_year_id === form.academic_year_id),
    [options.semester, form.academic_year_id],
  )

  const saveMutation = useMutation({
    mutationFn: (payload) => editing
      ? scheduleService.ubah({ id: editing.id, payload })
      : scheduleService.tambah(payload),
    onSuccess: (result) => {
      Swal.fire({ icon: 'success', title: 'Berhasil', text: result.message, confirmColor: '#0E5C44' })
      setModal(false)
      queryClient.invalidateQueries({ queryKey: ['schedules'] })
    },
    onError: (error) => Swal.fire({
      icon: 'error', title: 'Jadwal belum tersimpan',
      text: pickError(error, 'Periksa kembali data jadwal.'), confirmColor: '#0E5C44',
    }),
  })

  const deleteMutation = useMutation({
    mutationFn: scheduleService.hapus,
    onSuccess: (result) => {
      Swal.fire({ icon: 'success', title: 'Terhapus', text: result.message, confirmColor: '#0E5C44' })
      queryClient.invalidateQueries({ queryKey: ['schedules'] })
    },
    onError: (error) => Swal.fire('Gagal', pickError(error, 'Jadwal gagal dihapus.'), 'error'),
  })

  const openAdd = () => {
    const activeYear = (options.tahun_ajaran || []).find((item) => item.is_active)
    const yearId = activeYear?.id || options.tahun_ajaran?.[0]?.id || ''
    const activeSemester = (options.semester || []).find((item) => item.is_active && item.academic_year_id === yearId)
    setEditing(null)
    setForm({
      ...emptyForm,
      academic_year_id: yearId,
      semester_id: activeSemester?.id || options.semester?.find((item) => item.academic_year_id === yearId)?.id || '',
    })
    setModal(true)
  }

  const openEdit = (item) => {
    setEditing(item)
    setForm({
      kelas_id: item.kelas_id || '',
      employee_id: item.employee_id || '',
      subject_id: item.subject_id || '',
      academic_year_id: item.academic_year_id || '',
      semester_id: item.semester_id || '',
      day_of_week: item.day_of_week || 1,
      time_start: time(item.time_start),
      time_end: time(item.time_end),
      week_type: item.week_type || 'all',
      is_active: item.is_active ?? true,
    })
    setModal(true)
  }

  const submit = (event) => {
    event.preventDefault()
    saveMutation.mutate({ ...form, day_of_week: Number(form.day_of_week) })
  }

  const remove = async (item) => {
    const result = await Swal.fire({
      icon: 'warning', title: 'Hapus jadwal?',
      text: `${subjectName(item.subject)} · ${item.kelas?.nama_kelas || 'Kelas'}`,
      showCancelButton: true, confirmButtonText: 'Ya, hapus', cancelButtonText: 'Batal',
      confirmButtonColor: '#dc2626',
    })
    if (result.isConfirmed) deleteMutation.mutate(item.id)
  }

  return (
    <div className="space-y-6 pb-16">
      <header className="relative overflow-hidden rounded-[18px] bg-gradient-to-br from-[#0E5C44] via-[#1E8E5A] to-[#3FBF75] p-6 text-white shadow-xl">
        <div className="relative z-10 flex flex-col justify-between gap-5 md:flex-row md:items-center">
          <div>
            <p className="mb-2 flex items-center gap-2 text-xs font-bold uppercase tracking-widest text-emerald-100"><CalendarDays size={16} /> Akademik</p>
            <h1 className="text-2xl font-extrabold md:text-3xl">Jadwal Pelajaran</h1>
            <p className="mt-2 max-w-2xl text-sm text-emerald-50">Kelola guru, mata pelajaran, kelas, hari, dan jam mengajar dalam satu tempat.</p>
          </div>
          <button onClick={openAdd} className="flex items-center justify-center gap-2 rounded-xl bg-white px-5 py-3 font-bold text-[#0E5C44] shadow-lg transition hover:-translate-y-0.5 hover:scale-[1.03]">
            <Plus size={18} /> Tambah Jadwal
          </button>
        </div>
        <CalendarDays className="absolute -bottom-8 right-8 h-40 w-40 text-white/10" />
      </header>

      <section className="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <Metric icon={CalendarDays} label="Total Jadwal" value={stats.total} />
        <Metric icon={CheckCircle2} label="Jadwal Aktif" value={stats.aktif} />
        <Metric icon={Clock3} label="Tidak Aktif" value={stats.tidak_aktif} />
        <Metric icon={Users} label="Guru Terjadwal" value={stats.guru_terjadwal} />
      </section>

      <section className="rounded-[18px] border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-[#1B2433]">
        <div className="grid gap-3 md:grid-cols-4">
          <label className="relative md:col-span-1">
            <Search className="absolute left-3 top-3 text-slate-400" size={18} />
            <input value={search} onChange={(e) => { setSearch(e.target.value); setPage(1) }} placeholder="Cari guru, mapel, kelas..." className="w-full rounded-xl border border-slate-200 bg-transparent py-2.5 pl-10 pr-3 text-sm outline-none focus:ring-2 focus:ring-[#3FBF75] dark:border-slate-700" />
          </label>
          <select value={day} onChange={(e) => { setDay(e.target.value); setPage(1) }} className="rounded-xl border border-slate-200 bg-transparent px-3 py-2.5 text-sm dark:border-slate-700">
            <option value="">Semua Hari</option>
            {(options.hari || []).map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
          </select>
          <select value={teacher} onChange={(e) => { setTeacher(e.target.value); setPage(1) }} className="rounded-xl border border-slate-200 bg-transparent px-3 py-2.5 text-sm dark:border-slate-700">
            <option value="">Semua Guru</option>
            {(options.guru || []).map((item) => <option key={item.id} value={item.id}>{item.nama_lengkap}</option>)}
          </select>
          <select value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }} className="rounded-xl border border-slate-200 bg-transparent px-3 py-2.5 text-sm dark:border-slate-700">
            <option value="">Semua Status</option><option value="1">Aktif</option><option value="0">Tidak Aktif</option>
          </select>
        </div>
      </section>

      <section className="overflow-hidden rounded-[18px] border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-[#1B2433]">
        <div className="overflow-x-auto">
          <table className="w-full min-w-[950px] text-left text-sm">
            <thead className="sticky top-0 bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-900">
              <tr><th className="p-4">Hari & Jam</th><th>Pelajaran</th><th>Kelas</th><th>Guru</th><th>Periode</th><th>Status</th><th className="pr-4 text-right">Aksi</th></tr>
            </thead>
            <tbody>
              {items.map((item) => (
                <tr key={item.id} className="border-t border-slate-100 transition hover:bg-emerald-50/40 dark:border-slate-800 dark:hover:bg-emerald-950/20">
                  <td className="p-4"><b className="text-slate-900 dark:text-white">{item.nama_hari}</b><p className="text-xs text-slate-500">{time(item.time_start)}–{time(item.time_end)}</p></td>
                  <td><b className="text-slate-800 dark:text-slate-100">{subjectName(item.subject)}</b><p className="text-xs text-slate-500">{item.subject?.kode_mapel || item.subject?.code || '-'}</p></td>
                  <td><b className="text-slate-700 dark:text-slate-200">{item.kelas?.nama_kelas || item.school_class?.name || '-'}</b><p className="text-xs text-slate-500">{item.kelas?.unit_pendidikan?.name || '-'}</p></td>
                  <td><span className="flex items-center gap-2 font-semibold text-slate-700 dark:text-slate-200"><GraduationCap size={16} className="text-[#0E5C44]" />{item.employee?.nama_lengkap || item.teacher?.name || '-'}</span></td>
                  <td><b className="text-slate-700 dark:text-slate-200">{item.academic_year?.name || '-'}</b><p className="text-xs text-slate-500">{item.semester?.name || '-'}</p></td>
                  <td><span className={`rounded-full px-3 py-1 text-xs font-bold ${item.is_active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'}`}>{item.is_active ? 'Aktif' : 'Tidak Aktif'}</span></td>
                  <td className="pr-4"><div className="flex justify-end gap-2">
                    <button onClick={() => openEdit(item)} aria-label="Edit jadwal" className="rounded-lg bg-amber-50 p-2 text-amber-700 transition hover:scale-105"><Edit2 size={16} /></button>
                    <button onClick={() => remove(item)} aria-label="Hapus jadwal" className="rounded-lg bg-rose-50 p-2 text-rose-700 transition hover:scale-105"><Trash2 size={16} /></button>
                  </div></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        {isLoading && <div className="p-12 text-center text-sm text-slate-500">Memuat jadwal pelajaran...</div>}
        {!isLoading && !items.length && <div className="p-12 text-center text-sm text-slate-500">Belum ada jadwal yang sesuai filter.</div>}
        <footer className="flex items-center justify-between border-t border-slate-200 p-4 text-sm dark:border-slate-700">
          <span className="text-slate-500">Menampilkan {meta.from || 0}–{meta.to || 0} dari {meta.total || 0}</span>
          <div className="flex gap-2">
            <button disabled={page <= 1} onClick={() => setPage((value) => value - 1)} className="rounded-lg border border-slate-200 p-2 disabled:opacity-40 dark:border-slate-700"><ChevronLeft size={17} /></button>
            <span className="px-2 py-2 font-bold">{page} / {meta.last_page || 1}</span>
            <button disabled={page >= (meta.last_page || 1)} onClick={() => setPage((value) => value + 1)} className="rounded-lg border border-slate-200 p-2 disabled:opacity-40 dark:border-slate-700"><ChevronRight size={17} /></button>
          </div>
        </footer>
      </section>

      {modal && (
        <div className="fixed inset-0 z-50 grid place-items-center bg-slate-950/60 p-4 backdrop-blur-sm">
          <form onSubmit={submit} className="max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-[18px] border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-[#1B2433]">
            <header className="sticky top-0 z-10 flex items-center justify-between border-b border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-[#1B2433]">
              <div><h2 className="text-xl font-extrabold dark:text-white">{editing ? 'Edit Jadwal Pelajaran' : 'Tambah Jadwal Pelajaran'}</h2><p className="text-xs text-slate-500">Kolom bertanda * wajib diisi.</p></div>
              <button type="button" onClick={() => setModal(false)} className="rounded-lg p-2 hover:bg-slate-100 dark:hover:bg-slate-800"><X size={20} /></button>
            </header>
            <div className="grid gap-4 p-5 md:grid-cols-2">
              <label className="text-sm font-bold text-slate-700 dark:text-slate-200">Tahun Ajaran *
                <select required value={form.academic_year_id} onChange={(e) => setForm({ ...form, academic_year_id: e.target.value, semester_id: '' })} className="mt-2 w-full rounded-xl border border-slate-200 bg-transparent p-3 font-normal dark:border-slate-700">
                  <option value="">Pilih Tahun Ajaran</option>{(options.tahun_ajaran || []).map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
                </select>
              </label>
              <label className="text-sm font-bold text-slate-700 dark:text-slate-200">Semester *
                <select required value={form.semester_id} onChange={(e) => setForm({ ...form, semester_id: e.target.value })} className="mt-2 w-full rounded-xl border border-slate-200 bg-transparent p-3 font-normal dark:border-slate-700">
                  <option value="">Pilih Semester</option>{semesters.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
                </select>
              </label>
              <label className="text-sm font-bold text-slate-700 dark:text-slate-200">Kelas *
                <select required value={form.kelas_id} onChange={(e) => setForm({ ...form, kelas_id: e.target.value })} className="mt-2 w-full rounded-xl border border-slate-200 bg-transparent p-3 font-normal dark:border-slate-700">
                  <option value="">Pilih Kelas</option>{(options.kelas || []).map((item) => <option key={item.id} value={item.id}>{item.nama_kelas} {item.unit_pendidikan?.name ? `· ${item.unit_pendidikan.name}` : ''}</option>)}
                </select>
              </label>
              <label className="text-sm font-bold text-slate-700 dark:text-slate-200">Guru Pengampu *
                <select required value={form.employee_id} onChange={(e) => setForm({ ...form, employee_id: e.target.value })} className="mt-2 w-full rounded-xl border border-slate-200 bg-transparent p-3 font-normal dark:border-slate-700">
                  <option value="">Pilih Guru</option>{(options.guru || []).map((item) => <option key={item.id} value={item.id}>{item.nama_lengkap}</option>)}
                </select>
              </label>
              <label className="text-sm font-bold text-slate-700 dark:text-slate-200 md:col-span-2">Mata Pelajaran *
                <select required value={form.subject_id} onChange={(e) => setForm({ ...form, subject_id: e.target.value })} className="mt-2 w-full rounded-xl border border-slate-200 bg-transparent p-3 font-normal dark:border-slate-700">
                  <option value="">Pilih Mata Pelajaran</option>{(options.mata_pelajaran || []).map((item) => <option key={item.id} value={item.id}>{subjectName(item)}</option>)}
                </select>
              </label>
              <label className="text-sm font-bold text-slate-700 dark:text-slate-200">Hari *
                <select required value={form.day_of_week} onChange={(e) => setForm({ ...form, day_of_week: e.target.value })} className="mt-2 w-full rounded-xl border border-slate-200 bg-transparent p-3 font-normal dark:border-slate-700">
                  {(options.hari || []).map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
                </select>
              </label>
              <label className="text-sm font-bold text-slate-700 dark:text-slate-200">Pola Minggu
                <select value={form.week_type} onChange={(e) => setForm({ ...form, week_type: e.target.value })} className="mt-2 w-full rounded-xl border border-slate-200 bg-transparent p-3 font-normal dark:border-slate-700">
                  <option value="all">Setiap Minggu</option><option value="odd">Minggu Ganjil</option><option value="even">Minggu Genap</option>
                </select>
              </label>
              <label className="text-sm font-bold text-slate-700 dark:text-slate-200">Jam Mulai *
                <input required type="time" value={form.time_start} onChange={(e) => setForm({ ...form, time_start: e.target.value })} className="mt-2 w-full rounded-xl border border-slate-200 bg-transparent p-3 font-normal dark:border-slate-700" />
              </label>
              <label className="text-sm font-bold text-slate-700 dark:text-slate-200">Jam Selesai *
                <input required type="time" value={form.time_end} onChange={(e) => setForm({ ...form, time_end: e.target.value })} className="mt-2 w-full rounded-xl border border-slate-200 bg-transparent p-3 font-normal dark:border-slate-700" />
              </label>
              <label className="flex items-center gap-3 rounded-xl bg-slate-50 p-4 text-sm font-bold text-slate-700 md:col-span-2 dark:bg-slate-900 dark:text-slate-200">
                <input type="checkbox" checked={form.is_active} onChange={(e) => setForm({ ...form, is_active: e.target.checked })} className="h-4 w-4 accent-[#0E5C44]" /> Jadwal aktif dan dapat digunakan untuk presensi
              </label>
            </div>
            <footer className="sticky bottom-0 flex justify-end gap-3 border-t border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-[#1B2433]">
              <button type="button" onClick={() => setModal(false)} className="rounded-xl border border-slate-200 px-5 py-3 font-bold dark:border-slate-700">Batal</button>
              <button disabled={saveMutation.isPending} className="rounded-xl bg-[#0E5C44] px-5 py-3 font-bold text-white shadow-lg transition hover:scale-[1.03] disabled:opacity-50">{saveMutation.isPending ? 'Menyimpan...' : 'Simpan Jadwal'}</button>
            </footer>
          </form>
        </div>
      )}
    </div>
  )
}
