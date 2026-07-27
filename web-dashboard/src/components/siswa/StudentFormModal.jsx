import React, { useState, useEffect } from 'react'
import { FaTimes, FaCloudUploadAlt, FaArrowRight, FaArrowLeft, FaSave, FaCheckCircle, FaTrashAlt } from 'react-icons/fa'

const defaultForm = () => ({
  foto_url: '', no_pendaftaran: '', nik: '', no_registrasi_akta_lahir: '', no_kk: '', nisn: '', full_name: '', birth_date: '', birth_place: '', gender: 'male', agama: 'Islam', email: '', anak_ke: '', jumlah_saudara: '', jumlah_saudara_tiri: '', berat_badan: '', tinggi_badan: '', riwayat_penyakit: '', kewarganegaraan: 'WNI', alamat_siswa: '', rt: '', rw: '', dusun: '', kelurahan: '', kecamatan: '', kode_pos: '', kota_kabupaten: '', provinsi: '', jenis_tempat_tinggal: '', jarak_tempuh_ke_sekolah: '', moda_transportasi: '', hobi: '', cita_cita: '',
  sekolah_asal: '', status_sekolah_asal: 'Formal', kecamatan_sekolah_asal: '', kota_kab_sekolah_asal: '', nomor_hp_wa_sekolah_asal: '', nominal_spp: '', nominal_ortu_asuh: '', penerima_kps_pkh: 'tidak', apakah_punya_kip: 'tidak', apakah_layak_menerima_pip: 'tidak', alasan_menolak_pip: '',
  nik_ayah: '', nama_ayah: '', tempat_lahir_ayah: '', tgl_lahir_ayah: '', telfon_ayah: '', hp_ayah: '', pendidikan_terakhir_ayah: '', pekerjaan_ayah: '', instansi_pekerjaan_ayah: '', jabatan_pekerjaan_ayah: '', alamat_instansi_ayah: '', keahlian_ayah: '', penghasilan_ayah: '', alamat_ayah: '', nomor_wa_ayah: '', medsos_ayah: '',
  nik_ibu: '', nama_ibu: '', tempat_lahir_ibu: '', tgl_lahir_ibu: '', telfon_ibu: '', hp_ibu: '', pendidikan_terakhir_ibu: '', pekerjaan_ibu: '', instansi_pekerjaan_ibu: '', jabatan_pekerjaan_ibu: '', alamat_instansi_ibu: '', keahlian_ibu: '', penghasilan_ibu: '', alamat_ibu: '', nomor_wa_ibu: '', medsos_ibu: '',
  status_pernikahan_wali: '', tanggungan_anak_wali: '', nik_wali: '', nama_wali: '', tempat_lahir_wali: '', tgl_lahir_wali: '', telfon_wali: '', hp_wali: '', pendidikan_terakhir_wali: '', pekerjaan_wali: '', instansi_pekerjaan_wali: '', jabatan_pekerjaan_wali: '', alamat_instansi_wali: '', keahlian_wali: '', penghasilan_wali: '', alamat_wali: '', nomor_wa_wali: '', medsos_wali: '',
  unit_pendidikan: '', nis_pembayaran: '', tahun_ajaran_masuk: '', class_id: '', tahun_ajaran_berjalan: '', status_siswa: 'aktif', status_orang_tua: 'Umum', niy_ortu_jika_pegawai: '', wali_kelas: '', niy_wali_kelas: '',
})

