import { useCallback, useEffect, useMemo, useState } from 'react'
import {
  CalendarDays,
  ChevronLeft,
  ChevronRight,
  ClipboardCheck,
  Download,
  Eye,
  FileSpreadsheet,
  FileText,
  MoreVertical,
  Printer,
  RefreshCcw,
  Stethoscope,
  UserCheck,
  UserX,
  X,
} from 'lucide-react'
import {
  Area,
  AreaChart,
  CartesianGrid,
  Cell,
  Legend,
  Pie,
  PieChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts'
import { exportCsv } from '../components/reports/ReportKit'
import { reportService } from '../services/reportService'

const PAGE_SIZE = 5
const formatAngka = (value) => new Intl.NumberFormat('id-ID').format(Number(value || 0))
const normalisasiStatus = (status = '') => status.toLowerCase()
const statusLabel = { hadir: 'Hadir', terlambat: 'Terlambat', izin: 'Izin', sakit: 'Sakit', alpa: 'Alpha' }
const formatTanggal = (value) => {
  if (!value) return '-'
  return new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(`${value}T00:00:00`))
}

const columns = [
  { key: 'tanggal', label: 'Tanggal' },
  { key: 'siswa', label: 'Siswa', export: (row) => row.siswa?.full_name },
  { key: 'nis', label: 'NIS', export: (row) => row.siswa?.nis },
  { key: 'mapel', label: 'Mata Pelajaran', export: (row) => row.jadwal_pelajaran?.subject?.name },
  { key: 'status_hadir', label: 'Status' },
  { key: 'catatan', label: 'Catatan' },
]

const warnaStatus = {
  hadir: '#12a968',
  izin: '#3182f6',
  sakit: '#ff8a1f',
  alpa: '#ff4668',
  terlambat: '#8b5cf6',
}

function rentangPeriode(period, current) {
  if (period === 'semua') {
    return { ...current, date_from: '', date_to: '', period }
  }
  const akhir = new Date()
  const awal = new Date()
  if (period === 'minggu') awal.setDate(akhir.getDate() - 6)
  else if (period === 'bulan') awal.setDate(1)
  else awal.setMonth(akhir.getMonth() - 5, 1)
  const iso = (date) => date.toISOString().slice(0, 10)
  return { ...current, date_from: iso(awal), date_to: iso(akhir), period }
}

