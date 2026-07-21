import { useEffect, useState } from 'react'
import { useForm } from 'react-hook-form'
import Swal from 'sweetalert2'
import { useDaftarGuru, useDaftarKelas } from '../hooks/useReferenceData'
import { useAksiSiswa, useDaftarSiswa } from '../hooks/useStudents'
import { dashboardUnits, useUnitStore } from '../stores/unitStore'

const pilihanGender = [
  { value: 'male', label: 'Laki-laki' },
  { value: 'female', label: 'Perempuan' },
]

const pilihanBoolean = [
  { value: 'true', label: 'Ya' },
  { value: 'false', label: 'Tidak' },
]

const pilihanStatusAktif = [
  { value: 'true', label: 'Aktif' },
  { value: 'false', label: 'Tidak Aktif' },
]

const studentTabs = [
  { key: 'siswa', label: 'Data Siswa' },
  { key: 'ayah', label: 'Data Ayah' },
  { key: 'ibu', label: 'Data Ibu' },
  { key: 'wali', label: 'Data Wali' },
  { key: 'akademik', label: 'Data Akademik' },
]

const sectionSiswa = [
  ['metadata.no_pendaftaran', 'No Pendaftaran'],
  ['metadata.nik', 'NIK'],
  ['metadata.no_registrasi_akta_lahir', 'No Registrasi Akta Lahir'],
  ['metadata.no_kk', 'No KK'],
  ['nis', 'NIS'],
  ['metadata.nisn', 'NISN'],
  ['full_name', 'Nama Lengkap'],
  ['birth_date', 'Tanggal Lahir', 'date'],
  ['birth_place', 'Tempat Lahir'],
  ['gender', 'Jenis Kelamin', 'select', pilihanGender],
  ['metadata.agama', 'Agama'],
  ['metadata.email', 'Email', 'email'],
  ['metadata.anak_ke', 'Anak Ke', 'number'],
  ['metadata.jumlah_saudara', 'Jumlah Saudara', 'number'],
  ['metadata.jumlah_saudara_tiri', 'Jumlah Saudara Tiri', 'number'],
  ['metadata.berat_badan', 'Berat Badan', 'number'],
  ['metadata.tinggi_badan', 'Tinggi Badan', 'number'],
  ['metadata.riwayat_penyakit', 'Riwayat Penyakit'],
  ['metadata.kewarganegaraan', 'Kewarganegaraan'],
  ['address', 'Alamat Siswa'],
  ['metadata.rt', 'RT'],
  ['metadata.rw', 'RW'],
  ['metadata.dusun', 'Dusun'],
  ['metadata.kelurahan', 'Kelurahan'],
  ['metadata.kecamatan', 'Kecamatan'],
  ['metadata.kode_pos', 'Kode Pos'],
  ['metadata.kota_kabupaten', 'Kota/Kabupaten'],
  ['metadata.provinsi', 'Provinsi'],
  ['metadata.jenis_tempat_tinggal', 'Jenis Tempat Tinggal'],
  ['metadata.jarak_tempuh_ke_sekolah', 'Jarak Tempuh ke Sekolah'],
  ['metadata.modal_transportasi', 'Modal Transportasi'],
  ['metadata.sekolah_asal', 'Sekolah Asal'],
  ['metadata.status_sekolah_asal', 'Status Sekolah Asal'],
  ['metadata.kecamatan_sekolah_asal', 'Kecamatan Sekolah Asal'],
  ['metadata.kota_kab_sekolah_asal', 'Kota/Kab Sekolah Asal'],
  ['metadata.nomor_hp_wa_sekolah_asal', 'Nomor HP/WA Sekolah Asal'],
  ['metadata.hobi', 'Hobi'],
  ['metadata.cita_cita', 'Cita-cita'],
  ['metadata.nominal_spp', 'Nominal SPP', 'number'],
  ['metadata.nominal_ortu_asuh', 'Nominal Ortu Asuh', 'number'],
  ['metadata.penerima_kps_pkh', 'Penerima KPS/PKH', 'select', pilihanBoolean],
  ['metadata.apakah_punya_kip', 'Apakah Punya KIP', 'select', pilihanBoolean],
  ['metadata.apakah_layak_menerima_pip', 'Apakah Layak Menerima PIP', 'select', pilihanBoolean],
  ['metadata.alasan_menolak_pip', 'Alasan Menolak PIP', 'textarea'],
]

