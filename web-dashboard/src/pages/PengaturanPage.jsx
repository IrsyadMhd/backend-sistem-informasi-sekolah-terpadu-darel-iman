import { useMemo, useState } from 'react'
import Swal from 'sweetalert2'
import { usePengaturanStore } from '../stores/pengaturanStore'

export default function PengaturanPage() {
  const pengaturan = usePengaturanStore((state) => state.pengaturan)
  const simpanPengaturanStore = usePengaturanStore((state) => state.simpanPengaturan)

  const [form, setForm] = useState({
    namaDashboard: pengaturan.namaDashboard,
    namaSekolah: pengaturan.namaSekolah,
    logoTeks: pengaturan.logoTeks,
    logoUrl: pengaturan.logoUrl,
    faviconUrl: pengaturan.faviconUrl,
    alamatFooter: pengaturan.alamatFooter,
  })

  const logoPreview = useMemo(() => form.logoUrl || '', [form.logoUrl])

  const bacaFileLogo = (file) =>
    new Promise((resolve, reject) => {
      const reader = new FileReader()
      reader.onload = () => resolve(typeof reader.result === 'string' ? reader.result : '')
      reader.onerror = () => reject(new Error('Gagal membaca file logo'))
      reader.readAsDataURL(file)
    })

  const simpanPengaturan = async (event) => {
    event.preventDefault()
    simpanPengaturanStore(form)
    await Swal.fire('Berhasil', 'Pengaturan dashboard berhasil disimpan.', 'success')
  }

  const ubahLogo = async (event) => {
    const file = event.target.files?.[0]
    if (!file) return

    try {
      const dataUrl = await bacaFileLogo(file)
      setForm((prev) => ({ ...prev, logoUrl: dataUrl }))
    } catch {
      await Swal.fire('Gagal', 'File logo tidak dapat diproses.', 'error')
    }
  }

  const resetLogo = () => {
    setForm((prev) => ({ ...prev, logoUrl: '' }))
  }

  return (
    <section className="content-grid">
      <article className="panel wide">
        <h3>Pengaturan Dashboard</h3>
        <p className="modul-lead">
          Atur nama dashboard, logo, dan alamat footer yang tampil di sidebar serta bagian atas dashboard.
        </p>

        <form className="form-grid" onSubmit={simpanPengaturan}>
          <label className="student-field student-field-wide">
            <span>Nama Dashboard</span>
            <input
              value={form.namaDashboard}
              onChange={(event) => setForm((prev) => ({ ...prev, namaDashboard: event.target.value }))}
              placeholder="Contoh: Dashboard Monitoring Kepala Sekolah"
              required
            />
          </label>

          <label className="student-field">
            <span>Nama Sekolah</span>
            <input
              value={form.namaSekolah}
              onChange={(event) => setForm((prev) => ({ ...prev, namaSekolah: event.target.value }))}
              placeholder="Contoh: SDIT DAR EL-IMAN"
              required
            />
          </label>

          <label className="student-field">
            <span>Teks Logo (fallback)</span>
            <input
              value={form.logoTeks}
              onChange={(event) => setForm((prev) => ({ ...prev, logoTeks: event.target.value }))}
              placeholder="Contoh: SDIT"
              required
            />
          </label>

          <label className="student-field student-field-wide">
            <span>Alamat Footer Sidebar</span>
            <input
              value={form.alamatFooter}
              onChange={(event) => setForm((prev) => ({ ...prev, alamatFooter: event.target.value }))}
              placeholder="Contoh: Jl. Pendidikan No. 1, Kota Padang"
              required
            />
          </label>

          <label className="student-field student-field-wide">
            <span>Logo Sekolah (Gambar)</span>
            <input type="file" accept="image/*" onChange={ubahLogo} />
          </label>
          <label className="student-field student-field-wide">
            <span>Favicon URL</span>
            <input
              value={form.faviconUrl || ''}
              onChange={(e) => setForm((prev) => ({ ...prev, faviconUrl: e.target.value }))}
              placeholder="Contoh: https://example.com/favicon.ico"
            />
          </label>

          {logoPreview ? (
            <div className="logo-preview-wrap">
              <img src={logoPreview} alt="Logo sekolah" className="logo-preview-image" />
              <button className="aksi batal" type="button" onClick={resetLogo}>
                Hapus Logo
              </button>
            </div>
          ) : null}

          <div className="form-actions">
            <button className="aksi simpan" type="submit">
              Simpan Pengaturan
            </button>
          </div>
        </form>
      </article>
    </section>
  )
}
