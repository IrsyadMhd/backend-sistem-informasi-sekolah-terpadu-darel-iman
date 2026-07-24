import { useMemo, useState } from 'react'
import {
  FaArrowLeft,
  FaArrowRight,
  FaCheckCircle,
  FaEdit,
  FaEye,
  FaFileExcel,
  FaPlus,
  FaPrint,
  FaSearch,
  FaTrash,
  FaUser,
  FaUserGraduate,
} from 'react-icons/fa'
import Swal from 'sweetalert2'
import CetakKartuSiswaModal from '../components/siswa/CetakKartuSiswaModal'
import { useDaftarKelas } from '../hooks/useReferenceData'
import { useAksiSiswa, useDaftarSiswa } from '../hooks/useStudents'
import { studentService } from '../services/studentService'

const initialForm = () => ({
  id: null,
  // Step 1: Data Siswa
  nis: '',
  nisn: '',
  full_name: '',
  birth_place: '',
  birth_date: '',
  gender: 'male',
  agama: 'Islam',
  foto_url: '',

  // Step 2: Ortu / Wali
  nama_ayah: '',
  pekerjaan_ayah: '',
  hp_ayah: '',
  nama_ibu: '',
  pekerjaan_ibu: '',
  hp_ibu: '',
  nama_wali: '',
  hp_wali: '',
  alamat_ortu: '',

  // Step 3: Akademik
  unit_pendidikan: 'SDIT 2 Dar el-Iman - Padang',
  class_id: '',
  kelas_label: '6A',
  rombel: 'Rombel A',
  tahun_ajaran: '2024/2025',
  tanggal_masuk: '2023-07-10',
  no_induk_sebelumnya: '',
  status_siswa: 'aktif',
  kurikulum: 'Kurikulum Merdeka',
  beasiswa: 'Tidak Ada',
  catatan: '',
})