export default function LaporanAbsensiPage() {
  const [filters, setFilters] = useState({ date_from: '', date_to: '', status: '', subject_id: '' })
  const [draft, setDraft] = useState({ ...filters, period: 'semua' })
  const [report, setReport] = useState({ summary: {}, rows: [] })
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [page, setPage] = useState(1)
  const [selectedRow, setSelectedRow] = useState(null)
  const [openMenuId, setOpenMenuId] = useState(null)
  const [notice, setNotice] = useState('')

  const load = useCallback(async () => {
    try {
      setLoading(true)
      setError('')
      const params = Object.fromEntries(Object.entries(filters).filter(([, value]) => value))
      const data = await reportService.attendance(params)
      setReport(data || { summary: {}, rows: [] })
    } catch (err) {
      setError(err?.response?.data?.message || 'Laporan absensi gagal dimuat.')
    } finally {
      setLoading(false)
    }
  }, [filters])

  useEffect(() => { load() }, [load])
  useEffect(() => {
    if (!notice) return undefined
    const timeout = window.setTimeout(() => setNotice(''), 2600)
    return () => window.clearTimeout(timeout)
  }, [notice])

  const rows = useMemo(() => report.rows || [], [report.rows])
  const summary = useMemo(() => report.summary || {}, [report.summary])
  const total = Number(summary.total || 0)
  const subjectOptions = useMemo(() => {
    const subjects = new Map()
    rows.forEach((row) => {
      const subject = row.jadwal_pelajaran?.subject
      if (subject?.id) subjects.set(String(subject.id), subject.name)
    })
    return [...subjects.entries()]
  }, [rows])

  const cards = useMemo(() => [
    { label: 'Total Kehadiran', value: summary.present, icon: UserCheck, tone: 'green', percent: total ? (Number(summary.present || 0) / total) * 100 : 0 },
    { label: 'Izin', value: summary.permission, icon: ClipboardCheck, tone: 'blue', percent: total ? (Number(summary.permission || 0) / total) * 100 : 0 },
    { label: 'Sakit', value: summary.sick, icon: Stethoscope, tone: 'orange', percent: total ? (Number(summary.sick || 0) / total) * 100 : 0 },
    { label: 'Alpha', value: summary.absent, icon: UserX, tone: 'red', percent: total ? (Number(summary.absent || 0) / total) * 100 : 0 },
  ], [summary, total])

  const distribution = useMemo(() => [
    { name: 'Hadir', value: Number(summary.present || 0), color: warnaStatus.hadir },
    { name: 'Izin', value: Number(summary.permission || 0), color: warnaStatus.izin },
    { name: 'Sakit', value: Number(summary.sick || 0), color: warnaStatus.sakit },
    { name: 'Alpha', value: Number(summary.absent || 0), color: warnaStatus.alpa },
  ], [summary])

  const chartData = useMemo(() => {
    const grouped = new Map()
    rows.forEach((row) => {
      const key = row.tanggal || 'Tanpa tanggal'
      if (!grouped.has(key)) grouped.set(key, { tanggal: key, hadir: 0, izin: 0, sakit: 0, alpa: 0 })
      const item = grouped.get(key)
      const status = normalisasiStatus(row.status_hadir)
      if (status === 'hadir' || status === 'terlambat') item.hadir += 1
      else if (Object.hasOwn(item, status)) item[status] += 1
    })
    return [...grouped.values()]
      .sort((a, b) => String(a.tanggal).localeCompare(String(b.tanggal)))
      .slice(-12)
      .map((item) => ({ ...item, label: formatTanggal(item.tanggal).replace(/\s\d{4}$/, '') }))
  }, [rows])

  const totalPages = Math.max(1, Math.ceil(rows.length / PAGE_SIZE))
  const paginatedRows = rows.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE)

  const applyFilters = () => {
    const { period: _period, ...next } = draft
    setPage(1)
    setFilters(next)
  }

  const resetFilters = () => {
    const empty = { date_from: '', date_to: '', status: '', subject_id: '' }
    setDraft({ ...empty, period: 'semua' })
    setFilters(empty)
    setPage(1)
  }

  const changePeriod = (period) => {
    const nextDraft = rentangPeriode(period, draft)
    const { period: _period, ...nextFilters } = nextDraft
    setDraft(nextDraft)
    setFilters(nextFilters)
    setPage(1)
  }

  const downloadRows = (downloadRows = rows, filename = 'laporan-absensi.csv') => {
    if (!downloadRows.length) {
      setNotice('Belum ada data yang dapat diekspor.')
      return
    }
    exportCsv(filename, columns, downloadRows)
    setNotice('File laporan berhasil diunduh.')
  }

  const printReport = () => {
    setOpenMenuId(null)
    window.print()
  }

  return (
    <section className="attendance-report-page">
      <header className="attendance-report-header">
        <div>
          <h1>Laporan Absensi</h1>
          <p>Dashboard <span>/</span> Laporan <span>/</span> Laporan Absensi</p>
        </div>
        <div className="attendance-report-actions">
          <button type="button" onClick={() => downloadRows()}><FileSpreadsheet size={17} /> Export Excel</button>
          <button type="button" onClick={printReport}><FileText size={17} /> Export PDF</button>
          <button type="button" className="primary" onClick={printReport}><Printer size={17} /> Cetak Laporan</button>
        </div>
      </header>

      <div className="attendance-report-layout">
        <main className="attendance-report-main">
          <div className="attendance-summary-grid">
            {cards.map(({ label, value, icon: Icon, tone, percent }) => (
              <article className={`attendance-summary-card tone-${tone}`} key={label}>
                <div className="summary-icon"><Icon size={24} /></div>
                <div>
                  <p>{label}</p>
                  <div className="summary-value"><strong>{formatAngka(value)}</strong><span>{percent.toFixed(1)}%</span></div>
                  <small>dari total data</small>
                </div>
              </article>
            ))}
          </div>

          {error && <div className="attendance-report-alert">{error} <button type="button" onClick={load}>Coba lagi</button></div>}

          <div className="attendance-chart-grid">
            <article className="attendance-report-card attendance-trend-card">
              <div className="attendance-card-heading">
                <h2>Grafik Kehadiran</h2>
                <select value={draft.period} onChange={(event) => changePeriod(event.target.value)}>
                  {draft.period === 'custom' && <option value="custom">Rentang Kustom</option>}
                  <option value="semua">Semua Data</option>
                  <option value="minggu">7 Hari Terakhir</option>
                  <option value="bulan">Bulan Ini</option>
                  <option value="semester">6 Bulan Terakhir</option>
                </select>
              </div>
              <div className="attendance-chart">
                {loading ? <div className="attendance-loading">Memuat grafik...</div> : (
                  <ResponsiveContainer width="100%" height="100%">
                    <AreaChart data={chartData} margin={{ top: 18, right: 14, left: -18, bottom: 0 }}>
                      <defs>
                        <linearGradient id="hadirGradient" x1="0" y1="0" x2="0" y2="1">
                          <stop offset="0%" stopColor="#12a968" stopOpacity={0.22} />
                          <stop offset="100%" stopColor="#12a968" stopOpacity={0} />
                        </linearGradient>
                      </defs>
                      <CartesianGrid stroke="#edf1f5" vertical={false} />
                      <XAxis dataKey="label" tickLine={false} axisLine={false} tick={{ fill: '#718096', fontSize: 11 }} />
                      <YAxis allowDecimals={false} tickLine={false} axisLine={false} tick={{ fill: '#718096', fontSize: 11 }} />
                      <Tooltip />
                      <Legend iconType="circle" iconSize={7} />
                      <Area type="monotone" dataKey="hadir" name="Hadir" stroke={warnaStatus.hadir} fill="url(#hadirGradient)" strokeWidth={2.2} />
                      <Area type="monotone" dataKey="izin" name="Izin" stroke={warnaStatus.izin} fill="transparent" strokeWidth={2} />
                      <Area type="monotone" dataKey="sakit" name="Sakit" stroke={warnaStatus.sakit} fill="transparent" strokeWidth={2} />
                      <Area type="monotone" dataKey="alpa" name="Alpha" stroke={warnaStatus.alpa} fill="transparent" strokeWidth={2} />
                    </AreaChart>
                  </ResponsiveContainer>
                )}
              </div>
            </article>

            <article className="attendance-report-card attendance-distribution-card">
              <h2>Distribusi Kehadiran</h2>
              <div className="distribution-content">
                <div className="distribution-chart">
                  <ResponsiveContainer width="100%" height="100%">
                    <PieChart>
                      <Pie data={distribution} dataKey="value" innerRadius="62%" outerRadius="88%" paddingAngle={1}>
                        {distribution.map((item) => <Cell key={item.name} fill={item.color} />)}
                      </Pie>
                    </PieChart>
                  </ResponsiveContainer>
                  <div className="distribution-total"><strong>{formatAngka(total)}</strong><span>Total Data</span></div>
                </div>
                <div className="distribution-legend">
                  {distribution.map((item) => (
                    <div key={item.name}><i style={{ background: item.color }} /><p><span>{item.name}</span><strong>{formatAngka(item.value)} ({total ? ((item.value / total) * 100).toFixed(1) : '0.0'}%)</strong></p></div>
                  ))}
                </div>
              </div>
            </article>
          </div>

          <article className="attendance-report-card attendance-detail-card">
            <div className="attendance-card-heading">
              <div><h2>Rincian Absensi</h2><p>Data kehadiran siswa berdasarkan filter aktif</p></div>
              <button type="button" className="icon-button" onClick={load} aria-label="Muat ulang"><RefreshCcw size={17} /></button>
            </div>
            <div className="attendance-table-wrap">
              <table>
                <thead><tr><th>No</th><th>Siswa</th><th>NIS</th><th>Tanggal</th><th>Mata Pelajaran</th><th>Status</th><th>Catatan</th><th>Aksi</th></tr></thead>
                <tbody>
                  {!loading && paginatedRows.length ? paginatedRows.map((row, index) => {
                    const status = normalisasiStatus(row.status_hadir)
                    return (
                      <tr key={row.id || index}>
                        <td>{(page - 1) * PAGE_SIZE + index + 1}</td>
                        <td><strong>{row.siswa?.full_name || '-'}</strong></td>
                        <td>{row.siswa?.nis || '-'}</td>
                        <td>{formatTanggal(row.tanggal)}</td>
                        <td>{row.jadwal_pelajaran?.subject?.name || '-'}</td>
                        <td><span className={`attendance-status status-${status}`}>{statusLabel[status] || row.status_hadir || '-'}</span></td>
                        <td>{row.catatan || '-'}</td>
                        <td>
                          <div className="table-actions">
                            <button type="button" title="Lihat detail" onClick={() => { setSelectedRow(row); setOpenMenuId(null) }}><Eye size={16} /></button>
                            <div className="row-action-menu-wrap">
                              <button type="button" title="Menu" aria-expanded={openMenuId === (row.id || index)} onClick={() => setOpenMenuId((current) => current === (row.id || index) ? null : (row.id || index))}><MoreVertical size={16} /></button>
                              {openMenuId === (row.id || index) && (
                                <div className="row-action-menu">
                                  <button type="button" onClick={() => { setSelectedRow(row); setOpenMenuId(null) }}><Eye size={14} /> Lihat detail</button>
                                  <button type="button" onClick={() => { downloadRows([row], `absensi-${row.siswa?.nis || row.id || 'siswa'}.csv`); setOpenMenuId(null) }}><Download size={14} /> Unduh data</button>
                                  <button type="button" onClick={printReport}><Printer size={14} /> Cetak laporan</button>
                                </div>
                              )}
                            </div>
                          </div>
                        </td>
                      </tr>
                    )
                  }) : (
                    <tr><td colSpan="8" className="attendance-empty">{loading ? 'Memuat data absensi...' : 'Belum ada data pada filter ini.'}</td></tr>
                  )}
                </tbody>
              </table>
            </div>
            <footer className="attendance-table-footer">
              <span>Menampilkan {rows.length ? (page - 1) * PAGE_SIZE + 1 : 0}–{Math.min(page * PAGE_SIZE, rows.length)} dari {rows.length} data</span>
              <div>
                <button type="button" disabled={page === 1} onClick={() => setPage((value) => value - 1)}><ChevronLeft size={16} /></button>
                <strong>{page}</strong><span>dari {totalPages}</span>
                <button type="button" disabled={page === totalPages} onClick={() => setPage((value) => value + 1)}><ChevronRight size={16} /></button>
              </div>
            </footer>
          </article>
        </main>

        <aside className="attendance-report-sidebar">
          <article className="attendance-report-card attendance-filter-card">
            <h2>Filter Laporan</h2>
            <label>Mata Pelajaran<select value={draft.subject_id} onChange={(event) => setDraft({ ...draft, subject_id: event.target.value })}><option value="">Semua Mata Pelajaran</option>{subjectOptions.map(([id, name]) => <option key={id} value={id}>{name}</option>)}</select></label>
            <label>Periode<select value={draft.period} onChange={(event) => changePeriod(event.target.value)}>{draft.period === 'custom' && <option value="custom">Rentang Kustom</option>}<option value="semua">Semua Data</option><option value="minggu">7 Hari Terakhir</option><option value="bulan">Bulan Ini</option><option value="semester">6 Bulan Terakhir</option></select></label>
            <div className="date-filter-row">
              <label>Tanggal Mulai<div><input type="date" value={draft.date_from} onChange={(event) => setDraft({ ...draft, date_from: event.target.value, period: 'custom' })} /><CalendarDays size={15} /></div></label>
              <label>Tanggal Selesai<div><input type="date" value={draft.date_to} onChange={(event) => setDraft({ ...draft, date_to: event.target.value, period: 'custom' })} /><CalendarDays size={15} /></div></label>
            </div>
            <label>Jenis Absensi<select value={draft.status} onChange={(event) => setDraft({ ...draft, status: event.target.value })}><option value="">Semua</option><option value="hadir">Hadir</option><option value="terlambat">Terlambat</option><option value="izin">Izin</option><option value="sakit">Sakit</option><option value="alpa">Alpha</option></select></label>
            <div className="filter-buttons"><button type="button" onClick={resetFilters}>Reset</button><button type="button" className="primary" onClick={applyFilters}>Terapkan</button></div>
          </article>

          <article className="attendance-report-card quick-actions-card">
            <h2>Aksi Cepat</h2>
            <button type="button" onClick={() => downloadRows()}><span className="quick-icon excel"><FileSpreadsheet size={18} /></span><span><strong>Export Excel</strong><small>Unduh laporan dalam format CSV</small></span><Download size={16} /></button>
            <button type="button" onClick={printReport}><span className="quick-icon pdf"><FileText size={18} /></span><span><strong>Export PDF</strong><small>Simpan laporan sebagai PDF</small></span><ChevronRight size={16} /></button>
            <button type="button" onClick={printReport}><span className="quick-icon print"><Printer size={18} /></span><span><strong>Cetak Laporan</strong><small>Cetak laporan absensi siswa</small></span><ChevronRight size={16} /></button>
          </article>
        </aside>
      </div>

      {selectedRow && (
        <div className="attendance-detail-modal-backdrop" role="presentation" onMouseDown={() => setSelectedRow(null)}>
          <article className="attendance-detail-modal" role="dialog" aria-modal="true" aria-labelledby="attendance-detail-title" onMouseDown={(event) => event.stopPropagation()}>
            <header>
              <div><small>Detail Kehadiran</small><h2 id="attendance-detail-title">{selectedRow.siswa?.full_name || 'Siswa'}</h2></div>
              <button type="button" aria-label="Tutup detail" onClick={() => setSelectedRow(null)}><X size={19} /></button>
            </header>
            <dl>
              <div><dt>NIS</dt><dd>{selectedRow.siswa?.nis || '-'}</dd></div>
              <div><dt>Tanggal</dt><dd>{formatTanggal(selectedRow.tanggal)}</dd></div>
              <div><dt>Mata Pelajaran</dt><dd>{selectedRow.jadwal_pelajaran?.subject?.name || '-'}</dd></div>
              <div><dt>Status</dt><dd><span className={`attendance-status status-${normalisasiStatus(selectedRow.status_hadir)}`}>{statusLabel[normalisasiStatus(selectedRow.status_hadir)] || selectedRow.status_hadir || '-'}</span></dd></div>
              <div className="full"><dt>Catatan</dt><dd>{selectedRow.catatan || 'Tidak ada catatan.'}</dd></div>
            </dl>
            <footer>
              <button type="button" onClick={() => downloadRows([selectedRow], `absensi-${selectedRow.siswa?.nis || selectedRow.id || 'siswa'}.csv`)}><Download size={16} /> Unduh Data</button>
              <button type="button" className="primary" onClick={printReport}><Printer size={16} /> Cetak</button>
            </footer>
          </article>
        </div>
      )}

      {notice && <div className="attendance-report-toast" role="status">{notice}</div>}
    </section>
  )
}