export default function StudentFormModal({ isOpen, onClose, initialData, onSubmit, classes }) {
  const [activeStep, setActiveStep] = useState(0)
  const [formData, setFormData] = useState(defaultForm())
  const [isUploading, setIsUploading] = useState(false)

  const steps = [
    { number: 1, id: 'pribadi', title: 'Informasi Pribadi', desc: 'Identitas & Alamat Siswa' },
    { number: 2, id: 'pendidikan', title: 'Pendidikan & Bantuan', desc: 'Sekolah Asal & SPP/PIP' },
    { number: 3, id: 'ortu', title: 'Data Orang Tua', desc: 'Identitas Ayah & Ibu Kandung' },
    { number: 4, id: 'wali', title: 'Data Wali Siswa', desc: 'Wali (Opsional)' },
    { number: 5, id: 'akademik', title: 'Akademik & Status', desc: 'Unit, Kelas & Status' },
  ]

  useEffect(() => {
    if (isOpen) {
      setActiveStep(0)
      if (initialData) {
        const meta = initialData.raw?.metadata || {}
        setFormData({
          ...defaultForm(),
          id: initialData.id,
          full_name: initialData.nama || initialData.full_name || '',
          gender: initialData.gender === 'Perempuan' ? 'female' : 'male',
          birth_place: initialData.tempatLahir || initialData.birth_place || '',
          birth_date: initialData.tanggalLahir || initialData.birth_date || '',
          agama: initialData.agama || meta.agama || 'Islam',
          foto_url: initialData.foto || meta.foto_url || '',
          nisn: initialData.nisn || meta.nisn || '',
          alamat_siswa: initialData.alamat || meta.alamat_siswa || meta.alamat_ortu || '',
          class_id: initialData.raw?.class_id || '',
          status_siswa: (initialData.status || initialData.status_siswa || 'aktif').toLowerCase(),
          unit_pendidikan: initialData.unit || meta.akademik?.unit_pendidikan || '',
          ...meta
        })
      } else {
        setFormData(defaultForm())
      }
    }
  }, [isOpen, initialData])

  if (!isOpen) return null

  const handleChange = (e) => {
    const { name, value } = e.target
    setFormData(prev => ({ ...prev, [name]: value }))
  }

  const handleFileUpload = (e) => {
    const file = e.target.files?.[0]
    if (!file) return
    setIsUploading(true)
    const reader = new FileReader()
    reader.onloadend = () => {
      setFormData(prev => ({ ...prev, foto_url: reader.result }))
      setIsUploading(false)
    }
    reader.readAsDataURL(file)
  }

  const handleRemoveFoto = () => {
    setFormData(prev => ({ ...prev, foto_url: '' }))
  }

  const handleSave = (e) => {
    if (e) e.preventDefault()
    const payload = {
      id: formData.id,
      nis: formData.nis || `23${Math.floor(1000 + Math.random() * 9000)}`,
      full_name: formData.full_name,
      gender: formData.gender,
      birth_place: formData.birth_place,
      birth_date: formData.birth_date,
      address: formData.alamat_siswa,
      class_id: formData.class_id || null,
      is_active: formData.status_siswa === 'aktif',
      metadata: { ...formData }
    }
    delete payload.metadata.id
    delete payload.metadata.full_name
    delete payload.metadata.gender
    delete payload.metadata.birth_place
    delete payload.metadata.birth_date
    delete payload.metadata.class_id
    delete payload.metadata.is_active
    delete payload.metadata.address
    onSubmit(payload)
  }

  const Input = ({ label, name, required = false, type = 'text', placeholder = '', ...props }) => (
    <div>
      <label className="block text-xs font-semibold text-slate-700 mb-1.5">
        {label} {required && <span className="text-rose-500">*</span>}
      </label>
      <input
        type={type}
        name={name}
        value={formData[name] || ''}
        onChange={handleChange}
        placeholder={placeholder}
        required={required}
        className="w-full rounded-xl border border-slate-200 px-3.5 py-2 text-sm text-slate-800 placeholder:text-slate-400 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100 outline-none transition"
        {...props}
      />
    </div>
  )

  const Select = ({ label, name, required = false, options = [], ...props }) => (
    <div>
      <label className="block text-xs font-semibold text-slate-700 mb-1.5">
        {label} {required && <span className="text-rose-500">*</span>}
      </label>
      <select
        name={name}
        value={formData[name] || ''}
        onChange={handleChange}
        required={required}
        className="w-full rounded-xl border border-slate-200 px-3.5 py-2 text-sm text-slate-800 bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100 outline-none transition"
        {...props}
      >
        <option value="">-- Pilih --</option>
        {options.map(opt => typeof opt === 'string'
          ? <option key={opt} value={opt}>{opt}</option>
          : <option key={opt.value} value={opt.value}>{opt.label}</option>
        )}
      </select>
    </div>
  )

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4 overflow-y-auto">
      <div className="w-full max-w-5xl rounded-3xl bg-white shadow-2xl overflow-hidden flex flex-col my-4 border border-slate-100">

        {/* Modal Header — Clean Top with Title & Close (X) */}
        <div className="flex items-center justify-between border-b border-slate-100 px-8 py-5 bg-white">
          <div>
            <h2 className="text-lg font-bold text-slate-900 tracking-tight">
              {initialData ? 'Ubah Data Siswa' : 'Tambah Siswa Baru'}
            </h2>
            <p className="text-xs text-slate-500 mt-0.5">Isi formulir di bawah ini untuk menyimpan data siswa ke dalam sistem</p>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="rounded-full p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition"
          >
            <FaTimes className="text-lg" />
          </button>
        </div>

        {/* Modal Main Body (2 Columns: Left Vertical Stepper, Right Form Content) */}
        <div className="grid grid-cols-1 md:grid-cols-12 min-h-[520px] max-h-[72vh] overflow-hidden">

          {/* Left Vertical Stepper Sidebar */}
          <div className="md:col-span-4 bg-slate-50/70 border-r border-slate-100 p-6 md:p-8 flex flex-col justify-between overflow-y-auto">
            <div className="space-y-6">
              {steps.map((step, idx) => {
                const isActive = activeStep === idx
                const isCompleted = activeStep > idx
                return (
                  <div
                    key={step.id}
                    onClick={() => setActiveStep(idx)}
                    className="flex items-start gap-4 cursor-pointer group"
                  >
                    {/* Circle Number */}
                    <div
                      className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-bold transition-all shadow-xs ${
                        isActive
                          ? 'bg-[#064e3b] text-white ring-4 ring-emerald-100'
                          : isCompleted
                          ? 'bg-emerald-600 text-white'
                          : 'bg-slate-200 text-slate-600 group-hover:bg-slate-300'
                      }`}
                    >
                      {isCompleted ? <FaCheckCircle className="text-sm" /> : step.number}
                    </div>

                    {/* Step Title & Subtitle */}
                    <div className="pt-0.5">
                      <h3
                        className={`text-sm font-bold transition ${
                          isActive
                            ? 'text-[#064e3b]'
                            : isCompleted
                            ? 'text-slate-800'
                            : 'text-slate-500 group-hover:text-slate-700'
                        }`}
                      >
                        {step.title}
                      </h3>
                      <p className="text-[11px] text-slate-400 font-medium">{step.desc}</p>
                    </div>
                  </div>
                )
              })}
            </div>

            <div className="mt-8 rounded-2xl bg-emerald-50/80 border border-emerald-100 p-4">
              <p className="text-xs font-semibold text-emerald-900">Petunjuk Pengisian</p>
              <p className="text-[11px] text-emerald-700 mt-1">
                Tanda bintang (<span className="text-rose-500 font-bold">*</span>) wajib diisi. Data dapat disimpan kapan saja.
              </p>
            </div>
          </div>

          {/* Right Form Content Area */}
          <div className="md:col-span-8 p-6 md:p-8 overflow-y-auto bg-white flex flex-col justify-between">
            <form id="student-main-form" onSubmit={handleSave} className="space-y-6">

              {/* STEP 1: INFORMASI PRIBADI */}
              {activeStep === 0 && (
                <div className="space-y-6">
                  {/* Upload Foto Box - Sesuai UI Gambar */}
                  <div>
                    <label className="block text-xs font-semibold text-slate-700 mb-2">Unggah Foto Siswa</label>
                    {formData.foto_url ? (
                      <div className="relative flex items-center gap-4 p-4 rounded-2xl border border-emerald-200 bg-emerald-50/40">
                        <img
                          src={formData.foto_url}
                          alt="Preview Foto"
                          className="h-20 w-20 rounded-xl object-cover border-2 border-emerald-600 shadow"
                        />
                        <div className="space-y-1">
                          <p className="text-xs font-bold text-slate-800">Foto Siswa Berhasil Diunggah</p>
                          <p className="text-[11px] text-slate-500">Guna untuk data Kartu Siswa & Administrasi</p>
                          <button
                            type="button"
                            onClick={handleRemoveFoto}
                            className="inline-flex items-center gap-1 text-xs font-bold text-rose-600 hover:underline pt-1"
                          >
                            <FaTrashAlt /> Hapus & Unggah Ulang
                          </button>
                        </div>
                      </div>
                    ) : (
                      <label className="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50/50 p-6 text-center hover:bg-emerald-50/30 hover:border-emerald-400 cursor-pointer transition">
                        <div className="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-[#064e3b] mb-2 shadow-xs">
                          <FaCloudUploadAlt className="text-2xl" />
                        </div>
                        <span className="text-sm font-bold text-slate-800">
                          {isUploading ? 'Mengunggah...' : 'Upload Foto Siswa'}
                        </span>
                        <span className="text-[11px] text-slate-400 mt-1">PNG, JPG atau WEBP (maksimal 2MB)</span>
                        <input
                          type="file"
                          accept="image/*"
                          onChange={handleFileUpload}
                          className="hidden"
                        />
                      </label>
                    )}
                  </div>

                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <Input label="Nama Lengkap Siswa" name="full_name" required placeholder="Contoh: Fathir Ahmad" />
                    <Input label="NISN" name="nisn" required placeholder="10 Digit NISN" />
                    <Input label="NIK Siswa" name="nik" placeholder="16 Digit NIK" />
                    <Input label="No Pendaftaran" name="no_pendaftaran" placeholder="PDK-2024-001" />
                    <Input label="No Kartu Keluarga (KK)" name="no_kk" placeholder="16 Digit No KK" />
                    <Input label="No Registrasi Akta Lahir" name="no_registrasi_akta_lahir" placeholder="No Akta Lahir" />
                    <Input label="Tempat Lahir" name="birth_place" placeholder="Kota Lahir" />
                    <Input label="Tanggal Lahir" name="birth_date" type="date" />
                    <Select label="Jenis Kelamin" name="gender" required options={[{label:'Laki-laki', value:'male'}, {label:'Perempuan', value:'female'}]} />
                    <Select label="Agama" name="agama" options={['Islam','Kristen','Katolik','Hindu','Buddha','Konghucu']} />
                    <Input label="Email Siswa (Opsional)" name="email" type="email" placeholder="siswa@sekolah.sch.id" />
                    <Select label="Kewarganegaraan" name="kewarganegaraan" options={['WNI','WNA']} />
                  </div>

                  <hr className="border-slate-100 my-4" />

                  <h4 className="text-xs font-bold text-slate-800 uppercase tracking-wider">Alamat & Tempat Tinggal</h4>
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div className="sm:col-span-2">
                      <Input label="Alamat Lengkap" name="alamat_siswa" placeholder="Jl. Khatib Sulaiman No. 10..." />
                    </div>
                    <Input label="RT" name="rt" placeholder="001" />
                    <Input label="RW" name="rw" placeholder="002" />
                    <Input label="Kelurahan / Desa" name="kelurahan" placeholder="Kelurahan" />
                    <Input label="Kecamatan" name="kecamatan" placeholder="Kecamatan" />
                    <Input label="Kota / Kabupaten" name="kota_kabupaten" placeholder="Kota/Kab" />
                    <Input label="Provinsi" name="provinsi" placeholder="Provinsi" />
                    <Input label="Kode Pos" name="kode_pos" placeholder="25114" />
                    <Input label="Moda Transportasi" name="moda_transportasi" placeholder="Jalan Kaki / Sepeda / Bus" />
                  </div>
                </div>
              )}

              {/* STEP 2: PENDIDIKAN & BANTUAN */}
              {activeStep === 1 && (
                <div className="space-y-6">
                  <h4 className="text-xs font-bold text-slate-800 uppercase tracking-wider">Riwayat Sekolah Asal</h4>
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <Input label="Nama Sekolah Asal" name="sekolah_asal" placeholder="SD Negeri 01 Padang" />
                    <Select label="Status Sekolah Asal" name="status_sekolah_asal" options={['Formal', 'Tidak Formal']} />
                    <Input label="Kecamatan Sekolah Asal" name="kecamatan_sekolah_asal" placeholder="Kecamatan" />
                    <Input label="Kota / Kab Sekolah Asal" name="kota_kab_sekolah_asal" placeholder="Kota/Kab" />
                    <Input label="No HP / WA Sekolah Asal" name="nomor_hp_wa_sekolah_asal" placeholder="08xx-xxxx-xxxx" />
                  </div>

                  <hr className="border-slate-100 my-4" />

                  <h4 className="text-xs font-bold text-slate-800 uppercase tracking-wider">Administrasi Bantuan</h4>
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <Input label="Nominal SPP (Rp)" name="nominal_spp" type="number" placeholder="500000" />
                    <Input label="Nominal Bantuan Ortu Asuh (Rp)" name="nominal_ortu_asuh" type="number" placeholder="0" />
                    <Select label="Penerima KPS / PKH" name="penerima_kps_pkh" options={['ya', 'tidak']} />
                    <Select label="Apakah Punya KIP?" name="apakah_punya_kip" options={['ya', 'tidak']} />
                    <Select label="Apakah Layak Menerima PIP?" name="apakah_layak_menerima_pip" options={['ya', 'tidak']} />
                    <div className="sm:col-span-2">
                      <Input label="Alasan Menolak PIP (Jika ada)" name="alasan_menolak_pip" placeholder="Ditolak / Sudah Mampu" />
                    </div>
                  </div>
                </div>
              )}

              {/* STEP 3: DATA ORANG TUA */}
              {activeStep === 2 && (
                <div className="space-y-6">
                  <h4 className="text-xs font-bold text-emerald-900 border-b border-emerald-100 pb-2">Ayah Kandung</h4>
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <Input label="NIK Ayah" name="nik_ayah" placeholder="16 Digit NIK Ayah" />
                    <Input label="Nama Ayah Kandung" name="nama_ayah" placeholder="Nama Ayah" />
                    <Input label="Tempat Lahir Ayah" name="tempat_lahir_ayah" />
                    <Input label="Tanggal Lahir Ayah" name="tgl_lahir_ayah" type="date" />
                    <Input label="No HP / WA Ayah" name="hp_ayah" placeholder="08xx-xxxx-xxxx" />
                    <Select label="Pendidikan Terakhir Ayah" name="pendidikan_terakhir_ayah" options={['SD/MI','SMP/MTs','SMA/SMK/MA','D1/D2/D3','S1/D4','S2','S3','Tidak Sekolah']} />
                    <Input label="Pekerjaan Ayah" name="pekerjaan_ayah" placeholder="Wiraswasta / PNS / Guru" />
                    <Input label="Penghasilan Ayah (Rp)" name="penghasilan_ayah" placeholder="5000000" />
                  </div>

                  <h4 className="text-xs font-bold text-emerald-900 border-b border-emerald-100 pb-2 pt-2">Ibu Kandung</h4>
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <Input label="NIK Ibu" name="nik_ibu" placeholder="16 Digit NIK Ibu" />
                    <Input label="Nama Ibu Kandung" name="nama_ibu" placeholder="Nama Ibu" />
                    <Input label="Tempat Lahir Ibu" name="tempat_lahir_ibu" />
                    <Input label="Tanggal Lahir Ibu" name="tgl_lahir_ibu" type="date" />
                    <Input label="No HP / WA Ibu" name="hp_ibu" placeholder="08xx-xxxx-xxxx" />
                    <Select label="Pendidikan Terakhir Ibu" name="pendidikan_terakhir_ibu" options={['SD/MI','SMP/MTs','SMA/SMK/MA','D1/D2/D3','S1/D4','S2','S3','Tidak Sekolah']} />
                    <Input label="Pekerjaan Ibu" name="pekerjaan_ibu" placeholder="Ibu Rumah Tangga / PNS" />
                    <Input label="Penghasilan Ibu (Rp)" name="penghasilan_ibu" placeholder="0" />
                  </div>
                </div>
              )}

              {/* STEP 4: DATA WALI */}
              {activeStep === 3 && (
                <div className="space-y-6">
                  <div className="bg-slate-50 border border-slate-200 rounded-2xl p-4 text-xs text-slate-600">
                    Isi data di bawah ini hanya jika siswa tinggal bersama wali (selain ayah/ibu kandung).
                  </div>
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <Input label="NIK Wali" name="nik_wali" placeholder="16 Digit NIK Wali" />
                    <Input label="Nama Wali Siswa" name="nama_wali" placeholder="Nama Wali" />
                    <Input label="No HP / WA Wali" name="hp_wali" placeholder="08xx-xxxx-xxxx" />
                    <Select label="Pendidikan Terakhir Wali" name="pendidikan_terakhir_wali" options={['SD/MI','SMP/MTs','SMA/SMK/MA','D1/D2/D3','S1/D4','S2','S3','Tidak Sekolah']} />
                    <Input label="Pekerjaan Wali" name="pekerjaan_wali" placeholder="Pekerjaan Wali" />
                    <Input label="Penghasilan Wali (Rp)" name="penghasilan_wali" placeholder="Penghasilan" />
                    <div className="sm:col-span-2">
                      <Input label="Alamat Wali" name="alamat_wali" placeholder="Alamat lengkap wali..." />
                    </div>
                  </div>
                </div>
              )}

              {/* STEP 5: AKADEMIK & STATUS */}
              {activeStep === 4 && (
                <div className="space-y-6">
                  <h4 className="text-xs font-bold text-slate-800 uppercase tracking-wider">Penempatan Unit & Kelas</h4>
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <Input label="Unit Pendidikan" name="unit_pendidikan" placeholder="Contoh: SDIT 2 Dar el-Iman" />
                    <Select label="Pilih Kelas" name="class_id" options={classes?.map(c => ({ value: c.id, label: c.name })) || []} />
                    <Input label="NIS Pembayaran" name="nis_pembayaran" placeholder="NIS Pembayaran" />
                    <Input label="Tahun Ajaran Masuk" name="tahun_ajaran_masuk" placeholder="2024/2025" />
                    <Input label="Tahun Ajaran Berjalan" name="tahun_ajaran_berjalan" placeholder="2024/2025" />
                    <Select label="Status Siswa" name="status_siswa" options={['aktif', 'lulus', 'mutasi', 'berhenti']} />
                    <Select label="Status Orang Tua" name="status_orang_tua" options={['Umum', 'Pegawai']} />
                    <Input label="NIY Ortu (Jika Pegawai)" name="niy_ortu_jika_pegawai" placeholder="NIY Pegawai" />
                  </div>
                </div>
              )}

            </form>
          </div>

        </div>

        {/* Modal Footer — Standard Actions Matching UI Image */}
        <div className="flex items-center justify-between border-t border-slate-100 bg-white px-8 py-4">
          <button
            type="button"
            onClick={onClose}
            className="rounded-xl border border-slate-200 bg-white px-6 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition shadow-2xs"
          >
            Batal
          </button>

          <div className="flex items-center gap-3">
            {activeStep > 0 && (
              <button
                type="button"
                onClick={() => setActiveStep(prev => prev - 1)}
                className="flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition"
              >
                <FaArrowLeft className="text-xs" /> Kembali
              </button>
            )}

            <button
              type="button"
              onClick={() => handleSave()}
              className="rounded-xl border border-emerald-600 bg-white px-5 py-2.5 text-sm font-bold text-emerald-700 hover:bg-emerald-50 transition shadow-2xs"
            >
              Simpan Draft
            </button>

            {activeStep < steps.length - 1 ? (
              <button
                type="button"
                onClick={() => setActiveStep(prev => prev + 1)}
                className="flex items-center gap-2 rounded-xl bg-[#064e3b] hover:bg-emerald-900 px-6 py-2.5 text-sm font-bold text-white shadow-md transition"
              >
                Selanjutnya <FaArrowRight className="text-xs" />
              </button>
            ) : (
              <button
                type="submit"
                form="student-main-form"
                className="flex items-center gap-2 rounded-xl bg-[#064e3b] hover:bg-emerald-900 px-6 py-2.5 text-sm font-bold text-white shadow-md transition"
              >
                <FaSave /> Simpan Data
              </button>
            )}
          </div>
        </div>

      </div>
    </div>
  )
}
