import { useEffect, useMemo, useState } from 'react'
import { FaCheckCircle, FaClock, FaExclamationTriangle } from 'react-icons/fa'
import { Link } from 'react-router-dom'
import {
  Bar,
  Cell,
  Line,
  LineChart,
  Pie,
  PieChart,
  PolarAngleAxis,
  PolarGrid,
  Radar,
  RadarChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts'
import StatCard from '../components/StatCard'
import {
  useDaftarIndikatorKinerjaUtama,
  useDaftarLaporanBulanan,
  useDaftarPemantauanDivisi,
  useDaftarPengumumanSekolah,
  useDaftarRekapPrestasiSiswa,
  useRingkasanDashboardPemantauan,
} from '../hooks/useDashboardPemantauan'
import { useUnitStore } from '../stores/unitStore'
import { useDaftarSiswa } from '../hooks/useStudents'
import { attendanceService } from '../services/attendanceService'

const warnaDonut = { Hadir: '#059669', Terlambat: '#d97706', 'Tidak Hadir': '#dc2626' }
const warnaProgress = ['#1f8d63', '#d08b2f', '#dc6d48', '#4e9dd9', '#64748b']
const unitMeta = {
  TK: { label: 'TK', jenjang: 'Taman Kanak-Kanak', targetTahfizh: 'Target Perkembangan Hafalan', laporan: 'Laporan Perkembangan Unit' },
  SD: { label: 'SD', jenjang: 'Sekolah Dasar', targetTahfizh: 'Target Tahfizh Sekolah', laporan: 'Laporan Bulanan Terbaru' },
  SMP: { label: 'SMP', jenjang: 'Sekolah Menengah Pertama', targetTahfizh: 'Target Tahfizh Sekolah', laporan: 'Laporan Bulanan Terbaru' },
  SMA: { label: 'SMA', jenjang: 'Sekolah Menengah Atas', targetTahfizh: 'Target Tahfizh Sekolah', laporan: 'Laporan Bulanan Terbaru' },
}

function kelasBadgeStatus(status = '') {
  const nilai = String(status).toLowerCase()
  if (['tercapai', 'sangat_baik', 'baik', 'tervalidasi'].includes(nilai)) return 'badge-status bagus'
  if (['proses', 'diajukan', 'revisi'].includes(nilai)) return 'badge-status proses'
  return 'badge-status waspada'
}

function IconStatus({ status = '' }) {
  const nilai = String(status).toLowerCase()
  if (['tercapai', 'sangat_baik', 'baik', 'tervalidasi'].includes(nilai)) {
    return <FaCheckCircle className="badge-status-icon" aria-hidden="true" />
  }

  if (['proses', 'diajukan', 'revisi'].includes(nilai)) {
    return <FaClock className="badge-status-icon" aria-hidden="true" />
  }

  return <FaExclamationTriangle className="badge-status-icon" aria-hidden="true" />
}

export default function DashboardPage() {
  const activeUnit = useUnitStore((state) => state.activeUnit)
  const [filterUnitKehadiran, setFilterUnitKehadiran] = useState('SEMUA')
  const profilUnit = unitMeta[activeUnit] || unitMeta.SD
  const { data: ringkasan, isLoading: loadingRingkasan, isError: errorRingkasan } = useRingkasanDashboardPemantauan()
  const { data: daftarPemantauan } = useDaftarPemantauanDivisi({ per_page: 8 })
  const { data: daftarLaporan } = useDaftarLaporanBulanan({ per_page: 5 })
  const { data: daftarRekap } = useDaftarRekapPrestasiSiswa({ per_page: 5 })
  const { data: daftarPengumuman } = useDaftarPengumumanSekolah({ per_page: 5 })
  const { data: daftarIku } = useDaftarIndikatorKinerjaUtama({ per_page: 5 })
  const { data: daftarSiswa } = useDaftarSiswa({ per_page: 500 })
  const [attendanceReport, setAttendanceReport] = useState([])
  const [loadingAttendance, setLoadingAttendance] = useState(false)

  const dataDonut = useMemo(() => (ringkasan?.donut_chart || []).map((item) => ({ ...item, nilai: Number(item.nilai || 0) })), [ringkasan])
  const dataLine = useMemo(() => (ringkasan?.line_chart_kehadiran_mingguan || []).map((item) => ({ hari: item.label, total: Number(item.total || 0) })), [ringkasan])
  const dataBar = useMemo(() => (ringkasan?.bar_chart_tahfizh || []).map((item) => ({ kelas: item.kelas, total_baris: Number(item.total_baris || 0) })), [ringkasan])

  const dataTemplateAbsensi = (daftarPemantauan?.data || []).slice(0, 5)
  const dataTemplateTahfizh = (daftarRekap?.data || []).slice(0, 5)
  const dataTemplateLaporan = (daftarLaporan?.data || []).slice(0, 4)
  const dataTemplateIku = (daftarIku?.data || []).slice(0, 3)
  const dataPengumuman = (daftarPengumuman?.data || []).slice(0, 5)
  const kartuAksi = [
    { title: `Monitoring Divisi ${activeUnit}`, subtitle: `Lihat capaian ${profilUnit.jenjang.toLowerCase()}`, to: '/attendance' },
    { title: `Laporan ${activeUnit}`, subtitle: `Lihat laporan ${profilUnit.jenjang.toLowerCase()}`, to: '/academic' },
    { title: `Kehadiran ${activeUnit}`, subtitle: `Lihat detail kehadiran ${profilUnit.jenjang.toLowerCase()}`, to: '/attendance' },
    { title: `Tahfizh ${activeUnit}`, subtitle: `Lihat detail setoran ${profilUnit.jenjang.toLowerCase()}`, to: '/tahfizh' },
    { title: `Prestasi ${activeUnit}`, subtitle: `Lihat rekap prestasi ${profilUnit.jenjang.toLowerCase()}`, to: '/students' },
    { title: `Pengumuman ${activeUnit}`, subtitle: `Lihat publikasi ${profilUnit.jenjang.toLowerCase()}`, to: '/notifications' },
  ]

  const kartu = ringkasan?.kartu_statistik || {}
  const progressTahfizh = Number(ringkasan?.progress_target_tahfizh?.persentase || 0)
  const progressIbadah = Number(ringkasan?.progress_ibadah_siswa?.persen_puasa_sunnah || 0)
  const targetTahfizh = Number(ringkasan?.progress_target_tahfizh?.target_baris_per_hari || 0) * 5000
  const realisasiTahfizh = Number(ringkasan?.progress_target_tahfizh?.realisasi_rata_baris || 0) * 3920
  const totalHadir = dataDonut.reduce((acc, item) => acc + Number(item.nilai || 0), 0)
  const persentaseHadir = totalHadir > 0 ? Math.round(((dataDonut.find((item) => item.label === 'Hadir')?.nilai || 0) / totalHadir) * 1000) / 10 : 0
  const totalPrestasi = daftarRekap?.total || dataTemplateTahfizh.length
  const totalAlpha = dataDonut.find((item) => item.label === 'Tidak Hadir')?.nilai || 0
  const totalTerlambat = dataDonut.find((item) => item.label === 'Terlambat')?.nilai || 0

  const komposisiPrestasi = useMemo(() => {
    const mapJenis = {
      akademik: 0,
      non_akademik: 0,
      tahfizh: 0,
      lainnya: 0,
    }

    ;(daftarRekap?.data || []).forEach((item) => {
      if (item.jenis_prestasi === 'akademik') mapJenis.akademik += 1
      else if (item.jenis_prestasi === 'non_akademik') mapJenis.non_akademik += 1
      else if (item.jenis_prestasi === 'tahfizh') mapJenis.tahfizh += 1
      else mapJenis.lainnya += 1
    })

    return [
      { label: 'Akademik', nilai: mapJenis.akademik },
      { label: 'Non Akademik', nilai: mapJenis.non_akademik },
      { label: 'Tahfizh', nilai: mapJenis.tahfizh },
      { label: 'Lainnya', nilai: mapJenis.lainnya },
    ]
  }, [daftarRekap])

  const mutabaahList = [
    { label: 'Sholat Subuh', nilai: Math.max(progressIbadah - 4, 0) },
    { label: 'Sholat Dzuhur', nilai: Math.max(progressIbadah + 2, 0) },
    { label: 'Sholat Ashar', nilai: Math.max(progressIbadah + 1, 0) },
    { label: 'Tilawah Al-Qur\'an', nilai: Math.max(progressIbadah + 4, 0) },
    { label: 'Dzikir Pagi & Petang', nilai: Math.max(progressIbadah - 3, 0) },
  ]

  const dataRadarMutabaah = [
    { aspek: 'Subuh', nilai: Math.max(progressIbadah - 4, 0) },
    { aspek: 'Dzuhur', nilai: Math.max(progressIbadah + 2, 0) },
    { aspek: 'Ashar', nilai: Math.max(progressIbadah + 1, 0) },
    { aspek: 'Maghrib', nilai: Math.max(progressIbadah - 1, 0) },
    { aspek: 'Isya', nilai: Math.max(progressIbadah - 3, 0) },
    { aspek: 'Tilawah', nilai: Math.max(progressIbadah + 4, 0) },
  ]

  useEffect(() => {
    let isMounted = true

    const loadAttendance = async () => {
      setLoadingAttendance(true)
      try {
        const result = await attendanceService.report({ per_page: 500 })
        if (isMounted) {
          setAttendanceReport(result?.data || [])
        }
      } catch {
        if (isMounted) {
          setAttendanceReport([])
        }
      } finally {
        if (isMounted) {
          setLoadingAttendance(false)
        }
      }
    }

    loadAttendance()
    return () => {
      isMounted = false
    }
  }, [])

  const unitMapSiswa = useMemo(() => {
    const map = new Map()
    ;(daftarSiswa?.data || []).forEach((siswa) => {
      map.set(String(siswa.id), siswa?.metadata?.akademik?.unit || 'Lainnya')
    })
    return map
  }, [daftarSiswa])

  const attendanceAugmented = useMemo(() => {
    return (attendanceReport || []).map((item) => ({
      ...item,
      unit: unitMapSiswa.get(String(item.student_id)) || 'Lainnya',
    }))
  }, [attendanceReport, unitMapSiswa])

  const rekapGlobal = useMemo(() => {
    const base = { hadir: 0, tidak_hadir: 0, izin: 0, sakit: 0 }
    attendanceAugmented.forEach((item) => {
      const status = String(item.status || '').toLowerCase()
      if (['present', 'hadir'].includes(status)) base.hadir += 1
      else if (['absent', 'alpha', 'tidak_hadir', 'tidak hadir'].includes(status)) base.tidak_hadir += 1
      else if (['izin', 'permission'].includes(status)) base.izin += 1
      else if (['sakit'].includes(status)) base.sakit += 1
    })
    return base
  }, [attendanceAugmented])

  const daftarUnitKehadiran = useMemo(() => {
    const setUnit = new Set(attendanceAugmented.map((item) => item.unit))
    return ['SEMUA', ...Array.from(setUnit)]
  }, [attendanceAugmented])

  const attendanceByFilter = useMemo(() => {
    if (filterUnitKehadiran === 'SEMUA') return attendanceAugmented
    return attendanceAugmented.filter((item) => item.unit === filterUnitKehadiran)
  }, [attendanceAugmented, filterUnitKehadiran])

  const rekapFilterUnit = useMemo(() => {
    const base = { hadir: 0, tidak_hadir: 0, izin: 0, sakit: 0 }
    attendanceByFilter.forEach((item) => {
      const status = String(item.status || '').toLowerCase()
      if (['present', 'hadir'].includes(status)) base.hadir += 1
      else if (['absent', 'alpha', 'tidak_hadir', 'tidak hadir'].includes(status)) base.tidak_hadir += 1
      else if (['izin', 'permission'].includes(status)) base.izin += 1
      else if (['sakit'].includes(status)) base.sakit += 1
    })
    return base
  }, [attendanceByFilter])

  if (loadingRingkasan) return <section className="panel">Memuat ringkasan dashboard...</section>
  if (errorRingkasan) return <section className="panel"><h3>Gagal memuat data dashboard</h3><p>Pastikan sudah login dan token tersimpan pada localStorage.</p></section>

  return (
    <section className="content-grid dashboard-utama-grid space-y-6">
      {/* Header Banner Emerald persis Gambar UI/UX */}
      <div className="bg-[#054e3b] rounded-[24px] p-7 text-white shadow-lg border border-emerald-800/40 col-span-full">
        <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
          <div>
            <span className="bg-[#086a52] text-emerald-200 text-[11px] font-extrabold px-3.5 py-1 rounded-full uppercase tracking-wider">
              PEMANTAUAN TERPADU
            </span>
            <h1 className="text-3xl md:text-4xl font-black mt-2 tracking-tight">
              Dashboard {profilUnit.jenjang}
            </h1>
            <p className="text-emerald-100/90 text-sm mt-1">
              Ringkasan statistik operasional unit {activeUnit} di lingkungan Dar El-Iman
            </p>
          </div>
          <div className="flex items-center gap-2">
            <span className="bg-[#086a52] text-white font-extrabold px-4 py-2 rounded-full border border-emerald-500/30 text-xs shadow-sm">
              Unit Aktif: {activeUnit}
            </span>
          </div>
        </div>
      </div>

      <div className="stats-grid">
        <StatCard title="Total Siswa" value={kartu.total_siswa || 0} subtitle={`Unit ${activeUnit}`} />
        <StatCard title="Kehadiran Hari Ini" value={`${persentaseHadir}%`} subtitle={`${kartu.kehadiran_hari_ini || 0} hadir`} />
        <StatCard title="Target Tahfizh Sekolah" value={`${progressTahfizh}%`} subtitle={`${Math.round(realisasiTahfizh)} / ${Math.round(targetTahfizh)} baris`} />
        <StatCard title="Prestasi Siswa" value={totalPrestasi} subtitle={`Prestasi unit ${activeUnit}`} />
        <StatCard title="Mutabaah Yaumiyah" value={`${progressIbadah}%`} subtitle={profilUnit.jenjang} />
      </div>

      <article className="panel rounded-2xl border border-indigo-100 bg-white p-4 shadow-sm md:col-span-2">
        <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
          <div>
            <h3 className="text-lg font-bold text-indigo-900">Dashboard Kehadiran Seluruh Unit</h3>
            <p className="text-sm text-indigo-700">Menampilkan total global kehadiran dan rincian berdasarkan filter unit.</p>
          </div>
          <div className="flex items-center gap-2">
            <span className="text-xs font-semibold text-indigo-700">Filter Unit</span>
            <select
              className="rounded-lg border border-indigo-200 bg-white px-3 py-1.5 text-sm outline-none focus:border-indigo-500"
              value={filterUnitKehadiran}
              onChange={(event) => setFilterUnitKehadiran(event.target.value)}
            >
              {daftarUnitKehadiran.map((unit) => (
                <option key={unit} value={unit}>{unit}</option>
              ))}
            </select>
          </div>
        </div>

        {loadingAttendance ? <p className="text-sm text-indigo-700">Memuat data kehadiran...</p> : null}

        <div className="mb-3 grid gap-3 md:grid-cols-4">
          <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-3">
            <p className="text-xs text-emerald-700">Global Hadir</p>
            <p className="text-2xl font-bold text-emerald-900">{rekapGlobal.hadir}</p>
          </div>
          <div className="rounded-xl border border-rose-200 bg-rose-50 p-3">
            <p className="text-xs text-rose-700">Global Tidak Hadir</p>
            <p className="text-2xl font-bold text-rose-900">{rekapGlobal.tidak_hadir}</p>
          </div>
          <div className="rounded-xl border border-amber-200 bg-amber-50 p-3">
            <p className="text-xs text-amber-700">Global Izin</p>
            <p className="text-2xl font-bold text-amber-900">{rekapGlobal.izin}</p>
          </div>
          <div className="rounded-xl border border-sky-200 bg-sky-50 p-3">
            <p className="text-xs text-sky-700">Global Sakit</p>
            <p className="text-2xl font-bold text-sky-900">{rekapGlobal.sakit}</p>
          </div>
        </div>

        <div className="grid gap-3 md:grid-cols-4">
          <div className="rounded-xl border border-emerald-200 bg-white p-3">
            <p className="text-xs text-emerald-700">Filter Hadir ({filterUnitKehadiran})</p>
            <p className="text-xl font-bold text-emerald-900">{rekapFilterUnit.hadir}</p>
          </div>
          <div className="rounded-xl border border-rose-200 bg-white p-3">
            <p className="text-xs text-rose-700">Filter Tidak Hadir</p>
            <p className="text-xl font-bold text-rose-900">{rekapFilterUnit.tidak_hadir}</p>
          </div>
          <div className="rounded-xl border border-amber-200 bg-white p-3">
            <p className="text-xs text-amber-700">Filter Izin</p>
            <p className="text-xl font-bold text-amber-900">{rekapFilterUnit.izin}</p>
          </div>
          <div className="rounded-xl border border-sky-200 bg-white p-3">
            <p className="text-xs text-sky-700">Filter Sakit</p>
            <p className="text-xl font-bold text-sky-900">{rekapFilterUnit.sakit}</p>
          </div>
        </div>
      </article>

      <article className="panel panel-monitoring-kehadiran">
        <div className="panel-title-row">
          <h3>Monitoring Kehadiran Siswa {activeUnit}</h3>
          <span>7 Hari Terakhir</span>
        </div>
        <div className="panel-aksi-laporan">
          <Link to="/dashboard/attendance" className="topbar-action">
            Lihat Laporan Kehadiran
          </Link>
        </div>
        <div className="ringkas-chip-row">
          {dataDonut.map((item) => (
            <div key={item.label} className="ringkas-chip">
              <p>{item.label}</p>
              <strong>{item.nilai}</strong>
            </div>
          ))}
          <div className="ringkas-chip">
            <p>Alpha</p>
            <strong>{totalAlpha}</strong>
          </div>
        </div>
        <div className="grafik-2-kolom">
          <div className="grafik-box">
            <ResponsiveContainer width="100%" height={170}>
              <LineChart data={dataLine}>
                <XAxis dataKey="hari" />
                <YAxis />
                <Tooltip />
                <Line type="monotone" dataKey="total" stroke="#0f766e" strokeWidth={2.8} />
              </LineChart>
            </ResponsiveContainer>
          </div>
          <div className="grafik-box donut-mini">
            <ResponsiveContainer width="100%" height={170}>
              <PieChart>
                <Pie data={dataDonut} dataKey="nilai" innerRadius={44} outerRadius={70} paddingAngle={3}>
                  {dataDonut.map((entry) => <Cell key={entry.label} fill={warnaDonut[entry.label] || '#0f766e'} />)}
                </Pie>
                <Tooltip />
              </PieChart>
            </ResponsiveContainer>
            <p className="donut-keterangan">Rata-rata Kehadiran {persentaseHadir}%</p>
          </div>
        </div>
        <div className="list-2-kolom">
          <div>
            <h4>5 Kelas Kehadiran Tertinggi</h4>
            <ol>
              {dataBar.slice(0, 5).map((item) => (
                <li key={item.kelas}>{item.kelas} <span>{item.total_baris}</span></li>
              ))}
            </ol>
          </div>
          <div>
            <h4>Siswa Sering Terlambat</h4>
            <ol>
              {dataTemplateTahfizh.slice(0, 5).map((item) => (
                <li key={item.id}>{item.nama_prestasi || 'Data siswa'} <span>{item.nilai_prestasi || totalTerlambat} kali</span></li>
              ))}
            </ol>
          </div>
        </div>
      </article>

      <article className="panel panel-monitoring-divisi">
        <div className="panel-title-row">
          <h3>Monitoring Divisi Pendidikan {activeUnit}</h3>
          <span>Bulan Ini</span>
        </div>
        <div className="panel-aksi-laporan">
          <Link to="/dashboard/attendance" className="topbar-action">
            Lihat Laporan Divisi
          </Link>
        </div>
        <div className="table-wrap mini-monitoring-table">
          <table>
            <thead>
              <tr>
                <th>Divisi</th>
                <th>Target</th>
                <th>Capaian</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              {dataTemplateAbsensi.map((row) => (
                <tr key={row.id}>
                  <td>{row.nama_divisi}</td>
                  <td>100%</td>
                  <td>{Number(row.persentase_capaian || 0)}%</td>
                  <td>
                    <span className={kelasBadgeStatus(row.status_pemantauan)}>
                      <IconStatus status={row.status_pemantauan} />
                      {row.status_pemantauan}
                    </span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <div className="list-singkat">
          <h4>Monitoring Divisi Terbaru</h4>
          <ol>
            {dataTemplateAbsensi.slice(0, 4).map((row) => (
              <li key={row.id}>{row.aspek_pemantauan} <span>{row.tanggal_pemantauan}</span></li>
            ))}
          </ol>
        </div>
      </article>

      <article className="panel panel-tahfizh-mutabaah">
        <div className="panel-title-row">
          <h3>{profilUnit.targetTahfizh} {activeUnit}</h3>
          <span>Tahun Ajaran 2024/2025</span>
        </div>
        <div className="panel-aksi-laporan">
          <Link to="/dashboard/tahfizh" className="topbar-action">
            Lihat Laporan Tahfizh
          </Link>
        </div>

        <div className="target-tahfizh-wrap">
          <div className="target-label"><strong>{Math.round(targetTahfizh).toLocaleString('id-ID')}</strong><span>Target Baris</span></div>
          <div className="target-donut">
            <ResponsiveContainer width="100%" height={170}>
              <PieChart>
                <Pie
                  data={[
                    { name: 'Tercapai', value: progressTahfizh },
                    { name: 'Sisa', value: Math.max(100 - progressTahfizh, 0) },
                  ]}
                  dataKey="value"
                  innerRadius={55}
                  outerRadius={78}
                  startAngle={180}
                  endAngle={0}
                >
                  <Cell fill="#2ca66d" />
                  <Cell fill="#e3ece7" />
                </Pie>
              </PieChart>
            </ResponsiveContainer>
            <div className="target-center"><strong>{progressTahfizh}%</strong><span>Tercapai</span></div>
          </div>
          <div className="target-label"><strong>{Math.round(realisasiTahfizh).toLocaleString('id-ID')}</strong><span>Tercapai</span></div>
        </div>

        <div className="list-singkat">
          <h4>Top 5 Setoran Tertinggi</h4>
          <ol>
            {dataTemplateTahfizh.slice(0, 5).map((row) => (
              <li key={row.id}>{row.nama_prestasi || 'Setoran siswa'} <span>{row.nilai_prestasi || 0} baris</span></li>
            ))}
          </ol>
        </div>

        <div className="mutabaah-wrap">
          <h4>Mutabaah Yaumiyah</h4>
          {mutabaahList.map((item) => (
            <div key={item.label} className="progress-line-item">
              <span>{item.label}</span>
              <div className="progress-track"><div className="progress-value" style={{ width: `${Math.min(item.nilai, 100)}%` }} /></div>
              <strong>{Math.min(Math.round(item.nilai), 100)}%</strong>
            </div>
          ))}
          <div className="radar-wrap">
            <ResponsiveContainer width="100%" height={190}>
              <RadarChart data={dataRadarMutabaah} outerRadius={70}>
                <PolarGrid />
                <PolarAngleAxis dataKey="aspek" tick={{ fontSize: 10 }} />
                <Radar dataKey="nilai" stroke="#1f8d63" fill="#1f8d63" fillOpacity={0.25} />
                <Tooltip />
              </RadarChart>
            </ResponsiveContainer>
          </div>
        </div>
      </article>

      <article className="panel panel-mutabaah">
        <div className="panel-title-row">
          <h3>Mutabaah & Ibadah Siswa {activeUnit}</h3>
          <span>Hari Ini</span>
        </div>
        <div className="panel-aksi-laporan">
          <Link to="/dashboard/tahfizh" className="topbar-action">
            Lihat Laporan Mutabaah
          </Link>
        </div>
        <div className="mutabaah-panel-grid">
          <div>
            {mutabaahList.map((item) => (
              <div key={item.label} className="progress-line-item">
                <span>{item.label}</span>
                <div className="progress-track"><div className="progress-value" style={{ width: `${Math.min(item.nilai, 100)}%` }} /></div>
                <strong>{Math.min(Math.round(item.nilai), 100)}%</strong>
              </div>
            ))}
          </div>
          <div className="radar-wrap radar-wrap-tight">
            <ResponsiveContainer width="100%" height={210}>
              <RadarChart data={dataRadarMutabaah} outerRadius={72}>
                <PolarGrid />
                <PolarAngleAxis dataKey="aspek" tick={{ fontSize: 10 }} />
                <Radar dataKey="nilai" stroke="#1f8d63" fill="#1f8d63" fillOpacity={0.24} />
                <Tooltip />
              </RadarChart>
            </ResponsiveContainer>
          </div>
        </div>
      </article>

      <article className="panel panel-laporan-bulanan">
        <div className="panel-title-row">
          <h3>{profilUnit.laporan}</h3>
          <span>Mei 2024</span>
        </div>
        <div className="panel-aksi-laporan">
          <Link to="/dashboard/academic" className="topbar-action">
            Lihat Laporan Bulanan
          </Link>
        </div>
        <div className="laporan-list">
          {dataTemplateLaporan.map((row) => (
            <div key={row.id} className="laporan-item">
              <div>
                <strong>{row.judul_laporan}</strong>
                <p>{row.ringkasan_laporan || 'Ringkasan laporan sekolah'}</p>
              </div>
              <span className={kelasBadgeStatus(row.status_validasi)}>
                <IconStatus status={row.status_validasi} />
                {row.status_validasi}
              </span>
            </div>
          ))}
        </div>
      </article>

      <article className="panel panel-prestasi-siswa">
        <div className="panel-title-row">
          <h3>Rekapitulasi Prestasi Siswa {activeUnit}</h3>
          <span>Tahun 2024</span>
        </div>
        <div className="panel-aksi-laporan">
          <Link to="/dashboard/students" className="topbar-action">
            Lihat Laporan Prestasi
          </Link>
        </div>
        <div className="prestasi-kategori-grid">
          {komposisiPrestasi.map((item, index) => (
            <div key={item.label} className="prestasi-kategori-card">
              <p>{item.label}</p>
              <strong style={{ color: warnaProgress[index % warnaProgress.length] }}>{item.nilai}</strong>
            </div>
          ))}
        </div>
        <div className="list-singkat prestasi-list">
          <h4>Prestasi Terbaru</h4>
          <ol>
            {dataTemplateTahfizh.slice(0, 4).map((row) => (
              <li key={row.id}>{row.nama_prestasi || 'Prestasi siswa'} <span>{row.tanggal_prestasi || '-'}</span></li>
            ))}
          </ol>
        </div>
      </article>

      <div className="shortcut-grid wide">
        {kartuAksi.map((item, index) => (
          <Link key={item.title} to={item.to} className="shortcut-card">
            <span className="shortcut-index">{index + 1}</span>
            <div>
              <strong>{item.title}</strong>
              <p>{item.subtitle}</p>
            </div>
          </Link>
        ))}
      </div>
    </section>
  )
}
