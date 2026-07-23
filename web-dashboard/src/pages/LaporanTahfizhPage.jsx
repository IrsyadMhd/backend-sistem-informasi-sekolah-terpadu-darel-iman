import { Link } from 'react-router-dom'

const kartuRingkas = [
  { label: 'Total Siswa', nilai: '1.256', keterangan: 'Siswa aktif tahfizh' },
  { label: 'Total Hafalan', nilai: '39.860', keterangan: 'Baris hafalan' },
  { label: 'Target Tahunan', nilai: '50.000', keterangan: 'Baris target' },
  { label: 'Tercapai', nilai: '79,72%', keterangan: 'Pencapaian unit' },
]

const menuAksi = [
  { label: 'Lihat Detail Tahfizh', to: '/dashboard/tahfizh' },
  { label: 'Cetak Laporan', to: '/dashboard/tahfizh' },
  { label: 'Export Rekap', to: '/dashboard/tahfizh' },
]

export default function LaporanTahfizhPage() {
  return (
    <section className="content-grid">
      <article className="panel wide">
        <div className="panel-title-row">
          <h3>Laporan Modul Tahfizh & Mutabaah</h3>
          <span>Template Laporan</span>
        </div>
        <p className="modul-lead">
          Rekap setoran tahfizh, target hafalan, mutabaah yaumiyah, dan capaian siswa per periode.
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
              <strong>Input Setoran Harian</strong>
              <p>Pencatatan setoran hafalan siswa per hari dan per kelas.</p>
            </div>
            <span className="badge-status bagus">Aktif</span>
          </div>
          <div className="laporan-item">
            <div>
              <strong>Rekap Tahfizh Bulanan</strong>
              <p>Ringkasan progres target tahfizh bulanan untuk monitoring kepala sekolah.</p>
            </div>
            <span className="badge-status proses">Proses</span>
          </div>
          <div className="laporan-item">
            <div>
              <strong>Mutabaah Yaumiyah</strong>
              <p>Checklist ibadah siswa (shalat, tilawah, dzikir, puasa sunnah) per hari.</p>
            </div>
            <span className="badge-status bagus">Aktif</span>
          </div>
        </div>
      </article>
    </section>
  )
}
