import { Link } from 'react-router-dom'

const kartuRingkas = [
  { label: 'Total Siswa', nilai: '1.256', keterangan: 'Seluruh siswa aktif' },
  { label: 'Hadir Hari Ini', nilai: '1.098', keterangan: '87,42%' },
  { label: 'Terlambat', nilai: '86', keterangan: '6,84%' },
  { label: 'Tidak Hadir', nilai: '64', keterangan: '5,10%' },
  { label: 'Izin / Sakit', nilai: '8', keterangan: '0,64%' },
]

const menuAksi = [
  { label: 'Lihat Detail Rekap', to: '/dashboard/attendance' },
  { label: 'Cetak Laporan', to: '/dashboard/attendance' },
  { label: 'Export Excel', to: '/dashboard/attendance' },
]

export default function LaporanAbsensiPage() {
  return (
    <section className="content-grid">
      <article className="panel wide">
        <div className="panel-title-row">
          <h3>Laporan Modul Absensi Digital</h3>
          <span>Template Laporan</span>
        </div>
        <p className="modul-lead">
          Rekap absensi digital siswa dengan indikator kehadiran, keterlambatan, ketidakhadiran, dan izin/sakit.
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
              <strong>Grafik Kehadiran 7 Hari Terakhir</strong>
              <p>Menampilkan tren hadir, terlambat, dan tidak hadir sebagai bahan evaluasi harian.</p>
            </div>
            <span className="badge-status bagus">Aktif</span>
          </div>
          <div className="laporan-item">
            <div>
              <strong>Rekap Otomatis Keterlambatan</strong>
              <p>Daftar siswa paling sering terlambat per unit dan kelas.</p>
            </div>
            <span className="badge-status proses">Proses</span>
          </div>
          <div className="laporan-item">
            <div>
              <strong>Notifikasi Orang Tua</strong>
              <p>Pengiriman notifikasi absensi siswa ke orang tua secara real-time.</p>
            </div>
            <span className="badge-status bagus">Aktif</span>
          </div>
        </div>
      </article>
    </section>
  )
}
