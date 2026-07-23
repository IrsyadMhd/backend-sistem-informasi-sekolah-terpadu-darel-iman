import { useEffect, useMemo, useState } from 'react'
import { studentService } from '../services/studentService'

const formatAngka = (nilai) => new Intl.NumberFormat('id-ID').format(Number(nilai || 0))

const warnaKartu = ['biru', 'hijau', 'oranye', 'merah', 'teal']

export default function LaporanSiswaPage() {
  const [memuat, setMemuat] = useState(true)
  const [gagal, setGagal] = useState('')
  const [dashboard, setDashboard] = useState(null)

  useEffect(() => {
    const load = async () => {
      try {
        setMemuat(true)
        setGagal('')
        const data = await studentService.getDashboard()
        setDashboard(data)
      } catch (error) {
        setGagal(error?.response?.data?.message || 'Gagal memuat dashboard data siswa.')
      } finally {
        setMemuat(false)
      }
    }

    load()
  }, [])

  const kartuRingkas = useMemo(() => {
    if (!dashboard?.statistik) return []
    return [
      { label: 'Total Siswa', nilai: dashboard.statistik.total_siswa, ket: 'Siswa Aktif' },
      { label: 'Total Kelas', nilai: dashboard.statistik.total_kelas, ket: 'Rombongan Belajar' },
      { label: 'Siswa Baru', nilai: dashboard.statistik.siswa_baru, ket: 'Tahun Berjalan' },
      { label: 'Mutasi Keluar', nilai: dashboard.statistik.mutasi_keluar, ket: 'Siswa Pindah' },
      { label: 'Alumni', nilai: dashboard.statistik.alumni, ket: 'Data Kelulusan' },
    ]
  }, [dashboard])

  if (memuat) {
    return (
      <section className="content-grid">
        <article className="panel wide">Memuat dashboard data siswa...</article>
      </section>
    )
  }

  if (gagal) {
    return (
      <section className="content-grid">
        <article className="panel wide">{gagal}</article>
      </section>
    )
  }

  const siswaTerpilih = dashboard?.siswa_terpilih
  const daftarSiswa = dashboard?.daftar_siswa || []
  const kelasRombel = dashboard?.kelas_rombel || []
  const laporan = dashboard?.laporan_siswa || {}
  const grafikTahunan = laporan.grafik_tahunan || []

  return (
    <section className="content-grid">
      <article className="panel wide laporan-siswa">
        <div className="panel-title-row">
          <div>
            <h3>Manajemen Data Siswa</h3>
            <p className="modul-lead">Pengelolaan seluruh data administrasi siswa berbasis database.</p>
          </div>
          <button type="button" className="topbar-action">
            + Tambah Kelas
          </button>
        </div>

        <div className="stats-grid">
          {kartuRingkas.map((item, idx) => (
            <div key={item.label} className={`stat-card aksen-${warnaKartu[idx % warnaKartu.length]}`}>
              <h4>{item.label}</h4>
              <strong>{formatAngka(item.nilai)}</strong>
              <p>{item.ket}</p>
            </div>
          ))}
        </div>

        <div className="laporan-siswa-grid">
          <section className="panel-inset">
            <div className="panel-title-row">
              <h4>1. Data Lengkap Siswa</h4>
              <button type="button" className="topbar-action topbar-action--kecil">
                Cari
              </button>
            </div>
            <ul className="daftar-siswa">
              {daftarSiswa.map((item) => (
                <li key={item.id}>
                  <div>
                    <strong>{item.nama}</strong>
                    <p>NIS {item.nis} · {item.kelas}</p>
                  </div>
                  <span className={`badge-status ${item.aktif ? 'bagus' : 'proses'}`}>{item.aktif ? 'Aktif' : 'Nonaktif'}</span>
                </li>
              ))}
            </ul>
          </section>

          <section className="panel-inset">
            <h4>Profil Siswa Terpilih</h4>
            {siswaTerpilih ? (
              <div className="profil-siswa">
                <p><strong>NIS:</strong> {siswaTerpilih.nis}</p>
                <p><strong>Nama:</strong> {siswaTerpilih.nama}</p>
                <p><strong>Kelas:</strong> {siswaTerpilih.kelas}</p>
                <p><strong>Tempat, Tgl Lahir:</strong> {siswaTerpilih.tempat_lahir || '-'}, {siswaTerpilih.tanggal_lahir || '-'}</p>
                <p><strong>Alamat:</strong> {siswaTerpilih.alamat || '-'}</p>
                <p><strong>Status:</strong> {siswaTerpilih.status}</p>
                <hr />
                <p><strong>Nama Ayah:</strong> {siswaTerpilih.orang_tua?.nama_ayah || '-'}</p>
                <p><strong>Nama Ibu:</strong> {siswaTerpilih.orang_tua?.nama_ibu || '-'}</p>
                <p><strong>No HP:</strong> {siswaTerpilih.orang_tua?.no_hp || '-'}</p>
              </div>
            ) : (
              <p>Belum ada data siswa.</p>
            )}

            <div className="panel-aksi-laporan">
              <button type="button" className="topbar-action">Edit Data</button>
              <button type="button" className="topbar-action">Cetak PDF</button>
              <button type="button" className="topbar-action">Export Excel</button>
            </div>
          </section>

          <section className="panel-inset">
            <h4>2. Kelas & Rombongan Belajar</h4>
            <div className="tabel-mini">
              <div className="tabel-mini-head">
                <span>Kelas</span>
                <span>Wali Kelas</span>
                <span>Kapasitas</span>
                <span>Jumlah</span>
              </div>
              {kelasRombel.map((kelas) => (
                <div key={kelas.id} className="tabel-mini-row">
                  <span>{kelas.nama}</span>
                  <span>{kelas.wali_kelas}</span>
                  <span>{kelas.kapasitas}</span>
                  <span>{kelas.jumlah_siswa}</span>
                </div>
              ))}
            </div>

            <div className="panel-aksi-laporan">
              <button type="button" className="topbar-action">Mutasi Siswa</button>
              <button type="button" className="topbar-action">Pindah Kelas</button>
              <button type="button" className="topbar-action">Cetak Daftar Kelas</button>
            </div>
          </section>
        </div>

        <section className="panel-inset">
          <div className="panel-title-row">
            <h4>3. Laporan Siswa Masuk & Keluar</h4>
            <button type="button" className="topbar-action topbar-action--kecil">
              Filter
            </button>
          </div>

          <div className="ringkasan-laporan">
            <div className="ringkasan-kiri">
              <p><strong>Siswa Baru:</strong> {formatAngka(laporan.siswa_baru)}</p>
              <p><strong>Mutasi Masuk:</strong> {formatAngka(laporan.mutasi_masuk)}</p>
              <p><strong>Mutasi Keluar:</strong> {formatAngka(laporan.mutasi_keluar)}</p>
              <p><strong>Siswa Lulus:</strong> {formatAngka(laporan.siswa_lulus)}</p>
            </div>
            <div className="ringkasan-grafik">
              {grafikTahunan.map((g) => (
                <div key={g.tahun} className="grafik-item">
                  <span>{g.tahun}</span>
                  <strong>{formatAngka(g.jumlah)}</strong>
                </div>
              ))}
            </div>
          </div>
        </section>
      </article>
    </section>
  )
}
