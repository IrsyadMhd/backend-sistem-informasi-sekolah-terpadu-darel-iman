import { useForm } from 'react-hook-form'
import { useAksiLaporanBulanan, useDaftarLaporanBulanan } from '../hooks/useDashboardPemantauan'

export default function AcademicPage() {
  const { data: daftarLaporan } = useDaftarLaporanBulanan({ per_page: 20 })
  const aksiLaporan = useAksiLaporanBulanan()

  const formLaporan = useForm({
    defaultValues: {
      bulan: new Date().getMonth() + 1,
      tahun: new Date().getFullYear(),
      judul_laporan: '',
      ringkasan_laporan: '',
      tindak_lanjut: '',
      status_validasi: 'draf',
    },
  })

  const submitLaporan = async (values) => {
    const payload = {
      ...values,
      bulan: Number(values.bulan),
      tahun: Number(values.tahun),
      data_tambahan: { sumber: 'modul-akademik' },
    }

    await aksiLaporan.tambah.mutateAsync(payload)
    formLaporan.reset({
      bulan: new Date().getMonth() + 1,
      tahun: new Date().getFullYear(),
      judul_laporan: '',
      ringkasan_laporan: '',
      tindak_lanjut: '',
      status_validasi: 'draf',
    })
  }

  return (
    <section className="panel modul-crud-page">
      <h3>Modul Akademik - CRUD Laporan Bulanan</h3>
      <p className="modul-lead">Pembuatan dan penghapusan laporan bulanan dilakukan pada modul akademik.</p>

      <form className="form-grid" onSubmit={formLaporan.handleSubmit(submitLaporan)}>
        <input type="number" min="1" max="12" placeholder="Bulan" {...formLaporan.register('bulan', { required: true })} />
        <input type="number" min="2000" max="2100" placeholder="Tahun" {...formLaporan.register('tahun', { required: true })} />
        <input type="text" placeholder="Judul laporan" {...formLaporan.register('judul_laporan', { required: true })} />
        <input type="text" placeholder="Ringkasan laporan" {...formLaporan.register('ringkasan_laporan', { required: true })} />
        <input type="text" placeholder="Tindak lanjut" {...formLaporan.register('tindak_lanjut')} />
        <select {...formLaporan.register('status_validasi', { required: true })}>
          <option value="draf">draf</option>
          <option value="diajukan">diajukan</option>
          <option value="tervalidasi">tervalidasi</option>
          <option value="revisi">revisi</option>
        </select>
        <div className="form-actions">
          <button type="submit" className="aksi simpan">Simpan Laporan</button>
        </div>
      </form>

      <div className="table-wrap modul-table-wrap">
        <table>
          <thead>
            <tr>
              <th>Periode</th>
              <th>Judul</th>
              <th>Status</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            {(daftarLaporan?.data || []).map((row) => (
              <tr key={row.id}>
                <td>{row.bulan}/{row.tahun}</td>
                <td>{row.judul_laporan}</td>
                <td>{row.status_validasi}</td>
                <td>
                  <button
                    type="button"
                    className="aksi kecil danger"
                    onClick={() => aksiLaporan.hapus.mutate(row.id)}
                  >
                    Hapus
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </section>
  )
}
