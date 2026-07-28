import { useMemo, useState } from 'react'
import Swal from 'sweetalert2'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  FaArrowLeft,
  FaBook,
  FaBookOpen,
  FaCalendarAlt,
  FaChalkboardTeacher,
  FaCheckCircle,
  FaChevronLeft,
  FaChevronRight,
  FaCopy,
  FaDownload,
  FaEdit,
  FaExclamationCircle,
  FaExclamationTriangle,
  FaEye,
  FaFileExcel,
  FaFileImport,
  FaFilter,
  FaGraduationCap,
  FaLayerGroup,
  FaLink,
  FaPlus,
  FaPrint,
  FaRedo,
  FaSave,
  FaSearch,
  FaTimes,
  FaTrash,
} from 'react-icons/fa'
import { modulSemesterService } from '../services/modulSemesterService'

const UNIT_BADGES = {
  TK: { bg: 'bg-emerald-700', text: 'text-white', border: 'border-emerald-600' },
  TAUD: { bg: 'bg-emerald-700', text: 'text-white', border: 'border-emerald-600' },
  SD: { bg: 'bg-blue-600', text: 'text-white', border: 'border-blue-500' },
  SDIT: { bg: 'bg-blue-600', text: 'text-white', border: 'border-blue-500' },
  MIT: { bg: 'bg-amber-500', text: 'text-white', border: 'border-amber-400' },
  SMP: { bg: 'bg-cyan-600', text: 'text-white', border: 'border-cyan-500' },
  SMPIT: { bg: 'bg-cyan-600', text: 'text-white', border: 'border-cyan-500' },
  SMA: { bg: 'bg-purple-600', text: 'text-white', border: 'border-purple-500' },
  SMAIT: { bg: 'bg-purple-600', text: 'text-white', border: 'border-purple-500' },
  MA: { bg: 'bg-purple-700', text: 'text-white', border: 'border-purple-600' },
  PONPES: { bg: 'bg-emerald-900', text: 'text-white', border: 'border-emerald-800' },
  MAHAD: { bg: 'bg-amber-800', text: 'text-white', border: 'border-amber-700' },
}

function getUnitBadgeStyle(code) {
  const upper = code?.toUpperCase() || 'SDIT'
  const foundKey = Object.keys(UNIT_BADGES).find((k) => upper.includes(k))
  return (
    UNIT_BADGES[foundKey] || {
      bg: 'bg-slate-700',
      text: 'text-white',
      border: 'border-slate-600',
    }
  )
}

function initialDetailItem(minggu = 1) {
  return {
    minggu: minggu,
    materi: '',
    atp: '',
    cp: '',
    jp: 2,
    keterangan: '',
  }
}

function initialFormState() {
  return {
    id: null,
    tahun_ajaran_id: '',
    semester_id: '',
    unit_pendidikan_id: '',
    kelas_id: '',
    mata_pelajaran_id: '',
    guru_id: '',
    kode_modul: '',
    nama_modul: '',
    jenjang: 'SDIT',
    kurikulum: 'Kurikulum Merdeka',
    status: 'Aktif',

    // Pembelajaran
    atp: '',
    cp: '',
    tujuan_pembelajaran: '',
    alokasi_jam: 36,
    jumlah_pertemuan: 18,
    metode_pembelajaran: 'Problem Based Learning, Ceramah & Diskusi',
    model_pembelajaran: 'Problem Based Learning (PBL)',
    media_pembelajaran: 'Modul Cetak, Slide PPT, Video Pembelajaran',
    sumber_belajar: 'Buku Pegangan Utama & LKS Terpadu',

    // Target
    target_nilai_minimum: 75,
    target_kehadiran: 90,
    target_hafalan: '',
    target_proyek: '',

    // Pengaturan
    berlaku_mulai: '',
    berlaku_sampai: '',
    ditampilkan_di_portal_ortu: true,
    ditampilkan_di_aplikasi_siswa: true,
    arsip_otomatis: false,

    // Bobot Penilaian (%)
    bobot_tugas: 20,
    bobot_quiz: 15,
    bobot_projek: 25,
    bobot_uts: 20,
    bobot_uas: 20,

    // Detail Materi
    details: [
      initialDetailItem(1),
      initialDetailItem(2),
      initialDetailItem(3),
    ],
  }
}

