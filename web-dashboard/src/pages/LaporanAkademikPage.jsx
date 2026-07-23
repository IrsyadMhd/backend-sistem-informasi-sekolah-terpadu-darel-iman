import { Link } from 'react-router-dom'

const kartuRingkas = [
  { label: 'Total Mapel', nilai: '28', keterangan: 'Mata pelajaran aktif' },
  { label: 'Total Materi', nilai: '125', keterangan: 'Materi pembelajaran' },
  { label: 'Total Bank Soal', nilai: '8.420', keterangan: 'Butir soal' },
  { label: 'Jadwal Hari Ini', nilai: '32', keterangan: 'Kelas berjalan' },
]

const menuAksi = [
  { label: 'Lihat Detail Akademik', to: '/dashboard/academic' },
  { label: 'Cetak Laporan', to: '/dashboard/academic' },
  { label: 'Export Excel', to: '/dashboard/academic' },
]

export default function LaporanAkademikPage() {
  return (
    <section className="content-grid">
      <article className="panel wide">
        <div className="panel-title-row">
          <h3>Laporan Modul Sistem Akademik</h3>
          <span>Template Laporan</span>
        </div>
        <p className="modul-lead">
          Monitoring materi, tugas, bank soal, jadwal, dan kalender pendidikan dalam satu dashboard akademik.
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
              <strong>Penugasan Siswa</strong>
              <p>Ringkasan tugas per kelas, deadline, dan status pengumpulan.</p>
            </div>
            <span className="badge-status bagus">Aktif</span>
          </div>
          <div className="laporan-item">
            <div>
              <strong>Materi Terbaru</strong>
              <p>Daftar materi terbaru yang diunggah guru per mapel dan kelas.</p>
            </div>
            <span className="badge-status bagus">Aktif</span>
          </div>
          <div className="laporan-item">
            <div>
              <strong>Agenda Pendidikan</strong>
              <p>Kalender agenda sekolah, ujian, workshop, dan kegiatan resmi.</p>
            </div>
            <span className="badge-status proses">Proses</span>
          </div>
        </div>
      </article>
    </section>
  )
}
