import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { useAksiPemantauanDivisi, useDaftarPemantauanDivisi } from '../hooks/useDashboardPemantauan'

export default function AttendancePage() {
  const [search, setSearch] = useState('')
  const { data: daftarPemantauan } = useDaftarPemantauanDivisi({ search, per_page: 20 })
  const aksiPemantauan = useAksiPemantauanDivisi()

  const formPemantauan = useForm({
    defaultValues: {
      tanggal_pemantauan: new Date().toISOString().slice(0, 10),
      nama_divisi: 'Divisi Pendidikan',
      aspek_pemantauan: '',
      persentase_capaian: 0,
      status_pemantauan: 'proses',
      catatan: '',
    },
  })

  const submitPemantauan = async (values) => {
    const payload = {
      ...values,
      persentase_capaian: Number(values.persentase_capaian),
      data_tambahan: { sumber: 'modul-absensi' },
    }

    await aksiPemantauan.tambah.mutateAsync(payload)
    formPemantauan.reset({
      tanggal_pemantauan: new Date().toISOString().slice(0, 10),
      nama_divisi: 'Divisi Pendidikan',
      aspek_pemantauan: '',
      persentase_capaian: 0,
      status_pemantauan: 'proses',
      catatan: '',
    })
  }

  return (
    <section className="panel modul-crud-page">
      <h3>Modul Absensi - CRUD Monitoring Divisi</h3>
      <p className="modul-lead">Kelola monitoring divisi pada modul ini agar dashboard tetap fokus sebagai ringkasan.</p>

      <form className="form-grid" onSubmit={formPemantauan.handleSubmit(submitPemantauan)}>
        <input type="date" {...formPemantauan.register('tanggal_pemantauan', { required: true })} />
        <input type="text" placeholder="Nama divisi" {...formPemantauan.register('nama_divisi', { required: true })} />
        <input type="text" placeholder="Aspek monitoring" {...formPemantauan.register('aspek_pemantauan', { required: true })} />
        <input type="number" min="0" max="100" placeholder="Capaian (%)" {...formPemantauan.register('persentase_capaian', { required: true })} />
        <select {...formPemantauan.register('status_pemantauan', { required: true })}>
          <option value="proses">proses</option>
          <option value="tercapai">tercapai</option>
          <option value="terlambat">terlambat</option>
          <option value="belum_tercapai">belum_tercapai</option>
        </select>
        <input type="text" placeholder="Catatan" {...formPemantauan.register('catatan')} />
        <div className="form-actions">
          <button type="submit" className="aksi simpan">Simpan Monitoring</button>
        </div>
      </form>

      <div className="table-header">
        <h3>Daftar Monitoring Divisi</h3>
        <input
          type="text"
          className="search-input"
          placeholder="Cari divisi/aspek/status"
          value={search}
          onChange={(event) => setSearch(event.target.value)}
        />
      </div>

      <div className="table-wrap modul-table-wrap">
        <table>
          <thead>
            <tr>
              <th>Tanggal</th>
              <th>Divisi</th>
              <th>Aspek</th>
              <th>Capaian</th>
              <th>Status</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            {(daftarPemantauan?.data || []).map((row) => (
              <tr key={row.id}>
                <td>{row.tanggal_pemantauan}</td>
                <td>{row.nama_divisi}</td>
                <td>{row.aspek_pemantauan}</td>
                <td>{row.persentase_capaian}%</td>
                <td>{row.status_pemantauan}</td>
                <td>
                  <button
                    type="button"
                    className="aksi kecil danger"
                    onClick={() => aksiPemantauan.hapus.mutate(row.id)}
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
