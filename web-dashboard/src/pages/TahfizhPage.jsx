import { useForm } from 'react-hook-form'
import {
  useAksiIndikatorKinerjaUtama,
  useDaftarIndikatorKinerjaUtama,
} from '../hooks/useDashboardPemantauan'

export default function TahfizhPage() {
  const { data: daftarIku } = useDaftarIndikatorKinerjaUtama({ per_page: 20 })
  const aksiIku = useAksiIndikatorKinerjaUtama()

  const formIku = useForm({
    defaultValues: {
      kode_indikator: '',
      nama_indikator: '',
      kategori_indikator: 'Tahfizh',
      nilai: 0,
      target_nilai: 0,
      satuan: '%',
      bulan_periode: new Date().getMonth() + 1,
      tahun_periode: new Date().getFullYear(),
      warna_hex: '#1f8d63',
      urutan_tampil: 1,
    },
  })

  const submitIku = async (values) => {
    const payload = {
      ...values,
      nilai: Number(values.nilai),
      target_nilai: Number(values.target_nilai),
      bulan_periode: Number(values.bulan_periode),
      tahun_periode: Number(values.tahun_periode),
      urutan_tampil: Number(values.urutan_tampil),
      data_tambahan: { sumber: 'modul-tahfizh' },
    }

    await aksiIku.tambah.mutateAsync(payload)
    formIku.reset({
      ...formIku.getValues(),
      kode_indikator: '',
      nama_indikator: '',
      nilai: 0,
    })
  }

  return (
    <section className="panel modul-crud-page">
      <h3>Modul Tahfizh - CRUD Indikator Kinerja</h3>
      <p className="modul-lead">Kelola target dan pencapaian indikator tahfizh dari halaman ini.</p>

      <form className="form-grid" onSubmit={formIku.handleSubmit(submitIku)}>
        <input type="text" placeholder="Kode indikator" {...formIku.register('kode_indikator', { required: true })} />
        <input type="text" placeholder="Nama indikator" {...formIku.register('nama_indikator', { required: true })} />
        <input type="text" placeholder="Kategori" {...formIku.register('kategori_indikator', { required: true })} />
        <input type="number" placeholder="Nilai" {...formIku.register('nilai', { required: true })} />
        <input type="number" placeholder="Target nilai" {...formIku.register('target_nilai')} />
        <input type="text" placeholder="Satuan" {...formIku.register('satuan')} />
        <input type="number" min="1" max="12" placeholder="Bulan" {...formIku.register('bulan_periode', { required: true })} />
        <input type="number" min="2000" max="2100" placeholder="Tahun" {...formIku.register('tahun_periode', { required: true })} />
        <input type="text" placeholder="#RRGGBB" {...formIku.register('warna_hex')} />
        <input type="number" min="0" max="100" placeholder="Urutan tampil" {...formIku.register('urutan_tampil')} />
        <div className="form-actions">
          <button type="submit" className="aksi simpan">Simpan Indikator</button>
        </div>
      </form>

      <div className="table-wrap modul-table-wrap">
        <table>
          <thead>
            <tr>
              <th>Kode</th>
              <th>Indikator</th>
              <th>Nilai</th>
              <th>Periode</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            {(daftarIku?.data || []).map((row) => (
              <tr key={row.id}>
                <td>{row.kode_indikator}</td>
                <td>{row.nama_indikator}</td>
                <td>{row.nilai}{row.satuan || ''}</td>
                <td>{row.bulan_periode}/{row.tahun_periode}</td>
                <td>
                  <button
                    type="button"
                    className="aksi kecil danger"
                    onClick={() => aksiIku.hapus.mutate(row.id)}
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
