import { Link } from 'react-router-dom'

const daftarBerita = [
  {
    id: 1,
    judul: 'Penerimaan Siswa Baru Tahun Ajaran 2026/2027 Dibuka',
    tanggal: '22 Juli 2026',
    ringkasan: 'Pendaftaran peserta didik baru telah dibuka secara online dan offline melalui tata usaha.',
  },
  {
    id: 2,
    judul: 'Program Tahfizh Intensif Semester Ganjil',
    tanggal: '20 Juli 2026',
    ringkasan: 'Sekolah menyiapkan target setoran hafalan per jenjang untuk memperkuat capaian tahfizh siswa.',
  },
  {
    id: 3,
    judul: 'Workshop Parenting Bersama Orang Tua',
    tanggal: '18 Juli 2026',
    ringkasan: 'Kegiatan parenting bulanan dilaksanakan untuk membangun sinergi sekolah dan keluarga.',
  },
]

export default function BeritaPublikPage() {
  return (
    <section className="berita-publik-shell">
      <header className="berita-publik-header">
        <p className="showcase-badge">PORTAL INFORMASI SEKOLAH</p>
        <h1>Berita Terbaru SDIT DAR EL-IMAN</h1>
        <p>
          Halaman ini dapat diakses sebelum login oleh orang tua, siswa, maupun pengguna lainnya untuk melihat informasi terbaru sekolah.
        </p>
        <Link className="topbar-action" to="/masuk">
          Masuk ke Dashboard
        </Link>
      </header>

      <div className="berita-publik-grid">
        {daftarBerita.map((berita) => (
          <article key={berita.id} className="berita-card panel">
            <small>{berita.tanggal}</small>
            <h3>{berita.judul}</h3>
            <p>{berita.ringkasan}</p>
          </article>
        ))}
      </div>
    </section>
  )
}