const sectionAyah = [
  ['metadata.ayah.nik', 'NIK Ayah'],
  ['metadata.ayah.nama', 'Nama Ayah'],
  ['metadata.ayah.tempat_lahir', 'Tempat Lahir Ayah'],
  ['metadata.ayah.tanggal_lahir', 'Tgl Lahir Ayah', 'date'],
  ['metadata.ayah.telfon', 'Telfon Ayah'],
  ['metadata.ayah.hp', 'HP Ayah'],
  ['metadata.ayah.pendidikan_terakhir', 'Pendidikan Terakhir Ayah'],
  ['metadata.ayah.pekerjaan', 'Pekerjaan Ayah'],
  ['metadata.ayah.instansi_pekerjaan', 'Instansi Pekerjaan Ayah'],
  ['metadata.ayah.jabatan_pekerjaan', 'Jabatan Pekerjaan Ayah'],
  ['metadata.ayah.alamat_instansi', 'Alamat Instansi Ayah'],
  ['metadata.ayah.keahlian', 'Keahlian Ayah'],
  ['metadata.ayah.penghasilan', 'Penghasilan Ayah'],
  ['metadata.ayah.alamat', 'Alamat Ayah'],
  ['metadata.ayah.nomor_wa', 'Nomor WA Ayah'],
  ['metadata.ayah.medsos', 'Medsos Ayah'],
]

const sectionIbu = [
  ['metadata.ibu.nama', 'Nama Ibu'],
  ['metadata.ibu.nik', 'NIK Ibu'],
  ['metadata.ibu.tempat_lahir', 'Tempat Lahir Ibu'],
  ['metadata.ibu.tanggal_lahir', 'Tgl Lahir Ibu', 'date'],
  ['metadata.ibu.telfon', 'Telfon Ibu'],
  ['metadata.ibu.hp', 'HP Ibu'],
  ['metadata.ibu.pendidikan_terakhir', 'Pendidikan Terakhir Ibu'],
  ['metadata.ibu.pekerjaan', 'Pekerjaan Ibu'],
  ['metadata.ibu.instansi_pekerjaan', 'Instansi Pekerjaan Ibu'],
  ['metadata.ibu.jabatan_pekerjaan', 'Jabatan Pekerjaan Ibu'],
  ['metadata.ibu.alamat_instansi', 'Alamat Instansi Ibu'],
  ['metadata.ibu.keahlian', 'Keahlian Ibu'],
  ['metadata.ibu.penghasilan', 'Penghasilan Ibu'],
  ['metadata.ibu.alamat', 'Alamat Ibu'],
  ['metadata.ibu.nomor_wa', 'Nomor WA Ibu'],
  ['metadata.ibu.medsos', 'Medsos Ibu'],
]

const sectionWali = [
  ['metadata.wali.status_pernikahan', 'Status Pernikahan'],
  ['metadata.wali.tanggungan_anak', 'Tanggungan Anak'],
  ['metadata.wali.nik', 'NIK Wali'],
  ['metadata.wali.nama', 'Nama Wali'],
  ['metadata.wali.tempat_lahir', 'Tempat Lahir Wali'],
  ['metadata.wali.tanggal_lahir', 'Tgl Lahir Wali', 'date'],
  ['metadata.wali.telfon', 'Telfon Wali'],
  ['metadata.wali.hp', 'HP Wali'],
  ['metadata.wali.pendidikan_terakhir', 'Pendidikan Terakhir Wali'],
  ['metadata.wali.pekerjaan', 'Pekerjaan Wali'],
  ['metadata.wali.instansi_pekerjaan', 'Instansi Pekerjaan Wali'],
  ['metadata.wali.jabatan_pekerjaan', 'Jabatan Pekerjaan Wali'],
  ['metadata.wali.alamat_instansi', 'Alamat Instansi Wali'],
  ['metadata.wali.keahlian', 'Keahlian Wali'],
  ['metadata.wali.penghasilan', 'Penghasilan Wali'],
  ['metadata.wali.alamat', 'Alamat Wali'],
  ['metadata.wali.nomor_wa', 'Nomor WA Wali'],
  ['metadata.wali.medsos', 'Medsos Wali'],
]

