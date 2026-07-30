import { useCallback, useEffect, useMemo, useState } from 'react'
import { ReportHeader, ReportState, ReportStats, ReportTable, exportCsv } from '../components/reports/ReportKit'
import { reportService } from '../services/reportService'

const columns = [
  { key: 'siswa', label: 'Siswa', render: (row) => row.siswa?.full_name || row.student?.full_name || '-', export: (row) => row.siswa?.full_name || row.student?.full_name },
  { key: 'tugas', label: 'Tugas', render: (row) => row.penugasan?.judul || row.assignment?.title || '-', export: (row) => row.penugasan?.judul || row.assignment?.title },
  { key: 'submitted_at', label: 'Dikumpulkan', render: (row) => row.dikumpulkan_pada || row.submitted_at || '-' },
  { key: 'status', label: 'Status' },
  { key: 'nilai', label: 'Nilai', render: (row) => row.nilai ?? row.score ?? '-' },
]

const firstNumber = (object, keys) => {
  for (const key of keys) if (object?.[key] !== undefined) return object[key]
  return 0
}

export default function LaporanLmsPage() {
  const [data, setData] = useState({ material: {}, assignment: {}, submission: {}, reportCard: {}, rows: [] })
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  const load = useCallback(async () => {
    try {
      setLoading(true)
      setError('')
      const [material, assignment, submission, reportCard, list] = await Promise.all([
        reportService.materialStats(),
        reportService.assignmentStats(),
        reportService.submissionStats(),
        reportService.reportCardStats(),
        reportService.submissions(),
      ])
      setData({ material, assignment, submission, reportCard, rows: list.data || list || [] })
    } catch (err) {
      setError(err?.response?.data?.message || 'Laporan aktivitas LMS gagal dimuat.')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => { load() }, [load])
  const stats = useMemo(() => [
    { label: 'Materi', value: firstNumber(data.material, ['total', 'total_materi']), note: 'Materi pembelajaran' },
    { label: 'Penugasan', value: firstNumber(data.assignment, ['total', 'total_penugasan', 'total_tugas']), note: 'Tugas tercatat' },
    { label: 'Pengumpulan', value: firstNumber(data.submission, ['total', 'total_pengumpulan']), note: 'Tugas dikumpulkan' },
    { label: 'Belum Dinilai', value: firstNumber(data.submission, ['belum_dinilai', 'pending', 'total_belum_dinilai']), note: 'Perlu tindak lanjut guru' },
    { label: 'Rapor', value: firstNumber(data.reportCard, ['total', 'total_rapor']), note: 'Rapor digital' },
  ], [data])

  return (
    <section className="content-grid">
      <article className="panel wide laporan-produksi">
        <ReportHeader title="Laporan Aktivitas LMS" description="Ringkasan materi, penugasan, pengumpulan, penilaian, dan rapor dari modul LMS aktif." onRefresh={load} onExport={() => exportCsv('laporan-lms.csv', columns, data.rows)} />
        <ReportState loading={loading} error={error}>
          <ReportStats items={stats} />
          <h4>Status Pengumpulan Tugas</h4>
          <ReportTable columns={columns} rows={data.rows} />
        </ReportState>
      </article>
    </section>
  )
}

