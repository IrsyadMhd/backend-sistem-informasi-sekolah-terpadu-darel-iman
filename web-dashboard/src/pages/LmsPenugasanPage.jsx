import { useState, useEffect } from 'react'
import {
  ClipboardList,
  BookOpen,
  Plus,
  Search,
  Edit3,
  Trash2,
  RefreshCw,
  CheckCircle2,
  AlertCircle,
  X,
  Send,
  GraduationCap,
  ChevronLeft,
  ChevronRight,
  Clock,
  SlidersHorizontal,
  FileText,
  Paperclip,
  Check,
  Eye,
  Award,
  Users,
  UserCheck,
  Globe,
  Lock,
} from 'lucide-react'
import Swal from 'sweetalert2'
import { lmsPenugasanService } from '../services/lmsPenugasanService'

export default function LmsPenugasanPage() {
  const [dataPenugasan, setDataPenugasan] = useState([])
  const [options, setOptions] = useState({
    modul_ajar: [],
    kelas: [],
    guru: [],
    subjects: [],
    semesters: [],
    tahun_ajaran: [],
    tipe: [
      { value: 'individu', label: 'Individu' },
      { value: 'kelompok', label: 'Kelompok' },
    ],
    jenis: [
      { value: 'tugas', label: 'Tugas Mandiri / PR' },
      { value: 'proyek', label: 'Proyek / Portofolio' },
      { value: 'quiz', label: 'Kuis Formatif' },
      { value: 'latihan', label: 'Latihan Soal' },
    ],
    status: [
      { value: 'dipublikasikan', label: 'Dipublikasikan' },
      { value: 'draft', label: 'Draft' },
    ],
  })

  const [stats, setStats] = useState({
    total: 0,
    published: 0,
    draft: 0,
    total_pengumpulan: 0,
    total_dinilai: 0,
  })

  const [loading, setLoading] = useState(true)
  const [errorMsg, setErrorMsg] = useState('')
  const [successMsg, setSuccessMsg] = useState('')

  // Filters & Pagination
  const [search, setSearch] = useState('')
  const [selectedModulAjar, setSelectedModulAjar] = useState('')
  const [selectedTipe, setSelectedTipe] = useState('')
  const [selectedStatus, setSelectedStatus] = useState('')
  const [selectedKelas, setSelectedKelas] = useState('')
  const [page, setPage] = useState(1)
  const [pagination, setPagination] = useState({
    current_page: 1,
    last_page: 1,
    total: 0,
    per_page: 15,
  })

  // Modal Form State (Create / Edit Penugasan)
  const [isModalOpen, setIsModalOpen] = useState(false)
  const [editId, setEditId] = useState(null)
  const [formLoading, setFormLoading] = useState(false)

  const [formData, setFormData] = useState({
    judul: '',
    deskripsi: '',
    instruksi: '',
    tipe: 'individu',
    jenis_tugas: 'tugas',
    nilai_maksimal: 100,
    bobot_persen: 10,
    tanggal_mulai: '',
    tanggal_selesai: '',
    izin_kumpul_terlambat: true,
    status: 'dipublikasikan',
    lampiran: '',
    modul_ajar_id: '',
    mata_pelajaran_id: '',
    kelas_id: '',
    guru_id: '',
    semester_id: '',
    tahun_ajaran_id: '',
  })

  // Drawer / Detail Modal for Student Submissions & Grading
  const [isDetailOpen, setIsDetailOpen] = useState(false)
  const [selectedPenugasan, setSelectedPenugasan] = useState(null)
  const [gradingStudentId, setGradingStudentId] = useState(null)
  const [gradingForm, setGradingForm] = useState({
    nilai_guru: '',
    catatan_guru: '',
    status: 'dinilai',
  })
  const [gradingLoading, setGradingLoading] = useState(false)

  useEffect(() => {
    fetchOptions()
    fetchStats()
  }, [])

  useEffect(() => {
    fetchPenugasan()
  }, [page, search, selectedModulAjar, selectedTipe, selectedStatus, selectedKelas])

  const fetchOptions = async () => {
    try {
      const res = await lmsPenugasanService.getOptions()
      const data = res?.data || res
      if (data) {
        setOptions((prev) => ({
          ...prev,
          modul_ajar: data.modul_ajar || data.modulAjar || [],
          kelas: data.kelas || data.classes || [],
          guru: data.guru || data.teachers || [],
          subjects: data.subjects || [],
          semesters: data.semesters || [],
          tahun_ajaran: data.tahun_ajaran || data.academic_years || [],
        }))
      }
    } catch (err) {
      console.error('Gagal memuat opsi penugasan:', err)
    }
  }

  const fetchStats = async () => {
    try {
      const res = await lmsPenugasanService.getStats()
      if (res.success && res.data) {
        setStats(res.data)
      }
    } catch (err) {
      console.error('Gagal memuat statistik:', err)
    }
  }

  const fetchPenugasan = async () => {
    setLoading(true)
    setErrorMsg('')
    try {
      const params = {
        page,
        per_page: 15,
        search,
        modul_ajar_id: selectedModulAjar,
        tipe: selectedTipe,
        status: selectedStatus,
        kelas_id: selectedKelas,
      }
      const res = await lmsPenugasanService.getDaftar(params)
      if (res.data) {
        setDataPenugasan(res.data)
        if (res.meta) {
          setPagination({
            current_page: res.meta.current_page,
            last_page: res.meta.last_page,
            total: res.meta.total,
            per_page: res.meta.per_page,
          })
        }
      }
    } catch (err) {
      setErrorMsg(err?.response?.data?.message || 'Gagal mengambil data penugasan.')
    } finally {
      setLoading(false)
    }
  }

  const handleOpenCreateModal = () => {
    setEditId(null)
    setFormData({
      judul: '',
      deskripsi: '',
      instruksi: '',
      tipe: 'individu',
      jenis_tugas: 'tugas',
      nilai_maksimal: 100,
      bobot_persen: 10,
      tanggal_mulai: new Date().toISOString().slice(0, 16),
      tanggal_selesai: new Date(Date.now() + 7 * 86400000).toISOString().slice(0, 16),
      izin_kumpul_terlambat: true,
      status: 'dipublikasikan',
      lampiran: '',
      modul_ajar_id: options.modul_ajar[0]?.value || '',
      mata_pelajaran_id: options.subjects[0]?.value || '',
      kelas_id: options.kelas[0]?.value || '',
      guru_id: options.guru[0]?.value || '',
      semester_id: options.semesters[0]?.value || '',
      tahun_ajaran_id: options.tahun_ajaran[0]?.value || '',
    })
    setIsModalOpen(true)
  }

  const handleOpenEditModal = (item) => {
    setEditId(item.id)
    setFormData({
      judul: item.judul || item.judul_tugas || '',
      deskripsi: item.deskripsi || '',
      instruksi: item.instruksi || '',
      tipe: item.tipe || item.tipe_tugas || 'individu',
      jenis_tugas: item.jenis_tugas || 'tugas',
      nilai_maksimal: item.nilai_maksimal ?? 100,
      bobot_persen: item.bobot_persen ?? 10,
      tanggal_mulai: item.tanggal_mulai ? item.tanggal_mulai.replace(' ', 'T').slice(0, 16) : '',
      tanggal_selesai: item.tanggal_selesai ? item.tanggal_selesai.replace(' ', 'T').slice(0, 16) : '',
      izin_kumpul_terlambat: Boolean(item.izin_kumpul_terlambat),
      status: item.status || (item.is_published ? 'dipublikasikan' : 'draft'),
      lampiran: item.lampiran || item.file_lampiran || '',
      modul_ajar_id: item.modul_ajar_id || '',
      mata_pelajaran_id: item.mata_pelajaran_id || '',
      kelas_id: item.kelas_id || '',
      guru_id: item.guru_id || '',
      semester_id: item.semester_id || '',
      tahun_ajaran_id: item.tahun_ajaran_id || '',
    })
    setIsModalOpen(true)
  }

  const handleFormSubmit = async (e) => {
    e.preventDefault()
    setFormLoading(true)
    setErrorMsg('')

    try {
      if (editId) {
        await lmsPenugasanService.update(editId, formData)
        setSuccessMsg('Penugasan berhasil diperbarui.')
      } else {
        await lmsPenugasanService.create(formData)
        setSuccessMsg('Penugasan baru berhasil ditambahkan.')
      }
      setIsModalOpen(false)
      fetchPenugasan()
      fetchStats()
    } catch (err) {
      setErrorMsg(err?.response?.data?.message || 'Gagal menyimpan data penugasan.')
    } finally {
      setFormLoading(false)
    }
  }

  const handleDelete = async (id, judul) => {
    const result = await Swal.fire({
      title: 'Hapus Penugasan?',
      text: `Apakah Anda yakin ingin menghapus "${judul}"? Data pengumpulan siswa terkait juga akan terdampak.`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#0E5C44',
      confirmButtonText: 'Ya, Hapus!',
      cancelButtonText: 'Batal',
    })

    if (result.isConfirmed) {
      try {
        await lmsPenugasanService.delete(id)
        Swal.fire('Terhapus!', 'Penugasan berhasil dihapus.', 'success')
        fetchPenugasan()
        fetchStats()
      } catch (err) {
        Swal.fire('Gagal!', err?.response?.data?.message || 'Gagal menghapus penugasan.', 'error')
      }
    }
  }

  const handleTogglePublish = async (item) => {
    try {
      const res = await lmsPenugasanService.togglePublish(item.id)
      if (res.success) {
        setSuccessMsg(res.message)
        fetchPenugasan()
        fetchStats()
      }
    } catch (err) {
      setErrorMsg(err?.response?.data?.message || 'Gagal mengubah status publikasi.')
    }
  }

  const handleOpenDetail = async (item) => {
    setSelectedPenugasan(item)
    setIsDetailOpen(true)
    try {
      const res = await lmsPenugasanService.getById(item.id)
      if (res.success && res.data) {
        setSelectedPenugasan(res.data)
      }
    } catch (err) {
      console.error('Gagal mengambil detail penugasan:', err)
    }
  }

  const handleStartGrading = (submission) => {
    setGradingStudentId(submission.siswa_id)
    setGradingForm({
      nilai_guru: submission.nilai_guru !== null ? submission.nilai_guru : '',
      catatan_guru: submission.catatan_guru || '',
      status: 'dinilai',
    })
  }

  const handleSaveGrade = async (e) => {
    e.preventDefault()
    if (!selectedPenugasan || !gradingStudentId) return

    setGradingLoading(true)
    try {
      const payload = {
        siswa_id: gradingStudentId,
        nilai_guru: parseFloat(gradingForm.nilai_guru),
        catatan_guru: gradingForm.catatan_guru,
        status: 'dinilai',
      }

      const res = await lmsPenugasanService.gradeSubmission(selectedPenugasan.id, payload)
      if (res.success) {
        Swal.fire('Berhasil!', 'Nilai tugas siswa berhasil disimpan.', 'success')
        setGradingStudentId(null)
        // Refresh detail
        const updated = await lmsPenugasanService.getById(selectedPenugasan.id)
        if (updated.success) {
          setSelectedPenugasan(updated.data)
        }
        fetchPenugasan()
        fetchStats()
      }
    } catch (err) {
      Swal.fire('Gagal!', err?.response?.data?.message || 'Gagal menyimpan nilai.', 'error')
    } finally {
      setGradingLoading(false)
    }
  }

  return (
    <div className="space-y-6 pb-12">
      {/* Top Banner / Header */}
      <div className="bg-gradient-to-r from-[#0E5C44] via-[#1E8E5A] to-[#3FBF75] rounded-[18px] p-6 text-white shadow-xl relative overflow-hidden">
        <div className="absolute -right-6 -bottom-6 opacity-15 text-white">
          <ClipboardList size={180} />
        </div>
        <div className="relative z-10 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
          <div>
            <div className="flex items-center gap-2 text-emerald-200 text-sm font-medium mb-1">
              <BookOpen size={16} />
              <span>Pelaksanaan Pembelajaran & Assessments</span>
            </div>
            <h1 className="text-2xl md:text-3xl font-bold tracking-tight">Penugasan & Proyek Siswa</h1>
            <p className="text-emerald-100 text-sm max-w-xl mt-1">
              Kelola tugas mandiri, proyek kelompok, kuis formatif, serta penilaian hasil pengumpulan tugas siswa secara terpadu.
            </p>
          </div>
          <button
            onClick={handleOpenCreateModal}
            className="flex items-center gap-2 bg-white text-[#0E5C44] hover:bg-emerald-50 px-4 py-2.5 rounded-xl font-semibold text-sm transition-all duration-200 transform hover:scale-[1.03] active:scale-95 shadow-md"
          >
            <Plus size={18} />
            <span>Buat Penugasan Baru</span>
          </button>
        </div>
      </div>

      {/* Alert Notifications */}
      {errorMsg && (
        <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl flex items-center justify-between text-sm animate-fade-in">
          <div className="flex items-center gap-2">
            <AlertCircle size={18} />
            <span>{errorMsg}</span>
          </div>
          <button onClick={() => setErrorMsg('')} className="text-red-500 hover:text-red-700">
            <X size={16} />
          </button>
        </div>
      )}

      {successMsg && (
        <div className="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl flex items-center justify-between text-sm animate-fade-in">
          <div className="flex items-center gap-2">
            <CheckCircle2 size={18} />
            <span>{successMsg}</span>
          </div>
          <button onClick={() => setSuccessMsg('')} className="text-emerald-500 hover:text-emerald-700">
            <X size={16} />
          </button>
        </div>
      )}

      {/* KPI Cards / Statistics */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <div className="bg-white dark:bg-[#1B2433] rounded-[18px] p-5 border border-slate-200/80 dark:border-slate-800 shadow-sm hover:shadow-md transition-all duration-200">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Total Penugasan</span>
            <div className="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 flex items-center justify-center text-[#0E5C44] dark:text-emerald-400">
              <ClipboardList size={20} />
            </div>
          </div>
          <p className="text-2xl font-bold text-slate-800 dark:text-white mt-2">{stats.total}</p>
          <span className="text-[11px] text-slate-500 dark:text-slate-400 mt-1 block">Tercatat di sistem</span>
        </div>

        <div className="bg-white dark:bg-[#1B2433] rounded-[18px] p-5 border border-slate-200/80 dark:border-slate-800 shadow-sm hover:shadow-md transition-all duration-200">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Dipublikasikan</span>
            <div className="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-950/50 flex items-center justify-center text-blue-600 dark:text-blue-400">
              <Globe size={20} />
            </div>
          </div>
          <p className="text-2xl font-bold text-slate-800 dark:text-white mt-2">{stats.published}</p>
          <span className="text-[11px] text-blue-600 dark:text-blue-400 mt-1 block">Dapat diakses siswa</span>
        </div>

        <div className="bg-white dark:bg-[#1B2433] rounded-[18px] p-5 border border-slate-200/80 dark:border-slate-800 shadow-sm hover:shadow-md transition-all duration-200">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Draft</span>
            <div className="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-950/50 flex items-center justify-center text-amber-600 dark:text-amber-400">
              <Lock size={20} />
            </div>
          </div>
          <p className="text-2xl font-bold text-slate-800 dark:text-white mt-2">{stats.draft}</p>
          <span className="text-[11px] text-amber-600 dark:text-amber-400 mt-1 block">Belum dipublish</span>
        </div>

        <div className="bg-white dark:bg-[#1B2433] rounded-[18px] p-5 border border-slate-200/80 dark:border-slate-800 shadow-sm hover:shadow-md transition-all duration-200">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Pengumpulan Siswa</span>
            <div className="w-10 h-10 rounded-xl bg-purple-50 dark:bg-purple-950/50 flex items-center justify-center text-purple-600 dark:text-purple-400">
              <Users size={20} />
            </div>
          </div>
          <p className="text-2xl font-bold text-slate-800 dark:text-white mt-2">{stats.total_pengumpulan}</p>
          <span className="text-[11px] text-purple-600 dark:text-purple-400 mt-1 block">Submission terkirim</span>
        </div>

        <div className="bg-white dark:bg-[#1B2433] rounded-[18px] p-5 border border-slate-200/80 dark:border-slate-800 shadow-sm hover:shadow-md transition-all duration-200">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Tugas Dinilai</span>
            <div className="w-10 h-10 rounded-xl bg-teal-50 dark:bg-teal-950/50 flex items-center justify-center text-teal-600 dark:text-teal-400">
              <Award size={20} />
            </div>
          </div>
          <p className="text-2xl font-bold text-slate-800 dark:text-white mt-2">{stats.total_dinilai}</p>
          <span className="text-[11px] text-teal-600 dark:text-teal-400 mt-1 block">Sudah diberi nilai</span>
        </div>
      </div>

      {/* Filter & Search Toolbar */}
      <div className="bg-white dark:bg-[#1B2433] rounded-[18px] p-5 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-4">
        <div className="flex flex-col md:flex-row items-center justify-between gap-4">
          <div className="relative w-full md:w-80">
            <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" size={18} />
            <input
              type="text"
              placeholder="Cari judul, deskripsi..."
              value={search}
              onChange={(e) => {
                setSearch(e.target.value)
                setPage(1)
              }}
              className="w-full pl-10 pr-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44] transition-all"
            />
          </div>

          <div className="flex flex-wrap items-center gap-3 w-full md:w-auto">
            {/* Filter Modul Ajar */}
            <div className="flex items-center gap-1.5 text-xs text-slate-500 font-medium">
              <SlidersHorizontal size={14} />
              <span>Filter:</span>
            </div>

            <select
              value={selectedModulAjar}
              onChange={(e) => {
                setSelectedModulAjar(e.target.value)
                setPage(1)
              }}
              className="bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[#0E5C44]"
            >
              <option value="">Semua Modul Ajar</option>
              {(options.modul_ajar || []).map((m) => (
                <option key={m.value || m.id} value={m.value || m.id}>
                  {m.label || m.judul || m.judul_modul}
                </option>
              ))}
            </select>

            {/* Filter Kelas */}
            <select
              value={selectedKelas}
              onChange={(e) => {
                setSelectedKelas(e.target.value)
                setPage(1)
              }}
              className="bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[#0E5C44]"
            >
              <option value="">Semua Kelas</option>
              {(options.kelas || []).map((k) => (
                <option key={k.value || k.id} value={k.value || k.id}>
                  {k.label || k.nama_kelas || k.name}
                </option>
              ))}
            </select>

            {/* Filter Tipe */}
            <select
              value={selectedTipe}
              onChange={(e) => {
                setSelectedTipe(e.target.value)
                setPage(1)
              }}
              className="bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[#0E5C44]"
            >
              <option value="">Semua Tipe</option>
              {options.tipe.map((t) => (
                <option key={t.value} value={t.value}>
                  {t.label}
                </option>
              ))}
            </select>

            {/* Filter Status */}
            <select
              value={selectedStatus}
              onChange={(e) => {
                setSelectedStatus(e.target.value)
                setPage(1)
              }}
              className="bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[#0E5C44]"
            >
              <option value="">Semua Status</option>
              {options.status.map((s) => (
                <option key={s.value} value={s.value}>
                  {s.label}
                </option>
              ))}
            </select>

            <button
              onClick={fetchPenugasan}
              className="p-2 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 rounded-xl text-xs transition-colors"
              title="Refresh Data"
            >
              <RefreshCw size={16} />
            </button>
          </div>
        </div>
      </div>

      {/* Main Table Card */}
      <div className="bg-white dark:bg-[#1B2433] rounded-[18px] border border-slate-200/80 dark:border-slate-800 shadow-sm overflow-hidden">
        {loading ? (
          <div className="p-12 text-center text-slate-500">
            <RefreshCw className="animate-spin mx-auto mb-2 text-[#0E5C44]" size={28} />
            <p className="text-sm font-medium">Memuat data penugasan...</p>
          </div>
        ) : dataPenugasan.length === 0 ? (
          <div className="p-12 text-center">
            <div className="w-16 h-16 bg-slate-100 dark:bg-slate-800 rounded-full flex items-center justify-center mx-auto mb-3 text-slate-400">
              <ClipboardList size={32} />
            </div>
            <h3 className="text-base font-semibold text-slate-800 dark:text-white">Belum ada penugasan</h3>
            <p className="text-xs text-slate-500 max-w-sm mx-auto mt-1">
              Silakan tambahkan penugasan atau proyek baru yang terikat dengan Modul Ajar untuk kelas Anda.
            </p>
            <button
              onClick={handleOpenCreateModal}
              className="mt-4 inline-flex items-center gap-2 bg-[#0E5C44] text-white px-4 py-2 rounded-xl text-xs font-semibold hover:bg-[#1E8E5A] transition-colors"
            >
              <Plus size={16} />
              <span>Tambah Penugasan</span>
            </button>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse">
              <thead>
                <tr className="bg-slate-50/80 dark:bg-slate-800/50 border-b border-slate-200/80 dark:border-slate-800 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                  <th className="py-3.5 px-4">Penugasan & Modul Ajar</th>
                  <th className="py-3.5 px-4">Kelas & Guru</th>
                  <th className="py-3.5 px-4">Tipe & Jenis</th>
                  <th className="py-3.5 px-4">Tgl Mulai & Deadline</th>
                  <th className="py-3.5 px-4">Pengumpulan</th>
                  <th className="py-3.5 px-4">Status</th>
                  <th className="py-3.5 px-4 text-right">Aksi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-200/70 dark:divide-slate-800 text-sm">
                {dataPenugasan.map((item) => (
                  <tr
                    key={item.id}
                    className="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors"
                  >
                    {/* Judul & Modul Ajar */}
                    <td className="py-4 px-4">
                      <div className="font-semibold text-slate-800 dark:text-white line-clamp-1">
                        {item.judul || item.judul_tugas}
                      </div>
                      {item.modul_ajar && (
                        <div className="flex items-center gap-1 text-xs text-[#0E5C44] dark:text-emerald-400 mt-1 font-medium">
                          <BookOpen size={12} />
                          <span className="line-clamp-1">{item.modul_ajar.judul}</span>
                        </div>
                      )}
                      {item.lampiran && (
                        <div className="flex items-center gap-1 text-[11px] text-slate-400 mt-0.5">
                          <Paperclip size={10} />
                          <span className="truncate max-w-[200px]">{item.lampiran}</span>
                        </div>
                      )}
                    </td>

                    {/* Kelas & Guru */}
                    <td className="py-4 px-4">
                      <div className="text-xs font-semibold text-slate-700 dark:text-slate-300">
                        {item.kelas?.nama_kelas || 'Semua Kelas'}
                      </div>
                      <div className="text-[11px] text-slate-500 mt-0.5">
                        Guru: {item.guru?.nama || '-'}
                      </div>
                    </td>

                    {/* Tipe & Jenis */}
                    <td className="py-4 px-4">
                      <div className="flex flex-col gap-1 items-start">
                        <span
                          className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-medium ${
                            item.tipe === 'kelompok' || item.tipe_tugas === 'kelompok'
                              ? 'bg-purple-50 text-purple-700 dark:bg-purple-950/40 dark:text-purple-400 border border-purple-200 dark:border-purple-800'
                              : 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-400 border border-blue-200 dark:border-blue-800'
                          }`}
                        >
                          {item.tipe === 'kelompok' || item.tipe_tugas === 'kelompok' ? 'Kelompok' : 'Individu'}
                        </span>
                        <span className="text-[11px] text-slate-500 capitalize">
                          {item.jenis_tugas || 'tugas'} ({item.nilai_maksimal || 100} poin)
                        </span>
                      </div>
                    </td>

                    {/* Tanggal Mulai & Deadline */}
                    <td className="py-4 px-4">
                      <div className="text-xs text-slate-700 dark:text-slate-300 flex items-center gap-1">
                        <Clock size={12} className="text-slate-400" />
                        <span>{item.deadline || item.tanggal_selesai || '-'}</span>
                      </div>
                      <div className="text-[11px] text-slate-400 mt-0.5">
                        Mulai: {item.tanggal_mulai || '-'}
                      </div>
                    </td>

                    {/* Pengumpulan */}
                    <td className="py-4 px-4">
                      <div className="flex items-center gap-2">
                        <div className="text-xs font-semibold text-slate-800 dark:text-white">
                          {item.total_pengumpulan ?? 0} Siswa
                        </div>
                        {item.total_dinilai > 0 && (
                          <span className="text-[10px] bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300 px-1.5 py-0.5 rounded font-medium">
                            {item.total_dinilai} dinilai
                          </span>
                        )}
                      </div>
                    </td>

                    {/* Status */}
                    <td className="py-4 px-4">
                      <button
                        onClick={() => handleTogglePublish(item)}
                        className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold transition-all ${
                          item.is_published || item.status === 'dipublikasikan'
                            ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 hover:bg-emerald-100'
                            : 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300 border border-amber-200 dark:border-amber-800 hover:bg-amber-100'
                        }`}
                      >
                        {item.is_published || item.status === 'dipublikasikan' ? (
                          <>
                            <Globe size={12} />
                            <span>Dipublikasikan</span>
                          </>
                        ) : (
                          <>
                            <Lock size={12} />
                            <span>Draft</span>
                          </>
                        )}
                      </button>
                    </td>

                    {/* Actions */}
                    <td className="py-4 px-4 text-right">
                      <div className="flex items-center justify-end gap-1.5">
                        <button
                          onClick={() => handleOpenDetail(item)}
                          className="p-1.5 text-slate-500 hover:text-[#0E5C44] hover:bg-emerald-50 dark:hover:bg-emerald-950/50 rounded-lg transition-colors"
                          title="Detail Pengumpulan & Nilai"
                        >
                          <Eye size={16} />
                        </button>

                        <button
                          onClick={() => handleOpenEditModal(item)}
                          className="p-1.5 text-slate-500 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-950/50 rounded-lg transition-colors"
                          title="Edit Penugasan"
                        >
                          <Edit3 size={16} />
                        </button>

                        <button
                          onClick={() => handleDelete(item.id, item.judul || item.judul_tugas)}
                          className="p-1.5 text-slate-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/50 rounded-lg transition-colors"
                          title="Hapus Penugasan"
                        >
                          <Trash2 size={16} />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {/* Pagination */}
        {pagination.last_page > 1 && (
          <div className="px-6 py-4 bg-slate-50/50 dark:bg-slate-800/30 border-t border-slate-200/80 dark:border-slate-800 flex items-center justify-between text-xs text-slate-500">
            <div>
              Halaman <strong>{pagination.current_page}</strong> dari <strong>{pagination.last_page}</strong> (Total {pagination.total} penugasan)
            </div>
            <div className="flex items-center gap-2">
              <button
                disabled={page <= 1}
                onClick={() => setPage((p) => Math.max(1, p - 1))}
                className="p-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 disabled:opacity-40"
              >
                <ChevronLeft size={16} />
              </button>
              <button
                disabled={page >= pagination.last_page}
                onClick={() => setPage((p) => Math.min(pagination.last_page, p + 1))}
                className="p-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 disabled:opacity-40"
              >
                <ChevronRight size={16} />
              </button>
            </div>
          </div>
        )}
      </div>

      {/* CREATE / EDIT MODAL */}
      {isModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-fade-in">
          <div className="bg-white dark:bg-[#1B2433] rounded-[18px] border border-slate-200 dark:border-slate-800 shadow-2xl w-full max-w-2xl overflow-hidden max-h-[90vh] flex flex-col">
            {/* Modal Header */}
            <div className="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-800/50">
              <div className="flex items-center gap-2">
                <div className="w-9 h-9 rounded-xl bg-emerald-100 dark:bg-emerald-950/60 flex items-center justify-center text-[#0E5C44] dark:text-emerald-400">
                  <ClipboardList size={20} />
                </div>
                <div>
                  <h3 className="text-base font-bold text-slate-800 dark:text-white">
                    {editId ? 'Edit Penugasan' : 'Buat Penugasan Baru'}
                  </h3>
                  <p className="text-xs text-slate-500">
                    Isi detail tugas yang terhubung dengan Modul Ajar
                  </p>
                </div>
              </div>
              <button
                onClick={() => setIsModalOpen(false)}
                className="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800"
              >
                <X size={18} />
              </button>
            </div>

            {/* Modal Form */}
            <form onSubmit={handleFormSubmit} className="p-6 overflow-y-auto space-y-4 flex-1">
              {/* Judul Penugasan */}
              <div>
                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                  Judul Penugasan <span className="text-red-500">*</span>
                </label>
                <input
                  type="text"
                  required
                  placeholder="Contoh: Penugasan 1: Analisis Hukum Newton..."
                  value={formData.judul}
                  onChange={(e) => setFormData({ ...formData, judul: e.target.value })}
                  className="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44]"
                />
              </div>

              {/* Modul Ajar Relasi */}
              <div>
                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                  Tautkan ke Modul Ajar (1 : N)
                </label>
                <select
                  value={formData.modul_ajar_id}
                  onChange={(e) => setFormData({ ...formData, modul_ajar_id: e.target.value })}
                  className="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44]"
                >
                  <option value="">-- Opsional: Pilih Modul Ajar --</option>
                  {(options.modul_ajar || []).map((m) => (
                    <option key={m.value || m.id} value={m.value || m.id}>
                      {m.label || m.judul || m.judul_modul}
                    </option>
                  ))}
                </select>
              </div>

              {/* Grid 2 Kolom: Kelas & Guru */}
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Target Kelas / Rombel
                  </label>
                  <select
                    value={formData.kelas_id}
                    onChange={(e) => setFormData({ ...formData, kelas_id: e.target.value })}
                    className="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44]"
                  >
                    <option value="">-- Pilih Kelas --</option>
                    {(options.kelas || []).map((k) => (
                      <option key={k.value || k.id} value={k.value || k.id}>
                        {k.label || k.nama_kelas || k.name}
                      </option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Guru Pengampu
                  </label>
                  <select
                    value={formData.guru_id}
                    onChange={(e) => setFormData({ ...formData, guru_id: e.target.value })}
                    className="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44]"
                  >
                    <option value="">-- Pilih Guru --</option>
                    {(options.guru || []).map((g) => (
                      <option key={g.value || g.id} value={g.value || g.id}>
                        {g.label || g.nama || g.nama_lengkap}
                      </option>
                    ))}
                  </select>
                </div>
              </div>

              {/* Grid 2 Kolom: Tipe & Jenis */}
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Tipe Pengerjaan
                  </label>
                  <select
                    value={formData.tipe}
                    onChange={(e) => setFormData({ ...formData, tipe: e.target.value })}
                    className="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44]"
                  >
                    {options.tipe.map((t) => (
                      <option key={t.value} value={t.value}>
                        {t.label}
                      </option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Jenis Tugas
                  </label>
                  <select
                    value={formData.jenis_tugas}
                    onChange={(e) => setFormData({ ...formData, jenis_tugas: e.target.value })}
                    className="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44]"
                  >
                    {options.jenis.map((j) => (
                      <option key={j.value} value={j.value}>
                        {j.label}
                      </option>
                    ))}
                  </select>
                </div>
              </div>

              {/* Grid 2 Kolom: Nilai Maksimal & Bobot */}
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Nilai Maksimal
                  </label>
                  <input
                    type="number"
                    min="0"
                    max="1000"
                    value={formData.nilai_maksimal}
                    onChange={(e) => setFormData({ ...formData, nilai_maksimal: e.target.value })}
                    className="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44]"
                  />
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Bobot Nilai (%)
                  </label>
                  <input
                    type="number"
                    min="0"
                    max="100"
                    value={formData.bobot_persen}
                    onChange={(e) => setFormData({ ...formData, bobot_persen: e.target.value })}
                    className="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44]"
                  />
                </div>
              </div>

              {/* Grid 2 Kolom: Tanggal Mulai & Tanggal Selesai (Deadline) */}
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Tanggal Mulai
                  </label>
                  <input
                    type="datetime-local"
                    value={formData.tanggal_mulai}
                    onChange={(e) => setFormData({ ...formData, tanggal_mulai: e.target.value })}
                    className="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44]"
                  />
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Tanggal Selesai (Deadline)
                  </label>
                  <input
                    type="datetime-local"
                    value={formData.tanggal_selesai}
                    onChange={(e) => setFormData({ ...formData, tanggal_selesai: e.target.value })}
                    className="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44]"
                  />
                </div>
              </div>

              {/* Deskripsi & Instruksi */}
              <div>
                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                  Deskripsi Tugas
                </label>
                <textarea
                  rows="2"
                  placeholder="Ringkasan atau gambaran umum penugasan..."
                  value={formData.deskripsi}
                  onChange={(e) => setFormData({ ...formData, deskripsi: e.target.value })}
                  className="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44]"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                  Instruksi Pengerjaan
                </label>
                <textarea
                  rows="3"
                  placeholder="Langkah-langkah pengerjaan, format berkas yang diminta, dsb..."
                  value={formData.instruksi}
                  onChange={(e) => setFormData({ ...formData, instruksi: e.target.value })}
                  className="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44]"
                />
              </div>

              {/* File Lampiran / URL */}
              <div>
                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                  File Lampiran / Link (Opsional)
                </label>
                <input
                  type="text"
                  placeholder="URL berkas lampiran (e.g. https://...)"
                  value={formData.lampiran}
                  onChange={(e) => setFormData({ ...formData, lampiran: e.target.value })}
                  className="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44]"
                />
              </div>

              {/* Status Publikasi */}
              <div>
                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                  Status Publikasi
                </label>
                <select
                  value={formData.status}
                  onChange={(e) => setFormData({ ...formData, status: e.target.value })}
                  className="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44]"
                >
                  <option value="dipublikasikan">Dipublikasikan (Dapat diakses Siswa)</option>
                  <option value="draft">Draft (Simpan Sementara)</option>
                </select>
              </div>

              {/* Action Buttons */}
              <div className="pt-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-end gap-3">
                <button
                  type="button"
                  onClick={() => setIsModalOpen(false)}
                  className="px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={formLoading}
                  className="px-5 py-2.5 rounded-xl bg-[#0E5C44] hover:bg-[#1E8E5A] text-white text-xs font-semibold transition-all duration-200 flex items-center gap-2 disabled:opacity-50"
                >
                  {formLoading && <RefreshCw size={14} className="animate-spin" />}
                  <span>{editId ? 'Simpan Perubahan' : 'Buat Penugasan'}</span>
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* DETAIL & GRADING DRAWER / MODAL */}
      {isDetailOpen && selectedPenugasan && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-fade-in">
          <div className="bg-white dark:bg-[#1B2433] rounded-[18px] border border-slate-200 dark:border-slate-800 shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden">
            {/* Detail Header */}
            <div className="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between bg-slate-50/60 dark:bg-slate-800/60">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-xl bg-[#0E5C44] text-white flex items-center justify-center font-bold">
                  <ClipboardList size={22} />
                </div>
                <div>
                  <h3 className="text-base font-bold text-slate-800 dark:text-white line-clamp-1">
                    {selectedPenugasan.judul || selectedPenugasan.judul_tugas}
                  </h3>
                  <div className="flex items-center gap-3 text-xs text-slate-500 mt-0.5">
                    <span>Modul Ajar: {selectedPenugasan.modul_ajar?.judul || 'Tidak ditautkan'}</span>
                    <span>•</span>
                    <span>Deadline: {selectedPenugasan.deadline || selectedPenugasan.tanggal_selesai || '-'}</span>
                  </div>
                </div>
              </div>
              <button
                onClick={() => setIsDetailOpen(false)}
                className="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800"
              >
                <X size={20} />
              </button>
            </div>

            {/* Content Area */}
            <div className="p-6 overflow-y-auto space-y-6 flex-1">
              {/* Deskripsi & Instruksi Card */}
              <div className="bg-slate-50 dark:bg-slate-900/60 rounded-xl p-4 border border-slate-200 dark:border-slate-800 space-y-2">
                <h4 className="text-xs font-bold uppercase tracking-wider text-slate-500">Deskripsi & Instruksi Pengerjaan</h4>
                <p className="text-sm text-slate-700 dark:text-slate-300 whitespace-pre-line">
                  {selectedPenugasan.deskripsi || 'Tidak ada deskripsi.'}
                </p>
                {selectedPenugasan.instruksi && (
                  <div className="mt-3 pt-3 border-t border-slate-200 dark:border-slate-800 text-xs text-slate-600 dark:text-slate-400">
                    <strong className="text-slate-800 dark:text-slate-200">Instruksi:</strong>
                    <p className="mt-1 whitespace-pre-line">{selectedPenugasan.instruksi}</p>
                  </div>
                )}
                {selectedPenugasan.lampiran && (
                  <div className="mt-2 pt-2 border-t border-slate-200 dark:border-slate-800 flex items-center gap-2 text-xs text-[#0E5C44]">
                    <Paperclip size={14} />
                    <a
                      href={selectedPenugasan.lampiran}
                      target="_blank"
                      rel="noreferrer"
                      className="hover:underline font-semibold"
                    >
                      Buka File Lampiran Guru
                    </a>
                  </div>
                )}
              </div>

              {/* Submissions List Header */}
              <div className="flex items-center justify-between">
                <div>
                  <h4 className="text-base font-bold text-slate-800 dark:text-white">Pengumpulan Tugas Siswa</h4>
                  <p className="text-xs text-slate-500">Daftar siswa yang telah mengumpulkan jawaban beserta form input nilai</p>
                </div>
                <span className="text-xs bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 px-3 py-1 rounded-full font-bold">
                  {selectedPenugasan.pengumpulan?.length || 0} Pengumpulan
                </span>
              </div>

              {/* Submissions Table / Cards */}
              {!selectedPenugasan.pengumpulan || selectedPenugasan.pengumpulan.length === 0 ? (
                <div className="p-8 text-center bg-slate-50 dark:bg-slate-900/40 rounded-xl border border-dashed border-slate-200 dark:border-slate-800">
                  <UserCheck className="mx-auto text-slate-400 mb-2" size={32} />
                  <p className="text-xs text-slate-500">Belum ada siswa yang mengumpulkan tugas ini.</p>
                </div>
              ) : (
                <div className="space-y-3">
                  {selectedPenugasan.pengumpulan.map((sub) => (
                    <div
                      key={sub.id}
                      className="bg-white dark:bg-[#1B2433] rounded-xl p-4 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-3"
                    >
                      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                        <div className="flex items-center gap-3">
                          <div className="w-8 h-8 rounded-full bg-emerald-100 dark:bg-emerald-900/50 text-[#0E5C44] dark:text-emerald-400 flex items-center justify-center font-bold text-xs">
                            {sub.siswa?.nama ? sub.siswa.nama.charAt(0) : 'S'}
                          </div>
                          <div>
                            <h5 className="text-sm font-bold text-slate-800 dark:text-white">
                              {sub.siswa?.nama || 'Siswa'}
                            </h5>
                            <span className="text-[11px] text-slate-400">
                              NISN: {sub.siswa?.nisn || '-'} • Waktu Kumpul: {sub.waktu_kumpul || '-'}
                            </span>
                          </div>
                        </div>

                        <div className="flex items-center gap-2">
                          {sub.nilai_guru !== null ? (
                            <span className="bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300 font-bold px-3 py-1 rounded-lg text-xs border border-emerald-200 dark:border-emerald-800 flex items-center gap-1">
                              <Award size={14} />
                              Nilai: {sub.nilai_guru} / {selectedPenugasan.nilai_maksimal || 100}
                            </span>
                          ) : (
                            <span className="bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300 font-semibold px-3 py-1 rounded-lg text-xs border border-amber-200 dark:border-amber-800">
                              Belum Dinilai
                            </span>
                          )}

                          <button
                            onClick={() => handleStartGrading(sub)}
                            className="px-3 py-1 rounded-lg bg-[#0E5C44] text-white hover:bg-[#1E8E5A] text-xs font-semibold transition-colors"
                          >
                            {sub.nilai_guru !== null ? 'Edit Nilai' : 'Beri Nilai'}
                          </button>
                        </div>
                      </div>

                      {/* Jawaban Siswa */}
                      {sub.jawaban_teks && (
                        <div className="text-xs text-slate-700 dark:text-slate-300 bg-slate-50 dark:bg-slate-900/50 p-3 rounded-lg">
                          <strong className="text-slate-900 dark:text-white block mb-1">Jawaban Teks:</strong>
                          <p className="whitespace-pre-line">{sub.jawaban_teks}</p>
                        </div>
                      )}

                      {/* Link / File Jawaban */}
                      {(sub.file_path || sub.url_link) && (
                        <div className="flex flex-wrap gap-2 text-xs">
                          {sub.file_path && (
                            <a
                              href={sub.file_path}
                              target="_blank"
                              rel="noreferrer"
                              className="inline-flex items-center gap-1 text-[#0E5C44] hover:underline font-semibold bg-emerald-50 dark:bg-emerald-950/40 px-2.5 py-1 rounded-md"
                            >
                              <Paperclip size={12} />
                              <span>Berkas Siswa</span>
                            </a>
                          )}
                          {sub.url_link && (
                            <a
                              href={sub.url_link}
                              target="_blank"
                              rel="noreferrer"
                              className="inline-flex items-center gap-1 text-blue-600 hover:underline font-semibold bg-blue-50 dark:bg-blue-950/40 px-2.5 py-1 rounded-md"
                            >
                              <Globe size={12} />
                              <span>Tautan Jawaban</span>
                            </a>
                          )}
                        </div>
                      )}

                      {/* Catatan Guru */}
                      {sub.catatan_guru && (
                        <div className="text-xs text-emerald-800 dark:text-emerald-300 bg-emerald-50/60 dark:bg-emerald-950/30 p-2.5 rounded-lg border border-emerald-100 dark:border-emerald-900">
                          <strong>Catatan Guru:</strong> {sub.catatan_guru}
                        </div>
                      )}

                      {/* Inline Form Input Nilai jika tombol Beri Nilai diklik */}
                      {gradingStudentId === sub.siswa_id && (
                        <form onSubmit={handleSaveGrade} className="mt-3 p-4 bg-emerald-50/80 dark:bg-slate-900 rounded-xl border border-emerald-200 dark:border-slate-700 space-y-3">
                          <h6 className="text-xs font-bold text-[#0E5C44] dark:text-emerald-400">Form Input Nilai Guru</h6>
                          <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                              <label className="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                Nilai (0 - {selectedPenugasan.nilai_maksimal || 100})
                              </label>
                              <input
                                type="number"
                                step="0.1"
                                required
                                min="0"
                                max={selectedPenugasan.nilai_maksimal || 100}
                                value={gradingForm.nilai_guru}
                                onChange={(e) => setGradingForm({ ...gradingForm, nilai_guru: e.target.value })}
                                className="w-full px-3 py-1.5 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-xs font-bold focus:outline-none focus:ring-2 focus:ring-[#0E5C44]"
                              />
                            </div>
                            <div className="sm:col-span-2">
                              <label className="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                Catatan / Umpan Balik Guru
                              </label>
                              <input
                                type="text"
                                placeholder="Apresiasi atau evaluasi..."
                                value={gradingForm.catatan_guru}
                                onChange={(e) => setGradingForm({ ...gradingForm, catatan_guru: e.target.value })}
                                className="w-full px-3 py-1.5 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-[#0E5C44]"
                              />
                            </div>
                          </div>

                          <div className="flex justify-end gap-2 pt-1">
                            <button
                              type="button"
                              onClick={() => setGradingStudentId(null)}
                              className="px-3 py-1 rounded-lg border border-slate-300 text-xs font-semibold text-slate-600 hover:bg-slate-100"
                            >
                              Batal
                            </button>
                            <button
                              type="submit"
                              disabled={gradingLoading}
                              className="px-4 py-1 rounded-lg bg-[#0E5C44] text-white hover:bg-[#1E8E5A] text-xs font-semibold flex items-center gap-1"
                            >
                              {gradingLoading && <RefreshCw size={12} className="animate-spin" />}
                              <span>Simpan Nilai</span>
                            </button>
                          </div>
                        </form>
                      )}
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