function buatDefaultValues(activeUnit = 'SD') {
  return {
    class_id: '',
    nis: '',
    full_name: '',
    gender: 'male',
    birth_date: '',
    birth_place: '',
    address: '',
    is_active: 'true',
    metadata: {
      no_pendaftaran: '',
      nik: '',
      no_registrasi_akta_lahir: '',
      no_kk: '',
      nisn: '',
      agama: '',
      email: '',
      anak_ke: '',
      jumlah_saudara: '',
      jumlah_saudara_tiri: '',
      berat_badan: '',
      tinggi_badan: '',
      riwayat_penyakit: '',
      kewarganegaraan: '',
      rt: '',
      rw: '',
      dusun: '',
      kelurahan: '',
      kecamatan: '',
      kode_pos: '',
      kota_kabupaten: '',
      provinsi: '',
      jenis_tempat_tinggal: '',
      jarak_tempuh_ke_sekolah: '',
      modal_transportasi: '',
      sekolah_asal: '',
      status_sekolah_asal: '',
      kecamatan_sekolah_asal: '',
      kota_kab_sekolah_asal: '',
      nomor_hp_wa_sekolah_asal: '',
      hobi: '',
      cita_cita: '',
      nominal_spp: '',
      nominal_ortu_asuh: '',
      penerima_kps_pkh: 'false',
      apakah_punya_kip: 'false',
      apakah_layak_menerima_pip: 'false',
      alasan_menolak_pip: '',
      ayah: {},
      ibu: {},
      wali: {},
      akademik: {
        unit_pendidikan: activeUnit,
        nis_pembayaran: '',
        tahun_ajaran_masuk: '',
        kelas: '',
        kelas_id: '',
        tahun_ajaran_berjalan: '',
        status_siswa: '',
        status_orang_tua: '',
        niy_ortu_jika_pegawai: '',
        wali_kelas: '',
        wali_kelas_id: '',
        niy_wali_kelas: '',
      },
    },
  }
}

function parseBoolean(value) {
  if (typeof value === 'boolean') return value
  return String(value) === 'true'
}

function mapStudentToForm(student, activeUnit = 'SD') {
  const metadata = student?.metadata || {}

  return {
    ...buatDefaultValues(activeUnit),
    class_id: student?.class_id || metadata?.akademik?.kelas_id || '',
    nis: student?.nis || '',
    full_name: student?.full_name || '',
    gender: student?.gender || 'male',
    birth_date: student?.birth_date || '',
    birth_place: student?.birth_place || '',
    address: student?.address || '',
    is_active: String(Boolean(student?.is_active ?? true)),
    metadata: {
      ...buatDefaultValues(activeUnit).metadata,
      ...metadata,
      penerima_kps_pkh: String(Boolean(metadata?.penerima_kps_pkh ?? false)),
      apakah_punya_kip: String(Boolean(metadata?.apakah_punya_kip ?? false)),
      apakah_layak_menerima_pip: String(Boolean(metadata?.apakah_layak_menerima_pip ?? false)),
      ayah: metadata?.ayah || {},
      ibu: metadata?.ibu || {},
      wali: metadata?.wali || {},
      akademik: metadata?.akademik || {},
    },
  }
}

function sanitizeNumber(value) {
  return value === '' || value === null || typeof value === 'undefined' ? null : Number(value)
}

function buildPayload(values, kelasOptions, guruOptions) {
  const kelasTerpilih = kelasOptions.find((item) => item.value === values.class_id)
  const waliKelasTerpilih = guruOptions.find((item) => item.value === values.metadata.akademik.wali_kelas_id)

  return {
    class_id: values.class_id || null,
    nis: values.nis,
    full_name: values.full_name,
    gender: values.gender,
    birth_date: values.birth_date || null,
    birth_place: values.birth_place || null,
    address: values.address || null,
    is_active: parseBoolean(values.is_active),
    metadata: {
      ...values.metadata,
      anak_ke: sanitizeNumber(values.metadata.anak_ke),
      jumlah_saudara: sanitizeNumber(values.metadata.jumlah_saudara),
      jumlah_saudara_tiri: sanitizeNumber(values.metadata.jumlah_saudara_tiri),
      berat_badan: sanitizeNumber(values.metadata.berat_badan),
      tinggi_badan: sanitizeNumber(values.metadata.tinggi_badan),
      nominal_spp: sanitizeNumber(values.metadata.nominal_spp),
      nominal_ortu_asuh: sanitizeNumber(values.metadata.nominal_ortu_asuh),
      penerima_kps_pkh: parseBoolean(values.metadata.penerima_kps_pkh),
      apakah_punya_kip: parseBoolean(values.metadata.apakah_punya_kip),
      apakah_layak_menerima_pip: parseBoolean(values.metadata.apakah_layak_menerima_pip),
      akademik: {
        ...(values.metadata.akademik || {}),
        kelas_id: values.class_id || null,
        kelas: kelasTerpilih?.label || values.metadata.akademik?.kelas || '',
        wali_kelas: waliKelasTerpilih?.label || values.metadata.akademik?.wali_kelas || '',
        niy_wali_kelas: waliKelasTerpilih?.niy || values.metadata.akademik?.niy_wali_kelas || '',
      },
      ayah: values.metadata.ayah || {},
      ibu: values.metadata.ibu || {},
      wali: values.metadata.wali || {},
    },
  }
}

