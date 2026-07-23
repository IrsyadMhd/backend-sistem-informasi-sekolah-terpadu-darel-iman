import { Link } from 'react-router-dom'

const kartuRingkas = [
  { label: 'Total Prestasi', nilai: '128', keterangan: 'Tahun 2023/2024' },
  { label: 'Siswa Lulus', nilai: '156', keterangan: 'Tahun ini' },
  { label: 'Total Kelulusan', nilai: '1.248', keterangan: '5 tahun terakhir' },
  { label: 'Alumni Tercatat', nilai: '2.356', keterangan: 'Sejak 2015' },
]

const menuAksi = [
  { label: 'Lihat Detail Alumni', to: '/dashboard/notifications' },
  { label: 'Cetak Laporan', to: '/dashboard/notifications' },
  { label: 'Export Rekap', to: '/dashboard/notifications' },
]

export default function LaporanAlumniPage() {
  return (
    <section className="content-grid">
      <article className="panel wide">
        <div className="panel-title-row">
          <h3>Laporan Modul Prestasi & Alumni</h3>
          <span>Template Laporan</span>
        </div>
        <p className="modul-lead">
          Rekap prestasi siswa, persentase kelulusan, dan data tujuan lanjut sekolah alumni.
        </p>

        <div className="stats-grid">
          {kartuRingkas.map((item) => (
            <div key={item.label} className="stat-card">
              <h4>{item.label}</h4>
              <strong>{item.nilai}</strong>
              <p>{item.keterangan}</p>
            </div>
          ))}
        </div>

        <div className="panel-aksi-laporan">
          {menuAksi.map((item) => (
            <Link key={item.label} to={item.to} className="topbar-action">
              {item.label}
            </Link>
          ))}
        </div>

        <div className="laporan-list">
          <div className="laporan-item">
            <div>
              <strong>Rekapitulasi Prestasi</strong>
              <p>Akademik, tahfizh, olahraga, seni, dan keagamaan siswa.</p>
            </div>
            <span className="badge-status bagus">Aktif</span>
          </div>
          <div className="laporan-item">
            <div>
              <strong>Kelulusan Siswa per Unit</strong>
              <p>Persentase kelulusan unit SD, SMP, SMA dengan indikator mutu.</p>
            </div>
            <span className="badge-status bagus">Aktif</span>
          </div>
          <div className="laporan-item">
            <div>
              <strong>Data Tujuan Lanjut Alumni</strong>
              <p>Ringkasan sekolah lanjutan, universitas, dan pekerjaan alumni.</p>
            </div>
            <span className="badge-status proses">Proses</span>
          </div>
        </div>
      </article>
    </section>
  )
}