export default function MasterModulSemesterPage() {
  const queryClient = useQueryClient()

  // State Filters
  const [search, setSearch] = useState('')
  const [filterTahun, setFilterTahun] = useState('')
  const [filterUnit, setFilterUnit] = useState('')
  const [filterSemester, setFilterSemester] = useState('')
  const [filterKelas, setFilterKelas] = useState('')
  const [filterGuru, setFilterGuru] = useState('')
  const [filterStatus, setFilterStatus] = useState('')
  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState(15)

  // Form Modal & Drawer
  const [isModalOpen, setIsModalOpen] = useState(false)
  const [isEditMode, setIsEditMode] = useState(false)
  const [activeTab, setActiveTab] = useState('umum') // 'umum' | 'pembelajaran' | 'target' | 'materi' | 'bobot'
  const [formData, setFormData] = useState(initialFormState())
  const [formErrors, setFormErrors] = useState({})

  // Drawer Detail
  const [detailModul, setDetailModul] = useState(null)
  const [isDrawerOpen, setIsDrawerOpen] = useState(false)

  // Query Data List
  const { data: responseData, isLoading } = useQuery({
    queryKey: [
      'modul-semester',
      page,
      perPage,
      search,
      filterTahun,
      filterUnit,
      filterSemester,
      filterKelas,
      filterGuru,
      filterStatus,
    ],
    queryFn: () =>
      modulSemesterService.getDaftar({
        page,
        per_page: perPage,
        search,
        tahun_ajaran_id: filterTahun,
        unit_pendidikan_id: filterUnit,
        semester_id: filterSemester,
        kelas_id: filterKelas,
        guru_id: filterGuru,
        status: filterStatus,
      }),
    keepPreviousData: true,
  })

  // Query Master Dropdown Options
  const { data: optionsData } = useQuery({
    queryKey: ['modul-semester-options'],
    queryFn: () => modulSemesterService.getOptions(),
  })

  // Query Stats
  const { data: statsData } = useQuery({
    queryKey: ['modul-semester-stats'],
    queryFn: () => modulSemesterService.getStats(),
  })

  const modulList = responseData?.data || []
  const meta = responseData?.meta || {}
  const stats = statsData || responseData?.statistik || {}
  const options = optionsData || {}

  // Filtered dropdown lists based on selection
  const filteredKelasList = useMemo(() => {
    if (!options.kelas) return []
    let list = options.kelas
    if (formData.unit_pendidikan_id) {
      list = list.filter((k) => k.unit_pendidikan_id === formData.unit_pendidikan_id)
    }
    if (formData.tahun_ajaran_id) {
      list = list.filter((k) => k.tahun_ajaran_id === formData.tahun_ajaran_id)
    }
    return list
  }, [options.kelas, formData.unit_pendidikan_id, formData.tahun_ajaran_id])

  const filteredSemesterList = useMemo(() => {
    if (!options.semesters) return []
    if (formData.tahun_ajaran_id) {
      return options.semesters.filter((s) => s.academic_year_id === formData.tahun_ajaran_id)
    }
    return options.semesters
  }, [options.semesters, formData.tahun_ajaran_id])

  // Total Bobot Penilaian Live Calculation
  const totalBobotPenilaian = useMemo(() => {
    const t = parseFloat(formData.bobot_tugas || 0)
    const q = parseFloat(formData.bobot_quiz || 0)
    const p = parseFloat(formData.bobot_projek || 0)
    const uts = parseFloat(formData.bobot_uts || 0)
    const uas = parseFloat(formData.bobot_uas || 0)
    return Math.round((t + q + p + uts + uas) * 100) / 100
  }, [formData.bobot_tugas, formData.bobot_quiz, formData.bobot_projek, formData.bobot_uts, formData.bobot_uas])

  // Mutations
  const simpanMutation = useMutation({
    mutationFn: (payload) =>
      isEditMode
        ? modulSemesterService.ubah({ id: formData.id, payload })
        : modulSemesterService.tambah(payload),
    onSuccess: (res) => {
      queryClient.invalidateQueries(['modul-semester'])
      queryClient.invalidateQueries(['modul-semester-stats'])
      setIsModalOpen(false)
      Swal.fire({
        icon: 'success',
        title: 'Berhasil Disimpan!',
        text: res.message || 'Master Modul Semester berhasil disimpan.',
        timer: 2000,
        showConfirmButton: false,
      })
    },
    onError: (err) => {
      const respData = err.response?.data
      if (respData?.errors) {
        setFormErrors(respData.errors)
      } else {
        Swal.fire('Error', respData?.message || 'Terjadi kesalahan saat menyimpan data modul semester.', 'error')
      }
    },
  })

  const hapusMutation = useMutation({
    mutationFn: (id) => modulSemesterService.hapus(id),
    onSuccess: () => {
      queryClient.invalidateQueries(['modul-semester'])
      queryClient.invalidateQueries(['modul-semester-stats'])
      Swal.fire({
        icon: 'success',
        title: 'Terhapus!',
        text: 'Data Modul Semester berhasil dihapus.',
        timer: 2000,
        showConfirmButton: false,
      })
    },
    onError: (err) => {
      Swal.fire('Error', err.response?.data?.message || 'Gagal menghapus data modul semester.', 'error')
    },
  })

  const duplikasiMutation = useMutation({
    mutationFn: (id) => modulSemesterService.duplikasi(id),
    onSuccess: (res) => {
      queryClient.invalidateQueries(['modul-semester'])
      queryClient.invalidateQueries(['modul-semester-stats'])
      Swal.fire({
        icon: 'success',
        title: 'Berhasil Diduplikasi!',
        text: res.message || 'Modul Semester berhasil diduplikasi.',
        timer: 2000,
        showConfirmButton: false,
      })
    },
    onError: (err) => {
      Swal.fire('Error', err.response?.data?.message || 'Gagal menduplikasi modul semester.', 'error')
    },
  })

  // Handlers
  const handleOpenTambahModal = () => {
    setIsEditMode(false)
    setFormData(initialFormState())
    setFormErrors({})
    setActiveTab('umum')
    setIsModalOpen(true)
  }

  const handleOpenEditModal = (item) => {
    setIsEditMode(true)
    setFormErrors({})
    setActiveTab('umum')
    setFormData({
      id: item.id,
      tahun_ajaran_id: item.tahun_ajaran_id || '',
      semester_id: item.semester_id || '',
      unit_pendidikan_id: item.unit_pendidikan_id || '',
      kelas_id: item.kelas_id || '',
      mata_pelajaran_id: item.mata_pelajaran_id || '',
      guru_id: item.guru_id || '',
      kode_modul: item.kode_modul || '',
      nama_modul: item.nama_modul || '',
      jenjang: item.jenjang || 'SDIT',
      kurikulum: item.kurikulum || 'Kurikulum Merdeka',
      status: item.status || 'Aktif',
      atp: item.atp || '',
      cp: item.cp || '',
      tujuan_pembelajaran: item.tujuan_pembelajaran || '',
      alokasi_jam: item.alokasi_jam || 36,
      jumlah_pertemuan: item.jumlah_pertemuan || 18,
      metode_pembelajaran: item.metode_pembelajaran || '',
      model_pembelajaran: item.model_pembelajaran || '',
      media_pembelajaran: item.media_pembelajaran || '',
      sumber_belajar: item.sumber_belajar || '',
      target_nilai_minimum: item.target_nilai_minimum || 75,
      target_kehadiran: item.target_kehadiran || 90,
      target_hafalan: item.target_hafalan || '',
      target_proyek: item.target_proyek || '',
      berlaku_mulai: item.berlaku_mulai || '',
      berlaku_sampai: item.berlaku_sampai || '',
      ditampilkan_di_portal_ortu: item.ditampilkan_di_portal_ortu ?? true,
      ditampilkan_di_aplikasi_siswa: item.ditampilkan_di_aplikasi_siswa ?? true,
      arsip_otomatis: item.arsip_otomatis ?? false,
      bobot_tugas: item.bobot_tugas ?? 20,
      bobot_quiz: item.bobot_quiz ?? 15,
      bobot_projek: item.bobot_projek ?? 25,
      bobot_uts: item.bobot_uts ?? 20,
      bobot_uas: item.bobot_uas ?? 20,
      details: item.details && item.details.length > 0 ? item.details : [initialDetailItem(1)],
    })
    setIsModalOpen(true)
  }

  const handleOpenDetailDrawer = (item) => {
    setDetailModul(item)
    setIsDrawerOpen(true)
  }

  const handleTambahDetailMateri = () => {
    const nextMinggu = (formData.details?.length || 0) + 1
    setFormData((prev) => ({
      ...prev,
      details: [...(prev.details || []), initialDetailItem(nextMinggu)],
    }))
  }

  const handleUbahDetailMateri = (index, field, value) => {
    setFormData((prev) => {
      const newDetails = [...(prev.details || [])]
      newDetails[index] = { ...newDetails[index], [field]: value }
      return { ...prev, details: newDetails }
    })
  }

  const handleHapusDetailMateri = (index) => {
    setFormData((prev) => ({
      ...prev,
      details: prev.details.filter((_, i) => i !== index),
    }))
  }

  const handleGenerateKode = () => {
    const rand = Math.floor(1000 + Math.random() * 9000)
    const unitCode = options.unit_pendidikan?.find((u) => u.id === formData.unit_pendidikan_id)?.code || 'SDIT'
    const code = `MDS-${new Date().getFullYear()}${new Date().getMonth() + 1}-${unitCode}-${rand}`
    setFormData((prev) => ({ ...prev, kode_modul: code }))
  }

  const handleSubmitForm = (e) => {
    e.preventDefault()
    setFormErrors({})

    const errors = {}
    if (!formData.tahun_ajaran_id) errors.tahun_ajaran_id = ['Tahun Ajaran wajib dipilih.']
    if (!formData.semester_id) errors.semester_id = ['Semester wajib dipilih.']
    if (!formData.kelas_id) errors.kelas_id = ['Kelas wajib dipilih.']
    if (!formData.mata_pelajaran_id) errors.mata_pelajaran_id = ['Mata Pelajaran wajib dipilih.']
    if (!formData.guru_id) errors.guru_id = ['Guru Pengampu wajib dipilih.']
    if (!formData.nama_modul) errors.nama_modul = ['Nama Modul wajib diisi.']

    if (totalBobotPenilaian !== 100) {
      errors.bobot = [`Total bobot penilaian harus 100%. Saat ini: ${totalBobotPenilaian}%.`]
    }

    if (Object.keys(errors).length > 0) {
      setFormErrors(errors)
      Swal.fire({
        icon: 'warning',
        title: 'Form Belum Lengkap / Bobot Tidak 100%',
        text: errors.bobot ? errors.bobot[0] : 'Harap lengkapi semua kolom wajib di formulir.',
        confirmButtonColor: '#0E5C44',
      })
      return
    }

    simpanMutation.mutate(formData)
  }

  const handleConfirmHapus = (item) => {
    Swal.fire({
      title: 'Apakah Anda yakin ingin menghapus data ini?',
      html: `Apakah Anda yakin ingin menghapus <b>${item.nama_modul}</b>?<br><small className="text-slate-400">Data akan masuk ke soft delete (arsip).</small>`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#e11d48',
      cancelButtonColor: '#64748b',
      confirmButtonText: 'Ya, Hapus Data',
      cancelButtonText: 'Batal',
    }).then((result) => {
      if (result.isConfirmed) {
        hapusMutation.mutate(item.id)
      }
    })
  }

  const handleConfirmDuplikasi = (item) => {
    Swal.fire({
      title: 'Duplikasi Modul Semester?',
      html: `Sistem akan membuat salinan baru dari <b>${item.nama_modul}</b> dengan kode modul baru.`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#0E5C44',
      cancelButtonColor: '#64748b',
      confirmButtonText: 'Ya, Duplikasi',
      cancelButtonText: 'Batal',
    }).then((result) => {
      if (result.isConfirmed) {
        duplikasiMutation.mutate(item.id)
      }
    })
  }

  const handleExportCSV = () => {
    if (!modulList || modulList.length === 0) {
      Swal.fire('Info', 'Tidak ada data untuk diekspor.', 'info')
      return
    }

    const headers = [
      'NO',
      'KODE MODUL',
      'NAMA MODUL SEMESTER',
      'TAHUN AJARAN',
      'SEMESTER',
      'UNIT',
      'KELAS',
      'MATA PELAJARAN',
      'GURU PENGAMPU',
      'JUMLAH PERTEMUAN',
      'ALOKASI JAM',
      'STATUS',
    ]

    let csvStr = headers.join(',') + '\n'

    modulList.forEach((m, idx) => {
      const row = [
        idx + 1,
        `"${m.kode_modul}"`,
        `"${m.nama_modul}"`,
        `"${m.tahun_ajaran?.name || '-'}"`,
        `"${m.semester?.name || '-'}"`,
        `"${m.unit_pendidikan?.name || '-'}"`,
        `"${m.kelas?.nama_kelas || '-'}"`,
        `"${m.mata_pelajaran?.name || '-'}"`,
        `"${m.guru?.nama_lengkap || '-'}"`,
        m.jumlah_pertemuan || 0,
        m.alokasi_jam || 0,
        `"${m.status}"`,
      ].join(',')
      csvStr += row + '\n'
    })

    const blob = new Blob([csvStr], { type: 'text/csv;charset=utf-8;' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.setAttribute('href', url)
    link.setAttribute('download', `export_master_modul_semester_${new Date().toISOString().slice(0, 10)}.csv`)
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
  }

  return (
    <div className="space-y-6 pb-12">
      {/* HEADER BANNER - ENTERPRISE GREEN STYLING (IDENTIK REFERENSI GAMBAR) */}
      <div className="relative overflow-hidden rounded-2xl bg-gradient-to-r from-emerald-900 via-emerald-800 to-emerald-700 p-6 md:p-8 text-white shadow-xl">
        <div className="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
          {/* Header Left Text */}
          <div>
            <span className="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-emerald-700/80 text-emerald-100 border border-emerald-500/30 uppercase tracking-widest mb-3">
              AKADEMIK SEKOLAH
            </span>
            <h1 className="text-2xl md:text-3xl font-extrabold tracking-tight">
              Data Modul Semester
            </h1>
            <p className="text-emerald-100/90 text-sm mt-1.5 max-w-xl leading-relaxed">
              Kelola seluruh modul semester di lingkungan Dar El-Iman sebagai acuan pembelajaran terpadu.
            </p>
          </div>

          {/* Header Right Action Buttons */}
          <div className="flex flex-wrap items-center gap-3">
            <button
              onClick={handleExportCSV}
              className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-white font-semibold text-sm backdrop-blur-xs border border-white/20 transition-all shadow-xs"
            >
              <FaFileExcel className="w-4 h-4 text-emerald-300" />
              📥 Export Excel
            </button>

            <button
              onClick={handleOpenTambahModal}
              className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-white font-bold text-sm transition-all shadow-md hover:shadow-emerald-500/20 active:scale-95"
            >
              <FaPlus className="w-4 h-4" />
              ➕ Tambah Modul
            </button>
          </div>
        </div>

        {/* Decorative background blur */}
        <div className="absolute -right-12 -bottom-12 w-64 h-64 bg-emerald-500/10 rounded-full blur-2xl pointer-events-none"></div>
      </div>

      {/* DASHBOARD CARDS (4 STAT CARDS IDENTIK REFERENSI) */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        {/* Card 1 */}
        <div className="bg-white dark:bg-[#1B2433] p-5 rounded-2xl shadow-sm border border-emerald-100 dark:border-slate-800 flex items-center gap-4 hover:shadow-md transition-all">
          <div className="p-3.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 border border-emerald-100 dark:border-emerald-800/40">
            <FaBook className="w-6 h-6" />
          </div>
          <div>
            <p className="text-xs font-bold text-gray-400 dark:text-slate-400 uppercase tracking-wider">Total Modul Semester</p>
            <h3 className="text-2xl font-black text-gray-900 dark:text-white mt-0.5">{stats.total_modul ?? 0}</h3>
            <p className="text-xs font-medium text-emerald-600 dark:text-emerald-400 mt-0.5">Terdaftar di sistem</p>
          </div>
        </div>

        {/* Card 2 */}
        <div className="bg-white dark:bg-[#1B2433] p-5 rounded-2xl shadow-sm border border-emerald-100 dark:border-slate-800 flex items-center gap-4 hover:shadow-md transition-all">
          <div className="p-3.5 rounded-xl bg-blue-50 dark:bg-blue-950/40 text-blue-600 border border-blue-100 dark:border-blue-800/40">
            <FaCheckCircle className="w-6 h-6 text-blue-500" />
          </div>
          <div>
            <p className="text-xs font-bold text-gray-400 dark:text-slate-400 uppercase tracking-wider">Modul Aktif</p>
            <h3 className="text-2xl font-black text-gray-900 dark:text-white mt-0.5">{stats.total_aktif ?? 0}</h3>
            <p className="text-xs font-medium text-blue-600 dark:text-blue-400 mt-0.5">Beroperasi secara penuh</p>
          </div>
        </div>

        {/* Card 3 */}
        <div className="bg-white dark:bg-[#1B2433] p-5 rounded-2xl shadow-sm border border-emerald-100 dark:border-slate-800 flex items-center gap-4 hover:shadow-md transition-all">
          <div className="p-3.5 rounded-xl bg-purple-50 dark:bg-purple-950/40 text-purple-600 border border-purple-100 dark:border-purple-800/40">
            <FaLayerGroup className="w-6 h-6 text-purple-500" />
          </div>
          <div>
            <p className="text-xs font-bold text-gray-400 dark:text-slate-400 uppercase tracking-wider">Total Alokasi Jam</p>
            <h3 className="text-2xl font-black text-gray-900 dark:text-white mt-0.5">{stats.total_jam ?? 0} JP</h3>
            <p className="text-xs font-medium text-purple-600 dark:text-purple-400 mt-0.5">Dari semua unit</p>
          </div>
        </div>

        {/* Card 4 */}
        <div className="bg-white dark:bg-[#1B2433] p-5 rounded-2xl shadow-sm border border-emerald-100 dark:border-slate-800 flex items-center gap-4 hover:shadow-md transition-all">
          <div className="p-3.5 rounded-xl bg-amber-50 dark:bg-amber-950/40 text-amber-600 border border-amber-100 dark:border-amber-800/40">
            <FaGraduationCap className="w-6 h-6 text-amber-500" />
          </div>
          <div>
            <p className="text-xs font-bold text-gray-400 dark:text-slate-400 uppercase tracking-wider">Total Rincian Materi</p>
            <h3 className="text-2xl font-black text-gray-900 dark:text-white mt-0.5">{stats.total_materi ?? 0}</h3>
            <p className="text-xs font-medium text-amber-600 dark:text-amber-400 mt-0.5">Materi mingguan terpadu</p>
          </div>
        </div>
      </div>

      {/* SEARCH & FILTER BAR (IDENTIK REFERENSI) */}
      <div className="bg-white dark:bg-[#1B2433] p-4 rounded-2xl shadow-sm border border-emerald-100 dark:border-slate-800 flex flex-col md:flex-row md:items-center justify-between gap-4">
        {/* Search Box */}
        <div className="relative flex-1">
          <FaSearch className="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 w-4 h-4" />
          <input
            type="text"
            value={search}
            onChange={(e) => {
              setSearch(e.target.value)
              setPage(1)
            }}
            placeholder="Cari kode modul, nama modul, mapel, atau guru..."
            className="w-full pl-10 pr-4 py-2.5 rounded-xl bg-gray-50/80 dark:bg-slate-900 border border-gray-200 dark:border-slate-700 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 focus:bg-white dark:focus:bg-slate-900 transition-colors"
          />
        </div>

        {/* Filters */}
        <div className="flex flex-wrap items-center gap-3 text-sm">
          <span className="flex items-center gap-1.5 font-bold text-gray-600 dark:text-slate-300">
            <FaFilter className="w-3.5 h-3.5 text-emerald-600" />
            Filter:
          </span>

          <select
            value={filterTahun}
            onChange={(e) => {
              setFilterTahun(e.target.value)
              setPage(1)
            }}
            className="px-3.5 py-2 rounded-xl bg-gray-50/80 dark:bg-slate-900 border border-gray-200 dark:border-slate-700 font-medium text-gray-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
          >
            <option value="">Semua Thn Ajaran</option>
            {options.tahun_ajaran?.map((t) => (
              <option key={t.id} value={t.id}>
                {t.name}
              </option>
            ))}
          </select>

          <select
            value={filterUnit}
            onChange={(e) => {
              setFilterUnit(e.target.value)
              setPage(1)
            }}
            className="px-3.5 py-2 rounded-xl bg-gray-50/80 dark:bg-slate-900 border border-gray-200 dark:border-slate-700 font-medium text-gray-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
          >
            <option value="">Semua Unit</option>
            {options.unit_pendidikan?.map((u) => (
              <option key={u.id} value={u.id}>
                {u.name}
              </option>
            ))}
          </select>

          <select
            value={filterSemester}
            onChange={(e) => {
              setFilterSemester(e.target.value)
              setPage(1)
            }}
            className="px-3.5 py-2 rounded-xl bg-gray-50/80 dark:bg-slate-900 border border-gray-200 dark:border-slate-700 font-medium text-gray-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
          >
            <option value="">Semua Semester</option>
            {options.semesters?.map((s) => (
              <option key={s.id} value={s.id}>
                {s.name}
              </option>
            ))}
          </select>

          <select
            value={filterStatus}
            onChange={(e) => {
              setFilterStatus(e.target.value)
              setPage(1)
            }}
            className="px-3.5 py-2 rounded-xl bg-gray-50/80 dark:bg-slate-900 border border-gray-200 dark:border-slate-700 font-medium text-gray-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
          >
            <option value="">Semua Status</option>
            <option value="Aktif">Aktif</option>
            <option value="Nonaktif">Nonaktif</option>
            <option value="Arsip">Arsip</option>
          </select>
        </div>
      </div>

      {/* TABLE CONTAINER - IDENTIK REFERENSI (HEADER KREM/LIGHT BEIGE & BADGES) */}
      <div className="bg-white dark:bg-[#1B2433] rounded-2xl shadow-sm border border-emerald-100 dark:border-slate-800 overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse">
            <thead>
              <tr className="bg-[#F7F4EB] dark:bg-slate-900/80 text-gray-700 dark:text-slate-300 font-bold text-xs uppercase tracking-wider border-b border-gray-200 dark:border-slate-800">
                <th className="py-4 px-4 text-center w-12">NO</th>
                <th className="py-4 px-4 w-14 text-center">LOGO</th>
                <th className="py-4 px-4">NAMA MODUL SEMESTER</th>
                <th className="py-4 px-4">UNIT / KELAS</th>
                <th className="py-4 px-4">MAPEL & GURU</th>
                <th className="py-4 px-4 text-center">PERTEMUAN / JP</th>
                <th className="py-4 px-4 text-center">STATUS</th>
                <th className="py-4 px-4 text-center w-36">AKSI</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100 dark:divide-slate-800 text-sm">
              {isLoading ? (
                Array.from({ length: 5 }).map((_, idx) => (
                  <tr key={idx} className="animate-pulse">
                    <td className="py-4 px-4 text-center">
                      <div className="h-4 bg-gray-200 dark:bg-slate-700 rounded w-6 mx-auto"></div>
                    </td>
                    <td className="py-4 px-4 text-center">
                      <div className="w-8 h-8 bg-gray-200 dark:bg-slate-700 rounded-full mx-auto"></div>
                    </td>
                    <td className="py-4 px-4">
                      <div className="h-4 bg-gray-200 dark:bg-slate-700 rounded w-48 mb-1"></div>
                      <div className="h-3 bg-gray-200 dark:bg-slate-700 rounded w-24"></div>
                    </td>
                    <td className="py-4 px-4">
                      <div className="h-4 bg-gray-200 dark:bg-slate-700 rounded w-28"></div>
                    </td>
                    <td className="py-4 px-4">
                      <div className="h-4 bg-gray-200 dark:bg-slate-700 rounded w-32"></div>
                    </td>
                    <td className="py-4 px-4 text-center">
                      <div className="h-4 bg-gray-200 dark:bg-slate-700 rounded w-20 mx-auto"></div>
                    </td>
                    <td className="py-4 px-4 text-center">
                      <div className="h-6 bg-gray-200 dark:bg-slate-700 rounded-full w-16 mx-auto"></div>
                    </td>
                    <td className="py-4 px-4 text-center">
                      <div className="h-8 bg-gray-200 dark:bg-slate-700 rounded-xl w-24 mx-auto"></div>
                    </td>
                  </tr>
                ))
              ) : modulList.length === 0 ? (
                <tr>
                  <td colSpan={8} className="py-12 text-center text-gray-400 dark:text-slate-500">
                    <div className="flex flex-col items-center justify-center gap-2">
                      <FaBookOpen className="w-10 h-10 text-gray-300 dark:text-slate-600 mb-1" />
                      <p className="font-bold text-gray-700 dark:text-slate-300 text-sm">
                        Belum Ada Data Master Modul Semester
                      </p>
                      <p className="text-xs">
                        Silakan buat modul semester baru dengan mengklik tombol &ldquo;➕ Tambah Modul&rdquo;.
                      </p>
                    </div>
                  </td>
                </tr>
              ) : (
                modulList.map((item, index) => {
                  const badgeStyle = getUnitBadgeStyle(item.unit_pendidikan?.code || item.jenjang)
                  const recordNo = ((meta.current_page || 1) - 1) * (meta.per_page || 15) + index + 1

                  return (
                    <tr
                      key={item.id}
                      className="hover:bg-emerald-50/40 dark:hover:bg-slate-800/50 transition-colors"
                    >
                      {/* NO */}
                      <td className="py-4 px-4 text-center font-bold text-gray-500 dark:text-slate-400 text-xs">
                        {recordNo}
                      </td>

                      {/* LOGO BADGE */}
                      <td className="py-4 px-4 text-center">
                        <div
                          className={`w-9 h-9 rounded-full ${badgeStyle.bg} ${badgeStyle.text} font-black text-xs flex items-center justify-center shadow-xs mx-auto border ${badgeStyle.border}`}
                        >
                          {(item.unit_pendidikan?.code || item.jenjang || 'SD').slice(0, 3)}
                        </div>
                      </td>

                      {/* NAMA MODUL SEMESTER */}
                      <td className="py-4 px-4">
                        <div className="font-bold text-gray-900 dark:text-white hover:text-emerald-700 transition">
                          {item.nama_modul}
                        </div>
                        <div className="text-xs text-emerald-600 dark:text-emerald-400 font-mono mt-0.5">
                          {item.kode_modul} &bull; <span className="text-gray-500 dark:text-slate-400">{item.kurikulum}</span>
                        </div>
                      </td>

                      {/* UNIT / KELAS */}
                      <td className="py-4 px-4">
                        <div className="font-semibold text-gray-800 dark:text-slate-200">
                          {item.unit_pendidikan?.name || item.jenjang || 'Semua Unit'}
                        </div>
                        <div className="text-xs text-gray-500 dark:text-slate-400">
                          Kelas {item.kelas?.nama_kelas || '-'} ({item.tahun_ajaran?.name || '-'})
                        </div>
                      </td>

                      {/* MAPEL & GURU */}
                      <td className="py-4 px-4">
                        <div className="font-bold text-gray-800 dark:text-slate-200">
                          {item.mata_pelajaran?.name || '-'}
                        </div>
                        <div className="text-xs text-gray-500 dark:text-slate-400 flex items-center gap-1 mt-0.5">
                          <FaChalkboardTeacher className="text-emerald-600" />
                          <span>{item.guru?.nama_lengkap || '-'}</span>
                        </div>
                      </td>

                      {/* PERTEMUAN / JP */}
                      <td className="py-4 px-4 text-center">
                        <span className="font-bold text-gray-900 dark:text-white">
                          {item.jumlah_pertemuan || 0} Pertemuan
                        </span>
                        <div className="text-xs text-gray-500 dark:text-slate-400">
                          {item.alokasi_jam || 0} JP
                        </div>
                      </td>

                      {/* STATUS (IDENTIK REFERENSI PILL BORDER) */}
                      <td className="py-4 px-4 text-center">
                        <span
                          className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold ${
                            item.status === 'Aktif'
                              ? 'bg-emerald-100 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800'
                              : item.status === 'Nonaktif'
                              ? 'bg-slate-100 text-slate-700 border border-slate-200 dark:bg-slate-800 dark:text-slate-300'
                              : 'bg-amber-100 text-amber-700 border border-amber-200 dark:bg-amber-950/60 dark:text-amber-300'
                          }`}
                        >
                          <span
                            className={`w-2 h-2 rounded-full ${
                              item.status === 'Aktif'
                                ? 'bg-emerald-500'
                                : item.status === 'Nonaktif'
                                ? 'bg-slate-400'
                                : 'bg-amber-500'
                            }`}
                          ></span>
                          {item.status}
                        </span>
                      </td>

                      {/* AKSI (SQUARE BUTTONS IDENTIK REFERENSI GAMBAR) */}
                      <td className="py-4 px-4 text-center">
                        <div className="inline-flex items-center justify-center gap-1.5">
                          <button
                            onClick={() => handleOpenDetailDrawer(item)}
                            title="Detail Modul"
                            className="p-2 rounded-xl bg-blue-50 text-blue-600 hover:bg-blue-100 dark:bg-blue-950/50 dark:text-blue-400 dark:hover:bg-blue-900 transition-all border border-blue-100 dark:border-blue-900"
                          >
                            <FaEye className="w-3.5 h-3.5" />
                          </button>
                          <button
                            onClick={() => handleOpenEditModal(item)}
                            title="Edit Modul"
                            className="p-2 rounded-xl bg-amber-50 text-amber-600 hover:bg-amber-100 dark:bg-amber-950/50 dark:text-amber-400 dark:hover:bg-amber-900 transition-all border border-amber-100 dark:border-amber-900"
                          >
                            <FaEdit className="w-3.5 h-3.5" />
                          </button>
                          <button
                            onClick={() => handleConfirmDuplikasi(item)}
                            title="Duplikasi Modul"
                            className="p-2 rounded-xl bg-purple-50 text-purple-600 hover:bg-purple-100 dark:bg-purple-950/50 dark:text-purple-400 dark:hover:bg-purple-900 transition-all border border-purple-100 dark:border-purple-900"
                          >
                            <FaCopy className="w-3.5 h-3.5" />
                          </button>
                          <button
                            onClick={() => handleConfirmHapus(item)}
                            title="Hapus Modul"
                            className="p-2 rounded-xl bg-rose-50 text-rose-600 hover:bg-rose-100 dark:bg-rose-950/50 dark:text-rose-400 dark:hover:bg-rose-900 transition-all border border-rose-100 dark:border-rose-900"
                          >
                            <FaTrash className="w-3.5 h-3.5" />
                          </button>
                        </div>
                      </td>
                    </tr>
                  )
                })
              )}
            </tbody>
          </table>
        </div>

        {/* PAGINATION FOOTER */}
        {meta.last_page > 1 && (
          <div className="px-6 py-4 bg-gray-50/80 dark:bg-slate-900/80 border-t border-gray-200 dark:border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs">
            <p className="text-gray-500 dark:text-slate-400 font-medium">
              Menampilkan <span className="font-bold text-gray-900 dark:text-white">{meta.from || 0}</span> sampai{' '}
              <span className="font-bold text-gray-900 dark:text-white">{meta.to || 0}</span> dari{' '}
              <span className="font-bold text-gray-900 dark:text-white">{meta.total || 0}</span> modul
            </p>
            <div className="flex items-center gap-2">
              <button
                disabled={page <= 1}
                onClick={() => setPage((p) => Math.max(p - 1, 1))}
                className="px-3.5 py-2 border border-gray-300 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-800 font-bold text-gray-700 dark:text-slate-200 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-gray-100 dark:hover:bg-slate-700 transition flex items-center gap-1"
              >
                <FaChevronLeft className="w-3 h-3" /> Sebelumnya
              </button>
              <span className="px-4 py-2 bg-emerald-50 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 font-bold rounded-xl border border-emerald-200 dark:border-emerald-800">
                {meta.current_page} / {meta.last_page}
              </span>
              <button
                disabled={page >= meta.last_page}
                onClick={() => setPage((p) => p + 1)}
                className="px-3.5 py-2 border border-gray-300 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-800 font-bold text-gray-700 dark:text-slate-200 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-gray-100 dark:hover:bg-slate-700 transition flex items-center gap-1"
              >
                Selanjutnya <FaChevronRight className="w-3 h-3" />
              </button>
            </div>
          </div>
        )}
      </div>

      {/* CRUD MODAL FORM */}
      {isModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto">
          <div className="relative w-full max-w-4xl bg-white dark:bg-[#1B2433] rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 overflow-hidden my-8 flex flex-col max-h-[90vh]">
            {/* Modal Header */}
            <div className="px-6 py-4 bg-gradient-to-r from-emerald-900 to-emerald-800 text-white flex items-center justify-between">
              <div>
                <h3 className="text-lg font-bold flex items-center gap-2">
                  <FaBookOpen className="text-emerald-300" />
                  {isEditMode ? 'Edit Master Modul Semester' : 'Tambah Master Modul Semester'}
                </h3>
                <p className="text-xs text-emerald-100/90">
                  Isi formulir lengkap sesuai standar kurikulum dan bobot penilaian semester.
                </p>
              </div>
              <button
                onClick={() => setIsModalOpen(false)}
                className="p-2 text-white/80 hover:text-white rounded-lg transition"
              >
                <FaTimes />
              </button>
            </div>

            {/* Navigation Tabs */}
            <div className="flex border-b border-slate-200 dark:border-slate-800 bg-slate-100/80 dark:bg-slate-900/50 px-6 gap-2 text-xs font-bold overflow-x-auto">
              {[
                { key: 'umum', label: '1. Informasi Umum' },
                { key: 'pembelajaran', label: '2. Pembelajaran' },
                { key: 'target', label: '3. Target & Pengaturan' },
                { key: 'materi', label: '4. Detail Materi' },
                { key: 'bobot', label: '5. Bobot Penilaian' },
              ].map((tab) => (
                <button
                  key={tab.key}
                  onClick={() => setActiveTab(tab.key)}
                  className={`py-3 px-3 border-b-2 transition whitespace-nowrap ${
                    activeTab === tab.key
                      ? 'border-emerald-600 text-emerald-700 dark:text-emerald-400 font-extrabold'
                      : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
                  }`}
                >
                  {tab.label}
                </button>
              ))}
            </div>

            {/* Modal Body */}
            <form onSubmit={handleSubmitForm} className="p-6 overflow-y-auto flex-1 text-xs">
              {/* TAB 1: INFORMASI UMUM */}
              {activeTab === 'umum' && (
                <div className="space-y-4">
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-slate-700 dark:text-slate-300 font-bold mb-1">
                        Tahun Ajaran <span className="text-rose-500">*</span>
                      </label>
                      <select
                        value={formData.tahun_ajaran_id}
                        onChange={(e) => setFormData({ ...formData, tahun_ajaran_id: e.target.value })}
                        className="w-full p-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white font-medium"
                      >
                        <option value="">-- Pilih Tahun Ajaran --</option>
                        {options.tahun_ajaran?.map((t) => (
                          <option key={t.id} value={t.id}>
                            {t.name}
                          </option>
                        ))}
                      </select>
                      {formErrors.tahun_ajaran_id && (
                        <p className="text-[11px] text-rose-500 mt-1">{formErrors.tahun_ajaran_id[0]}</p>
                      )}
                    </div>

                    <div>
                      <label className="block text-slate-700 dark:text-slate-300 font-bold mb-1">
                        Unit Pendidikan <span className="text-rose-500">*</span>
                      </label>
                      <select
                        value={formData.unit_pendidikan_id}
                        onChange={(e) => setFormData({ ...formData, unit_pendidikan_id: e.target.value })}
                        className="w-full p-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white font-medium"
                      >
                        <option value="">-- Pilih Unit Pendidikan --</option>
                        {options.unit_pendidikan?.map((u) => (
                          <option key={u.id} value={u.id}>
                            {u.name} ({u.code})
                          </option>
                        ))}
                      </select>
                    </div>
                  </div>

                  <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                      <label className="block text-slate-700 dark:text-slate-300 font-bold mb-1">
                        Semester <span className="text-rose-500">*</span>
                      </label>
                      <select
                        value={formData.semester_id}
                        onChange={(e) => setFormData({ ...formData, semester_id: e.target.value })}
                        className="w-full p-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white font-medium"
                      >
                        <option value="">-- Pilih Semester --</option>
                        {filteredSemesterList.map((s) => (
                          <option key={s.id} value={s.id}>
                            {s.name}
                          </option>
                        ))}
                      </select>
                      {formErrors.semester_id && (
                        <p className="text-[11px] text-rose-500 mt-1">{formErrors.semester_id[0]}</p>
                      )}
                    </div>

                    <div>
                      <label className="block text-slate-700 dark:text-slate-300 font-bold mb-1">
                        Kelas / Rombel <span className="text-rose-500">*</span>
                      </label>
                      <select
                        value={formData.kelas_id}
                        onChange={(e) => setFormData({ ...formData, kelas_id: e.target.value })}
                        className="w-full p-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white font-medium"
                      >
                        <option value="">-- Pilih Kelas --</option>
                        {filteredKelasList.map((k) => (
                          <option key={k.id} value={k.id}>
                            {k.nama_kelas} ({k.kode_kelas})
                          </option>
                        ))}
                      </select>
                      {formErrors.kelas_id && (
                        <p className="text-[11px] text-rose-500 mt-1">{formErrors.kelas_id[0]}</p>
                      )}
                    </div>

                    <div>
                      <label className="block text-slate-700 dark:text-slate-300 font-bold mb-1">
                        Mata Pelajaran <span className="text-rose-500">*</span>
                      </label>
                      <select
                        value={formData.mata_pelajaran_id}
                        onChange={(e) => setFormData({ ...formData, mata_pelajaran_id: e.target.value })}
                        className="w-full p-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white font-medium"
                      >
                        <option value="">-- Pilih Mata Pelajaran --</option>
                        {options.mata_pelajaran?.map((m) => (
                          <option key={m.id} value={m.id}>
                            {m.name} ({m.code})
                          </option>
                        ))}
                      </select>
                      {formErrors.mata_pelajaran_id && (
                        <p className="text-[11px] text-rose-500 mt-1">{formErrors.mata_pelajaran_id[0]}</p>
                      )}
                    </div>
                  </div>

                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-slate-700 dark:text-slate-300 font-bold mb-1">
                        Guru Pengampu <span className="text-rose-500">*</span>
                      </label>
                      <select
                        value={formData.guru_id}
                        onChange={(e) => setFormData({ ...formData, guru_id: e.target.value })}
                        className="w-full p-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white font-medium"
                      >
                        <option value="">-- Pilih Guru Pengampu --</option>
                        {options.guru?.map((g) => (
                          <option key={g.id} value={g.id}>
                            {g.nama_lengkap} (NIY: {g.niy || '-'})
                          </option>
                        ))}
                      </select>
                      {formErrors.guru_id && (
                        <p className="text-[11px] text-rose-500 mt-1">{formErrors.guru_id[0]}</p>
                      )}
                    </div>

                    <div>
                      <label className="block text-slate-700 dark:text-slate-300 font-bold mb-1">
                        Kurikulum
                      </label>
                      <select
                        value={formData.kurikulum}
                        onChange={(e) => setFormData({ ...formData, kurikulum: e.target.value })}
                        className="w-full p-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white font-medium"
                      >
                        {options.kurikulum?.map((k, idx) => (
                          <option key={idx} value={k}>
                            {k}
                          </option>
                        ))}
                      </select>
                    </div>
                  </div>

                  <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div className="sm:col-span-2">
                      <label className="block text-slate-700 dark:text-slate-300 font-bold mb-1">
                        Kode Modul (Auto Generate)
                      </label>
                      <div className="flex gap-2">
                        <input
                          type="text"
                          placeholder="Kode unik modul semester"
                          value={formData.kode_modul}
                          onChange={(e) => setFormData({ ...formData, kode_modul: e.target.value })}
                          className="w-full p-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white font-mono font-bold"
                        />
                        <button
                          type="button"
                          onClick={handleGenerateKode}
                          className="px-3 py-2 bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold rounded-xl hover:bg-slate-300 transition"
                        >
                          Generate
                        </button>
                      </div>
                    </div>

                    <div>
                      <label className="block text-slate-700 dark:text-slate-300 font-bold mb-1">
                        Status Modul
                      </label>
                      <select
                        value={formData.status}
                        onChange={(e) => setFormData({ ...formData, status: e.target.value })}
                        className="w-full p-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white font-medium"
                      >
                        <option value="Aktif">Aktif</option>
                        <option value="Nonaktif">Nonaktif</option>
                        <option value="Arsip">Arsip</option>
                      </select>
                    </div>
                  </div>

                  <div>
                    <label className="block text-slate-700 dark:text-slate-300 font-bold mb-1">
                      Nama Modul Semester <span className="text-rose-500">*</span>
                    </label>
                    <input
                      type="text"
                      placeholder="Contoh: Modul Semester 1 Matematika Terpadu Kelas VII"
                      value={formData.nama_modul}
                      onChange={(e) => setFormData({ ...formData, nama_modul: e.target.value })}
                      className="w-full p-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white font-bold"
                    />
                    {formErrors.nama_modul && (
                      <p className="text-[11px] text-rose-500 mt-1">{formErrors.nama_modul[0]}</p>
                    )}
                  </div>
                </div>
              )}

              {/* TAB 2: PEMBELAJARAN */}
              {activeTab === 'pembelajaran' && (
                <div className="space-y-4">
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-slate-700 dark:text-slate-300 font-bold mb-1">
                        ATP (Alur Tujuan Pembelajaran)
                      </label>
                      <textarea
                        rows={3}
                        placeholder="Tuliskan alur tujuan pembelajaran..."
                        value={formData.atp}
                        onChange={(e) => setFormData({ ...formData, atp: e.target.value })}
                        className="w-full p-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white"
                      ></textarea>
                    </div>

                    <div>
                      <label className="block text-slate-700 dark:text-slate-300 font-bold mb-1">
                        CP (Capaian Pembelajaran)
                      </label>
                      <textarea
                        rows={3}
                        placeholder="Tuliskan capaian pembelajaran semester..."
                        value={formData.cp}
                        onChange={(e) => setFormData({ ...formData, cp: e.target.value })}
                        className="w-full p-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white"
                      ></textarea>
                    </div>
                  </div>

                  <div>
                    <label className="block text-slate-700 dark:text-slate-300 font-bold mb-1">
                      Tujuan Pembelajaran Spesifik
                    </label>
                    <textarea
                      rows={2}
                      placeholder="Detail tujuan spesifik yang ingin dicapai..."
                      value={formData.tujuan_pembelajaran}
                      onChange={(e) => setFormData({ ...formData, tujuan_pembelajaran: e.target.value })}
                      className="w-full p-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white"
                    ></textarea>
                  </div>

                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-slate-700 dark:text-slate-300 font-bold mb-1">
                        Alokasi Jam Pelajaran (JP)
                      </label>
                      <input
                        type="number"
                        min={1}
                        value={formData.alokasi_jam}
                        onChange={(e) => setFormData({ ...formData, alokasi_jam: parseInt(e.target.value) || 0 })}
                        className="w-full p-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white"
                      />
                    </div>

                    <div>
                      <label className="block text-slate-700 dark:text-slate-300 font-bold mb-1">
                        Jumlah Pertemuan
                      </label>
                      <input
                        type="number"
                        min={1}
                        value={formData.jumlah_pertemuan}
                        onChange={(e) => setFormData({ ...formData, jumlah_pertemuan: parseInt(e.target.value) || 0 })}
                        className="w-full p-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white"
                      />
                    </div>
                  </div>

                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-slate-700 dark:text-slate-300 font-bold mb-1">
                        Metode Pembelajaran
                      </label>
                      <input
                        type="text"
                        placeholder="Contoh: Problem Based Learning, Ceramah, Diskusi"
                        value={formData.metode_pembelajaran}
                        onChange={(e) => setFormData({ ...formData, metode_pembelajaran: e.target.value })}
                        className="w-full p-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white"
                      />
                    </div>

                    <div>
                      <label className="block text-slate-700 dark:text-slate-300 font-bold mb-1">
                        Model Pembelajaran
                      </label>
                      <select
                        value={formData.model_pembelajaran}
                        onChange={(e) => setFormData({ ...formData, model_pembelajaran: e.target.value })}
                        className="w-full p-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white"
                      >
                        {options.model_pembelajaran?.map((m, idx) => (
                          <option key={idx} value={m}>
                            {m}
                          </option>
                        ))}
                      </select>
                    </div>
                  </div>
                </div>
              )}

              {/* TAB 3: TARGET & PENGATURAN */}
              {activeTab === 'target' && (
                <div className="space-y-4">
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-slate-700 dark:text-slate-300 font-bold mb-1">
                        Target Nilai Minimum (KKM)
                      </label>
                      <input
                        type="number"
                        min={0}
                        max={100}
                        value={formData.target_nilai_minimum}
                        onChange={(e) =>
                          setFormData({ ...formData, target_nilai_minimum: parseFloat(e.target.value) || 0 })
                        }
                        className="w-full p-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white"
                      />
                    </div>

                    <div>
                      <label className="block text-slate-700 dark:text-slate-300 font-bold mb-1">
                        Target Kehadiran (%)
                      </label>
                      <input
                        type="number"
                        min={0}
                        max={100}
                        value={formData.target_kehadiran}
                        onChange={(e) =>
                          setFormData({ ...formData, target_kehadiran: parseFloat(e.target.value) || 0 })
                        }
                        className="w-full p-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white"
                      />
                    </div>
                  </div>

                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-slate-700 dark:text-slate-300 font-bold mb-1">
                        Target Hafalan (Integrasi Tahfizh)
                      </label>
                      <textarea
                        rows={2}
                        placeholder="Contoh: Surah Al-Mulk ayat 1-30..."
                        value={formData.target_hafalan}
                        onChange={(e) => setFormData({ ...formData, target_hafalan: e.target.value })}
                        className="w-full p-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white"
                      ></textarea>
                    </div>

                    <div>
                      <label className="block text-slate-700 dark:text-slate-300 font-bold mb-1">
                        Target Proyek Pembelajaran
                      </label>
                      <textarea
                        rows={2}
                        placeholder="Contoh: Pembuatan Miniatur Rumah Ramah Lingkungan..."
                        value={formData.target_proyek}
                        onChange={(e) => setFormData({ ...formData, target_proyek: e.target.value })}
                        className="w-full p-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white"
                      ></textarea>
                    </div>
                  </div>

                  <div className="border-t border-slate-200 dark:border-slate-800 pt-4">
                    <h4 className="font-bold text-slate-900 dark:text-white mb-3">Pengaturan Akses Portal</h4>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                      <div>
                        <label className="block text-slate-700 dark:text-slate-300 font-bold mb-1">
                          Berlaku Mulai
                        </label>
                        <input
                          type="date"
                          value={formData.berlaku_mulai}
                          onChange={(e) => setFormData({ ...formData, berlaku_mulai: e.target.value })}
                          className="w-full p-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white"
                        />
                      </div>

                      <div>
                        <label className="block text-slate-700 dark:text-slate-300 font-bold mb-1">
                          Berlaku Sampai
                        </label>
                        <input
                          type="date"
                          value={formData.berlaku_sampai}
                          onChange={(e) => setFormData({ ...formData, berlaku_sampai: e.target.value })}
                          className="w-full p-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white"
                        />
                      </div>
                    </div>

                    <div className="mt-4 space-y-2 font-medium">
                      <label className="flex items-center gap-2 cursor-pointer text-slate-800 dark:text-slate-200">
                        <input
                          type="checkbox"
                          checked={formData.ditampilkan_di_portal_ortu}
                          onChange={(e) => setFormData({ ...formData, ditampilkan_di_portal_ortu: e.target.checked })}
                          className="w-4 h-4 rounded text-emerald-600 focus:ring-emerald-500"
                        />
                        <span>Ditampilkan di Portal Orang Tua</span>
                      </label>

                      <label className="flex items-center gap-2 cursor-pointer text-slate-800 dark:text-slate-200">
                        <input
                          type="checkbox"
                          checked={formData.ditampilkan_di_aplikasi_siswa}
                          onChange={(e) => setFormData({ ...formData, ditampilkan_di_aplikasi_siswa: e.target.checked })}
                          className="w-4 h-4 rounded text-emerald-600 focus:ring-emerald-500"
                        />
                        <span>Ditampilkan di Aplikasi Siswa</span>
                      </label>
                    </div>
                  </div>
                </div>
              )}

              {/* TAB 4: DETAIL MATERI */}
              {activeTab === 'materi' && (
                <div className="space-y-4">
                  <div className="flex items-center justify-between">
                    <div>
                      <h4 className="font-bold text-slate-900 dark:text-white">Detail Rincian Materi Per Minggu</h4>
                      <p className="text-[11px] text-slate-500">Tentukan susunan materi, JP, ATP, dan CP mingguan.</p>
                    </div>
                    <button
                      type="button"
                      onClick={handleTambahDetailMateri}
                      className="px-3 py-1.5 bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 rounded-xl hover:bg-emerald-200 transition font-bold flex items-center gap-1.5"
                    >
                      <FaPlus /> Tambah Minggu
                    </button>
                  </div>

                  <div className="overflow-x-auto border border-slate-200 dark:border-slate-800 rounded-xl">
                    <table className="w-full text-left text-xs border-collapse">
                      <thead>
                        <tr className="bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold border-b border-slate-200 dark:border-slate-800">
                          <th className="p-2.5 w-16 text-center">Minggu</th>
                          <th className="p-2.5">Materi Pembelajaran</th>
                          <th className="p-2.5">ATP / Catatan</th>
                          <th className="p-2.5 w-20 text-center">JP</th>
                          <th className="p-2.5">Keterangan</th>
                          <th className="p-2.5 w-12 text-center">Aksi</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                        {formData.details?.map((detail, idx) => (
                          <tr key={idx}>
                            <td className="p-2 text-center">
                              <input
                                type="number"
                                min={1}
                                value={detail.minggu}
                                onChange={(e) => handleUbahDetailMateri(idx, 'minggu', parseInt(e.target.value) || 1)}
                                className="w-12 text-center p-1 rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 font-bold"
                              />
                            </td>
                            <td className="p-2">
                              <input
                                type="text"
                                placeholder="Nama Materi minggu ini..."
                                value={detail.materi}
                                onChange={(e) => handleUbahDetailMateri(idx, 'materi', e.target.value)}
                                className="w-full p-1.5 rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 font-medium"
                              />
                            </td>
                            <td className="p-2">
                              <input
                                type="text"
                                placeholder="Target ATP minggu ini..."
                                value={detail.atp}
                                onChange={(e) => handleUbahDetailMateri(idx, 'atp', e.target.value)}
                                className="w-full p-1.5 rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900"
                              />
                            </td>
                            <td className="p-2 text-center">
                              <input
                                type="number"
                                min={1}
                                value={detail.jp}
                                onChange={(e) => handleUbahDetailMateri(idx, 'jp', parseInt(e.target.value) || 2)}
                                className="w-14 text-center p-1 rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 font-bold"
                              />
                            </td>
                            <td className="p-2">
                              <input
                                type="text"
                                placeholder="Ket / Kuis / Tugas"
                                value={detail.keterangan}
                                onChange={(e) => handleUbahDetailMateri(idx, 'keterangan', e.target.value)}
                                className="w-full p-1.5 rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900"
                              />
                            </td>
                            <td className="p-2 text-center">
                              <button
                                type="button"
                                onClick={() => handleHapusDetailMateri(idx)}
                                className="p-1.5 text-rose-500 hover:bg-rose-100 dark:hover:bg-rose-950 rounded transition"
                              >
                                <FaTrash />
                              </button>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </div>
              )}

              {/* TAB 5: BOBOT PENILAIAN */}
              {activeTab === 'bobot' && (
                <div className="space-y-4">
                  <div className="p-4 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-200 dark:border-slate-800 flex items-center justify-between">
                    <div>
                      <h4 className="font-bold text-slate-900 dark:text-white">Pengaturan Bobot Penilaian (%)</h4>
                      <p className="text-[11px] text-slate-500">Total akumulasi seluruh komponen bobot penilaian WAJIB 100%.</p>
                    </div>
                    <div className="text-right">
                      <p className="text-[10px] uppercase font-bold text-slate-500">Total Bobot saat ini</p>
                      <span
                        className={`text-xl font-black ${
                          totalBobotPenilaian === 100
                            ? 'text-emerald-600 dark:text-emerald-400'
                            : 'text-rose-600 dark:text-rose-400 animate-pulse'
                        }`}
                      >
                        {totalBobotPenilaian}%
                      </span>
                    </div>
                  </div>

                  {totalBobotPenilaian !== 100 && (
                    <div className="p-3 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/60 rounded-xl text-rose-700 dark:text-rose-300 flex items-center gap-2 text-xs">
                      <FaExclamationTriangle className="text-rose-500 shrink-0" />
                      <span>
                        Peringatan: Total bobot penilaian saat ini adalah <b>{totalBobotPenilaian}%</b>. Sesuaikan angka di bawah hingga total pas 100%.
                      </span>
                    </div>
                  )}

                  <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                      <label className="block text-slate-700 dark:text-slate-300 font-bold mb-1">
                        Bobot Tugas (%)
                      </label>
                      <input
                        type="number"
                        min={0}
                        max={100}
                        value={formData.bobot_tugas}
                        onChange={(e) => setFormData({ ...formData, bobot_tugas: parseFloat(e.target.value) || 0 })}
                        className="w-full p-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white font-bold"
                      />
                    </div>

                    <div>
                      <label className="block text-slate-700 dark:text-slate-300 font-bold mb-1">
                        Bobot Kuis (%)
                      </label>
                      <input
                        type="number"
                        min={0}
                        max={100}
                        value={formData.bobot_quiz}
                        onChange={(e) => setFormData({ ...formData, bobot_quiz: parseFloat(e.target.value) || 0 })}
                        className="w-full p-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white font-bold"
                      />
                    </div>

                    <div>
                      <label className="block text-slate-700 dark:text-slate-300 font-bold mb-1">
                        Bobot Projek (%)
                      </label>
                      <input
                        type="number"
                        min={0}
                        max={100}
                        value={formData.bobot_projek}
                        onChange={(e) => setFormData({ ...formData, bobot_projek: parseFloat(e.target.value) || 0 })}
                        className="w-full p-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white font-bold"
                      />
                    </div>

                    <div>
                      <label className="block text-slate-700 dark:text-slate-300 font-bold mb-1">
                        Bobot UTS (%)
                      </label>
                      <input
                        type="number"
                        min={0}
                        max={100}
                        value={formData.bobot_uts}
                        onChange={(e) => setFormData({ ...formData, bobot_uts: parseFloat(e.target.value) || 0 })}
                        className="w-full p-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white font-bold"
                      />
                    </div>

                    <div>
                      <label className="block text-slate-700 dark:text-slate-300 font-bold mb-1">
                        Bobot UAS (%)
                      </label>
                      <input
                        type="number"
                        min={0}
                        max={100}
                        value={formData.bobot_uas}
                        onChange={(e) => setFormData({ ...formData, bobot_uas: parseFloat(e.target.value) || 0 })}
                        className="w-full p-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white font-bold"
                      />
                    </div>
                  </div>
                </div>
              )}

              {/* Modal Buttons */}
              <div className="mt-6 pt-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between">
                <button
                  type="button"
                  onClick={() => setIsModalOpen(false)}
                  className="px-4 py-2.5 text-xs font-bold text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 rounded-xl transition"
                >
                  Batal
                </button>

                <div className="flex items-center gap-2">
                  {activeTab !== 'umum' && (
                    <button
                      type="button"
                      onClick={() => {
                        const tabs = ['umum', 'pembelajaran', 'target', 'materi', 'bobot']
                        const currIdx = tabs.indexOf(activeTab)
                        if (currIdx > 0) setActiveTab(tabs[currIdx - 1])
                      }}
                      className="px-4 py-2.5 text-xs font-bold text-slate-700 dark:text-slate-300 bg-slate-200 dark:bg-slate-800 rounded-xl hover:bg-slate-300 transition"
                    >
                      Kembali
                    </button>
                  )}

                  {activeTab !== 'bobot' ? (
                    <button
                      type="button"
                      onClick={() => {
                        const tabs = ['umum', 'pembelajaran', 'target', 'materi', 'bobot']
                        const currIdx = tabs.indexOf(activeTab)
                        if (currIdx < tabs.length - 1) setActiveTab(tabs[currIdx + 1])
                      }}
                      className="px-4 py-2.5 text-xs font-bold text-white bg-slate-800 dark:bg-slate-700 hover:bg-slate-900 rounded-xl transition"
                    >
                      Lanjut &rarr;
                    </button>
                  ) : (
                    <button
                      type="submit"
                      disabled={simpanMutation.isPending || totalBobotPenilaian !== 100}
                      className="px-5 py-2.5 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 disabled:cursor-not-allowed rounded-xl shadow-md transition flex items-center gap-2"
                    >
                      <FaSave />
                      {simpanMutation.isPending ? 'Memproses...' : 'Simpan Modul'}
                    </button>
                  )}
                </div>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* DETAIL DRAWER */}
      {isDrawerOpen && detailModul && (
        <div className="fixed inset-0 z-50 flex justify-end bg-slate-900/50 backdrop-blur-xs">
          <div className="w-full max-w-2xl bg-white dark:bg-[#1B2433] h-full shadow-2xl overflow-y-auto border-l border-slate-200 dark:border-slate-800 p-6 flex flex-col justify-between">
            <div>
              <div className="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-800 mb-6">
                <div className="flex items-center gap-2.5">
                  <FaBookOpen className="text-emerald-600 text-xl" />
                  <div>
                    <h3 className="text-lg font-extrabold text-slate-900 dark:text-white">{detailModul.nama_modul}</h3>
                    <p className="text-xs text-emerald-600 dark:text-emerald-400 font-mono">
                      {detailModul.kode_modul} &bull; {detailModul.kurikulum}
                    </p>
                  </div>
                </div>
                <button
                  onClick={() => setIsDrawerOpen(false)}
                  className="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-lg transition"
                >
                  <FaTimes />
                </button>
              </div>

              <div className="grid grid-cols-2 gap-4 bg-slate-50 dark:bg-slate-900/60 p-4 rounded-xl mb-6 text-xs">
                <div>
                  <p className="text-slate-400 font-medium">Mata Pelajaran</p>
                  <p className="font-bold text-slate-900 dark:text-white">{detailModul.mata_pelajaran?.name || '-'}</p>
                </div>
                <div>
                  <p className="text-slate-400 font-medium">Guru Pengampu</p>
                  <p className="font-bold text-slate-900 dark:text-white">{detailModul.guru?.nama_lengkap || '-'}</p>
                </div>
                <div>
                  <p className="text-slate-400 font-medium">Jumlah Pertemuan / Jam</p>
                  <p className="font-bold text-slate-900 dark:text-white">{detailModul.jumlah_pertemuan} Pertemuan ({detailModul.alokasi_jam} JP)</p>
                </div>
                <div>
                  <p className="text-slate-400 font-medium">Target KKM / Kehadiran</p>
                  <p className="font-bold text-slate-900 dark:text-white">KKM: {detailModul.target_nilai_minimum} &bull; Kehadiran: {detailModul.target_kehadiran}%</p>
                </div>
              </div>

              {/* Rincian Materi Table */}
              <div className="mb-6">
                <h4 className="font-bold text-slate-900 dark:text-white text-xs uppercase mb-2">Rincian Materi Mingguan ({detailModul.details?.length || 0})</h4>
                <div className="overflow-x-auto border border-slate-200 dark:border-slate-800 rounded-xl text-xs">
                  <table className="w-full text-left border-collapse">
                    <thead>
                      <tr className="bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold">
                        <th className="p-2 text-center">Minggu</th>
                        <th className="p-2">Materi</th>
                        <th className="p-2 text-center">JP</th>
                        <th className="p-2">Ket</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                      {detailModul.details?.map((dt, idx) => (
                        <tr key={idx}>
                          <td className="p-2 text-center font-bold">{dt.minggu}</td>
                          <td className="p-2 font-medium">{dt.materi}</td>
                          <td className="p-2 text-center">{dt.jp}</td>
                          <td className="p-2 text-slate-500">{dt.keterangan || '-'}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            <div className="pt-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs">
              <button
                onClick={() => setIsDrawerOpen(false)}
                className="px-4 py-2.5 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold rounded-xl"
              >
                Tutup Drawer
              </button>
              <button
                onClick={() => {
                  setIsDrawerOpen(false)
                  handleOpenEditModal(detailModul)
                }}
                className="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl flex items-center gap-1.5 shadow"
              >
                <FaEdit /> Edit Modul Ini
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
