import { useMemo } from 'react'
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
  const profilUnit = unitMeta[activeUnit] || unitMeta.SD
  const { data: ringkasan, isLoading: loadingRingkasan, isError: errorRingkasan } = useRingkasanDashboardPemantauan()
  const { data: daftarPemantauan } = useDaftarPemantauanDivisi({ per_page: 8 })
  const { data: daftarLaporan } = useDaftarLaporanBulanan({ per_page: 5 })
  const { data: daftarRekap } = useDaftarRekapPrestasiSiswa({ per_page: 5 })
  const { data: daftarPengumuman } = useDaftarPengumumanSekolah({ per_page: 5 })
  const { data: daftarIku } = useDaftarIndikatorKinerjaUtama({ per_page: 5 })

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

  if (loadingRingkasan) return <section className="panel">Memuat ringkasan dashboard...</section>
  if (errorRingkasan) return <section className="panel"><h3>Gagal memuat data dashboard</h3><p>Pastikan sudah login dan token tersimpan pada localStorage.</p></section>

  return (
    <section className="content-grid dashboard-utama-grid">
      <div className="panel unit-banner wide">
        <div>
          <p className="topbar-label">Dashboard Unit</p>
          <h3>{profilUnit.jenjang}</h3>
        </div>
        <span className="panel-filter-chip">Unit {activeUnit}</span>
      </div>

      <div className="stats-grid">
        <StatCard title="Total Siswa" value={kartu.total_siswa || 0} subtitle={`Unit ${activeUnit}`} />
        <StatCard title="Kehadiran Hari Ini" value={`${persentaseHadir}%`} subtitle={`${kartu.kehadiran_hari_ini || 0} hadir`} />
        <StatCard title="Target Tahfizh Sekolah" value={`${progressTahfizh}%`} subtitle={`${Math.round(realisasiTahfizh)} / ${Math.round(targetTahfizh)} baris`} />
        <StatCard title="Prestasi Siswa" value={totalPrestasi} subtitle={`Prestasi unit ${activeUnit}`} />
        <StatCard title="Mutabaah Yaumiyah" value={`${progressIbadah}%`} subtitle={profilUnit.jenjang} />
      </div>

      <article className="panel panel-monitoring-kehadiran">
        <div className="panel-title-row">
          <h3>Monitoring Kehadiran Siswa {activeUnit}</h3>
          <span>7 Hari Terakhir</span>
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
