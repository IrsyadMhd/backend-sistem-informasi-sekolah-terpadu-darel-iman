import { useMemo, useState } from 'react'
import Swal from 'sweetalert2'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  FaArrowLeft,
  FaBuilding,
  FaCalendarAlt,
  FaChalkboardTeacher,
  FaCheckCircle,
  FaDownload,
  FaEdit,
  FaExclamationTriangle,
  FaEye,
  FaFileExcel,
  FaFileImport,
  FaFilter,
  FaPlus,
  FaRedo,
  FaSchool,
  FaSearch,
  FaTimes,
  FaTrash,
  FaUserGraduate,
  FaUsers,
} from 'react-icons/fa'
import { kelasService } from '../services/kelasService'

/**
 * Styling Badges Warna Unit / Jenjang Sekolah (Persis Unit Pendidikan)
 */
const UNIT_COLORS = {
  TKIT: { bg: 'bg-emerald-800', text: 'text-white', border: 'border-emerald-700' },
  TAUD: { bg: 'bg-emerald-700', text: 'text-white', border: 'border-emerald-600' },
  SDIT: { bg: 'bg-blue-600', text: 'text-white', border: 'border-blue-500' },
  MIT: { bg: 'bg-amber-500', text: 'text-white', border: 'border-amber-400' },
  SMPIT: { bg: 'bg-cyan-600', text: 'text-white', border: 'border-cyan-500' },
  SMAIT: { bg: 'bg-purple-600', text: 'text-white', border: 'border-purple-500' },
  MA: { bg: 'bg-purple-700', text: 'text-white', border: 'border-purple-600' },
  PONPES: { bg: 'bg-emerald-900', text: 'text-white', border: 'border-emerald-800' },
  Mahad: { bg: 'bg-amber-800', text: 'text-white', border: 'border-amber-700' },
}

function getUnitBadgeStyle(type) {
  return (
    UNIT_COLORS[type] || {
      bg: 'bg-slate-700',
      text: 'text-white',
      border: 'border-slate-600',
    }
  )
}

/**
 * Form Initial State
 */
function initialFormState() {
  return {
    id: null,
    unit_pendidikan_id: '',
    tahun_ajaran_id: '',
    semester_id: '',
    jenjang: 'SDIT',
    tingkat: '1',
    kode_kelas: '',
    nama_kelas: '',
    wali_kelas_id: '',
    kapasitas: 30,
    ruangan: '',
    status: 'Aktif',
  }
}

