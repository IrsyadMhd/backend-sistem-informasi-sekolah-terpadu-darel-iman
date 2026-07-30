import { useEffect, useMemo, useState } from 'react'
import {
  BookHeart, CalendarDays, Check, ChevronDown, CircleMinus, Clock3,
  History, ListChecks, Loader2, Pencil, Plus, Save, Trash2, UserRound, X,
} from 'lucide-react'
import Swal from 'sweetalert2'
import { useSearchParams } from 'react-router-dom'
import { mutabaahService } from '../services/mutabaahService'
import { useAuthStore } from '../stores/authStore'
import './MutabaahPage.css'

const statusMeta = {
  baik: { label: 'Baik', icon: Check, className: 'good' },
  kurang: { label: 'Kurang', icon: CircleMinus, className: 'less' },
  belum: { label: 'Belum', icon: X, className: 'missing' },
  na: { label: 'N/A', icon: Clock3, className: 'na' },
}

const emptyAgenda = {
  jenis_unit_id: '', unit_id: '', category: '', name: '', description: '',
  sort_order: 0, is_active: true, effective_from: '', effective_until: '',
}

const isoToday = () => new Date().toLocaleDateString('en-CA')

export default function MutabaahPage() {
  const user = useAuthStore((state) => state.user)
  const roles = user?.roles || []
  const permissions = user?.permissions || []
  const [searchParams, setSearchParams] = useSearchParams()
  const [tab, setTabState] = useState(searchParams.get('tab') === 'agenda' ? 'agenda' : 'input')
  const [options, setOptions] = useState({ students: [], mentors: [], jenis_units: [], units: [] })
  const [studentId, setStudentId] = useState('')
  const [mentorId, setMentorId] = useState('')
  const [date, setDate] = useState(isoToday)
  const [daily, setDaily] = useState(null)
  const [values, setValues] = useState({})
  const [note, setNote] = useState('')
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [agendaModal, setAgendaModal] = useState(false)
  const [agendaForm, setAgendaForm] = useState(emptyAgenda)
  const [agendas, setAgendas] = useState([])
  const [filterJenis, setFilterJenis] = useState('')
  const canManage = Boolean(options.can_manage_agenda) || roles.includes('Super Admin') || roles.some((r) => /tata usaha|\btu\b/i.test(r)) || permissions.includes('mutabaah.agenda.manage')
  const setTab = (nextTab) => {
    setTabState(nextTab)
    setSearchParams(nextTab === 'agenda' ? { tab: 'agenda' } : {})
  }

  useEffect(() => {
    setTabState(searchParams.get('tab') === 'agenda' ? 'agenda' : 'input')
  }, [searchParams])

  useEffect(() => {
    mutabaahService.options().then((data) => {
      setOptions(data)
      setStudentId(data.students?.[0]?.id || '')
      setMentorId(data.mentors?.find((m) => m.name === user?.name)?.id || data.mentors?.[0]?.id || '')
    }).catch(showError).finally(() => setLoading(false))
  }, [user?.name])

  useEffect(() => {
    if (!studentId || tab !== 'input') return
    setLoading(true)
    mutabaahService.daily(studentId, date).then((data) => {
      setDaily(data)
      setValues(Object.fromEntries(data.agendas.map((agenda) => [agenda.id, data.entries?.[agenda.id]?.status || ''])))
      setNote(data.daily_note?.note || '')
    }).catch(showError).finally(() => setLoading(false))
  }, [studentId, date, tab])

  useEffect(() => {
    if (tab !== 'agenda') return
    setLoading(true)
    mutabaahService.agendas(filterJenis ? { jenis_unit_id: filterJenis } : {})
      .then(setAgendas).catch(showError).finally(() => setLoading(false))
  }, [tab, filterJenis])

  const selectedStudent = options.students.find((item) => item.id === studentId)
  const grouped = useMemo(() => {
    const groups = {}
    daily?.agendas?.forEach((agenda) => {
      if (!groups[agenda.category]) groups[agenda.category] = []
      groups[agenda.category].push(agenda)
    })
    return groups
  }, [daily])
  const summary = useMemo(() => Object.keys(statusMeta).reduce((acc, key) => {
    acc[key] = Object.values(values).filter((value) => value === key).length
    return acc
  }, {}), [values])

  const setAll = (status) => setValues(Object.fromEntries((daily?.agendas || []).map((agenda) => [agenda.id, status])))

  const saveDaily = async () => {
    const missing = daily.agendas.filter((agenda) => !values[agenda.id])
    if (missing.length) {
      return Swal.fire({ icon: 'warning', title: 'Belum lengkap', text: `${missing.length} rincian agenda belum dinilai.`, confirmButtonColor: '#0E5C44' })
    }
    setSaving(true)
    try {
      const result = await mutabaahService.saveDaily({
        student_id: studentId, mentor_id: mentorId, date, note,
        entries: daily.agendas.map((agenda) => ({ agenda_id: agenda.id, status: values[agenda.id] })),
      })
      await Swal.fire({ icon: 'success', title: 'Tersimpan', text: result.message, timer: 1500, showConfirmButton: false })
    } catch (error) { showError(error) } finally { setSaving(false) }
  }

  const openAgenda = (agenda = null) => {
    setAgendaForm(agenda ? {
      ...agenda,
      unit_id: agenda.unit_id || '',
      effective_from: agenda.effective_from?.slice(0, 10) || '',
      effective_until: agenda.effective_until?.slice(0, 10) || '',
    } : { ...emptyAgenda, jenis_unit_id: filterJenis || options.jenis_units?.[0]?.uuid || '' })
    setAgendaModal(true)
  }

  const saveAgenda = async (event) => {
    event.preventDefault()
    setSaving(true)
    try {
      const payload = { ...agendaForm, unit_id: agendaForm.unit_id || null, effective_from: agendaForm.effective_from || null, effective_until: agendaForm.effective_until || null }
      const result = agendaForm.id
        ? await mutabaahService.updateAgenda(agendaForm.id, payload)
        : await mutabaahService.createAgenda(payload)
      setAgendaModal(false)
      setAgendas(await mutabaahService.agendas(filterJenis ? { jenis_unit_id: filterJenis } : {}))
      await Swal.fire({ icon: 'success', title: 'Berhasil', text: result.message, timer: 1400, showConfirmButton: false })
    } catch (error) { showError(error) } finally { setSaving(false) }
  }

  const removeAgenda = async (agenda) => {
    const confirm = await Swal.fire({ icon: 'question', title: 'Hapus rincian agenda?', text: agenda.name, showCancelButton: true, confirmButtonText: 'Ya, hapus', cancelButtonText: 'Batal', confirmButtonColor: '#dc2626' })
    if (!confirm.isConfirmed) return
    try {
      const result = await mutabaahService.deleteAgenda(agenda.id)
      setAgendas(await mutabaahService.agendas(filterJenis ? { jenis_unit_id: filterJenis } : {}))
      Swal.fire({ icon: 'success', title: 'Selesai', text: result.message })
    } catch (error) { showError(error) }
  }

  if (loading && !options.students.length) return <div className="mutabaah-loading"><Loader2 className="spin" /> Memuat modul Mutaba’ah...</div>

  return (
    <div className="mutabaah-page">
      <header className="mutabaah-title">
        <div><span className="eyebrow">Pembinaan Siswa</span><h1>Mutaba’ah Yaumiyyah</h1><p>Monitoring pembiasaan dan ibadah harian siswa berdasarkan jenis unit pendidikan.</p></div>
        <div className="mutabaah-tabs">
          <button className={tab === 'input' ? 'active' : ''} onClick={() => setTab('input')}><BookHeart size={17} /> Input Harian</button>
          {canManage && <button className={tab === 'agenda' ? 'active' : ''} onClick={() => setTab('agenda')}><ListChecks size={17} /> Rincian Agenda TU</button>}
        </div>
      </header>

      {tab === 'input' ? (
        <>
          <section className="mutabaah-toolbar">
            <LabeledSelect label="Pilih Siswa" icon={<UserRound size={17} />} value={studentId} onChange={setStudentId} options={options.students.map((s) => ({ value: s.id, label: `${s.name} — ${s.nis}` }))} />
            <label className="field"><span>Tanggal</span><div className="field-control"><CalendarDays size={17} /><input type="date" value={date} onChange={(e) => setDate(e.target.value)} /></div></label>
            <LabeledSelect label="Pilih Pembimbing" icon={<BookHeart size={17} />} value={mentorId} onChange={setMentorId} options={options.mentors.map((m) => ({ value: m.id, label: `${m.name}${m.position ? ` — ${m.position}` : ''}` }))} />
            <button className="outline-action" onClick={() => Swal.fire({ title: 'Riwayat siswa', text: 'Gunakan tanggal untuk melihat rekaman harian sebelumnya.', icon: 'info' })}><History size={18} /> Riwayat</button>
            <button className="primary-action" onClick={saveDaily} disabled={saving || !daily?.agendas?.length}>{saving ? <Loader2 className="spin" size={18} /> : <Save size={18} />} Simpan Semua</button>
          </section>

          <div className="mutabaah-grid">
            <section className="agenda-card">
              <div className="card-heading"><div><h2>Daftar Agenda Mutaba’ah</h2><p>{selectedStudent?.jenis_unit || 'Unit belum dipilih'} · {daily?.agendas?.length || 0} rincian aktif</p></div><div className="bulk-actions">{Object.entries(statusMeta).map(([key, meta]) => <button key={key} className={meta.className} onClick={() => setAll(key)}><meta.icon size={14} /> Semua {meta.label}</button>)}</div></div>
              {loading ? <div className="empty-box"><Loader2 className="spin" /> Memuat agenda...</div> : !daily?.agendas?.length ? <div className="empty-box"><ListChecks /><b>Agenda belum ditentukan TU</b><span>Silakan atur rincian agenda untuk jenis unit {selectedStudent?.jenis_unit || 'siswa ini'}.</span></div> : (
                <div className="agenda-table-wrap"><table className="agenda-table"><thead><tr><th>No</th><th>Agenda</th><th>Rincian Agenda</th><th>Penilaian {formatDate(date)}</th></tr></thead><tbody>
                  {Object.entries(grouped).map(([category, items]) => items.map((agenda, index) => (
                    <tr key={agenda.id}>
                      <td>{daily.agendas.findIndex((a) => a.id === agenda.id) + 1}</td>
                      {index === 0 && <td className="category-cell" rowSpan={items.length}><span>{category}</span></td>}
                      <td><b>{agenda.name}</b>{agenda.description && <small>{agenda.description}</small>}</td>
                      <td><div className="status-picker">{Object.entries(statusMeta).map(([key, meta]) => <button key={key} title={meta.label} className={`${meta.className} ${values[agenda.id] === key ? 'selected' : ''}`} onClick={() => setValues((old) => ({ ...old, [agenda.id]: key }))}><meta.icon size={17} /><span>{meta.label}</span></button>)}</div></td>
                    </tr>
                  )))}
                </tbody></table></div>
              )}
            </section>

            <aside className="mutabaah-side">
              <section className="info-card"><h3>Informasi Siswa</h3><div className="student-profile"><div className="avatar">{selectedStudent?.name?.split(' ').map((v) => v[0]).slice(0, 2).join('')}</div><div><b>{selectedStudent?.name || '-'}</b><span>NIS: {selectedStudent?.nis || '-'}</span><span>{selectedStudent?.class_name || 'Kelas belum terhubung'}</span><em>{selectedStudent?.unit_name || '-'}</em></div></div></section>
              <section className="info-card"><h3>Ringkasan Hari Ini</h3><div className="summary-grid">{Object.entries(statusMeta).map(([key, meta]) => <div className={`summary-pill ${meta.className}`} key={key}><span>{meta.label}</span><b>{summary[key]}</b></div>)}</div></section>
              <section className="info-card"><h3>Catatan Hari Ini</h3><textarea maxLength={1000} value={note} onChange={(e) => setNote(e.target.value)} placeholder="Tulis evaluasi dan tindak lanjut untuk siswa..." /><small>{note.length} / 1000</small></section>
            </aside>
          </div>
        </>
      ) : (
        <section className="agenda-management">
          <div className="management-head"><div><h2>Pengaturan Rincian Agenda</h2><p>Agenda umum berlaku per jenis unit. Pilih unit tertentu bila rincian hanya berlaku pada cabang tersebut.</p></div><div className="management-actions"><select value={filterJenis} onChange={(e) => setFilterJenis(e.target.value)}><option value="">Semua jenis unit</option>{options.jenis_units.map((j) => <option key={j.uuid} value={j.uuid}>{j.singkatan} — {j.nama_jenis}</option>)}</select><button className="primary-action" onClick={() => openAgenda()}><Plus size={18} /> Tambah Rincian</button></div></div>
          <div className="management-table-wrap"><table className="management-table"><thead><tr><th>Urut</th><th>Jenis / Unit</th><th>Kelompok</th><th>Rincian Agenda</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
            {agendas.map((agenda) => <tr key={agenda.id}><td>{agenda.sort_order}</td><td><b>{agenda.jenis_unit?.singkatan}</b><small>{agenda.unit?.name || 'Semua unit sejenis'}</small></td><td>{agenda.category}</td><td><b>{agenda.name}</b><small>{agenda.description}</small></td><td><span className={`state ${agenda.is_active ? 'active' : ''}`}>{agenda.is_active ? 'Aktif' : 'Nonaktif'}</span></td><td><div className="row-actions"><button onClick={() => openAgenda(agenda)}><Pencil size={16} /></button><button className="danger" onClick={() => removeAgenda(agenda)}><Trash2 size={16} /></button></div></td></tr>)}
            {!agendas.length && <tr><td colSpan="6"><div className="empty-box">Belum ada rincian agenda.</div></td></tr>}
          </tbody></table></div>
        </section>
      )}

      {agendaModal && <div className="modal-layer" onMouseDown={(e) => e.target === e.currentTarget && setAgendaModal(false)}><form className="agenda-modal" onSubmit={saveAgenda}><div className="modal-head"><div><h2>{agendaForm.id ? 'Ubah' : 'Tambah'} Rincian Agenda</h2><p>Rincian ditampilkan otomatis sesuai jenis unit siswa.</p></div><button type="button" onClick={() => setAgendaModal(false)}><X /></button></div><div className="form-grid">
        <label><span>Jenis Unit *</span><select required value={agendaForm.jenis_unit_id} onChange={(e) => setAgendaForm({ ...agendaForm, jenis_unit_id: e.target.value, unit_id: '' })}>{options.jenis_units.map((j) => <option value={j.uuid} key={j.uuid}>{j.singkatan} — {j.nama_jenis}</option>)}</select></label>
        <label><span>Unit Khusus (opsional)</span><select value={agendaForm.unit_id} onChange={(e) => setAgendaForm({ ...agendaForm, unit_id: e.target.value })}><option value="">Semua unit dalam jenis ini</option>{options.units.filter((u) => !agendaForm.jenis_unit_id || u.jenis_unit_id === agendaForm.jenis_unit_id).map((u) => <option value={u.id} key={u.id}>{u.name}</option>)}</select></label>
        <label><span>Kelompok Agenda *</span><input required value={agendaForm.category} onChange={(e) => setAgendaForm({ ...agendaForm, category: e.target.value })} placeholder="Contoh: Sholat, Adab, Tahfizh" /></label>
        <label><span>Nomor Urut</span><input type="number" min="0" value={agendaForm.sort_order} onChange={(e) => setAgendaForm({ ...agendaForm, sort_order: Number(e.target.value) })} /></label>
        <label className="full"><span>Rincian Agenda *</span><input required value={agendaForm.name} onChange={(e) => setAgendaForm({ ...agendaForm, name: e.target.value })} placeholder="Contoh: Sholat Subuh berjamaah" /></label>
        <label className="full"><span>Petunjuk / Deskripsi</span><textarea value={agendaForm.description || ''} onChange={(e) => setAgendaForm({ ...agendaForm, description: e.target.value })} /></label>
        <label><span>Berlaku Mulai</span><input type="date" value={agendaForm.effective_from || ''} onChange={(e) => setAgendaForm({ ...agendaForm, effective_from: e.target.value })} /></label>
        <label><span>Berlaku Sampai</span><input type="date" value={agendaForm.effective_until || ''} onChange={(e) => setAgendaForm({ ...agendaForm, effective_until: e.target.value })} /></label>
        <label className="toggle full"><input type="checkbox" checked={agendaForm.is_active} onChange={(e) => setAgendaForm({ ...agendaForm, is_active: e.target.checked })} /><span>Agenda aktif dan ditampilkan kepada pembimbing</span></label>
      </div><div className="modal-actions"><button type="button" onClick={() => setAgendaModal(false)}>Batal</button><button className="primary-action" disabled={saving}>{saving ? <Loader2 className="spin" /> : <Save />} Simpan Agenda</button></div></form></div>}
    </div>
  )
}

function LabeledSelect({ label, icon, value, onChange, options }) {
  return <label className="field"><span>{label}</span><div className="field-control">{icon}<select value={value} onChange={(e) => onChange(e.target.value)}>{options.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}</select><ChevronDown size={15} /></div></label>
}
function formatDate(date) { return new Intl.DateTimeFormat('id-ID', { weekday: 'short', day: '2-digit', month: 'short' }).format(new Date(`${date}T12:00:00`)) }
function showError(error) { Swal.fire({ icon: 'error', title: 'Tidak dapat memproses', text: error?.response?.data?.message || 'Terjadi kesalahan saat menghubungi server.', confirmButtonColor: '#0E5C44' }) }
