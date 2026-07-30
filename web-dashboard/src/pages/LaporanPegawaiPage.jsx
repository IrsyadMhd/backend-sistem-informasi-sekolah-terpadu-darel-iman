import { useCallback, useEffect, useMemo, useState } from 'react'
import { ReportFilters, ReportHeader, ReportState, ReportStats, ReportTable, exportCsv } from '../components/reports/ReportKit'
import { reportService } from '../services/reportService'

const columns = [
  { key: 'nip', label: 'NIP' },
  { key: 'full_name', label: 'Nama', render: (row) => row.full_name || row.nama || '-' },
  { key: 'gender', label: 'JK', render: (row) => row.gender || row.jenis_kelamin || '-' },
  { key: 'unit', label: 'Unit', render: (row) => row.education_unit?.name || row.unit?.name || '-', export: (row) => row.education_unit?.name || row.unit?.name },
  { key: 'position', label: 'Jabatan', render: (row) => row.position?.name || row.jabatan?.name || '-', export: (row) => row.position?.name || row.jabatan?.name },
  { key: 'status', label: 'Status', render: (row) => row.status_pegawai || row.status || '-' },
]

export default function LaporanPegawaiPage() {
  const [search, setSearch] = useState('')
  const [rows, setRows] = useState([])
  const [dashboard, setDashboard] = useState({})
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  const load = useCallback(async () => {
    try {
      setLoading(true)
      setError('')
      const [list, stats] = await Promise.all([reportService.employees({ search }), reportService.employeeStats()])
      setRows(list.data || list || [])
      setDashboard(stats || {})
    } catch (err) {
      setError(err?.response?.data?.message || 'Laporan pegawai gagal dimuat.')
    } finally {
      setLoading(false)
    }
  }, [search])

  useEffect(() => { load() }, [load])
  const stats = useMemo(() => [
    { label: 'Total Pegawai', value: dashboard.total || dashboard.total_pegawai || rows.length, note: 'Data pada sistem' },
    { label: 'Pegawai Aktif', value: dashboard.aktif || dashboard.pegawai_aktif || rows.filter((r) => ['aktif', 'ACTIVE'].includes(String(r.status_pegawai || r.status))).length, note: 'Status aktif' },
    { label: 'Guru', value: dashboard.guru || dashboard.total_guru, note: 'Tenaga pendidik' },
    { label: 'Tenaga Kependidikan', value: dashboard.tendik || dashboard.total_tendik, note: 'Tenaga kependidikan' },
  ], [dashboard, rows])

  return (
    <section className="content-grid">
      <article className="panel wide laporan-produksi">
        <ReportHeader title="Laporan Pegawai & Guru" description="Daftar pegawai aktif berdasarkan master pegawai yang telah berjalan." onRefresh={load} onExport={() => exportCsv('laporan-pegawai.csv', columns, rows)} />
        <ReportFilters><label>Cari pegawai<input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Nama atau NIP" /></label></ReportFilters>
        <ReportState loading={loading} error={error}>
          <ReportStats items={stats} />
          <h4>Daftar Pegawai</h4>
          <ReportTable columns={columns} rows={rows} />
        </ReportState>
      </article>
    </section>
  )
}