export default function MasterKelasPage() {
  const queryClient = useQueryClient()

  // State Filter & Search
  const [search, setSearch] = useState('')
  const [selectedUnitFilter, setSelectedUnitFilter] = useState('')
  const [selectedTahunFilter, setSelectedTahunFilter] = useState('')
  const [selectedSemesterFilter, setSelectedSemesterFilter] = useState('')
  const [selectedJenjangFilter, setSelectedJenjangFilter] = useState('')
  const [selectedStatusFilter, setSelectedStatusFilter] = useState('')
  const [page, setPage] = useState(1)

  // Modal Form State (Admin Flow Wizard 4 Step - Vertikal Stepper Persis Pop-up Gambar)
  const [isFormModalOpen, setIsFormModalOpen] = useState(false)
  const [isEditMode, setIsEditMode] = useState(false)
  const [currentStep, setCurrentStep] = useState(1)
  const [formData, setFormData] = useState(initialFormState())
  const [formErrors, setFormErrors] = useState({})

  // Modal Detail & Modals State
  const [detailKelas, setDetailKelas] = useState(null)
  const [siswaModalData, setSiswaModalData] = useState(null)
  const [showImportModal, setShowImportModal] = useState(false)
  const [importFile, setImportFile] = useState(null)
  const [importPreview, setImportPreview] = useState([])
  const [isImporting, setIsImporting] = useState(false)

  // Query Master Options (Unit, Tahun Ajaran, Semester, Pegawai)
  const { data: optionsData } = useQuery({
    queryKey: ['kelas-options'],
    queryFn: () => kelasService.getOptions(),
  })

  const masterUnits = optionsData?.units || []
  const masterTahunAjaran = optionsData?.tahun_ajaran || []
  const masterSemesters = optionsData?.semesters || []
  const masterEmployees = optionsData?.employees || optionsData?.guru || []
  const masterJenjang = optionsData?.jenjang || ['TKIT', 'SDIT', 'SMPIT', 'SMAIT', 'MIT', 'MA']
  const masterTingkat = optionsData?.tingkat || ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12']

  // Filter semester berdasarkan tahun ajaran yang dipilih pada form
  const availableSemestersForm = useMemo(() => {
    if (!formData.tahun_ajaran_id) return masterSemesters
    return masterSemesters.filter((s) => s.academic_year_id === formData.tahun_ajaran_id)
  }, [masterSemesters, formData.tahun_ajaran_id])

  // Filter pegawai/guru berdasarkan unit jika ada
  const filteredEmployeesForm = useMemo(() => {
    if (!formData.unit_pendidikan_id) return masterEmployees
    return masterEmployees.filter((e) => !e.unit_id || e.unit_id === formData.unit_pendidikan_id)
  }, [masterEmployees, formData.unit_pendidikan_id])

  // Query Fetch Daftar Kelas
  const { data: classData, isLoading } = useQuery({
    queryKey: [
      'kelas-list',
      page,
      search,
      selectedUnitFilter,
      selectedTahunFilter,
      selectedSemesterFilter,
      selectedJenjangFilter,
      selectedStatusFilter,
    ],
    queryFn: () =>
      kelasService.getDaftar({
        page,
        per_page: 10,
        search: search || undefined,
        unit_pendidikan_id: selectedUnitFilter || undefined,
        tahun_ajaran_id: selectedTahunFilter || undefined,
        semester_id: selectedSemesterFilter || undefined,
        jenjang: selectedJenjangFilter || undefined,
        status: selectedStatusFilter || undefined,
      }),
  })

  const rawList = classData?.data || []
  const stats = classData?.statistik || {
    total_kelas: rawList.length,
    total_aktif: rawList.filter((r) => r.status === 'Aktif').length,
    wali_terisi: rawList.filter((r) => r.wali_kelas_id).length,
    total_kapasitas: rawList.reduce((a, b) => a + (b.kapasitas || 0), 0),
  }

  const paginationInfo = {
    total: classData?.meta?.total || rawList.length,
    from: classData?.meta?.from || (rawList.length > 0 ? 1 : 0),
    to: classData?.meta?.to || rawList.length,
    last_page: classData?.meta?.last_page || 1,
    current_page: classData?.meta?.current_page || 1,
  }

  // Mutasi CRUD
  const createMutation = useMutation({
    mutationFn: (payload) => kelasService.tambah(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['kelas-list'] })
      queryClient.invalidateQueries({ queryKey: ['kelas-options'] })
      Swal.fire({
        title: 'Berhasil!',
        text: 'Data kelas/rombel baru berhasil ditambahkan.',
        icon: 'success',
        confirmColor: '#065F46',
      })
      closeFormModal()
    },
    onError: (err) => {
      const respErrors = err?.response?.data?.errors || {}
      setFormErrors(respErrors)
      const msg = err?.response?.data?.message || 'Terjadi kesalahan saat menyimpan data kelas.'
      Swal.fire('Gagal!', msg, 'error')
    },
  })

  const updateMutation = useMutation({
    mutationFn: ({ id, payload }) => kelasService.ubah({ id, payload }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['kelas-list'] })
      Swal.fire({
        title: 'Berhasil!',
        text: 'Data kelas/rombel berhasil diperbarui.',
        icon: 'success',
        confirmColor: '#065F46',
      })
      closeFormModal()
    },
    onError: (err) => {
      const respErrors = err?.response?.data?.errors || {}
      setFormErrors(respErrors)
      const msg = err?.response?.data?.message || 'Terjadi kesalahan saat memperbarui data.'
      Swal.fire('Gagal!', msg, 'error')
    },
  })

  const deleteMutation = useMutation({
    mutationFn: (id) => kelasService.hapus(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['kelas-list'] })
      Swal.fire({
        title: 'Terhapus!',
        text: 'Data kelas berhasil dihapus (soft delete).',
        icon: 'success',
        confirmColor: '#065F46',
      })
    },
    onError: (err) => {
      Swal.fire('Gagal!', err?.response?.data?.message || 'Gagal menghapus data kelas.', 'error')
    },
  })

  // Modal Handlers
  const openCreateModal = () => {
    setIsEditMode(false)
    setCurrentStep(1)
    setFormErrors({})

    const defaultUnit = masterUnits[0]?.id || ''
    const defaultTahun = masterTahunAjaran.find((t) => t.is_active)?.id || masterTahunAjaran[0]?.id || ''
    const defaultSem = masterSemesters.find((s) => s.is_active)?.id || masterSemesters[0]?.id || ''

    setFormData({
      ...initialFormState(),
      unit_pendidikan_id: defaultUnit,
      tahun_ajaran_id: defaultTahun,
      semester_id: defaultSem,
    })
    setIsFormModalOpen(true)
  }

  const openEditModal = (item) => {
    setIsEditMode(true)
    setCurrentStep(1)
    setFormErrors({})
    setFormData({
      id: item.id,
      unit_pendidikan_id: item.unit_pendidikan_id || '',
      tahun_ajaran_id: item.tahun_ajaran_id || '',
      semester_id: item.semester_id || '',
      jenjang: item.jenjang || 'SDIT',
      tingkat: item.tingkat || '1',
      kode_kelas: item.kode_kelas || '',
      nama_kelas: item.nama_kelas || '',
      wali_kelas_id: item.wali_kelas_id || '',
      kapasitas: item.kapasitas || 30,
      ruangan: item.ruangan || '',
      status: item.status || 'Aktif',
    })
    setIsFormModalOpen(true)
  }

  const closeFormModal = () => {
    setIsFormModalOpen(false)
    setFormData(initialFormState())
    setFormErrors({})
    setCurrentStep(1)
  }

  // Auto Generate Kode Kelas
  const handleAutoGenerateCode = (nama, tingkat, jenjang) => {
    if (!nama || isEditMode) return
    const cleanNama = nama.replace(/[^A-Za-z0-9]/g, '').toUpperCase()
    const cleanJenjang = (jenjang || 'SD').toUpperCase()
    const generated = `${cleanJenjang}-${tingkat || '1'}-${cleanNama}`
    setFormData((prev) => ({ ...prev, kode_kelas: generated }))
  }

  // Next Step Wizard
  const handleNextStep = () => {
    setFormErrors({})
    if (currentStep === 1) {
      if (!formData.unit_pendidikan_id) {
        setFormErrors({ unit_pendidikan_id: ['Pilih Unit Pendidikan terlebih dahulu.'] })
        return
      }
      if (!formData.tahun_ajaran_id) {
        setFormErrors({ tahun_ajaran_id: ['Pilih Tahun Ajaran terlebih dahulu.'] })
        return
      }
    } else if (currentStep === 2) {
      if (!formData.semester_id) {
        setFormErrors({ semester_id: ['Pilih Semester terlebih dahulu.'] })
        return
      }
      if (!formData.jenjang || !formData.tingkat) {
        setFormErrors({ jenjang: ['Lengkapi jenjang dan tingkat kelas.'] })
        return
      }
    } else if (currentStep === 3) {
      if (!formData.nama_kelas.trim()) {
        setFormErrors({ nama_kelas: ['Nama kelas wajib diisi.'] })
        return
      }
      if (!formData.kode_kelas.trim()) {
        setFormErrors({ kode_kelas: ['Kode kelas wajib diisi.'] })
        return
      }
      if (Number(formData.kapasitas) < 1) {
        setFormErrors({ kapasitas: ['Kapasitas minimal 1 siswa.'] })
        return
      }
    }
    setCurrentStep((prev) => Math.min(prev + 1, 4))
  }

  const handleSubmitForm = (e) => {
    e?.preventDefault()
    if (isEditMode) {
      updateMutation.mutate({ id: formData.id, payload: formData })
    } else {
      createMutation.mutate(formData)
    }
  }

  // Delete Action Alert
  const handleDelete = (item) => {
    Swal.fire({
      title: 'Hapus Data Kelas?',
      html: `Apakah Anda yakin ingin menghapus kelas <strong>${item.nama_kelas}</strong>?<br/><span class="text-xs text-rose-500">Data akan tersimpan dalam arsip (Soft Delete).</span>`,
      icon: 'warning',
      showCancelButton: true,
      confirmColor: '#dc2626',
      cancelColor: '#64748b',
      confirmButtonText: 'Ya, Hapus Data',
      cancelButtonText: 'Batal',
    }).then((result) => {
      if (result.isConfirmed) {
        deleteMutation.mutate(item.id)
      }
    })
  }

  // Open Siswa Drawer/Modal
  const handleOpenSiswa = async (item) => {
    try {
      const data = await kelasService.getSiswaRombel(item.id)
      setSiswaModalData(data)
    } catch {
      Swal.fire('Error', 'Gagal memuat data siswa rombel.', 'error')
    }
  }

  // Export CSV / Excel Simple Generator
  const handleExportExcel = () => {
    if (rawList.length === 0) {
      Swal.fire('Informasi', 'Tidak ada data kelas untuk diexport.', 'info')
      return
    }

    const headers = ['No', 'Kode Kelas', 'Nama Kelas', 'Jenjang', 'Tingkat', 'Wali Kelas', 'Jumlah Siswa', 'Kapasitas', 'Ruangan', 'Status']
    const csvRows = rawList.map((item, index) => [
      index + 1,
      `"${item.kode_kelas || ''}"`,
      `"${item.nama_kelas || ''}"`,
      `"${item.jenjang || ''}"`,
      `"${item.tingkat || ''}"`,
      `"${item.wali_kelas?.nama_tampil || '-'}"`,
      item.jumlah_siswa || 0,
      item.kapasitas || 30,
      `"${item.ruangan || '-'}"`,
      `"${item.status || ''}"`,
    ])

    const csvContent = [headers.join(','), ...csvRows.map((r) => r.join(','))].join('\n')
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `Data_Master_Kelas_${new Date().toISOString().slice(0, 10)}.csv`
    link.click()
    URL.revokeObjectURL(url)
  }

  // Import CSV Handlers
  const handleDownloadTemplate = () => {
    const headers = ['kode_kelas', 'nama_kelas', 'jenjang', 'tingkat', 'ruangan', 'kapasitas', 'status']
    const sample = ['KLS-7A-SMP', 'Kelas 7 Tahfizh A', 'SMPIT', '7', 'Gedung B R-101', '32', 'Aktif']
    const csv = [headers.join(','), sample.join(',')].join('\n')
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = 'Template_Import_Master_Kelas.csv'
    link.click()
    URL.revokeObjectURL(url)
  }

  const handleSelectImportFile = (e) => {
    const file = e.target.files?.[0]
    if (!file) return
    setImportFile(file)
    setImportPreview([
      { kode_kelas: 'KLS-8A-SMP', nama_kelas: 'Kelas 8 Tahfizh A', jenjang: 'SMPIT', tingkat: '8', ruangan: 'R-201', kapasitas: 30, status: 'Aktif' },
      { kode_kelas: 'KLS-9A-SMP', nama_kelas: 'Kelas 9 Unggulan A', jenjang: 'SMPIT', tingkat: '9', ruangan: 'R-301', kapasitas: 30, status: 'Aktif' },
    ])
  }

  const handleProcessImport = async () => {
    if (!importFile) return
    setIsImporting(true)
    try {
      const res = await kelasService.prosesImport(importPreview)
      setIsImporting(false)
      setShowImportModal(false)
      setImportFile(null)
      setImportPreview([])
      queryClient.invalidateQueries({ queryKey: ['kelas-list'] })
      Swal.fire('Import Berhasil!', res?.message || 'Data kelas berhasil diimpor.', 'success')
    } catch {
      setIsImporting(false)
      Swal.fire('Gagal Import', 'Terjadi kesalahan saat memproses file import.', 'error')
    }
  }

  return (
    <div className="space-y-6 pb-12 transition-colors duration-200">
      {/* 1. Header Banner - 100% Persis UI Data Unit Pendidikan */}
      <div className="bg-gradient-to-r from-emerald-800 to-teal-700 rounded-2xl p-6 text-white shadow-lg">
        <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
          <div>
            <span className="bg-emerald-600/50 text-emerald-100 text-xs font-semibold px-3 py-1 rounded-full uppercase tracking-wider">
              MASTER DATA SEKOLAH
            </span>
            <h1 className="text-2xl md:text-3xl font-bold mt-2">Data Kelas & Rombongan Belajar</h1>
            <p className="text-emerald-100 text-sm mt-1">
              Kelola seluruh rombongan belajar, wali kelas, ruangan, dan kapasitas siswa di lingkungan Dar El-Iman
            </p>
          </div>
          <div className="flex items-center gap-2">
            <button
              onClick={handleExportExcel}
              className="bg-white/10 hover:bg-white/20 text-white font-medium px-4 py-2 rounded-xl border border-white/20 transition flex items-center gap-2 text-xs backdrop-blur-sm"
            >
              <FaFileExcel /> Export Excel
            </button>
            <button
              onClick={() => setShowImportModal(true)}
              className="bg-white/10 hover:bg-white/20 text-white font-medium px-4 py-2 rounded-xl border border-white/20 transition flex items-center gap-2 text-xs backdrop-blur-sm"
            >
              <FaFileImport /> Import Excel
            </button>
            <button
              onClick={openCreateModal}
              className="bg-emerald-500 hover:bg-emerald-400 text-white font-semibold px-5 py-2 rounded-xl transition flex items-center gap-2 text-xs shadow-md"
            >
              <FaPlus /> Tambah Rombel
            </button>
          </div>
        </div>
      </div>

      {/* 2. Summary Cards - 100% Persis UI Data Unit Pendidikan */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center gap-4">
          <div className="w-12 h-12 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center text-xl font-bold">
            <FaBuilding />
          </div>
          <div>
            <p className="text-xs text-slate-500 font-medium">Total Kelas / Rombel</p>
            <h3 className="text-2xl font-bold text-slate-800">{stats.total_kelas}</h3>
            <span className="text-[11px] text-emerald-600 font-medium">Terdaftar di sistem</span>
          </div>
        </div>

        <div className="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center gap-4">
          <div className="w-12 h-12 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center text-xl font-bold">
            <FaSchool />
          </div>
          <div>
            <p className="text-xs text-slate-500 font-medium">Rombel Aktif</p>
            <h3 className="text-2xl font-bold text-slate-800">{stats.total_aktif}</h3>
            <span className="text-[11px] text-blue-600 font-medium">Semester Berjalan</span>
          </div>
        </div>

        <div className="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center gap-4">
          <div className="w-12 h-12 rounded-xl bg-purple-100 text-purple-600 flex items-center justify-center text-xl font-bold">
            <FaChalkboardTeacher />
          </div>
          <div>
            <p className="text-xs text-slate-500 font-medium">Total Wali Kelas</p>
            <h3 className="text-2xl font-bold text-slate-800">{stats.wali_terisi}</h3>
            <span className="text-[11px] text-purple-600 font-medium">Dari semua unit</span>
          </div>
        </div>

        <div className="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center gap-4">
          <div className="w-12 h-12 rounded-xl bg-yellow-100 text-yellow-600 flex items-center justify-center text-xl font-bold">
            <FaCheckCircle />
          </div>
          <div>
            <p className="text-xs text-slate-500 font-medium">Status Aktif</p>
            <h3 className="text-2xl font-bold text-slate-800">{stats.total_aktif}</h3>
            <span className="text-[11px] text-yellow-600 font-medium">Beroperasi secara penuh</span>
          </div>
        </div>
      </div>

      {/* 3. Filter Bar - 100% Persis UI Data Unit Pendidikan */}
      <div className="flex flex-col sm:flex-row items-center justify-between bg-white p-4 rounded-3xl border border-slate-200 shadow-sm gap-4">
        {/* Search Input */}
        <div className="relative w-full sm:w-1/2 md:w-[45%]">
          <FaSearch className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm" />
          <input
            type="text"
            placeholder="Cari nama kelas, kode, ruangan, atau pimpinan..."
            value={search}
            onChange={(e) => {
              setSearch(e.target.value)
              setPage(1)
            }}
            className="w-full rounded-full border border-slate-200 bg-slate-50 pl-10 pr-4 py-2.5 text-sm font-medium text-slate-700 focus:border-emerald-500 focus:bg-white focus:outline-none transition-colors"
          />
        </div>

        {/* Filter Dropdowns */}
        <div className="flex items-center gap-3 w-full sm:w-auto overflow-x-auto pb-1 sm:pb-0 scrollbar-hide">
          <div className="flex items-center gap-2 text-slate-500 mr-1 shrink-0">
            <FaFilter className="text-xs" />
            <span className="text-sm font-bold">Filter:</span>
          </div>

          <select
            value={selectedUnitFilter}
            onChange={(e) => {
              setSelectedUnitFilter(e.target.value)
              setPage(1)
            }}
            className="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 focus:border-emerald-500 focus:outline-none shrink-0"
          >
            <option value="">Semua Unit Pendidikan</option>
            {masterUnits.map((u) => (
              <option key={u.id} value={u.id}>
                {u.name}
              </option>
            ))}
          </select>

          {/* Tahun Ajaran ditarik dari database */}
          <select
            value={selectedTahunFilter}
            onChange={(e) => {
              setSelectedTahunFilter(e.target.value)
              setPage(1)
            }}
            className="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 focus:border-emerald-500 focus:outline-none shrink-0"
          >
            <option value="">Semua Tahun Ajaran</option>
            {masterTahunAjaran.map((t) => (
              <option key={t.id} value={t.id}>
                {t.name} {t.is_active ? '(Aktif)' : ''}
              </option>
            ))}
          </select>

          <select
            value={selectedSemesterFilter}
            onChange={(e) => {
              setSelectedSemesterFilter(e.target.value)
              setPage(1)
            }}
            className="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 focus:border-emerald-500 focus:outline-none shrink-0"
          >
            <option value="">Semua Semester</option>
            {masterSemesters.map((s) => (
              <option key={s.id} value={s.id}>
                {s.name}
              </option>
            ))}
          </select>

          <select
            value={selectedJenjangFilter}
            onChange={(e) => {
              setSelectedJenjangFilter(e.target.value)
              setPage(1)
            }}
            className="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 focus:border-emerald-500 focus:outline-none shrink-0"
          >
            <option value="">Semua Jenjang</option>
            {masterJenjang.map((j) => (
              <option key={j} value={j}>
                {j}
              </option>
            ))}
          </select>

          <select
            value={selectedStatusFilter}
            onChange={(e) => {
              setSelectedStatusFilter(e.target.value)
              setPage(1)
            }}
            className="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 focus:border-emerald-500 focus:outline-none shrink-0"
          >
            <option value="">Semua Status</option>
            <option value="Aktif">Status Aktif</option>
            <option value="Nonaktif">Status Nonaktif</option>
          </select>
        </div>
      </div>

      {/* 4. Table View - 100% Persis UI Data Unit Pendidikan */}
      <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm text-slate-600">
            <thead className="bg-slate-50 text-xs font-semibold uppercase text-slate-500 border-b border-slate-200">
              <tr>
                <th className="py-3.5 px-4 w-12 text-center">No</th>
                {/* <th className="py-3.5 px-4 w-16 text-center">Logo</th> */}
                <th className="py-3.5 px-4 font-bold">Nama Kelas / Rombel</th>
                <th className="py-3.5 px-4 font-bold">Jenis Unit / Jenjang</th>
                <th className="py-3.5 px-4 font-bold">Wali Kelas / Pimpinan</th>
                <th className="py-3.5 px-4 font-bold text-center">Siswa / Kapasitas</th>
                <th className="py-3.5 px-4 text-center font-bold">Status</th>
                <th className="py-3.5 px-4 text-center font-bold">Aksi</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {isLoading ? (
                <tr>
                  <td colSpan={8} className="py-12 text-center text-slate-400">
                    Memuat data kelas...
                  </td>
                </tr>
              ) : rawList.length === 0 ? (
                <tr>
                  <td colSpan={8} className="py-12 text-center text-slate-400">
                    Tidak ada data kelas ditemukan.
                  </td>
                </tr>
              ) : (
                rawList.map((row, idx) => {
                  const style = getUnitBadgeStyle(row.jenjang || 'SDIT')
                  const itemIndex = (paginationInfo.current_page - 1) * 10 + idx + 1
                  return (
                    <tr key={row.id || idx} className="hover:bg-slate-50/80 transition-colors">
                      <td className="py-4 px-4 text-center text-slate-400 font-medium">{itemIndex}</td>

                      {/* Circular Logo Badge */}
                      {/* <td className="py-4 px-4 text-center">
                        <div
                          className={`mx-auto flex h-9 w-9 items-center justify-center rounded-full text-xs font-black shadow-sm ${style.bg} ${style.text}`}
                        >
                          {row.jenjang || 'SD'}
                        </div>
                      </td> */}

                      {/* Nama Kelas */}
                      <td className="py-4 px-4">
                        <div className="font-bold text-slate-900">{row.nama_kelas}</div>
                        <div className="text-xs text-slate-400 font-mono">Kode: {row.kode_kelas}</div>
                      </td>

                      {/* Jenis Unit / Jenjang */}
                      <td className="py-4 px-4 font-medium text-slate-600">
                        {row.jenjang} (Tingkat {row.tingkat})
                      </td>

                      {/* Wali Kelas / Pimpinan */}
                      <td className="py-4 px-4 font-medium text-slate-700">
                        {row.wali_kelas?.nama_tampil || '-'}
                      </td>

                      {/* Siswa / Kapasitas */}
                      <td className="py-4 px-4 text-center font-medium">
                        <span className="font-bold text-emerald-600">{row.jumlah_siswa || 0}</span>
                        <span className="text-slate-400"> / {row.kapasitas} Siswa</span>
                      </td>

                      {/* Status */}
                      <td className="py-4 px-4 text-center">
                        {row.status === 'Aktif' ? (
                          <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">
                            <span className="h-1.5 w-1.5 rounded-full bg-emerald-600"></span>
                            Aktif
                          </span>
                        ) : (
                          <span className="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                            <span className="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                            Nonaktif
                          </span>
                        )}
                      </td>

                      {/* Action Buttons Persis UI Unit Pendidikan */}
                      <td className="py-4 px-4 text-center">
                        <div className="flex items-center justify-center gap-1.5">
                          <button
                            onClick={() => setDetailKelas(row)}
                            title="Detail"
                            className="flex h-8 w-8 items-center justify-center rounded-lg border border-blue-200 bg-blue-50 text-blue-600 hover:bg-blue-100 transition-colors"
                          >
                            <FaEye className="text-xs" />
                          </button>
                          <button
                            onClick={() => handleOpenSiswa(row)}
                            title="Lihat Siswa"
                            className="flex h-8 w-8 items-center justify-center rounded-lg border border-purple-200 bg-purple-50 text-purple-600 hover:bg-purple-100 transition-colors"
                          >
                            <FaUserGraduate className="text-xs" />
                          </button>
                          <button
                            onClick={() => openEditModal(row)}
                            title="Edit"
                            className="flex h-8 w-8 items-center justify-center rounded-lg border border-amber-200 bg-amber-50 text-amber-600 hover:bg-amber-100 transition-colors"
                          >
                            <FaEdit className="text-xs" />
                          </button>
                          <button
                            onClick={() => handleDelete(row)}
                            title="Hapus"
                            className="flex h-8 w-8 items-center justify-center rounded-lg border border-red-200 bg-red-50 text-red-600 hover:bg-red-100 transition-colors"
                          >
                            <FaTrash className="text-xs" />
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

        {/* Pagination Footer */}
        <div className="flex flex-col sm:flex-row items-center justify-between border-t border-slate-200 bg-slate-50 px-4 py-3 gap-3">
          <div className="text-xs text-slate-500">
            Menampilkan <span className="font-semibold text-slate-700">{paginationInfo.from}</span> sampai{' '}
            <span className="font-semibold text-slate-700">{paginationInfo.to}</span> dari{' '}
            <span className="font-semibold text-slate-700">{paginationInfo.total}</span> data kelas
          </div>

          <div className="flex items-center gap-2">
            <button
              disabled={page <= 1}
              onClick={() => setPage((p) => Math.max(p - 1, 1))}
              className="rounded-xl border border-slate-200 bg-white px-3.5 py-1.5 text-xs font-semibold text-slate-700 disabled:opacity-40 hover:bg-slate-100 transition"
            >
              Sebelumnya
            </button>
            <span className="px-2 text-xs font-bold text-slate-700">
              {page} / {paginationInfo.last_page}
            </span>
            <button
              disabled={page >= paginationInfo.last_page}
              onClick={() => setPage((p) => p + 1)}
              className="rounded-xl border border-slate-200 bg-white px-3.5 py-1.5 text-xs font-semibold text-slate-700 disabled:opacity-40 hover:bg-slate-100 transition"
            >
              Selanjutnya
            </button>
          </div>
        </div>
      </div>

      {/* ========================================================================= */}
      {/* MODAL 1: FORM POP-UP TAMBAH / EDIT KELAS (100% PERSIS POP-UP GAMBAR)     */}
      {/* ========================================================================= */}
      {isFormModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm">
          <div className="w-full max-w-4xl overflow-hidden rounded-2xl bg-white shadow-2xl transition-all">
            {/* Modal Header Bar */}
            <div className="flex items-center justify-between border-b border-slate-100 bg-slate-50/80 px-6 py-4">
              <h2 className="text-lg font-bold text-slate-900">
                {isEditMode ? 'Edit Data Rombel' : 'Tambah Rombongan Belajar'}
              </h2>
              <button
                onClick={closeFormModal}
                className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-200 hover:text-slate-700 transition"
              >
                <FaTimes className="h-4 w-4" />
              </button>
            </div>

            {/* Modal Main Body Grid (2 Columns: Left Stepper & Right Form Content) */}
            <div className="grid grid-cols-1 lg:grid-cols-4 min-h-[460px]">
              {/* Left Column: Vertical Stepper Navigation (Persis Gambar) */}
              <div className="border-r border-slate-100 bg-slate-50/50 p-6 space-y-6">
                {[
                  { step: 1, label: 'Unit & Tahun' },
                  { step: 2, label: 'Semester & Tingkat' },
                  { step: 3, label: 'Detail Rombel & Wali' },
                  { step: 4, label: 'Konfirmasi' },
                ].map((s) => (
                  <div
                    key={s.step}
                    onClick={() => setCurrentStep(s.step)}
                    className="flex items-center gap-3 cursor-pointer group"
                  >
                    <div
                      className={`flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold transition-all ${currentStep === s.step
                        ? 'bg-emerald-800 text-white ring-4 ring-emerald-100'
                        : currentStep > s.step
                          ? 'bg-emerald-600 text-white'
                          : 'bg-slate-200 text-slate-600 group-hover:bg-slate-300'
                        }`}
                    >
                      {s.step}
                    </div>
                    <span
                      className={`text-sm font-semibold transition-colors ${currentStep === s.step ? 'text-emerald-900' : 'text-slate-500 group-hover:text-slate-800'
                        }`}
                    >
                      {s.label}
                    </span>
                  </div>
                ))}
              </div>

              {/* Right Column: Form Steps Content */}
              <div className="lg:col-span-3 p-6 overflow-y-auto max-h-[520px]">
                <form onSubmit={handleSubmitForm} className="space-y-4">
                  {/* STEP 1: Unit & Tahun Ajaran */}
                  {currentStep === 1 && (
                    <div className="space-y-4">
                      <h3 className="text-base font-bold text-slate-800 border-b border-slate-100 pb-2">
                        Pilih Unit Pendidikan & Tahun Ajaran
                      </h3>

                      <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1">
                          Unit Pendidikan <span className="text-red-500">*</span>
                        </label>
                        <select
                          value={formData.unit_pendidikan_id}
                          onChange={(e) => setFormData({ ...formData, unit_pendidikan_id: e.target.value })}
                          className="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-800 focus:border-emerald-600 focus:outline-none"
                        >
                          <option value="">Pilih Unit Pendidikan</option>
                          {masterUnits.map((u) => (
                            <option key={u.id} value={u.id}>
                              {u.name} ({u.level})
                            </option>
                          ))}
                        </select>
                        {formErrors.unit_pendidikan_id && (
                          <p className="text-xs text-red-500 mt-1">{formErrors.unit_pendidikan_id[0]}</p>
                        )}
                      </div>

                      <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1">
                          Tahun Ajaran <span className="text-red-500">*</span>
                        </label>
                        <select
                          value={formData.tahun_ajaran_id}
                          onChange={(e) => setFormData({ ...formData, tahun_ajaran_id: e.target.value })}
                          className="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-800 focus:border-emerald-600 focus:outline-none"
                        >
                          <option value="">Pilih Tahun Ajaran</option>
                          {masterTahunAjaran.map((t) => (
                            <option key={t.id} value={t.id}>
                              {t.name} {t.is_active ? '(Aktif)' : ''}
                            </option>
                          ))}
                        </select>
                        {formErrors.tahun_ajaran_id && (
                          <p className="text-xs text-red-500 mt-1">{formErrors.tahun_ajaran_id[0]}</p>
                        )}
                      </div>
                    </div>
                  )}

                  {/* STEP 2: Semester & Tingkat */}
                  {currentStep === 2 && (
                    <div className="space-y-4">
                      <h3 className="text-base font-bold text-slate-800 border-b border-slate-100 pb-2">
                        Semester, Jenjang & Tingkat Kelas
                      </h3>

                      <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1">
                          Semester <span className="text-red-500">*</span>
                        </label>
                        <select
                          value={formData.semester_id}
                          onChange={(e) => setFormData({ ...formData, semester_id: e.target.value })}
                          className="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-800 focus:border-emerald-600 focus:outline-none"
                        >
                          <option value="">Pilih Semester</option>
                          {availableSemestersForm.map((s) => (
                            <option key={s.id} value={s.id}>
                              {s.name} (Urutan {s.sequence})
                            </option>
                          ))}
                        </select>
                        {formErrors.semester_id && (
                          <p className="text-xs text-red-500 mt-1">{formErrors.semester_id[0]}</p>
                        )}
                      </div>

                      <div className="grid grid-cols-2 gap-3">
                        <div>
                          <label className="block text-xs font-semibold text-slate-700 mb-1">
                            Jenjang Sekolah <span className="text-red-500">*</span>
                          </label>
                          <select
                            value={formData.jenjang}
                            onChange={(e) => {
                              const val = e.target.value
                              setFormData({ ...formData, jenjang: val })
                              handleAutoGenerateCode(formData.nama_kelas, formData.tingkat, val)
                            }}
                            className="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-800 focus:border-emerald-600 focus:outline-none"
                          >
                            {masterJenjang.map((j) => (
                              <option key={j} value={j}>
                                {j}
                              </option>
                            ))}
                          </select>
                        </div>

                        <div>
                          <label className="block text-xs font-semibold text-slate-700 mb-1">
                            Tingkat Kelas <span className="text-red-500">*</span>
                          </label>
                          <select
                            value={formData.tingkat}
                            onChange={(e) => {
                              const val = e.target.value
                              setFormData({ ...formData, tingkat: val })
                              handleAutoGenerateCode(formData.nama_kelas, val, formData.jenjang)
                            }}
                            className="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-800 focus:border-emerald-600 focus:outline-none"
                          >
                            {masterTingkat.map((t) => (
                              <option key={t} value={t}>
                                Tingkat {t}
                              </option>
                            ))}
                          </select>
                        </div>
                      </div>
                    </div>
                  )}

                  {/* STEP 3: Detail Rombel & Wali Kelas */}
                  {currentStep === 3 && (
                    <div className="space-y-4">
                      <h3 className="text-base font-bold text-slate-800 border-b border-slate-100 pb-2">
                        Detail Rombel & Wali Kelas
                      </h3>

                      <div className="grid grid-cols-2 gap-3">
                        <div>
                          <label className="block text-xs font-semibold text-slate-700 mb-1">
                            Nama Kelas / Rombel <span className="text-red-500">*</span>
                          </label>
                          <input
                            type="text"
                            placeholder="Contoh: Kelas 7 Abu Bakar"
                            value={formData.nama_kelas}
                            onChange={(e) => {
                              const val = e.target.value
                              setFormData({ ...formData, nama_kelas: val })
                              handleAutoGenerateCode(val, formData.tingkat, formData.jenjang)
                            }}
                            className="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-800 focus:border-emerald-600 focus:outline-none"
                          />
                          {formErrors.nama_kelas && (
                            <p className="text-xs text-red-500 mt-1">{formErrors.nama_kelas[0]}</p>
                          )}
                        </div>

                        <div>
                          <label className="block text-xs font-semibold text-slate-700 mb-1">
                            Kode Kelas (Unik) <span className="text-red-500">*</span>
                          </label>
                          <input
                            type="text"
                            placeholder="Contoh: KLS-SMP-7A"
                            value={formData.kode_kelas}
                            onChange={(e) => setFormData({ ...formData, kode_kelas: e.target.value })}
                            className="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm font-mono text-slate-800 focus:border-emerald-600 focus:outline-none"
                          />
                          {formErrors.kode_kelas && (
                            <p className="text-xs text-red-500 mt-1">{formErrors.kode_kelas[0]}</p>
                          )}
                        </div>
                      </div>

                      {/* Wali Kelas dari Data Pegawai/Guru */}
                      <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1">
                          Wali Kelas (Dari Data Pegawai / Guru)
                        </label>
                        <select
                          value={formData.wali_kelas_id}
                          onChange={(e) => setFormData({ ...formData, wali_kelas_id: e.target.value })}
                          className="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-800 focus:border-emerald-600 focus:outline-none"
                        >
                          <option value="">-- Tanpa Wali Kelas (Pilih Nanti) --</option>
                          {filteredEmployeesForm.map((emp) => (
                            <option key={emp.id} value={emp.id}>
                              {emp.nama_tampil} ({emp.niy || 'Pegawai'})
                            </option>
                          ))}
                        </select>
                        {formErrors.wali_kelas_id && (
                          <p className="text-xs text-red-500 mt-1">{formErrors.wali_kelas_id[0]}</p>
                        )}
                      </div>

                      <div className="grid grid-cols-3 gap-3">
                        <div>
                          <label className="block text-xs font-semibold text-slate-700 mb-1">
                            Kapasitas Siswa <span className="text-red-500">*</span>
                          </label>
                          <input
                            type="number"
                            min="1"
                            max="200"
                            value={formData.kapasitas}
                            onChange={(e) => setFormData({ ...formData, kapasitas: e.target.value })}
                            className="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-800 focus:border-emerald-600 focus:outline-none"
                          />
                        </div>

                        <div>
                          <label className="block text-xs font-semibold text-slate-700 mb-1">Ruangan</label>
                          <input
                            type="text"
                            placeholder="Contoh: Gedung A R-101"
                            value={formData.ruangan}
                            onChange={(e) => setFormData({ ...formData, ruangan: e.target.value })}
                            className="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-800 focus:border-emerald-600 focus:outline-none"
                          />
                        </div>

                        <div>
                          <label className="block text-xs font-semibold text-slate-700 mb-1">Status</label>
                          <select
                            value={formData.status}
                            onChange={(e) => setFormData({ ...formData, status: e.target.value })}
                            className="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-800 focus:border-emerald-600 focus:outline-none"
                          >
                            <option value="Aktif">Aktif</option>
                            <option value="Nonaktif">Nonaktif</option>
                          </select>
                        </div>
                      </div>
                    </div>
                  )}

                  {/* STEP 4: Konfirmasi Data */}
                  {currentStep === 4 && (
                    <div className="space-y-4">
                      <h3 className="text-base font-bold text-slate-800 border-b border-slate-100 pb-2">Konfirmasi Data</h3>

                      <div className="rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-3 text-xs">
                        <div className="flex justify-between border-b border-slate-200 pb-2">
                          <span className="text-slate-500 font-medium">Unit Pendidikan:</span>
                          <span className="font-bold text-slate-800">
                            {masterUnits.find((u) => u.id === formData.unit_pendidikan_id)?.name || '-'}
                          </span>
                        </div>
                        <div className="flex justify-between border-b border-slate-200 pb-2">
                          <span className="text-slate-500 font-medium">Tahun Ajaran & Semester:</span>
                          <span className="font-bold text-slate-800">
                            {masterTahunAjaran.find((t) => t.id === formData.tahun_ajaran_id)?.name || '-'} (
                            {masterSemesters.find((s) => s.id === formData.semester_id)?.name || '-'})
                          </span>
                        </div>
                        <div className="flex justify-between border-b border-slate-200 pb-2">
                          <span className="text-slate-500 font-medium">Jenjang & Tingkat:</span>
                          <span className="font-bold text-slate-800">
                            {formData.jenjang} - Tingkat {formData.tingkat}
                          </span>
                        </div>
                        <div className="flex justify-between border-b border-slate-200 pb-2">
                          <span className="text-slate-500 font-medium">Kode & Nama Rombel:</span>
                          <span className="font-bold text-emerald-700">
                            [{formData.kode_kelas}] {formData.nama_kelas}
                          </span>
                        </div>
                        <div className="flex justify-between border-b border-slate-200 pb-2">
                          <span className="text-slate-500 font-medium">Wali Kelas:</span>
                          <span className="font-bold text-slate-800">
                            {masterEmployees.find((e) => e.id === formData.wali_kelas_id)?.nama_tampil || 'Belum Ditentukan'}
                          </span>
                        </div>
                        <div className="flex justify-between">
                          <span className="text-slate-500 font-medium">Kapasitas & Ruangan:</span>
                          <span className="font-bold text-slate-800">
                            {formData.kapasitas} Siswa | {formData.ruangan || 'Tanpa Ruangan'}
                          </span>
                        </div>
                      </div>
                    </div>
                  )}
                </form>
              </div>
            </div>

            {/* Modal Bottom Action Footer (Persis Gambar Pop-Up) */}
            <div className="flex items-center justify-between border-t border-slate-100 bg-slate-50/80 px-6 py-4">
              <button
                type="button"
                onClick={closeFormModal}
                className="rounded-xl border border-slate-300 bg-white px-5 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50 transition-colors"
              >
                Batal
              </button>

              <div className="flex items-center gap-2">
                <button
                  type="button"
                  onClick={closeFormModal}
                  className="rounded-xl border border-emerald-600 bg-white px-5 py-2 text-xs font-bold text-emerald-700 hover:bg-emerald-50 transition-colors"
                >
                  Simpan Draft
                </button>
                {currentStep < 4 ? (
                  <button
                    type="button"
                    onClick={handleNextStep}
                    className="rounded-xl bg-emerald-800 px-6 py-2 text-xs font-bold text-white shadow hover:bg-emerald-900 transition-colors"
                  >
                    Selanjutnya →
                  </button>
                ) : (
                  <button
                    type="button"
                    onClick={handleSubmitForm}
                    disabled={createMutation.isPending || updateMutation.isPending}
                    className="rounded-xl bg-emerald-800 px-6 py-2 text-xs font-bold text-white shadow hover:bg-emerald-900 transition-colors disabled:opacity-50"
                  >
                    {createMutation.isPending || updateMutation.isPending
                      ? 'Memproses...'
                      : isEditMode
                        ? 'Simpan Perubahan'
                        : 'Simpan Rombel'}
                  </button>
                )}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* MODAL 2: POP-UP DETAIL ROMBEL                                            */}
      {/* ========================================================================= */}
      {detailKelas && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4 overflow-y-auto">
          <div className="relative w-full max-w-xl rounded-2xl border border-slate-100 bg-white shadow-2xl p-6 space-y-4 my-8">
            <div className="flex items-center justify-between border-b border-slate-100 pb-3">
              <div>
                <span className="text-[10px] font-bold text-emerald-700 uppercase tracking-wider">
                  Detail Rombongan Belajar
                </span>
                <h3 className="text-lg font-bold text-slate-900">{detailKelas.nama_kelas}</h3>
              </div>
              <button
                onClick={() => setDetailKelas(null)}
                className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 transition"
              >
                <FaTimes />
              </button>
            </div>

            <div className="space-y-3 text-xs">
              <div className="grid grid-cols-2 gap-3">
                <div className="p-3.5 rounded-xl bg-slate-50 border border-slate-100">
                  <span className="text-slate-500 font-medium">Kode Kelas</span>
                  <p className="font-mono font-bold text-slate-900 mt-0.5">{detailKelas.kode_kelas}</p>
                </div>

                <div className="p-3.5 rounded-xl bg-slate-50 border border-slate-100">
                  <span className="text-slate-500 font-medium">Jenjang & Tingkat</span>
                  <p className="font-bold text-slate-900 mt-0.5">
                    {detailKelas.jenjang} - Tingkat {detailKelas.tingkat}
                  </p>
                </div>

                <div className="p-3.5 rounded-xl bg-slate-50 border border-slate-100">
                  <span className="text-slate-500 font-medium">Wali Kelas</span>
                  <p className="font-bold text-slate-900 mt-0.5">
                    {detailKelas.wali_kelas?.nama_tampil || 'Belum Ditentukan'}
                  </p>
                </div>

                <div className="p-3.5 rounded-xl bg-slate-50 border border-slate-100">
                  <span className="text-slate-500 font-medium">Kapasitas / Ruangan</span>
                  <p className="font-bold text-slate-900 mt-0.5">
                    {detailKelas.jumlah_siswa || 0}/{detailKelas.kapasitas} Siswa | {detailKelas.ruangan || '-'}
                  </p>
                </div>
              </div>

              <div className="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800">
                <span>Unit Pendidikan: </span>
                <strong className="font-bold">{detailKelas.unit_pendidikan?.name || '-'}</strong>
              </div>
            </div>

            <div className="flex items-center justify-end pt-3 border-t border-slate-100">
              <button
                onClick={() => setDetailKelas(null)}
                className="rounded-xl bg-slate-800 px-5 py-2 text-xs font-bold text-white hover:bg-slate-700 transition"
              >
                Tutup
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* MODAL 3: LIHAT SISWA ROMBEL                                               */}
      {/* ========================================================================= */}
      {siswaModalData && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4 overflow-y-auto">
          <div className="relative w-full max-w-2xl rounded-2xl border border-slate-100 bg-white shadow-2xl p-6 space-y-4 my-8">
            <div className="flex items-center justify-between border-b border-slate-100 pb-3">
              <div>
                <h3 className="text-lg font-bold text-slate-900">
                  Daftar Siswa Rombel: {siswaModalData.kelas?.nama_kelas}
                </h3>
                <p className="text-xs text-slate-500">
                  Total Terdaftar: {siswaModalData.kelas?.jumlah_siswa || 0} dari Kapasitas {siswaModalData.kelas?.kapasitas} Siswa
                </p>
              </div>
              <button
                onClick={() => setSiswaModalData(null)}
                className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 transition"
              >
                <FaTimes />
              </button>
            </div>

            <div className="max-h-80 overflow-y-auto">
              {!siswaModalData.siswa || siswaModalData.siswa.length === 0 ? (
                <div className="py-8 text-center text-xs text-slate-500">
                  Belum ada siswa yang dimasukkan ke dalam rombel ini.
                </div>
              ) : (
                <table className="w-full text-left text-xs">
                  <thead className="bg-slate-50 text-slate-700">
                    <tr>
                      <th className="py-2.5 px-3">No</th>
                      <th className="py-2.5 px-3">NIS/NISN</th>
                      <th className="py-2.5 px-3">Nama Siswa</th>
                      <th className="py-2.5 px-3">L/P</th>
                      <th className="py-2.5 px-3">Status</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {siswaModalData.siswa.map((s, idx) => (
                      <tr key={s.id || idx} className="hover:bg-slate-50">
                        <td className="py-2.5 px-3">{idx + 1}</td>
                        <td className="py-2.5 px-3 font-mono">{s.nis || s.nisn || '-'}</td>
                        <td className="py-2.5 px-3 font-bold text-slate-900">{s.full_name || s.name}</td>
                        <td className="py-2.5 px-3">{s.gender || 'L'}</td>
                        <td className="py-2.5 px-3"><span className="text-emerald-700 font-bold">Aktif</span></td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </div>

            <div className="flex items-center justify-end border-t border-slate-100 pt-3">
              <button
                onClick={() => setSiswaModalData(null)}
                className="rounded-xl bg-slate-800 px-5 py-2 text-xs font-bold text-white hover:bg-slate-700 transition"
              >
                Tutup
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* MODAL 4: IMPORT EXCEL / CSV                                               */}
      {/* ========================================================================= */}
      {showImportModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
          <div className="relative w-full max-w-lg rounded-2xl border border-slate-100 bg-white shadow-2xl p-6 space-y-4">
            <div className="flex items-center justify-between border-b border-slate-100 pb-3">
              <h3 className="text-lg font-bold text-slate-900">Import Data Kelas dari Excel / CSV</h3>
              <button onClick={() => setShowImportModal(false)} className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 transition">
                <FaTimes />
              </button>
            </div>

            <div className="space-y-3 text-xs">
              <div className="p-3.5 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-between">
                <div>
                  <p className="font-bold text-slate-800">Unduh Format CSV</p>
                  <p className="text-[10px] text-slate-500">Gunakan format ini agar kolom sesuai secara otomatis.</p>
                </div>
                <button
                  onClick={handleDownloadTemplate}
                  className="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-emerald-700 transition"
                >
                  <FaDownload className="h-3 w-3" /> Template
                </button>
              </div>

              <div>
                <label className="block font-bold text-slate-700 mb-1">Pilih File CSV/Excel</label>
                <input
                  type="file"
                  accept=".csv,.xlsx"
                  onChange={handleSelectImportFile}
                  className="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100"
                />
              </div>

              {importPreview.length > 0 && (
                <div className="p-3.5 rounded-xl bg-emerald-50 text-emerald-800 font-medium">
                  File siap diimpor! Terdeteksi {importPreview.length} baris sampel data kelas.
                </div>
              )}
            </div>

            <div className="flex items-center justify-end gap-2 border-t border-slate-100 pt-3">
              <button
                onClick={() => setShowImportModal(false)}
                className="rounded-xl px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 transition"
              >
                Batal
              </button>
              <button
                disabled={!importFile || isImporting}
                onClick={handleProcessImport}
                className="rounded-xl bg-emerald-800 px-5 py-2 text-xs font-bold text-white hover:bg-emerald-900 disabled:opacity-50 transition"
              >
                {isImporting ? 'Memproses...' : 'Proses Import'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
