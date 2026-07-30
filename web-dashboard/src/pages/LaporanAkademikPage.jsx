import { useCallback, useEffect, useMemo, useState } from 'react'
import { ReportFilters, ReportHeader, ReportState, ReportStats, ReportTable, exportCsv } from '../components/reports/ReportKit'
import { reportService } from '../services/reportService'

const columns = [
  { key: 'siswa', label: 'Siswa', render: (row) => row.student?.full_name || row.student?.nama || '-', export: (row) => row.student?.full_name || row.student?.nama },
  { key: 'nis', label: 'NIS', render: (row) => row.student?.nis || '-', export: (row) => row.student?.nis },
  { key: 'mapel', label: 'Mata Pelajaran', render: (row) => row.subject?.name || '-', export: (row) => row.subject?.name },
  { key: 'assignment_score', label: 'Tugas' },
  { key: 'midterm_score', label: 'UTS' },
  { key: 'final_exam_score', label: 'UAS' },
  { key: 'final_score', label: 'Nilai Akhir' },
  { key: 'grade_letter', label: 'Predikat' },
  { key: 'is_passed', label: 'Ketuntasan', render: (row) => row.is_passed ? 'Tuntas' : 'Belum Tuntas', export: (row) => row.is_passed ? 'Tuntas' : 'Belum Tuntas' },
]

export default function LaporanAkademikPage() {
  const [filters, setFilters] = useState({ kelas_id: '', semester_id: '' })
  const [rows, setRows] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  const load = useCallback(async () => {
    try {
      setLoading(true)
      setError('')
      const result = await reportService.grades(Object.fromEntries(Object.entries(filters).filter(([, value]) => value)))
      setRows(result.data || result || [])
    } catch (err) {
      setError(err?.response?.data?.message || 'Laporan akademik gagal dimuat.')
    } finally {
      setLoading(false)
    }
  }, [filters])

  useEffect(() => { load() }, [load])
  const stats = useMemo(() => {
    const values = rows.map((row) => Number(row.final_score || 0))
    const passed = rows.filter((row) => row.is_passed).length
    return [
      { label: 'Catatan Nilai', value: rows.length, note: 'Nilai yang tercatat' },
      { label: 'Rata-rata', value: values.length ? (values.reduce((a, b) => a + b, 0) / values.length).toFixed(1) : 0, note: 'Nilai akhir' },
      { label: 'Tuntas', value: passed, note: 'Mencapai batas ketuntasan' },
      { label: 'Belum Tuntas', value: rows.length - passed, note: 'Perlu remedial' },
    ]
  }, [rows])

  return (
    <section className="content-grid">
      <article className="panel wide laporan-produksi">
        <ReportHeader title="Laporan Akademik & Nilai" description="Leger operasional dari nilai siswa yang telah tersimpan." onRefresh={load} onExport={() => exportCsv('laporan-akademik.csv', columns, rows)} />
        <ReportFilters>
          <label>ID Kelas<input value={filters.kelas_id} onChange={(e) => setFilters({ ...filters, kelas_id: e.target.value })} placeholder="Opsional" /></label>
          <label>ID Semester<input value={filters.semester_id} onChange={(e) => setFilters({ ...filters, semester_id: e.target.value })} placeholder="Opsional" /></label>
        </ReportFilters>
        <ReportState loading={loading} error={error}>
          <ReportStats items={stats} />
          <h4>Leger Nilai</h4>
          <ReportTable columns={columns} rows={rows} />
        </ReportState>
      </article>
    </section>
  )
}
