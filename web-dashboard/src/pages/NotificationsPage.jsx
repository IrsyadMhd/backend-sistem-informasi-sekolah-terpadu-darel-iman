import { useForm } from 'react-hook-form'
import {
  useAksiPengumumanSekolah,
  useDaftarPengumumanSekolah,
} from '../hooks/useDashboardPemantauan'

export default function NotificationsPage() {
  const { data: daftarPengumuman } = useDaftarPengumumanSekolah({ per_page: 20 })
  const aksiPengumuman = useAksiPengumumanSekolah()

  const formPengumuman = useForm({
    defaultValues: {
      judul_pengumuman: '',
      isi_pengumuman: '',
      target_peran: 'Semua',
      mulai_tampil: new Date().toISOString().slice(0, 10),
      selesai_tampil: '',
      prioritas: 1,
      status_aktif: true,
    },
  })

  const submitPengumuman = async (values) => {
    const payload = {
      judul_pengumuman: values.judul_pengumuman,
      isi_pengumuman: values.isi_pengumuman,
      target_peran: [values.target_peran],
      mulai_tampil: values.mulai_tampil,
      selesai_tampil: values.selesai_tampil || null,
      prioritas: Number(values.prioritas),
      status_aktif: values.status_aktif,
      data_tambahan: { sumber: 'modul-notifikasi' },
    }

    await aksiPengumuman.tambah.mutateAsync(payload)
    formPengumuman.reset({
      ...formPengumuman.getValues(),
      judul_pengumuman: '',
      isi_pengumuman: '',
    })
  }

  return (
    <section className="panel modul-crud-page">
      <h3>Modul Notifikasi - CRUD Pengumuman</h3>
      <p className="modul-lead">Kelola publikasi pengumuman sekolah di modul ini.</p>

      <form className="form-grid" onSubmit={formPengumuman.handleSubmit(submitPengumuman)}>
        <input type="text" placeholder="Judul pengumuman" {...formPengumuman.register('judul_pengumuman', { required: true })} />
        <input type="text" placeholder="Isi ringkas" {...formPengumuman.register('isi_pengumuman', { required: true })} />
        <input type="text" placeholder="Target peran" {...formPengumuman.register('target_peran')} />
        <input type="date" {...formPengumuman.register('mulai_tampil', { required: true })} />
        <input type="date" {...formPengumuman.register('selesai_tampil')} />
        <input type="number" min="1" max="10" placeholder="Prioritas" {...formPengumuman.register('prioritas')} />
        <select {...formPengumuman.register('status_aktif')}>
          <option value={true}>Aktif</option>
          <option value={false}>Nonaktif</option>
        </select>
        <div className="form-actions">
          <button type="submit" className="aksi simpan">Simpan Pengumuman</button>
        </div>
      </form>

      <div className="table-wrap modul-table-wrap">
        <table>
          <thead>
            <tr>
              <th>Judul</th>
              <th>Mulai</th>
              <th>Selesai</th>
              <th>Prioritas</th>
              <th>Status</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            {(daftarPengumuman?.data || []).map((row) => (
              <tr key={row.id}>
                <td>{row.judul_pengumuman}</td>
                <td>{row.mulai_tampil}</td>
                <td>{row.selesai_tampil || '-'}</td>
                <td>{row.prioritas}</td>
                <td>{row.status_aktif ? 'aktif' : 'nonaktif'}</td>
                <td>
                  <button
                    type="button"
                    className="aksi kecil danger"
                    onClick={() => aksiPengumuman.hapus.mutate(row.id)}
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