function RenderField({ form, config }) {
  const [name, label, type = 'text', options] = config

  if (type === 'select') {
    return (
      <label className="student-field">
        <span>{label}</span>
        <select {...form.register(name)}>
          {(options || []).map((option) => (
            <option key={option.value} value={option.value}>{option.label}</option>
          ))}
        </select>
      </label>
    )
  }

  if (type === 'textarea') {
    return (
      <label className="student-field student-field-wide">
        <span>{label}</span>
        <textarea rows="3" {...form.register(name)} />
      </label>
    )
  }

  return (
    <label className="student-field">
      <span>{label}</span>
      <input type={type} {...form.register(name)} />
    </label>
  )
}

export default function StudentsPage() {
  const [search, setSearch] = useState('')
  const [editingId, setEditingId] = useState(null)
  const [activeTab, setActiveTab] = useState('siswa')
  const activeUnit = useUnitStore((state) => state.activeUnit)
  const { data: daftarSiswa } = useDaftarSiswa({ search, per_page: 20 })
  const { data: daftarKelas } = useDaftarKelas()
  const { data: daftarGuru } = useDaftarGuru()
  const aksiSiswa = useAksiSiswa()
  const form = useForm({ defaultValues: buatDefaultValues(activeUnit) })

  useEffect(() => {
    if (!editingId) {
      form.setValue('metadata.akademik.unit_pendidikan', activeUnit)
    }
  }, [activeUnit, editingId, form])

  const kelasOptions = [
    { value: '', label: 'Pilih kelas' },
    ...((daftarKelas?.data || []).map((item) => ({
      value: item.id,
      label: [item.level, item.name].filter(Boolean).join(' '),
    }))),
  ]

  const guruOptions = [
    { value: '', label: 'Pilih wali kelas' },
    ...((daftarGuru?.data || []).map((item) => ({
      value: item.id,
      label: item.full_name,
      niy: item.employee_number || '',
    }))),
  ]

  const sectionAkademik = [
    ['metadata.akademik.unit_pendidikan', 'Unit Pendidikan', 'select', dashboardUnits.map((unit) => ({ value: unit, label: unit }))],
    ['metadata.akademik.nis_pembayaran', 'NIS Pembayaran'],
    ['metadata.akademik.tahun_ajaran_masuk', 'Tahun Ajaran Masuk'],
    ['class_id', 'Kelas', 'select', kelasOptions],
    ['metadata.akademik.tahun_ajaran_berjalan', 'Tahun Ajaran Berjalan'],
    ['metadata.akademik.status_siswa', 'Status Siswa'],
    ['metadata.akademik.status_orang_tua', 'Status Orang Tua'],
    ['metadata.akademik.niy_ortu_jika_pegawai', 'NIY Ortu Jika Pegawai'],
    ['metadata.akademik.wali_kelas_id', 'Wali Kelas', 'select', guruOptions],
    ['metadata.akademik.niy_wali_kelas', 'NIY Wali Kelas'],
    ['is_active', 'Status Aktif', 'select', pilihanStatusAktif],
  ]

  const submitSiswa = async (values) => {
    const payload = buildPayload(values, kelasOptions, guruOptions)

    if (editingId) {
      await aksiSiswa.ubah.mutateAsync({ id: editingId, payload })
    } else {
      await aksiSiswa.tambah.mutateAsync(payload)
    }

    setEditingId(null)
    setActiveTab('siswa')
    form.reset(buatDefaultValues(activeUnit))
  }

  const pilihEdit = (row) => {
    setEditingId(row.id)
    setActiveTab('siswa')
    form.reset(mapStudentToForm(row, activeUnit))
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }

  const hapusSiswa = async (id, name) => {
    const konfirmasi = await Swal.fire({
      title: `Hapus data ${name}?`,
      text: 'Data siswa yang dihapus tidak dapat dikembalikan.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Ya, Hapus',
      cancelButtonText: 'Batal',
    })

    if (konfirmasi.isConfirmed) {
      await aksiSiswa.hapus.mutateAsync(id)
      if (editingId === id) {
        setEditingId(null)
        setActiveTab('siswa')
        form.reset(buatDefaultValues(activeUnit))
      }
    }
  }

  return (
    <section className="panel modul-crud-page student-crud-page">
      <div className="table-header student-page-head">
        <div>
          <h3>Modul Siswa - CRUD Data Siswa</h3>
          <p className="modul-lead">Input data siswa lengkap beserta data ayah, ibu, wali, dan akademik.</p>
        </div>
        <input
          type="text"
          className="search-input"
          placeholder="Cari NIS / nama siswa"
          value={search}
          onChange={(event) => setSearch(event.target.value)}
        />
      </div>

      <form className="student-form-layout" onSubmit={form.handleSubmit(submitSiswa)}>
        <div className="student-tab-row">
          {studentTabs.map((tab) => (
            <button
              key={tab.key}
              type="button"
              className={`student-tab-btn ${activeTab === tab.key ? 'active' : ''}`}
              onClick={() => setActiveTab(tab.key)}
            >
              {tab.label}
            </button>
          ))}
        </div>

        <section className={`student-form-section student-form-primary ${activeTab === 'siswa' ? '' : 'is-hidden'}`}>
          <h4>Data Siswa</h4>
          <div className="student-fields-grid">
            {sectionSiswa.map((config) => <RenderField key={config[0]} form={form} config={config} />)}
          </div>
        </section>

        <section className={`student-form-section student-form-father ${activeTab === 'ayah' ? '' : 'is-hidden'}`}>
          <h4>Data Ayah</h4>
          <div className="student-fields-grid">
            {sectionAyah.map((config) => <RenderField key={config[0]} form={form} config={config} />)}
          </div>
        </section>

        <section className={`student-form-section student-form-mother ${activeTab === 'ibu' ? '' : 'is-hidden'}`}>
          <h4>Data Ibu</h4>
          <div className="student-fields-grid">
            {sectionIbu.map((config) => <RenderField key={config[0]} form={form} config={config} />)}
          </div>
        </section>

        <section className={`student-form-section student-form-guardian ${activeTab === 'wali' ? '' : 'is-hidden'}`}>
          <h4>Data Wali</h4>
          <div className="student-fields-grid">
            {sectionWali.map((config) => <RenderField key={config[0]} form={form} config={config} />)}
          </div>
        </section>

        <section className={`student-form-section student-form-academic ${activeTab === 'akademik' ? '' : 'is-hidden'}`}>
          <h4>Data Akademik</h4>
          <div className="student-fields-grid student-fields-grid-academic">
            {sectionAkademik.map((config) => <RenderField key={config[0]} form={form} config={config} />)}
          </div>
        </section>

        <div className="form-actions">
          <button className="aksi simpan" type="submit">{editingId ? 'Perbarui Data Siswa' : 'Simpan Data Siswa'}</button>
          {editingId ? <button className="aksi batal" type="button" onClick={() => { setEditingId(null); form.reset(buatDefaultValues()) }}>Batal Edit</button> : null}
        </div>
      </form>

      <div className="table-wrap modul-table-wrap">
        <table>
          <thead>
            <tr>
              <th>NIS</th>
              <th>Nama Siswa</th>
              <th>Unit</th>
              <th>Kelas</th>
              <th>Status</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            {(daftarSiswa?.data || []).map((row) => (
              <tr key={row.id}>
                <td>{row.nis}</td>
                <td>{row.full_name}</td>
                <td>{row.metadata?.akademik?.unit_pendidikan || '-'}</td>
                <td>{row.metadata?.akademik?.kelas || '-'}</td>
                <td>{row.is_active ? 'Aktif' : 'Tidak Aktif'}</td>
                <td>
                  <div className="aksi-row">
                    <button type="button" className="aksi kecil" onClick={() => pilihEdit(row)}>Edit</button>
                    <button type="button" className="aksi kecil danger" onClick={() => hapusSiswa(row.id, row.full_name)}>Hapus</button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </section>
  )
}
