import { useMemo, useState } from 'react'
import {
  FaArrowLeft,
  FaArrowRight,
  FaCheckCircle,
  FaDownload,
  FaEdit,
  FaEye,
  FaFileExcel,
  FaFileImport,
  FaFilter,
  FaPlus,
  FaPrint,
  FaSearch,
  FaMale,
  FaFemale,
  FaBuilding,
  FaTimes,
  FaTrash,
  FaUpload,
  FaUser,
  FaUserGraduate,
} from 'react-icons/fa'
import Swal from 'sweetalert2'
import CetakKartuSiswaModal from '../components/siswa/CetakKartuSiswaModal'
import StudentFormModal from '../components/siswa/StudentFormModal'
import { useDaftarKelas } from '../hooks/useReferenceData'
import { useAksiSiswa, useDaftarSiswa } from '../hooks/useStudents'

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
  const [step, setStep] = useState(1)

  // Filters
  const [unitFilter, setUnitFilter] = useState('')
  const [kelasFilter, setKelasFilter] = useState('')
  const [statusFilter, setStatusFilter] = useState('')
  const [searchInput, setSearchInput] = useState('')

  // Modal Control States
  const [showDetailModal, setShowDetailModal] = useState(false)
  const [showFormModal, setShowFormModal] = useState(false)
  const [showCetakModal, setShowCetakModal] = useState(false)
  const [showImportModal, setShowImportModal] = useState(false)

  // Import Data States
  const [importFile, setImportFile] = useState(null)
  const [importPreviewData, setImportPreviewData] = useState([])
  const [isImporting, setIsImporting] = useState(false)

  // Selected student data
  const [selectedStudent, setSelectedStudent] = useState(null)
  const [activeDetailTab, setActiveDetailTab] = useState('siswa')
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

  // --- Handlers Import ---
  const handleDownloadTemplateSiswa = () => {
    const headers = [
      // Identitas Siswa
      'No Pendaftaran', 'NIK', 'No Registrasi Akta Lahir', 'No KK', 'NIS', 'NISN', 'Nama Lengkap',
      'Tempat Lahir', 'Tanggal Lahir', 'Jenis Kelamin (L/P)', 'Agama', 'Kewarganegaraan', 'Email',
      'Anak Ke', 'Jumlah Saudara', 'Jumlah Saudara Tiri', 'Berat Badan (kg)', 'Tinggi Badan (cm)',
      'Riwayat Penyakit', 'Foto URL',
      // Alamat
      'Alamat Siswa', 'RT', 'RW', 'Dusun', 'Kelurahan', 'Kecamatan', 'Kota/Kabupaten',
      'Provinsi', 'Kode Pos', 'Jenis Tempat Tinggal', 'Jarak ke Sekolah (km)', 'Moda Transportasi',
      'Hobi', 'Cita-cita',
      // Sekolah & Bantuan
      'Sekolah Asal', 'Status Sekolah Asal', 'Kecamatan Sekolah Asal', 'Kota/Kab Sekolah Asal',
      'HP/WA Sekolah Asal', 'Nominal SPP', 'Nominal Bantuan Ortu Asuh', 'Penerima KPS/PKH (ya/tidak)',
      'Punya KIP (ya/tidak)', 'Layak PIP (ya/tidak)', 'Alasan Menolak PIP',
      // Data Ayah
      'NIK Ayah', 'Nama Ayah', 'Tempat Lahir Ayah', 'Tgl Lahir Ayah', 'Telfon Ayah', 'HP Ayah',
      'WA Ayah', 'Medsos Ayah', 'Pendidikan Terakhir Ayah', 'Pekerjaan Ayah',
      'Instansi Pekerjaan Ayah', 'Jabatan Ayah', 'Keahlian Ayah', 'Penghasilan Ayah',
      'Alamat Instansi Ayah', 'Alamat Rumah Ayah',
      // Data Ibu
      'NIK Ibu', 'Nama Ibu', 'Tempat Lahir Ibu', 'Tgl Lahir Ibu', 'Telfon Ibu', 'HP Ibu',
      'WA Ibu', 'Medsos Ibu', 'Pendidikan Terakhir Ibu', 'Pekerjaan Ibu',
      'Instansi Pekerjaan Ibu', 'Jabatan Ibu', 'Keahlian Ibu', 'Penghasilan Ibu',
      'Alamat Instansi Ibu', 'Alamat Rumah Ibu',
      // Data Wali
      'NIK Wali', 'Nama Wali', 'HP Wali', 'WA Wali', 'Pekerjaan Wali', 'Alamat Wali',
      // Akademik
      'Unit Pendidikan', 'NIS Pembayaran', 'Tahun Ajaran Masuk', 'Tahun Ajaran Berjalan',
      'Status Siswa', 'Status Orang Tua (Umum/Pegawai)', 'NIY Ortu Jika Pegawai',
      'Wali Kelas', 'NIY Wali Kelas',
    ]
    const sampleRow = [
      'PDK-2024-001', '1371234567890123', 'AK.2014.001', '1371234567890001', '23010', '0098123456', 'Fathir Ahmad',
      'Padang', '2014-05-12', 'L', 'Islam', 'WNI', 'fathir@example.com',
      '1', '2', '0', '35', '130',
      '-', 'https://example.com/foto.jpg',
      'Jl. Khatib Sulaiman No. 10', '04', '02', 'Lolong', 'Lolong Belanti', 'Padang Utara', 'Padang',
      'Sumatera Barat', '25114', 'Milik Sendiri', '2', 'Jalan Kaki',
      'Membaca', 'Dokter',
      'SD Negeri 01 Padang', 'Formal', 'Padang Utara', 'Padang',
      '0812-0000-0001', '500000', '0', 'tidak',
      'tidak', 'tidak', '-',
      '1371098765432101', 'Rahmat Hidayat', 'Padang', '1985-03-10', '0751-000001', '081299887766',
      '081299887766', '-', 'S1/D4', 'Wiraswasta',
      'CV Rahmat Jaya', 'Direktur', 'Manajemen', '7500000',
      'Jl. Sudirman No. 5 Padang', 'Jl. Khatib Sulaiman No. 10',
      '1371098765432102', 'Siti Aminah', 'Bukittinggi', '1988-07-22', '0751-000002', '081299887777',
      '081299887777', '-', 'S1/D4', 'Guru',
      'SMA Negeri 1 Padang', 'Guru Matematika', 'Pendidikan', '4500000',
      'Jl. Hamka No. 10 Padang', 'Jl. Khatib Sulaiman No. 10',
      '-', '-', '-', '-', '-', '-',
      'SDIT 2 Dar el-Iman - Padang', '23010', '2024/2025', '2024/2025',
      'aktif', 'Umum', '-',
      'Budi Santoso S.Pd', 'NIY-2024-001',
    ]
    const csvContent = [headers.join(','), sampleRow.join(',')].join('\n')
    const blob = new Blob(['\uFEFF' + csvContent], { type: 'text/csv;charset=utf-8;' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = 'Template_Import_Data_Lengkap_Siswa.csv'
    link.click()
    URL.revokeObjectURL(url)
  }

  const handleFileSelect = (e) => {
    const file = e.target.files?.[0]
    if (!file) return
    setImportFile(file)
    setImportPreviewData([
      { nis: '23011', nisn: '0098761122', nama: 'Fatimah Az-Zahra', gender: 'P', tempatLahir: 'Padang', tanggalLahir: '2015-03-10', agama: 'Islam', alamat: 'Jl. Raden Saleh', unit: 'SDIT 2', kelas: '6A', namaAyah: 'Abdullah', hpAyah: '0812-1111-2222', namaIbu: 'Khadijah', hpIbu: '0812-1111-3333', namaWali: '-', hpWali: '-', status: 'Valid', email: 'fatimah@example.com' },
      { nis: '23012', nisn: '0098761123', nama: 'Abdullah Al-Fatih', gender: 'L', tempatLahir: 'Padang', tanggalLahir: '2015-07-22', agama: 'Islam', alamat: 'Jl. Khatib Sulaiman', unit: 'SDIT 2', kelas: '6A', namaAyah: 'Ahmad', hpAyah: '0812-2222-3333', namaIbu: '-', hpIbu: '-', namaWali: '-', hpWali: '-', status: 'Valid', email: 'abdullah@example.com' },
    ])
  }

  const handleProcessImport = () => {
    if (!importFile) return
    setIsImporting(true)
    setTimeout(() => {
      setIsImporting(false)
      setShowImportModal(false)
      setImportFile(null)
      setImportPreviewData([])
      Swal.fire({ title: 'Import Berhasil!', text: 'Data siswa berhasil diimpor.', icon: 'success', confirmColor: '#064e3b' })
    }, 1200)
  }

  // Predefined default mock students
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
      alamat: 'Jl. Raden Saleh Padang',
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
      alamat: 'Kuranji Padang',
    },
    {
      id: 'demo-4',
      nis: '23004',
      nisn: '0098765449',
      nama: 'Nabila Putri',
      unit: 'SDIT 1 Dar el-Iman - 50 Kota',
      kelas: '5A',
      orangTua: 'Rudi Santoso (Ayah)',
      noHp: '0812-3333-4444',
      status: 'Aktif',
      gender: 'Perempuan',
      tempatLahir: 'Payakumbuh',
      tanggalLahir: '2015-11-03',
      agama: 'Islam',
      alamat: '50 Kota',
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
      tanggalLahir: '2016-01-15',
      agama: 'Islam',
      alamat: 'Nanggalo Padang',
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
      tanggalLahir: '2018-09-09',
      agama: 'Islam',
      alamat: 'Marapalam Padang',
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
    setSelectedStudent(null)
    setShowFormModal(true)
  }

  const handleOpenEdit = (student) => {
    setIsEdit(true)
    setSelectedStudent(student)
    setShowDetailModal(false)
    setShowFormModal(true)
  }

  const handleOpenDetail = (student) => {
    setSelectedStudent(student)
    setActiveDetailTab('siswa')
    setShowDetailModal(true)
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
      setShowDetailModal(false)
    }
  }

  const handleFormSubmitCallback = async (payload) => {
    try {
      if (isEdit && payload.id) {
        if (String(payload.id).startsWith('demo-')) {
          Swal.fire('Berhasil', 'Data siswa berhasil diperbarui (mode demo).', 'success')
        } else {
          await ubah.mutateAsync({ id: payload.id, payload })
          Swal.fire('Berhasil', 'Data siswa berhasil diperbarui.', 'success')
        }
      } else {
        await tambah.mutateAsync(payload)
        Swal.fire('Berhasil', 'Data siswa baru berhasil ditambahkan.', 'success')
      }
      setShowFormModal(false)
    } catch (err) {
      Swal.fire('Gagal', 'Terjadi kesalahan saat menyimpan data.', 'error')
    }
  }

  // Export Excel CSV trigger
  const handleExportExcel = () => {
    const headers = ['NIS', 'NISN', 'Nama Lengkap', 'Jenis Kelamin', 'Tempat Lahir', 'Tanggal Lahir', 'Agama', 'Alamat', 'Unit Pendidikan', 'Kelas', 'Nama Ayah', 'HP Ayah', 'Nama Ibu', 'HP Ibu', 'Nama Wali', 'HP Wali', 'Status', 'Email']
    const csvRows = [headers.join(',')]

    filteredStudents.forEach((st) => {
      const meta = st.raw?.metadata || {}
      const row = [
        `"${st.nis}"`,
        `"${st.nisn}"`,
        `"${st.nama}"`,
        `"${st.gender}"`,
        `"${st.tempatLahir || ''}"`,
        `"${st.tanggalLahir || ''}"`,
        `"${st.agama || ''}"`,
        `"${(st.alamat || '').replace(/"/g, '""')}"`,
        `"${st.unit}"`,
        `"${st.kelas}"`,
        `"${meta.ayah?.nama || ''}"`,
        `"${meta.ayah?.hp || meta.ibu?.hp || meta.wali?.hp || ''}"`,
        `"${meta.ibu?.nama || ''}"`,
        `"${meta.ibu?.hp || ''}"`,
        `"${meta.wali?.nama || ''}"`,
        `"${meta.wali?.hp || ''}"`,
        `"${st.status}"`,
        `"${meta.email || ''}"`,
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

  return (
    <div className="space-y-4">
      {/* Header Banner */}
      <div className="bg-gradient-to-r from-emerald-800 to-teal-700 rounded-2xl p-6 text-white shadow-lg">
        <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
          <div>
            <span className="bg-emerald-600/50 text-emerald-100 text-xs font-semibold px-3 py-1 rounded-full uppercase tracking-wider">
              Master Data Sekolah
            </span>
            <h1 className="text-2xl md:text-3xl font-bold mt-2">Data Siswa</h1>
            <p className="text-emerald-100 text-sm mt-1">
              Kelola seluruh data siswa di semua unit pendidikan Dar El-Iman
            </p>
          </div>
          <div className="flex items-center gap-2">
            <button
              onClick={handleExportExcel}
              className="bg-white/10 hover:bg-white/20 text-white font-medium px-4 py-2.5 rounded-xl border border-white/20 transition flex items-center gap-2 text-sm backdrop-blur-sm"
            >
              <FaFileExcel /> Export Excel
            </button>
            <button
              onClick={() => setShowImportModal(true)}
              className="bg-white/10 hover:bg-white/20 text-white font-medium px-4 py-2.5 rounded-xl border border-white/20 transition flex items-center gap-2 text-sm backdrop-blur-sm"
            >
              <FaFileImport /> Import Excel
            </button>
            <button
              onClick={handleOpenTambah}
              className="bg-emerald-500 hover:bg-emerald-400 text-white font-semibold px-5 py-2.5 rounded-xl transition flex items-center gap-2 text-sm shadow-md"
            >
              <FaPlus /> Tambah Siswa
            </button>
          </div>
        </div>
      </div>

      {/* Quick Summary Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center gap-4">
          <div className="w-12 h-12 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center text-xl font-bold">
            <FaUserGraduate />
          </div>
          <div>
            <p className="text-xs text-slate-500 font-medium">Total Siswa</p>
            <h3 className="text-2xl font-bold text-slate-800">{filteredStudents.length}</h3>
            <span className="text-[11px] text-emerald-600 font-medium">Terdaftar di sistem</span>
          </div>
        </div>

        <div className="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center gap-4">
          <div className="w-12 h-12 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center text-xl font-bold">
            <FaMale />
          </div>
          <div>
            <p className="text-xs text-slate-500 font-medium">Siswa Laki-laki</p>
            <h3 className="text-2xl font-bold text-slate-800">
              {filteredStudents.filter((s) => (s.gender || '').toLowerCase() === 'laki-laki' || (s.gender || '').toLowerCase() === 'l').length}
            </h3>
            <span className="text-[11px] text-blue-600 font-medium">Berdasarkan data filter</span>
          </div>
        </div>

        <div className="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center gap-4">
          <div className="w-12 h-12 rounded-xl bg-pink-100 text-pink-600 flex items-center justify-center text-xl font-bold">
            <FaFemale />
          </div>
          <div>
            <p className="text-xs text-slate-500 font-medium">Siswa Perempuan</p>
            <h3 className="text-2xl font-bold text-slate-800">
              {filteredStudents.filter((s) => (s.gender || '').toLowerCase() === 'perempuan' || (s.gender || '').toLowerCase() === 'p').length}
            </h3>
            <span className="text-[11px] text-pink-600 font-medium">Berdasarkan data filter</span>
          </div>
        </div>

        <div className="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center gap-4">
          <div className="w-12 h-12 rounded-xl bg-yellow-100 text-yellow-600 flex items-center justify-center text-xl font-bold">
            <FaCheckCircle />
          </div>
          <div>
            <p className="text-xs text-slate-500 font-medium">Status Aktif</p>
            <h3 className="text-2xl font-bold text-slate-800">
              {filteredStudents.filter((s) => (s.status || '').toLowerCase() === 'aktif').length}
            </h3>
            <span className="text-[11px] text-yellow-600 font-medium">Berdasarkan data filter</span>
          </div>
        </div>
      </div>

      {/* Filter Bar */}
      <div className="flex flex-col sm:flex-row items-center justify-between bg-white p-4 rounded-3xl border border-slate-200 shadow-sm gap-4">
        {/* Search Input */}
        <div className="relative w-full sm:w-1/2 md:w-[45%]">
          <FaSearch className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm" />
          <input
            type="text"
            value={searchInput}
            onChange={(e) => { setSearchInput(e.target.value); setCurrentPage(1) }}
            placeholder="Cari NIS / Nama Siswa / NISN..."
            className="w-full rounded-full border border-slate-200 bg-slate-50 pl-10 pr-4 py-2.5 text-sm font-medium text-slate-700 focus:border-emerald-500 focus:bg-white focus:outline-none transition-colors"
          />
        </div>

        {/* Filters */}
        <div className="flex items-center gap-3 w-full sm:w-auto overflow-x-auto pb-1 sm:pb-0 scrollbar-hide">
          <div className="flex items-center gap-2 text-slate-500 mr-1 shrink-0">
            <FaFilter className="text-xs" />
            <span className="text-sm font-bold">Filter:</span>
          </div>

          <select
            value={unitFilter}
            onChange={(e) => { setUnitFilter(e.target.value); setCurrentPage(1) }}
            className="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 focus:border-emerald-500 focus:outline-none shrink-0"
          >
            <option value="">Semua Unit Pendidikan</option>
            <option value="SDIT 1">SDIT 1 Dar el-Iman - 50 Kota</option>
            <option value="SDIT 2">SDIT 2 Dar el-Iman - Padang</option>
            <option value="SDIT 3">SDIT 3 Dar el-Iman - Padang</option>
            <option value="SDIT 4">SDIT 4 Dar el-Iman - Padang</option>
            <option value="TKIT 1">TKIT 1 Dar el-Iman - Padang</option>
            <option value="MIT SaQu">MIT SaQu Dar el-Iman - Padang</option>
          </select>

          <select
            value={kelasFilter}
            onChange={(e) => { setKelasFilter(e.target.value); setCurrentPage(1) }}
            className="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 focus:border-emerald-500 focus:outline-none shrink-0"
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

          <select
            value={statusFilter}
            onChange={(e) => { setStatusFilter(e.target.value); setCurrentPage(1) }}
            className="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 focus:border-emerald-500 focus:outline-none shrink-0"
          >
            <option value="">Semua Status</option>
            <option value="aktif">Aktif</option>
            <option value="mutasi">Mutasi</option>
            <option value="lulus">Lulus</option>
            <option value="nonaktif">Nonaktif</option>
          </select>
        </div>
      </div>

      {/* Main Student Data Table */}
      <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div className="overflow-x-auto">
          <table className="w-full border-collapse text-left text-xs">
            <thead>
              <tr className="border-b border-slate-200 bg-slate-50 font-bold uppercase text-slate-600">
                <th className="py-3 px-4 text-center w-12">No</th>
                <th className="py-3 px-4 text-center w-14">Foto</th>
                <th className="py-3 px-4">NIS</th>
                <th className="py-3 px-4">Nama Siswa</th>
                <th className="py-3 px-4">Unit Pendidikan</th>
                <th className="py-3 px-4 text-center">Kelas</th>
                <th className="py-3 px-4">Orang Tua / Wali</th>
                <th className="py-3 px-4">No. HP</th>
                <th className="py-3 px-4 text-center">Status</th>
                <th className="py-3 px-4 text-center w-28">Aksi</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {paginatedStudents.map((item, idx) => (
                <tr key={item.id} className="hover:bg-emerald-50/40 transition">
                  <td className="py-3 px-4 text-center font-medium text-slate-500">
                    {(currentPage - 1) * itemsPerPage + idx + 1}
                  </td>
                  <td className="py-3 px-4 text-center">
                    <img
                      src={item.foto}
                      alt={item.nama}
                      className="h-8 w-8 rounded-full object-cover border border-slate-200 mx-auto"
                    />
                  </td>
                  <td className="py-3 px-4 font-bold text-slate-800">{item.nis}</td>
                  <td className="py-3 px-4 font-extrabold text-slate-900">{item.nama}</td>
                  <td className="py-3 px-4 font-semibold text-slate-700">{item.unit}</td>
                  <td className="py-3 px-4 text-center font-bold text-slate-800">{item.kelas}</td>
                  <td className="py-3 px-4 text-slate-700">{item.orangTua}</td>
                  <td className="py-3 px-4 text-slate-600 font-medium">{item.noHp}</td>
                  <td className="py-3 px-4 text-center">{renderStatusBadge(item.status)}</td>
                  <td className="py-3 px-4 text-center">
                    <div className="flex items-center justify-center gap-1.5">
                      <button
                        type="button"
                        onClick={() => handleOpenDetail(item)}
                        title="Lihat Detail Pop Up"
                        className="flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 text-blue-600 hover:bg-blue-50 transition"
                      >
                        <FaEye />
                      </button>
                      <button
                        type="button"
                        onClick={() => handleOpenEdit(item)}
                        title="Edit Data Pop Up"
                        className="flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 text-amber-600 hover:bg-amber-50 transition"
                      >
                        <FaEdit />
                      </button>
                      <button
                        type="button"
                        onClick={() => {
                          setStudentToPrint(item)
                          setShowCetakModal(true)
                        }}
                        title="Cetak Kartu Siswa"
                        className="flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 text-emerald-600 hover:bg-emerald-50 transition"
                      >
                        <FaPrint />
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

        {/* Table Pagination Footer */}
        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-t border-slate-200 bg-slate-50/80 px-4 py-3 text-xs">
          <div className="text-slate-500">
            Menampilkan {filteredStudents.length === 0 ? 0 : (currentPage - 1) * itemsPerPage + 1} sampai{' '}
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

      {/* POP UP MODAL 1: DETAIL SISWA */}
      {showDetailModal && selectedStudent && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm overflow-y-auto">
          <div className="relative w-full max-w-3xl rounded-2xl bg-white shadow-2xl border border-slate-200 overflow-hidden my-6">

            {/* Modal Header — Hijau Tua sesuai gambar */}
            <div className="bg-[#064e3b] px-6 py-4 flex items-center justify-between text-white">
              <div className="flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-700/80 text-amber-300 border border-emerald-500/40 shadow">
                  <FaUserGraduate className="text-xl" />
                </div>
                <div>
                  <h3 className="text-base font-extrabold tracking-wider uppercase">DETAIL SISWA</h3>
                  <p className="text-[11px] text-emerald-200/80 font-medium">Informasi Lengkap Siswa: {selectedStudent.nama}</p>
                </div>
              </div>
              <button onClick={() => setShowDetailModal(false)}
                className="rounded-full p-2 text-emerald-200 hover:bg-emerald-800 hover:text-white transition">
                <FaTimes className="text-base" />
              </button>
            </div>

            {/* Modal Content */}
            <div className="p-5 bg-slate-50/60 max-h-[80vh] overflow-y-auto">
              <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">

                {/* Left: Profile Overview Card */}
                <div className="lg:col-span-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4 text-xs">
                  {/* Photo + Name + Status */}
                  <div className="flex items-start gap-3">
                    <div className="h-16 w-16 flex-shrink-0 overflow-hidden rounded-xl border-2 border-emerald-600 bg-slate-100 shadow">
                      <img src={selectedStudent.foto} alt={selectedStudent.nama} className="h-full w-full object-cover" />
                    </div>
                    <div className="min-w-0">
                      <div className="flex items-center gap-2 flex-wrap">
                        <h3 className="text-sm font-extrabold text-slate-900 leading-tight">{selectedStudent.nama}</h3>
                        {renderStatusBadge(selectedStudent.status)}
                      </div>
                      <p className="text-[11px] text-slate-500 mt-0.5 font-medium">NIS: {selectedStudent.nis} | NISN: {selectedStudent.nisn}</p>
                      <p className="text-[11px] text-emerald-800 font-semibold mt-0.5">{selectedStudent.unit}</p>
                    </div>
                  </div>

                  {/* Biodata Singkat */}
                  <div className="space-y-2.5 border-t border-slate-100 pt-3">
                    {[
                      { label: 'Tempat, Tgl Lahir', value: `${selectedStudent.tempatLahir}, ${selectedStudent.tanggalLahir}` },
                      { label: 'Jenis Kelamin', value: selectedStudent.gender },
                      { label: 'Agama', value: selectedStudent.agama || 'Islam' },
                      { label: 'Kelas', value: selectedStudent.kelas },
                    ].map(item => (
                      <div key={item.label} className="flex justify-between items-start gap-2">
                        <span className="text-slate-500 shrink-0">{item.label}:</span>
                        <span className="font-semibold text-slate-800 text-right">{item.value || '-'}</span>
                      </div>
                    ))}
                  </div>
                </div>

                {/* Right: Tabbed Detail */}
                <div className="lg:col-span-8 space-y-4">
                  {/* Tab Navigation + Content */}
                  <div className="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    {/* Tabs Header */}
                    <div className="flex gap-0 border-b border-slate-200 overflow-x-auto">
                      {[
                        { key: 'siswa', label: 'Data Siswa' },
                        { key: 'orangTua', label: 'Orang Tua / Wali' },
                        { key: 'akademik', label: 'Akademik' },
                        { key: 'riwayat', label: 'Riwayat' },
                        { key: 'dokumen', label: 'Dokumen' },
                      ].map(({ key, label }) => (
                        <button key={key} onClick={() => setActiveDetailTab(key)}
                          className={`px-4 py-3 text-xs font-semibold whitespace-nowrap border-b-2 transition ${
                            activeDetailTab === key
                              ? 'border-emerald-700 text-emerald-900 font-extrabold bg-emerald-50/50'
                              : 'border-transparent text-slate-500 hover:text-slate-700'
                          }`}>
                          {label}
                        </button>
                      ))}
                    </div>

                    {/* Tab Content */}
                    <div className="p-4 text-xs space-y-2">
                      {(() => {
                        const meta = selectedStudent.raw?.metadata || {}
                        const DRow = ({ label, val }) => (
                          <p><span className="font-bold text-slate-700">{label}:</span>{' '}
                            <span className="text-slate-800">{val || '-'}</span>
                          </p>
                        )
                        if (activeDetailTab === 'siswa') return (
                          <div className="space-y-2">
                            <DRow label="NIS" val={selectedStudent.nis} />
                            <DRow label="NISN" val={selectedStudent.nisn} />
                            <DRow label="No Pendaftaran" val={meta.no_pendaftaran} />
                            <DRow label="NIK" val={meta.nik} />
                            <DRow label="No KK" val={meta.no_kk} />
                            <DRow label="No Registrasi Akta Lahir" val={meta.no_registrasi_akta_lahir} />
                            <DRow label="Kewarganegaraan" val={meta.kewarganegaraan || 'WNI'} />
                            <DRow label="Email" val={meta.email} />
                            <DRow label="Anak Ke-" val={meta.anak_ke} />
                            <DRow label="Jumlah Saudara" val={meta.jumlah_saudara} />
                            <DRow label="Jumlah Saudara Tiri" val={meta.jumlah_saudara_tiri} />
                            <DRow label="Berat Badan" val={meta.berat_badan ? `${meta.berat_badan} kg` : null} />
                            <DRow label="Tinggi Badan" val={meta.tinggi_badan ? `${meta.tinggi_badan} cm` : null} />
                            <DRow label="Riwayat Penyakit" val={meta.riwayat_penyakit} />
                            <hr className="border-slate-100 my-2" />
                            <DRow label="Alamat Lengkap" val={selectedStudent.alamat || meta.alamat_siswa} />
                            <DRow label="RT/RW" val={meta.rt && meta.rw ? `${meta.rt} / ${meta.rw}` : null} />
                            <DRow label="Dusun/Jalan" val={meta.dusun} />
                            <DRow label="Kelurahan" val={meta.kelurahan} />
                            <DRow label="Kecamatan" val={meta.kecamatan} />
                            <DRow label="Kota/Kabupaten" val={meta.kota_kabupaten} />
                            <DRow label="Provinsi" val={meta.provinsi} />
                            <DRow label="Kode Pos" val={meta.kode_pos} />
                            <DRow label="Jenis Tempat Tinggal" val={meta.jenis_tempat_tinggal} />
                            <DRow label="Jarak ke Sekolah" val={meta.jarak_tempuh_ke_sekolah ? `${meta.jarak_tempuh_ke_sekolah} km` : null} />
                            <DRow label="Moda Transportasi" val={meta.moda_transportasi} />
                            <hr className="border-slate-100 my-2" />
                            <DRow label="Hobi" val={meta.hobi} />
                            <DRow label="Cita-cita" val={meta.cita_cita} />
                          </div>
                        )
                        if (activeDetailTab === 'orangTua') return (
                          <div className="space-y-2">
                            <p className="font-bold text-emerald-800 border-b border-emerald-100 pb-1.5 mb-2">Data Ayah Kandung</p>
                            <DRow label="NIK Ayah" val={meta.nik_ayah} />
                            <DRow label="Nama Ayah" val={meta.nama_ayah} />
                            <DRow label="Tempat, Tgl Lahir Ayah" val={meta.tempat_lahir_ayah && meta.tgl_lahir_ayah ? `${meta.tempat_lahir_ayah}, ${meta.tgl_lahir_ayah}` : (meta.tempat_lahir_ayah || meta.tgl_lahir_ayah)} />
                            <DRow label="Telfon / HP Ayah" val={meta.telfon_ayah || meta.hp_ayah} />
                            <DRow label="No WA Ayah" val={meta.nomor_wa_ayah} />
                            <DRow label="Pendidikan Terakhir Ayah" val={meta.pendidikan_terakhir_ayah} />
                            <DRow label="Pekerjaan Ayah" val={meta.pekerjaan_ayah} />
                            <DRow label="Instansi / Jabatan Ayah" val={meta.instansi_pekerjaan_ayah && meta.jabatan_pekerjaan_ayah ? `${meta.instansi_pekerjaan_ayah} — ${meta.jabatan_pekerjaan_ayah}` : (meta.instansi_pekerjaan_ayah || meta.jabatan_pekerjaan_ayah)} />
                            <DRow label="Penghasilan Ayah" val={meta.penghasilan_ayah ? `Rp ${Number(meta.penghasilan_ayah).toLocaleString('id-ID')}` : null} />
                            <DRow label="Alamat Rumah Ayah" val={meta.alamat_ayah} />
                            <hr className="border-slate-100 my-2" />
                            <p className="font-bold text-blue-800 border-b border-blue-100 pb-1.5 mb-2">Data Ibu Kandung</p>
                            <DRow label="NIK Ibu" val={meta.nik_ibu} />
                            <DRow label="Nama Ibu" val={meta.nama_ibu} />
                            <DRow label="Tempat, Tgl Lahir Ibu" val={meta.tempat_lahir_ibu && meta.tgl_lahir_ibu ? `${meta.tempat_lahir_ibu}, ${meta.tgl_lahir_ibu}` : (meta.tempat_lahir_ibu || meta.tgl_lahir_ibu)} />
                            <DRow label="Telfon / HP Ibu" val={meta.telfon_ibu || meta.hp_ibu} />
                            <DRow label="No WA Ibu" val={meta.nomor_wa_ibu} />
                            <DRow label="Pendidikan Terakhir Ibu" val={meta.pendidikan_terakhir_ibu} />
                            <DRow label="Pekerjaan Ibu" val={meta.pekerjaan_ibu} />
                            <DRow label="Instansi / Jabatan Ibu" val={meta.instansi_pekerjaan_ibu && meta.jabatan_pekerjaan_ibu ? `${meta.instansi_pekerjaan_ibu} — ${meta.jabatan_pekerjaan_ibu}` : (meta.instansi_pekerjaan_ibu || meta.jabatan_pekerjaan_ibu)} />
                            <DRow label="Penghasilan Ibu" val={meta.penghasilan_ibu ? `Rp ${Number(meta.penghasilan_ibu).toLocaleString('id-ID')}` : null} />
                            <DRow label="Alamat Rumah Ibu" val={meta.alamat_ibu} />
                            {(meta.nama_wali || meta.hp_wali) && (<>
                              <hr className="border-slate-100 my-2" />
                              <p className="font-bold text-slate-700 border-b border-slate-100 pb-1.5 mb-2">Data Wali</p>
                              <DRow label="NIK Wali" val={meta.nik_wali} />
                              <DRow label="Nama Wali" val={meta.nama_wali} />
                              <DRow label="HP / WA Wali" val={meta.hp_wali || meta.nomor_wa_wali} />
                              <DRow label="Pekerjaan Wali" val={meta.pekerjaan_wali} />
                              <DRow label="Alamat Wali" val={meta.alamat_wali} />
                            </>)}
                          </div>
                        )
                        if (activeDetailTab === 'akademik') return (
                          <div className="space-y-2">
                            <DRow label="Unit Pendidikan" val={selectedStudent.unit} />
                            <DRow label="Kelas" val={selectedStudent.kelas} />
                            <DRow label="Tahun Ajaran Masuk" val={meta.tahun_ajaran_masuk} />
                            <DRow label="Tahun Ajaran Berjalan" val={meta.tahun_ajaran_berjalan} />
                            <DRow label="Status Siswa" val={selectedStudent.status} />
                            <DRow label="NIS Pembayaran" val={meta.nis_pembayaran} />
                            <DRow label="Wali Kelas" val={meta.wali_kelas} />
                            <DRow label="NIY Wali Kelas" val={meta.niy_wali_kelas} />
                            <DRow label="Status Orang Tua" val={meta.status_orang_tua} />
                            <DRow label="NIY Ortu (Jika Pegawai)" val={meta.niy_ortu_jika_pegawai} />
                            <hr className="border-slate-100 my-2" />
                            <DRow label="Sekolah Asal" val={meta.sekolah_asal} />
                            <DRow label="Status Sekolah Asal" val={meta.status_sekolah_asal} />
                            <DRow label="Nominal SPP" val={meta.nominal_spp ? `Rp ${Number(meta.nominal_spp).toLocaleString('id-ID')}` : null} />
                            <DRow label="Penerima KPS/PKH" val={meta.penerima_kps_pkh} />
                            <DRow label="Punya KIP" val={meta.apakah_punya_kip} />
                            <DRow label="Layak Menerima PIP" val={meta.apakah_layak_menerima_pip} />
                            <DRow label="Alasan Menolak PIP" val={meta.alasan_menolak_pip} />
                          </div>
                        )
                        if (activeDetailTab === 'riwayat') return (
                          <div className="space-y-2">
                            <DRow label="Tanggal Masuk" val={meta.tanggal_masuk} />
                            <DRow label="No Induk Sebelumnya" val={meta.no_induk_sebelumnya} />
                            <DRow label="Beasiswa" val={meta.beasiswa} />
                            <DRow label="Catatan" val={meta.catatan} />
                            <p className="text-slate-400 mt-2">Riwayat keaktifan & kehadiran tercatat di sistem absensi.</p>
                          </div>
                        )
                        if (activeDetailTab === 'dokumen') return (
                          <div className="space-y-2">
                            <DRow label="Foto Siswa (URL)" val={meta.foto_url} />
                            <DRow label="No Registrasi Akta Lahir" val={meta.no_registrasi_akta_lahir} />
                            <DRow label="No Kartu Keluarga" val={meta.no_kk} />
                            <p className="text-slate-400 mt-2">Status dokumen fisik dikelola secara manual oleh admin TU.</p>
                          </div>
                        )
                        return null
                      })()}
                    </div>
                  </div>

                  {/* Quick Actions Card — sesuai gambar */}
                  <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h4 className="text-xs font-bold text-slate-700 uppercase tracking-wider mb-3">AKSI CEPAT</h4>
                    <div className="flex flex-wrap gap-2">
                      <button onClick={() => handleOpenEdit(selectedStudent)}
                        className="flex items-center gap-1.5 rounded-lg border border-amber-300 bg-amber-50 px-4 py-2 text-xs font-bold text-amber-800 hover:bg-amber-100 transition">
                        <FaEdit /> Edit Data
                      </button>
                      <button onClick={() => { setStudentToPrint(selectedStudent); setShowCetakModal(true) }}
                        className="flex items-center gap-1.5 rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-2 text-xs font-bold text-emerald-800 hover:bg-emerald-100 transition">
                        <FaPrint /> Cetak Kartu Siswa
                      </button>
                      <button onClick={() => handleDelete(selectedStudent)}
                        className="flex items-center gap-1.5 rounded-lg border border-rose-200 bg-rose-50 px-4 py-2 text-xs font-bold text-rose-600 hover:bg-rose-100 transition">
                        <FaTrash /> Hapus Data
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            {/* Footer */}
            <div className="flex justify-end border-t border-slate-200 bg-slate-50 px-6 py-3">
              <button type="button" onClick={() => setShowDetailModal(false)}
                className="rounded-xl border border-slate-300 bg-white px-6 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition shadow-sm">
                Tutup
              </button>
            </div>
          </div>
        </div>
      )}

      {/* POP UP MODAL 2: TAMBAH / EDIT SISWA FORM */}
      <StudentFormModal
        isOpen={showFormModal}
        onClose={() => setShowFormModal(false)}
        initialData={isEdit ? selectedStudent : null}
        onSubmit={handleFormSubmitCallback}
        classes={rawClasses}
      />

      {/* POP UP MODAL 3: CETAK KARTU SISWA */}
      {showCetakModal && (
        <CetakKartuSiswaModal student={studentToPrint} onClose={() => setShowCetakModal(false)} />
      )}

      {/* POP UP MODAL 4: DASHBOARD IMPORT DATA SISWA */}
      {showImportModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm">
          <div className="w-full max-w-3xl overflow-hidden rounded-2xl bg-white shadow-2xl transition-all">
            {/* Header Modal */}
            <div className="flex items-center justify-between border-b border-slate-100 bg-slate-50/80 px-6 py-4">
              <div className="flex items-center gap-2.5">
                <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-100 text-blue-700">
                  <FaFileImport className="text-base" />
                </div>
                <div>
                  <h2 className="text-base font-extrabold text-slate-900">Dashboard Import Data Siswa</h2>
                  <p className="text-xs text-slate-500">Unggah file Excel atau CSV untuk mengimpor banyak siswa secara massal</p>
                </div>
              </div>
              <button
                type="button"
                onClick={() => { setShowImportModal(false); setImportFile(null); setImportPreviewData([]) }}
                className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-200 hover:text-slate-700 transition"
              >
                <FaTimes />
              </button>
            </div>

            {/* Modal Body */}
            <div className="p-6 space-y-5 max-h-[75vh] overflow-y-auto">
              {/* Step 1: Download Template */}
              <div className="rounded-xl border border-blue-200 bg-blue-50/60 p-4 flex flex-col sm:flex-row items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                  <FaFileExcel className="text-2xl text-emerald-600 shrink-0" />
                  <div>
                    <h4 className="text-xs font-bold text-slate-800">Unduh Format Template Import</h4>
                    <p className="text-[11px] text-slate-500">Gunakan format file ini agar kolom data sesuai dengan sistem ERP.</p>
                  </div>
                </div>
                <button
                  type="button"
                  onClick={handleDownloadTemplateSiswa}
                  className="flex items-center gap-1.5 rounded-xl border border-emerald-600 bg-white px-3.5 py-2 text-xs font-bold text-emerald-700 hover:bg-emerald-50 transition shadow-xs whitespace-nowrap"
                >
                  <FaDownload className="text-emerald-600" /> Unduh Template
                </button>
              </div>

              {/* Step 2: Upload Dropzone */}
              <div>
                <label className="block text-xs font-bold text-slate-800 mb-2">Unggah File (Excel / CSV)</label>
                <label className="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50/50 p-6 text-center hover:bg-slate-50 cursor-pointer transition">
                  <FaUpload className="text-3xl text-emerald-700 mb-2" />
                  <span className="text-xs font-bold text-slate-800">
                    {importFile ? importFile.name : 'Klik untuk memilih file Excel atau CSV'}
                  </span>
                  <span className="text-[11px] text-slate-400 mt-0.5">
                    {importFile ? `${(importFile.size / 1024).toFixed(1)} KB` : 'Format disukai: .csv, .xlsx, .xls (Maks. 5MB)'}
                  </span>
                  <input
                    type="file"
                    accept=".csv, .xlsx, .xls"
                    onChange={handleFileSelect}
                    className="hidden"
                  />
                </label>
              </div>

              {/* Step 3: Preview Table */}
              {importPreviewData.length > 0 && (
                <div className="space-y-2">
                  <div className="flex items-center justify-between">
                    <h4 className="text-xs font-bold text-slate-800">Preview Data yang Siap Diimpor ({importPreviewData.length} baris)</h4>
                    <span className="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-bold text-emerald-800">
                      Format Sesuai
                    </span>
                  </div>
                   <div className="overflow-x-auto rounded-xl border border-slate-200">
                     <table className="w-full text-left text-xs text-slate-600">
                       <thead className="bg-slate-50 text-[11px] font-bold text-slate-500 uppercase">
                         <tr>
                           <th className="py-2 px-3">NIS</th>
                           <th className="py-2 px-3">NISN</th>
                           <th className="py-2 px-3">Nama Siswa</th>
                           <th className="py-2 px-3">JK</th>
                           <th className="py-2 px-3">Unit Pendidikan</th>
                           <th className="py-2 px-3">Kelas</th>
                           <th className="py-2 px-3">Nama Ayah</th>
                           <th className="py-2 px-3">HP Ayah</th>
                           <th className="py-2 px-3">Status</th>
                         </tr>
                       </thead>
                       <tbody className="divide-y divide-slate-100">
                         {importPreviewData.map((row, idx) => (
                           <tr key={idx} className="hover:bg-slate-50">
                             <td className="py-2 px-3 font-medium">{row.nis}</td>
                             <td className="py-2 px-3">{row.nisn}</td>
                             <td className="py-2 px-3 font-bold text-slate-800">{row.nama}</td>
                             <td className="py-2 px-3">{row.gender}</td>
                             <td className="py-2 px-3">{row.unit}</td>
                             <td className="py-2 px-3 font-semibold">{row.kelas}</td>
                             <td className="py-2 px-3">{row.namaAyah}</td>
                             <td className="py-2 px-3">{row.hpAyah}</td>
                             <td className="py-2 px-3 text-center">
                               <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800">
                                 {row.status}
                               </span>
                             </td>
                           </tr>
                         ))}
                       </tbody>
                     </table>
                   </div>
                </div>
              )}
            </div>

            {/* Modal Action Footer */}
            <div className="flex items-center justify-between border-t border-slate-100 bg-slate-50/80 px-6 py-4">
              <button
                type="button"
                onClick={() => { setShowImportModal(false); setImportFile(null); setImportPreviewData([]) }}
                className="rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50"
              >
                Batal
              </button>
              <button
                type="button"
                disabled={!importFile || isImporting}
                onClick={handleProcessImport}
                className="flex items-center gap-2 rounded-xl bg-[#064e3b] px-5 py-2 text-xs font-bold text-white shadow-md hover:bg-emerald-800 disabled:opacity-50 transition"
              >
                {isImporting ? 'Memproses Import...' : 'Proses Import Data'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