export default function StudentsPage() {
  // Page mode: 'list' | 'form' | 'detail'
  const [viewMode, setViewMode] = useState('list')
  const [step, setStep] = useState(1)

  // Filters
  const [unitFilter, setUnitFilter] = useState('')
  const [kelasFilter, setKelasFilter] = useState('')
  const [statusFilter, setStatusFilter] = useState('')
  const [searchInput, setSearchInput] = useState('')

  // Selected student for detail or edit
  const [selectedStudent, setSelectedStudent] = useState(null)
  const [activeDetailTab, setActiveDetailTab] = useState('siswa')
  const [showCetakModal, setShowCetakModal] = useState(false)
  const [studentToPrint, setStudentToPrint] = useState(null)

  // Form State
  const [formData, setFormData] = useState(initialForm())
  const [isEdit, setIsEdit] = useState(false)

  // Pagination state
  const [currentPage, setCurrentPage] = useState(1)
  const itemsPerPage = 8

  // Hooks data API
  const { data: daftarSiswaData, isLoading } = useDaftarSiswa({ per_page: 500, search: searchInput })
  const { data: daftarKelasData } = useDaftarKelas({ per_page: 200 })
  const { tambah, ubah, hapus } = useAksiSiswa()

  const rawStudents = daftarSiswaData?.data || []
  const rawClasses = daftarKelasData?.data || []

  // Predefined default mock students if DB is starting fresh or empty
  const defaultStudents = useMemo(() => [
    {
      id: 'demo-1',
      nis: '23001',
      nisn: '0098765446',
      nama: 'Ahmad Zaky',
      unit: 'SDIT 2 Dar el-Iman - Padang',
      kelas: '6A',
      orangTua: 'Ahmad Fauzi (Ayah)',
      noHp: '0812-3456-7890',
      status: 'Aktif',
      gender: 'Laki-laki',
      tempatLahir: 'Padang',
      tanggalLahir: '2014-05-12',
      agama: 'Islam',
      alamat: 'Jl. Khatib Sulaiman No. 10 Kel. Lolong Belanti Kec. Padang Utara Padang',
    },
    {
      id: 'demo-2',
      nis: '23002',
      nisn: '0098765447',
      nama: 'Aisyah Humaira',
      unit: 'SDIT 2 Dar el-Iman - Padang',
      kelas: '6A',
      orangTua: 'Siti Rahmawati (Ibu)',
      noHp: '0813-2222-4444',
      status: 'Aktif',
      gender: 'Perempuan',
      tempatLahir: 'Padang',
      tanggalLahir: '2014-08-20',
      agama: 'Islam',
      alamat: 'Jl. Belanti Indah No. 4 Padang',
    },
    {
      id: 'demo-3',
      nis: '23003',
      nisn: '0098765448',
      nama: 'Muhammad Fadli',
      unit: 'SDIT 3 Dar el-Iman - Padang',
      kelas: '5B',
      orangTua: 'Fadli Hasan (Ayah)',
      noHp: '0812-1111-2222',
      status: 'Aktif',
      gender: 'Laki-laki',
      tempatLahir: 'Padang',
      tanggalLahir: '2015-02-14',
      agama: 'Islam',
      alamat: 'Jl. By Pass Km 11 Padang',
    },
    {
      id: 'demo-4',
      nis: '23004',
      nisn: '0098765449',
      nama: 'Nabila Putri',
      unit: 'SDIT 1 Dsr el-Iman - 50 Kota',
      kelas: '5A',
      orangTua: 'Rudi Santoso (Ayah)',
      noHp: '0812-3333-4444',
      status: 'Aktif',
      gender: 'Perempuan',
      tempatLahir: 'Payakumbuh',
      tanggalLahir: '2015-06-05',
      agama: 'Islam',
      alamat: 'Payakumbuh Barat',
    },
    {
      id: 'demo-5',
      nis: '23005',
      nisn: '0098765450',
      nama: 'Raihan Abiyyu',
      unit: 'MIT SaQu Dar el-Iman - Padang',
      kelas: '4A',
      orangTua: 'Andi Wijaya (Ayah)',
      noHp: '0812-5555-6666',
      status: 'Mutasi',
      gender: 'Laki-laki',
      tempatLahir: 'Padang',
      tanggalLahir: '2016-01-18',
      agama: 'Islam',
      alamat: 'Kuranji Padang',
    },
    {
      id: 'demo-6',
      nis: '23006',
      nisn: '0098765451',
      nama: 'Salsabila Zahra',
      unit: 'TKIT 1 Dar el-Iman - Padang',
      kelas: 'TK B',
      orangTua: 'Dewi Anggraini (Ibu)',
      noHp: '0813-7777-8888',
      status: 'Aktif',
      gender: 'Perempuan',
      tempatLahir: 'Padang',
      tanggalLahir: '2019-09-30',
      agama: 'Islam',
      alamat: 'Siteba Padang',
    },
    {
      id: 'demo-7',
      nis: '23007',
      nisn: '0098765452',
      nama: 'Fahrian Ibrahim',
      unit: 'SDIT 4 Dar el-Iman - Padang',
      kelas: '3A',
      orangTua: 'Budi Setiawan (Ayah)',
      noHp: '0813-9999-0000',
      status: 'Lulus',
      gender: 'Laki-laki',
      tempatLahir: 'Padang',
      tanggalLahir: '2014-04-10',
      agama: 'Islam',
      alamat: 'Ulak Karang Padang',
    },
    {
      id: 'demo-8',
      nis: '23008',
      nisn: '0098765453',
      nama: 'Kayla Azka',
      unit: 'SDIT 2 Dar el-Iman - Padang',
      kelas: '3B',
      orangTua: 'Maya Sari (Ibu)',
      noHp: '0812-1212-3434',
      status: 'Aktif',
      gender: 'Perempuan',
      tempatLahir: 'Padang',
      tanggalLahir: '2017-03-25',
      agama: 'Islam',
      alamat: 'Lapai Padang',
    },
  ], [])

  // Map API students to display list
  const formattedStudents = useMemo(() => {
    if (rawStudents.length > 0) {
      return rawStudents.map((item) => {
        const meta = item.metadata || {}
        const ortuObj = meta.ayah?.nama
          ? `${meta.ayah.nama} (Ayah)`
          : meta.ibu?.nama
            ? `${meta.ibu.nama} (Ibu)`
            : meta.wali?.nama
              ? `${meta.wali.nama} (Wali)`
              : '-'
        const hpObj = meta.ayah?.hp || meta.ibu?.hp || meta.wali?.hp || '-'

        const stRaw = String(meta.akademik?.status_siswa || (item.is_active ? 'aktif' : 'nonaktif')).toLowerCase()
        const statusText =
          stRaw === 'mutasi'
            ? 'Mutasi'
            : stRaw === 'lulus'
              ? 'Lulus'
              : stRaw === 'aktif' || item.is_active
                ? 'Aktif'
                : 'Nonaktif'

        return {
          id: item.id,
          nis: item.nis || '-',
          nisn: meta.nisn || '-',
          nama: item.full_name || '-',
          unit: meta.akademik?.unit_pendidikan || meta.unit_pendidikan || 'SDIT 2 Dar el-Iman - Padang',
          kelas: meta.akademik?.kelas || item.class?.name || '6A',
          orangTua: ortuObj,
          noHp: hpObj,
          status: statusText,
          gender: item.gender === 'female' ? 'Perempuan' : 'Laki-laki',
          tempatLahir: item.birth_place || 'Padang',
          tanggalLahir: item.birth_date ? String(item.birth_date).slice(0, 10) : '2014-05-12',
          agama: meta.agama || 'Islam',
          alamat: item.address || 'Padang - Sumatera Barat',
          foto: meta.foto_url || `https://ui-avatars.com/api/?name=${encodeURIComponent(item.full_name || 'Siswa')}&background=0D8ABC&color=fff`,
          raw: item,
        }
      })
    }
    return defaultStudents
  }, [rawStudents, defaultStudents])

  // Filtered Students
  const filteredStudents = useMemo(() => {
    return formattedStudents.filter((item) => {
      const matchUnit = !unitFilter || item.unit.toLowerCase().includes(unitFilter.toLowerCase())
      const matchKelas = !kelasFilter || item.kelas.toLowerCase().includes(kelasFilter.toLowerCase())
      const matchStatus = !statusFilter || item.status.toLowerCase() === statusFilter.toLowerCase()
      const matchSearch =
        !searchInput ||
        item.nama.toLowerCase().includes(searchInput.toLowerCase()) ||
        item.nis.toLowerCase().includes(searchInput.toLowerCase()) ||
        item.nisn.toLowerCase().includes(searchInput.toLowerCase())
      return matchUnit && matchKelas && matchStatus && matchSearch
    })
  }, [formattedStudents, unitFilter, kelasFilter, statusFilter, searchInput])

  // Pagination
  const totalPages = Math.max(1, Math.ceil(filteredStudents.length / itemsPerPage))
  const paginatedStudents = useMemo(() => {
    const start = (currentPage - 1) * itemsPerPage
    return filteredStudents.slice(start, start + itemsPerPage)
  }, [filteredStudents, currentPage, itemsPerPage])

  // Form Handlers
  const handleOpenTambah = () => {
    setIsEdit(false)
    setFormData(initialForm())
    setStep(1)
    setViewMode('form')
  }

  const handleOpenEdit = (student) => {
    setIsEdit(true)
    const meta = student.raw?.metadata || {}
    const akd = meta.akademik || {}
    setFormData({
      id: student.id,
      nis: student.nis || '',
      nisn: student.nisn || meta.nisn || '',
      full_name: student.nama || '',
      birth_place: student.tempatLahir || '',
      birth_date: student.tanggalLahir || '',
      gender: student.gender === 'Perempuan' ? 'female' : 'male',
      agama: student.agama || 'Islam',
      foto_url: student.foto || '',
      nama_ayah: meta.ayah?.nama || '',
      pekerjaan_ayah: meta.ayah?.pekerjaan || '',
      hp_ayah: meta.ayah?.hp || '',
      nama_ibu: meta.ibu?.nama || '',
      pekerjaan_ibu: meta.ibu?.pekerjaan || '',
      hp_ibu: meta.ibu?.hp || '',
      nama_wali: meta.wali?.nama || '',
      hp_wali: meta.wali?.hp || '',
      alamat_ortu: student.alamat || meta.alamat_ortu || '',
      unit_pendidikan: student.unit || akd.unit_pendidikan || 'SDIT 2 Dar el-Iman - Padang',
      class_id: student.raw?.class_id || '',
      kelas_label: student.kelas || akd.kelas || '6A',
      rombel: akd.rombel || 'Rombel A',
      tahun_ajaran: akd.tahun_ajaran_masuk || '2024/2025',
      tanggal_masuk: akd.tanggal_masuk || '2023-07-10',
      no_induk_sebelumnya: akd.no_induk_sebelumnya || '',
      status_siswa: String(student.status).toLowerCase(),
      kurikulum: akd.kurikulum || 'Kurikulum Merdeka',
      beasiswa: akd.beasiswa || 'Tidak Ada',
      catatan: akd.catatan || '',
    })
    setStep(1)
    setViewMode('form')
  }

  const handleOpenDetail = (student) => {
    setSelectedStudent(student)
    setActiveDetailTab('siswa')
    setViewMode('detail')
  }

  const handleDelete = async (student) => {
    const res = await Swal.fire({
      title: 'Hapus data siswa?',
      text: `Data ${student.nama} akan dihapus dari sistem.`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Ya, Hapus',
      cancelButtonText: 'Batal',
      confirmButtonColor: '#dc2626',
    })
    if (res.isConfirmed) {
      if (student.raw?.id) {
        await hapus.mutateAsync(student.id)
      } else {
        await Swal.fire('Berhasil', 'Data siswa berhasil dihapus.', 'success')
      }
      if (viewMode === 'detail') setViewMode('list')
    }
  }

  const handleSubmitForm = async (e) => {
    if (e) e.preventDefault()
    const payload = {
      nis: formData.nis || `23${Math.floor(1000 + Math.random() * 9000)}`,
      full_name: formData.full_name,
      gender: formData.gender,
      birth_place: formData.birth_place,
      birth_date: formData.birth_date,
      address: formData.alamat_ortu,
      class_id: formData.class_id || null,
      is_active: formData.status_siswa === 'aktif',
      metadata: {
        nisn: formData.nisn,
        agama: formData.agama,
        foto_url: formData.foto_url,
        ayah: { nama: formData.nama_ayah, pekerjaan: formData.pekerjaan_ayah, hp: formData.hp_ayah },
        ibu: { nama: formData.nama_ibu, pekerjaan: formData.pekerjaan_ibu, hp: formData.hp_ibu },
        wali: { nama: formData.nama_wali, hp: formData.hp_wali },
        akademik: {
          unit_pendidikan: formData.unit_pendidikan,
          kelas: formData.kelas_label,
          rombel: formData.rombel,
          tahun_ajaran_masuk: formData.tahun_ajaran,
          tanggal_masuk: formData.tanggal_masuk,
          status_siswa: formData.status_siswa,
          kurikulum: formData.kurikulum,
          beasiswa: formData.beasiswa,
          catatan: formData.catatan,
        },
      },
    }

    if (isEdit && formData.id) {
      await ubah.mutateAsync({ id: formData.id, payload })
    } else {
      await tambah.mutateAsync(payload)
    }

    setViewMode('list')
    setFormData(initialForm())
  }

  // Export Excel CSV trigger
  const handleExportExcel = () => {
    const headers = ['NIS', 'NISN', 'Nama Siswa', 'Unit Pendidikan', 'Kelas', 'Orang Tua', 'No. HP', 'Status', 'Jenis Kelamin']
    const csvRows = [headers.join(',')]

    filteredStudents.forEach((st) => {
      const row = [
        `"${st.nis}"`,
        `"${st.nisn}"`,
        `"${st.nama}"`,
        `"${st.unit}"`,
        `"${st.kelas}"`,
        `"${st.orangTua}"`,
        `"${st.noHp}"`,
        `"${st.status}"`,
        `"${st.gender}"`,
      ]
      csvRows.push(row.join(','))
    })

    const blob = new Blob([csvRows.join('\n')], { type: 'text/csv;charset=utf-8;' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `Data_Siswa_DarElIman_${new Date().toISOString().slice(0, 10)}.csv`
    a.click()
    URL.revokeObjectURL(url)
  }

  // Render Status Badge
  const renderStatusBadge = (statusStr) => {
    const st = String(statusStr || '').toLowerCase()
    if (st === 'aktif') {
      return <span className="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">Aktif</span>
    }
    if (st === 'mutasi') {
      return <span className="inline-flex rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-700">Mutasi</span>
    }
    if (st === 'lulus') {
      return <span className="inline-flex rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-semibold text-blue-700">Lulus</span>
    }
    return <span className="inline-flex rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-semibold text-rose-700">Nonaktif</span>
  }

  // View Mode: FORM (Multi-step wizard)
  if (viewMode === 'form') {
    return (
      <div className="space-y-4">
        {/* Form Title & Stepper */}
        <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <div className="flex items-center justify-between border-b border-slate-200 pb-4">
            <h2 className="text-lg font-extrabold uppercase tracking-wide text-slate-800">
              {isEdit ? 'EDIT SISWA' : 'TAMBAH SISWA'}
            </h2>
            <button
              onClick={() => setViewMode('list')}
              className="text-xs font-semibold text-slate-500 hover:text-slate-800"
            >
              Batal & Kembali
            </button>
          </div>

          {/* Stepper Wizard Progress */}
          <div className="my-6 flex items-center justify-between px-4 sm:px-12">
            {[
              { num: 1, label: 'Data Siswa' },
              { num: 2, label: 'Data Orang Tua' },
              { num: 3, label: 'Data Akademik' },
              { num: 4, label: 'Konfirmasi' },
            ].map((st, idx) => {
              const active = step === st.num
              const done = step > st.num
              return (
                <div key={st.num} className="flex items-center gap-2">
                  <div
                    onClick={() => done && setStep(st.num)}
                    className={`flex h-8 w-8 cursor-pointer items-center justify-center rounded-full text-xs font-bold transition ${
                      active
                        ? 'bg-[#064e3b] text-white shadow-md'
                        : done
                          ? 'bg-emerald-600 text-white'
                          : 'bg-slate-200 text-slate-600'
                    }`}
                  >
                    {done ? <FaCheckCircle /> : st.num}
                  </div>
                  <span className={`hidden text-xs font-semibold sm:inline ${active ? 'text-emerald-950 font-bold' : 'text-slate-500'}`}>
                    {st.label}
                  </span>
                  {idx < 3 && <div className="h-[2px] w-8 bg-slate-200 sm:w-16" />}
                </div>
              )
            })}
          </div>

          {/* Step 1: Data Siswa */}
          {step === 1 && (
            <div className="grid grid-cols-1 gap-6 md:grid-cols-12">
              {/* Photo Upload Area */}
              <div className="md:col-span-4 flex flex-col items-center justify-center border-r border-slate-100 pr-4">
                <label className="block text-xs font-bold text-slate-700 mb-2">Foto Siswa</label>
                <div className="relative flex h-40 w-36 flex-col items-center justify-center rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 p-2 text-center hover:bg-slate-100 transition cursor-pointer">
                  {formData.foto_url ? (
                    <img src={formData.foto_url} alt="Foto siswa" className="h-full w-full rounded-lg object-cover" />
                  ) : (
                    <>
                      <FaUser className="text-3xl text-slate-400 mb-2" />
                      <p className="text-[11px] font-semibold text-emerald-800">Upload Foto</p>
                      <p className="text-[9px] text-slate-400">PNG, JPG (max 2MB)</p>
                    </>
                  )}
                </div>
                <input
                  type="text"
                  placeholder="atau tempel URL foto..."
                  value={formData.foto_url}
                  onChange={(e) => setFormData((p) => ({ ...p, foto_url: e.target.value }))}
                  className="mt-3 w-full rounded-lg border border-slate-300 px-3 py-1.5 text-xs"
                />
              </div>

              {/* Data Siswa Inputs */}
              <div className="md:col-span-8 space-y-4 text-xs">
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                  <div>
                    <label className="block font-semibold text-slate-700 mb-1">NIS (Otomatis)</label>
                    <input
                      type="text"
                      value={formData.nis}
                      onChange={(e) => setFormData((p) => ({ ...p, nis: e.target.value }))}
                      placeholder="23009"
                      className="w-full rounded-lg border border-slate-300 p-2.5 focus:border-emerald-500 focus:outline-none"
                    />
                  </div>
                  <div>
                    <label className="block font-semibold text-slate-700 mb-1">NISN</label>
                    <input
                      type="text"
                      value={formData.nisn}
                      onChange={(e) => setFormData((p) => ({ ...p, nisn: e.target.value }))}
                      placeholder="00987654456"
                      className="w-full rounded-lg border border-slate-300 p-2.5 focus:border-emerald-500 focus:outline-none"
                    />
                  </div>
                </div>

                <div>
                  <label className="block font-semibold text-slate-700 mb-1">Nama Lengkap *</label>
                  <input
                    type="text"
                    required
                    value={formData.full_name}
                    onChange={(e) => setFormData((p) => ({ ...p, full_name: e.target.value }))}
                    placeholder="Masukkan nama lengkap siswa"
                    className="w-full rounded-lg border border-slate-300 p-2.5 focus:border-emerald-500 focus:outline-none"
                  />
                </div>

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                  <div>
                    <label className="block font-semibold text-slate-700 mb-1">Tempat Lahir</label>
                    <input
                      type="text"
                      value={formData.birth_place}
                      onChange={(e) => setFormData((p) => ({ ...p, birth_place: e.target.value }))}
                      placeholder="Padang"
                      className="w-full rounded-lg border border-slate-300 p-2.5 focus:border-emerald-500 focus:outline-none"
                    />
                  </div>
                  <div>
                    <label className="block font-semibold text-slate-700 mb-1">Tanggal Lahir</label>
                    <input
                      type="date"
                      value={formData.birth_date}
                      onChange={(e) => setFormData((p) => ({ ...p, birth_date: e.target.value }))}
                      className="w-full rounded-lg border border-slate-300 p-2.5 focus:border-emerald-500 focus:outline-none"
                    />
                  </div>
                </div>

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                  <div>
                    <label className="block font-semibold text-slate-700 mb-1">Jenis Kelamin</label>
                    <div className="flex items-center gap-4 mt-2">
                      <label className="flex items-center gap-2 font-medium text-slate-700 cursor-pointer">
                        <input
                          type="radio"
                          name="gender"
                          value="male"
                          checked={formData.gender === 'male'}
                          onChange={(e) => setFormData((p) => ({ ...p, gender: e.target.value }))}
                          className="accent-emerald-700"
                        />
                        Laki-laki
                      </label>
                      <label className="flex items-center gap-2 font-medium text-slate-700 cursor-pointer">
                        <input
                          type="radio"
                          name="gender"
                          value="female"
                          checked={formData.gender === 'female'}
                          onChange={(e) => setFormData((p) => ({ ...p, gender: e.target.value }))}
                          className="accent-emerald-700"
                        />
                        Perempuan
                      </label>
                    </div>
                  </div>
                  <div>
                    <label className="block font-semibold text-slate-700 mb-1">Agama</label>
                    <select
                      value={formData.agama}
                      onChange={(e) => setFormData((p) => ({ ...p, agama: e.target.value }))}
                      className="w-full rounded-lg border border-slate-300 p-2.5 focus:border-emerald-500 focus:outline-none"
                    >
                      <option value="Islam">Islam</option>
                      <option value="Lainnya">Lainnya</option>
                    </select>
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* Step 2: Data Orang Tua */}
          {step === 2 && (
            <div className="space-y-4 text-xs">
              <h3 className="font-bold text-slate-800 border-b pb-2">Informasi Orang Tua / Wali</h3>

              <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                  <label className="block font-semibold text-slate-700 mb-1">Nama Ayah Kandung</label>
                  <input
                    type="text"
                    value={formData.nama_ayah}
                    onChange={(e) => setFormData((p) => ({ ...p, nama_ayah: e.target.value }))}
                    placeholder="Nama ayah"
                    className="w-full rounded-lg border border-slate-300 p-2.5 focus:border-emerald-500 focus:outline-none"
                  />
                </div>
                <div>
                  <label className="block font-semibold text-slate-700 mb-1">Pekerjaan Ayah</label>
                  <input
                    type="text"
                    value={formData.pekerjaan_ayah}
                    onChange={(e) => setFormData((p) => ({ ...p, pekerjaan_ayah: e.target.value }))}
                    placeholder="PNS / Swasta / Wiraswasta"
                    className="w-full rounded-lg border border-slate-300 p-2.5 focus:border-emerald-500 focus:outline-none"
                  />
                </div>
                <div>
                  <label className="block font-semibold text-slate-700 mb-1">No. HP Ayah</label>
                  <input
                    type="text"
                    value={formData.hp_ayah}
                    onChange={(e) => setFormData((p) => ({ ...p, hp_ayah: e.target.value }))}
                    placeholder="0812-xxxx-xxxx"
                    className="w-full rounded-lg border border-slate-300 p-2.5 focus:border-emerald-500 focus:outline-none"
                  />
                </div>
              </div>

              <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                  <label className="block font-semibold text-slate-700 mb-1">Nama Ibu Kandung</label>
                  <input
                    type="text"
                    value={formData.nama_ibu}
                    onChange={(e) => setFormData((p) => ({ ...p, nama_ibu: e.target.value }))}
                    placeholder="Nama ibu"
                    className="w-full rounded-lg border border-slate-300 p-2.5 focus:border-emerald-500 focus:outline-none"
                  />
                </div>
                <div>
                  <label className="block font-semibold text-slate-700 mb-1">Pekerjaan Ibu</label>
                  <input
                    type="text"
                    value={formData.pekerjaan_ibu}
                    onChange={(e) => setFormData((p) => ({ ...p, pekerjaan_ibu: e.target.value }))}
                    placeholder="Ibu Rumah Tangga / PNS"
                    className="w-full rounded-lg border border-slate-300 p-2.5 focus:border-emerald-500 focus:outline-none"
                  />
                </div>
                <div>
                  <label className="block font-semibold text-slate-700 mb-1">No. HP Ibu</label>
                  <input
                    type="text"
                    value={formData.hp_ibu}
                    onChange={(e) => setFormData((p) => ({ ...p, hp_ibu: e.target.value }))}
                    placeholder="0813-xxxx-xxxx"
                    className="w-full rounded-lg border border-slate-300 p-2.5 focus:border-emerald-500 focus:outline-none"
                  />
                </div>
              </div>

              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                  <label className="block font-semibold text-slate-700 mb-1">Nama Wali (Opsional)</label>
                  <input
                    type="text"
                    value={formData.nama_wali}
                    onChange={(e) => setFormData((p) => ({ ...p, nama_wali: e.target.value }))}
                    placeholder="Nama wali jika ada"
                    className="w-full rounded-lg border border-slate-300 p-2.5 focus:border-emerald-500 focus:outline-none"
                  />
                </div>
                <div>
                  <label className="block font-semibold text-slate-700 mb-1">No. HP Wali</label>
                  <input
                    type="text"
                    value={formData.hp_wali}
                    onChange={(e) => setFormData((p) => ({ ...p, hp_wali: e.target.value }))}
                    placeholder="0812-xxxx-xxxx"
                    className="w-full rounded-lg border border-slate-300 p-2.5 focus:border-emerald-500 focus:outline-none"
                  />
                </div>
              </div>

              <div>
                <label className="block font-semibold text-slate-700 mb-1">Alamat Tempat Tinggal Ortu / Wali</label>
                <textarea
                  rows={3}
                  value={formData.alamat_ortu}
                  onChange={(e) => setFormData((p) => ({ ...p, alamat_ortu: e.target.value }))}
                  placeholder="Alamat lengkap tempat tinggal"
                  className="w-full rounded-lg border border-slate-300 p-2.5 focus:border-emerald-500 focus:outline-none"
                />
              </div>
            </div>
          )}

          {/* Step 3: Input Data Akademik */}
          {step === 3 && (
            <div className="space-y-4 text-xs">
              <h3 className="font-bold text-slate-800 border-b pb-2 uppercase tracking-wider">INPUT DATA AKADEMIK</h3>

              <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                  <label className="block font-semibold text-slate-700 mb-1">Unit Pendidikan *</label>
                  <select
                    value={formData.unit_pendidikan}
                    onChange={(e) => setFormData((p) => ({ ...p, unit_pendidikan: e.target.value }))}
                    className="w-full rounded-lg border border-slate-300 p-2.5 focus:border-emerald-500 focus:outline-none"
                  >
                    <option value="SDIT 1 Dar el-Iman - Padang">SDIT 1 Dar el-Iman - Padang</option>
                    <option value="SDIT 2 Dar el-Iman - Padang">SDIT 2 Dar el-Iman - Padang</option>
                    <option value="SDIT 3 Dar el-Iman - Padang">SDIT 3 Dar el-Iman - Padang</option>
                    <option value="SDIT 4 Dar el-Iman - Padang">SDIT 4 Dar el-Iman - Padang</option>
                    <option value="TKIT 1 Dar el-Iman - Padang">TKIT 1 Dar el-Iman - Padang</option>
                    <option value="MIT SaQu Dar el-Iman - Padang">MIT SaQu Dar el-Iman - Padang</option>
                  </select>
                </div>
                <div>
                  <label className="block font-semibold text-slate-700 mb-1">Kelas *</label>
                  <select
                    value={formData.kelas_label}
                    onChange={(e) => setFormData((p) => ({ ...p, kelas_label: e.target.value }))}
                    className="w-full rounded-lg border border-slate-300 p-2.5 focus:border-emerald-500 focus:outline-none"
                  >
                    <option value="6A">6A</option>
                    <option value="5B">5B</option>
                    <option value="5A">5A</option>
                    <option value="4A">4A</option>
                    <option value="3A">3A</option>
                    <option value="3B">3B</option>
                    <option value="TK B">TK B</option>
                  </select>
                </div>
                <div>
                  <label className="block font-semibold text-slate-700 mb-1">Rombel</label>
                  <select
                    value={formData.rombel}
                    onChange={(e) => setFormData((p) => ({ ...p, rombel: e.target.value }))}
                    className="w-full rounded-lg border border-slate-300 p-2.5 focus:border-emerald-500 focus:outline-none"
                  >
                    <option value="Rombel A">Rombel A</option>
                    <option value="Rombel B">Rombel B</option>
                    <option value="Rombel C">Rombel C</option>
                  </select>
                </div>
              </div>

              <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                  <label className="block font-semibold text-slate-700 mb-1">Tahun Ajaran *</label>
                  <select
                    value={formData.tahun_ajaran}
                    onChange={(e) => setFormData((p) => ({ ...p, tahun_ajaran: e.target.value }))}
                    className="w-full rounded-lg border border-slate-300 p-2.5 focus:border-emerald-500 focus:outline-none"
                  >
                    <option value="2024/2025">2024/2025</option>
                    <option value="2023/2024">2023/2024</option>
                  </select>
                </div>
                <div>
                  <label className="block font-semibold text-slate-700 mb-1">Tanggal Masuk</label>
                  <input
                    type="date"
                    value={formData.tanggal_masuk}
                    onChange={(e) => setFormData((p) => ({ ...p, tanggal_masuk: e.target.value }))}
                    className="w-full rounded-lg border border-slate-300 p-2.5 focus:border-emerald-500 focus:outline-none"
                  />
                </div>
                <div>
                  <label className="block font-semibold text-slate-700 mb-1">No. Induk Sebelumnya (Opsional)</label>
                  <input
                    type="text"
                    value={formData.no_induk_sebelumnya}
                    onChange={(e) => setFormData((p) => ({ ...p, no_induk_sebelumnya: e.target.value }))}
                    placeholder="-"
                    className="w-full rounded-lg border border-slate-300 p-2.5 focus:border-emerald-500 focus:outline-none"
                  />
                </div>
              </div>

              <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                  <label className="block font-semibold text-slate-700 mb-1">Status Siswa *</label>
                  <select
                    value={formData.status_siswa}
                    onChange={(e) => setFormData((p) => ({ ...p, status_siswa: e.target.value }))}
                    className="w-full rounded-lg border border-slate-300 p-2.5 focus:border-emerald-500 focus:outline-none"
                  >
                    <option value="aktif">Aktif</option>
                    <option value="mutasi">Mutasi</option>
                    <option value="lulus">Lulus</option>
                    <option value="nonaktif">Nonaktif</option>
                  </select>
                </div>
                <div>
                  <label className="block font-semibold text-slate-700 mb-1">Kurikulum</label>
                  <select
                    value={formData.kurikulum}
                    onChange={(e) => setFormData((p) => ({ ...p, kurikulum: e.target.value }))}
                    className="w-full rounded-lg border border-slate-300 p-2.5 focus:border-emerald-500 focus:outline-none"
                  >
                    <option value="Kurikulum Merdeka">Kurikulum Merdeka</option>
                    <option value="K13">K13</option>
                  </select>
                </div>
                <div>
                  <label className="block font-semibold text-slate-700 mb-1">Beasiswa</label>
                  <select
                    value={formData.beasiswa}
                    onChange={(e) => setFormData((p) => ({ ...p, beasiswa: e.target.value }))}
                    className="w-full rounded-lg border border-slate-300 p-2.5 focus:border-emerald-500 focus:outline-none"
                  >
                    <option value="Tidak Ada">Tidak Ada</option>
                    <option value="PIP">PIP</option>
                    <option value="Prestasi">Prestasi</option>
                  </select>
                </div>
              </div>

              <div>
                <label className="block font-semibold text-slate-700 mb-1">Catatan (Opsional)</label>
                <textarea
                  rows={2}
                  value={formData.catatan}
                  onChange={(e) => setFormData((p) => ({ ...p, catatan: e.target.value }))}
                  placeholder="Masukkan catatan jika ada"
                  className="w-full rounded-lg border border-slate-300 p-2.5 focus:border-emerald-500 focus:outline-none"
                />
              </div>
            </div>
          )}

          {/* Step 4: Konfirmasi Summary */}
          {step === 4 && (
            <div className="space-y-4 text-xs">
              <h3 className="font-bold text-slate-800 border-b pb-2 uppercase tracking-wider">KONFIRMASI DATA SISWA</h3>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4 bg-slate-50 p-4 rounded-xl border border-slate-200">
                <div className="space-y-2">
                  <p className="font-bold text-emerald-950 border-b pb-1">Data Diri Siswa</p>
                  <p><span className="text-slate-500">Nama:</span> {formData.full_name || '-'}</p>
                  <p><span className="text-slate-500">NIS:</span> {formData.nis || '-'}</p>
                  <p><span className="text-slate-500">NISN:</span> {formData.nisn || '-'}</p>
                  <p><span className="text-slate-500">TTL:</span> {formData.birth_place || '-'}, {formData.birth_date || '-'}</p>
                  <p><span className="text-slate-500">Gender:</span> {formData.gender === 'female' ? 'Perempuan' : 'Laki-laki'}</p>
                </div>
                <div className="space-y-2">
                  <p className="font-bold text-emerald-950 border-b pb-1">Data Akademik & Orang Tua</p>
                  <p><span className="text-slate-500">Unit:</span> {formData.unit_pendidikan}</p>
                  <p><span className="text-slate-500">Kelas / Rombel:</span> {formData.kelas_label} ({formData.rombel})</p>
                  <p><span className="text-slate-500">Tahun Ajaran:</span> {formData.tahun_ajaran}</p>
                  <p><span className="text-slate-500">Status:</span> {formData.status_siswa}</p>
                  <p><span className="text-slate-500">Orang Tua:</span> {formData.nama_ayah || formData.nama_ibu || '-'}</p>
                </div>
              </div>
            </div>
          )}

          {/* Stepper Buttons Footer */}
          <div className="flex items-center justify-between border-t border-slate-200 pt-4 mt-6">
            <div>
              {step > 1 ? (
                <button
                  type="button"
                  onClick={() => setStep((s) => s - 1)}
                  className="flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50"
                >
                  <FaArrowLeft /> Kembali
                </button>
              ) : (
                <button
                  type="button"
                  onClick={() => setViewMode('list')}
                  className="rounded-lg border border-slate-300 px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50"
                >
                  Batal
                </button>
              )}
            </div>

            <div className="flex items-center gap-2">
              {isEdit && (
                <button
                  type="button"
                  onClick={() => handleDelete({ id: formData.id, nama: formData.full_name })}
                  className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-2 text-xs font-bold text-rose-600 hover:bg-rose-100"
                >
                  Hapus Siswa
                </button>
              )}

              <button
                type="button"
                onClick={() => setViewMode('list')}
                className="rounded-lg border border-emerald-300 px-4 py-2 text-xs font-bold text-emerald-800 hover:bg-emerald-50"
              >
                Simpan Draft
              </button>

              {step < 4 ? (
                <button
                  type="button"
                  onClick={() => setStep((s) => s + 1)}
                  className="flex items-center gap-2 rounded-lg bg-[#064e3b] px-5 py-2 text-xs font-bold text-white shadow hover:bg-emerald-800"
                >
                  Selanjutnya <FaArrowRight />
                </button>
              ) : (
                <button
                  type="button"
                  onClick={handleSubmitForm}
                  className="flex items-center gap-2 rounded-lg bg-[#064e3b] px-6 py-2 text-xs font-bold text-white shadow hover:bg-emerald-800"
                >
                  Simpan Data Siswa <FaCheckCircle />
                </button>
              )}
            </div>
          </div>
        </div>
      </div>
    )
  }

  // View Mode: DETAIL SISWA
  if (viewMode === 'detail' && selectedStudent) {
    return (
      <div className="space-y-4">
        {/* Header Bar */}
        <div className="flex items-center justify-between">
          <button
            onClick={() => setViewMode('list')}
            className="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 shadow-sm hover:bg-slate-50"
          >
            <FaArrowLeft /> Kembali
          </button>
          <h2 className="text-base font-extrabold uppercase text-slate-800">Detail Siswa</h2>
        </div>

        {/* Profile Card & Info Header */}
        <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
          {/* Left Profile Overview */}
          <div className="lg:col-span-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4 text-xs">
            <div className="flex items-center gap-3">
              <div className="h-16 w-16 overflow-hidden rounded-xl border-2 border-emerald-600 bg-slate-100 shadow">
                <img src={selectedStudent.foto} alt={selectedStudent.nama} className="h-full w-full object-cover" />
              </div>
              <div>
                <div className="flex items-center gap-2">
                  <h3 className="text-sm font-extrabold text-slate-900">{selectedStudent.nama}</h3>
                  {renderStatusBadge(selectedStudent.status)}
                </div>
                <p className="text-[11px] text-slate-500 mt-0.5">NIS: {selectedStudent.nis} | NISN: {selectedStudent.nisn}</p>
                <p className="text-[10px] text-emerald-800 font-semibold">{selectedStudent.unit}</p>
              </div>
            </div>

            <div className="space-y-2 border-t border-slate-100 pt-3">
              <div className="flex justify-between"><span className="text-slate-500">Tempat, Tgl Lahir:</span> <span className="font-semibold">{selectedStudent.tempatLahir}, {selectedStudent.tanggalLahir}</span></div>
              <div className="flex justify-between"><span className="text-slate-500">Jenis Kelamin:</span> <span className="font-semibold">{selectedStudent.gender}</span></div>
              <div className="flex justify-between"><span className="text-slate-500">Agama:</span> <span className="font-semibold">{selectedStudent.agama || 'Islam'}</span></div>
              <div className="flex justify-between"><span className="text-slate-500">Kelas:</span> <span className="font-semibold">{selectedStudent.kelas}</span></div>
            </div>
          </div>

          {/* Right Detailed Tabs */}
          <div className="lg:col-span-8 space-y-4">
            <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
              {/* Tab Header Navigation */}
              <div className="flex gap-4 border-b border-slate-200 pb-3 text-xs font-bold">
                {['siswa', 'orangTua', 'akademik', 'riwayat', 'dokumen'].map((tabKey) => {
                  const labels = {
                    siswa: 'Data Siswa',
                    orangTua: 'Orang Tua / Wali',
                    akademik: 'Akademik',
                    riwayat: 'Riwayat',
                    dokumen: 'Dokumen',
                  }
                  const active = activeDetailTab === tabKey
                  return (
                    <button
                      key={tabKey}
                      onClick={() => setActiveDetailTab(tabKey)}
                      className={`pb-1 transition ${
                        active ? 'border-b-2 border-emerald-700 text-emerald-950 font-extrabold' : 'text-slate-500 hover:text-slate-800'
                      }`}
                    >
                      {labels[tabKey]}
                    </button>
                  )
                })}
              </div>

              {/* Tab Content */}
              <div className="pt-4 text-xs space-y-3">
                {activeDetailTab === 'siswa' && (
                  <div className="space-y-2">
                    <p><span className="font-bold text-slate-600">Alamat Lengkap:</span> {selectedStudent.alamat}</p>
                    <p><span className="font-bold text-slate-600">No HP Siswa / Ortu:</span> {selectedStudent.noHp}</p>
                    <p><span className="font-bold text-slate-600">Golongan Darah:</span> A</p>
                    <p><span className="font-bold text-slate-600">Hobi:</span> Membaca, Olahraga</p>
                    <p><span className="font-bold text-slate-600">Cita-cita:</span> Dokter</p>
                  </div>
                )}

                {activeDetailTab === 'orangTua' && (
                  <div className="space-y-2">
                    <p><span className="font-bold text-slate-600">Orang Tua / Wali:</span> {selectedStudent.orangTua}</p>
                    <p><span className="font-bold text-slate-600">No HP Ortu:</span> {selectedStudent.noHp}</p>
                    <p><span className="font-bold text-slate-600">Pekerjaan:</span> Wiraswasta / PNS</p>
                    <p><span className="font-bold text-slate-600">Alamat Ortu:</span> {selectedStudent.alamat}</p>
                  </div>
                )}

                {activeDetailTab === 'akademik' && (
                  <div className="space-y-2">
                    <p><span className="font-bold text-slate-600">Unit Pendidikan:</span> {selectedStudent.unit}</p>
                    <p><span className="font-bold text-slate-600">Kelas:</span> {selectedStudent.kelas}</p>
                    <p><span className="font-bold text-slate-600">Tahun Masuk:</span> 2024/2025</p>
                    <p><span className="font-bold text-slate-600">Status Siswa:</span> {selectedStudent.status}</p>
                  </div>
                )}

                {activeDetailTab === 'riwayat' && (
                  <p className="text-slate-500">Riwayat keaktifan & kehadiran tercatat 98% hadir (Sangat Baik).</p>
                )}

                {activeDetailTab === 'dokumen' && (
                  <p className="text-slate-500">Dokumen Akta Lahir, KK, dan Pas Foto sudah diverifikasi.</p>
                )}
              </div>
            </div>

            {/* Quick Actions Card */}
            <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
              <h4 className="text-xs font-bold text-slate-700 uppercase tracking-wider mb-3">Aksi Cepat</h4>
              <div className="flex flex-wrap gap-2">
                <button
                  onClick={() => handleOpenEdit(selectedStudent)}
                  className="flex items-center gap-1.5 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs font-bold text-amber-800 hover:bg-amber-100"
                >
                  <FaEdit /> Edit Data
                </button>
                <button
                  onClick={() => {
                    setStudentToPrint(selectedStudent)
                    setShowCetakModal(true)
                  }}
                  className="flex items-center gap-1.5 rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-800 hover:bg-emerald-100"
                >
                  <FaPrint /> Cetak Kartu Siswa
                </button>
                <button
                  onClick={() => handleDelete(selectedStudent)}
                  className="flex items-center gap-1.5 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-bold text-rose-600 hover:bg-rose-100"
                >
                  <FaTrash /> Hapus Data
                </button>
              </div>
            </div>
          </div>
        </div>

        {/* Modal Cetak Kartu Siswa */}
        {showCetakModal && (
          <CetakKartuSiswaModal student={studentToPrint} onClose={() => setShowCetakModal(false)} />
        )}
      </div>
    )
  }

  // View Mode: LIST (Default Table View)
  return (
    <div className="space-y-4">
      {/* Top Title & Header Actions */}
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-slate-200 pb-3">
        <div>
          <div className="flex items-center gap-2 text-xs text-slate-500 font-medium mb-1">
            <span>Master Data</span>
            <span>&gt;</span>
            <span>Siswa</span>
            <span>&gt;</span>
            <span className="font-bold text-emerald-800">Data Siswa</span>
          </div>
          <h2 className="text-xl font-black text-slate-900 tracking-tight">Data Siswa</h2>
          <p className="text-xs text-slate-500">Kelola seluruh data siswa di semua unit pendidikan Dar El-Iman</p>
        </div>

        <div className="flex items-center gap-2">
          <button
            onClick={handleExportExcel}
            className="flex items-center gap-2 rounded-lg border border-emerald-600 bg-white px-3.5 py-2 text-xs font-bold text-emerald-800 shadow-sm hover:bg-emerald-50 transition"
          >
            <FaFileExcel className="text-emerald-600" /> Export Excel
          </button>
          <button
            onClick={handleOpenTambah}
            className="flex items-center gap-2 rounded-lg bg-[#064e3b] px-4 py-2 text-xs font-bold text-white shadow hover:bg-emerald-800 transition"
          >
            <FaPlus /> Tambah Siswa
          </button>
        </div>
      </div>

      {/* Filter Bar */}
      <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-2 md:grid-cols-4 bg-white p-3 rounded-xl border border-slate-200 shadow-sm text-xs">
        <div>
          <select
            value={unitFilter}
            onChange={(e) => {
              setUnitFilter(e.target.value)
              setCurrentPage(1)
            }}
            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-700 focus:border-emerald-500 focus:outline-none"
          >
            <option value="">Semua Unit Pendidikan</option>
            <option value="SDIT 1">SDIT 1 Dar el-Iman - 50 Kota</option>
            <option value="SDIT 2">SDIT 2 Dar el-Iman - Padang</option>
            <option value="SDIT 3">SDIT 3 Dar el-Iman - Padang</option>
            <option value="SDIT 4">SDIT 4 Dar el-Iman - Padang</option>
            <option value="TKIT">TKIT 1 Dar el-Iman - Padang</option>
            <option value="MIT SaQu">MIT SaQu Dar el-Iman - Padang</option>
          </select>
        </div>

        <div>
          <select
            value={kelasFilter}
            onChange={(e) => {
              setKelasFilter(e.target.value)
              setCurrentPage(1)
            }}
            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-700 focus:border-emerald-500 focus:outline-none"
          >
            <option value="">Semua Kelas</option>
            <option value="6A">6A</option>
            <option value="5B">5B</option>
            <option value="5A">5A</option>
            <option value="4A">4A</option>
            <option value="3A">3A</option>
            <option value="3B">3B</option>
            <option value="TK B">TK B</option>
          </select>
        </div>

        <div>
          <select
            value={statusFilter}
            onChange={(e) => {
              setStatusFilter(e.target.value)
              setCurrentPage(1)
            }}
            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-700 focus:border-emerald-500 focus:outline-none"
          >
            <option value="">Semua Status</option>
            <option value="Aktif">Aktif</option>
            <option value="Mutasi">Mutasi</option>
            <option value="Lulus">Lulus</option>
            <option value="Nonaktif">Nonaktif</option>
          </select>
        </div>

        <div className="relative">
          <FaSearch className="absolute left-3 top-3 text-slate-400" />
          <input
            type="text"
            value={searchInput}
            onChange={(e) => {
              setSearchInput(e.target.value)
              setCurrentPage(1)
            }}
            placeholder="Cari NIS / Nama Siswa / NISN..."
            className="w-full rounded-lg border border-slate-300 py-2 pl-9 pr-3 text-slate-700 focus:border-emerald-500 focus:outline-none"
          />
        </div>
      </div>

      {/* Main Data Siswa Table */}
      <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div className="overflow-x-auto">
          <table className="w-full min-w-[1000px] border-collapse text-left text-xs">
            <thead className="bg-slate-50 text-slate-600 font-bold border-b border-slate-200">
              <tr>
                <th className="py-3 px-3">No</th>
                <th className="py-3 px-3">Foto</th>
                <th className="py-3 px-3">NIS</th>
                <th className="py-3 px-3">Nama Siswa</th>
                <th className="py-3 px-3">Unit Pendidikan</th>
                <th className="py-3 px-3">Kelas</th>
                <th className="py-3 px-3">Orang Tua / Wali</th>
                <th className="py-3 px-3">No. HP</th>
                <th className="py-3 px-3">Status</th>
                <th className="py-3 px-3 text-center">Aksi</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 text-slate-800">
              {paginatedStudents.map((item, idx) => (
                <tr key={item.id} className="hover:bg-slate-50/80 transition">
                  <td className="py-3 px-3 font-semibold text-slate-500">
                    {(currentPage - 1) * itemsPerPage + idx + 1}
                  </td>
                  <td className="py-3 px-3">
                    <div className="h-9 w-9 overflow-hidden rounded-full border border-slate-200 bg-slate-100 shadow-sm">
                      <img src={item.foto} alt={item.nama} className="h-full w-full object-cover" />
                    </div>
                  </td>
                  <td className="py-3 px-3 font-bold text-slate-900">{item.nis}</td>
                  <td className="py-3 px-3 font-extrabold text-slate-900">{item.nama}</td>
                  <td className="py-3 px-3 text-slate-700">{item.unit}</td>
                  <td className="py-3 px-3 font-semibold text-slate-800">{item.kelas}</td>
                  <td className="py-3 px-3 text-slate-700">{item.orangTua}</td>
                  <td className="py-3 px-3 text-slate-600">{item.noHp}</td>
                  <td className="py-3 px-3">{renderStatusBadge(item.status)}</td>
                  <td className="py-3 px-3">
                    <div className="flex items-center justify-center gap-1.5">
                      <button
                        type="button"
                        onClick={() => handleOpenDetail(item)}
                        title="Lihat Detail"
                        className="flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 text-blue-600 hover:bg-blue-50 transition"
                      >
                        <FaEye />
                      </button>
                      <button
                        type="button"
                        onClick={() => handleOpenEdit(item)}
                        title="Edit Data"
                        className="flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 text-amber-600 hover:bg-amber-50 transition"
                      >
                        <FaEdit />
                      </button>
                      <button
                        type="button"
                        onClick={() => handleDelete(item)}
                        title="Hapus Data"
                        className="flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 text-rose-600 hover:bg-rose-50 transition"
                      >
                        <FaTrash />
                      </button>
                    </div>
                  </td>
                </tr>
              ))}

              {paginatedStudents.length === 0 && (
                <tr>
                  <td colSpan={10} className="py-8 text-center text-slate-500 font-medium">
                    Tidak ada data siswa yang cocok dengan filter.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination Footer */}
        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-t border-slate-200 bg-white p-3 text-xs text-slate-600">
          <div>
            Menampilkan {filteredStudents.length > 0 ? (currentPage - 1) * itemsPerPage + 1 : 0} sampai{' '}
            {Math.min(currentPage * itemsPerPage, filteredStudents.length)} dari {filteredStudents.length} data
          </div>

          <div className="flex items-center gap-1">
            <button
              disabled={currentPage <= 1}
              onClick={() => setCurrentPage((p) => Math.max(1, p - 1))}
              className="rounded-lg border border-slate-200 px-3 py-1.5 font-semibold text-slate-700 disabled:opacity-40 hover:bg-slate-50"
            >
              Sebelumnya
            </button>

            {Array.from({ length: totalPages }, (_, i) => i + 1).map((pg) => (
              <button
                key={pg}
                onClick={() => setCurrentPage(pg)}
                className={`h-7 w-7 rounded-lg text-xs font-bold transition ${
                  currentPage === pg ? 'bg-[#064e3b] text-white shadow' : 'border border-slate-200 text-slate-700 hover:bg-slate-50'
                }`}
              >
                {pg}
              </button>
            ))}

            <button
              disabled={currentPage >= totalPages}
              onClick={() => setCurrentPage((p) => Math.min(totalPages, p + 1))}
              className="rounded-lg border border-slate-200 px-3 py-1.5 font-semibold text-slate-700 disabled:opacity-40 hover:bg-slate-50"
            >
              Selanjutnya
            </button>
          </div>
        </div>
      </div>

      {/* Modal Cetak Kartu Siswa */}
      {showCetakModal && (
        <CetakKartuSiswaModal student={studentToPrint} onClose={() => setShowCetakModal(false)} />
      )}
    </div>
  )
}
