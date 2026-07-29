import React, { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import Swal from 'sweetalert2'
import {
  BookOpen,
  Plus,
  Search,
  Filter,
  CheckCircle,
  XCircle,
  ChevronLeft,
  ChevronRight,
  Sparkles,
  Edit2,
  Trash2,
  RefreshCw,
  Eye,
  Layers,
  Award,
  Download,
  Upload,
  FileSpreadsheet,
  FileText,
  Copy,
  Clock,
  Printer,
  History,
  FileCheck,
  Send,
  Archive,
  CheckSquare,
  AlertCircle,
  User,
  GraduationCap,
  Calendar,
  Tag,
  Book,
} from 'lucide-react'
import { lmsModulAjarService } from '../services/lmsModulAjarService'

const FASE_LIST = ['Fase A', 'Fase B', 'Fase C', 'Fase D', 'Fase E', 'Fase F']
const STATUS_LIST = ['Draft', 'Review', 'Publish', 'Arsip']

export default function LmsModulAjarPage() {
  const queryClient = useQueryClient()

  // State Filter & Paginasi
  const [activeTab, setActiveTab] = useState('daftar') // 'daftar' | 'dashboard'
  const [search, setSearch] = useState('')
  const [selectedUnitFilter, setSelectedUnitFilter] = useState('')
  const [selectedMapelFilter, setSelectedMapelFilter] = useState('')
  const [selectedFaseFilter, setSelectedFaseFilter] = useState('')
  const [selectedStatusFilter, setSelectedStatusFilter] = useState('')
  const [denganSampahFilter, setDenganSampahFilter] = useState('')
  const [page, setPage] = useState(1)
  const [perPage] = useState(10)

  // State Modal
  const [isFormModalOpen, setIsFormModalOpen] = useState(false)
  const [isDetailModalOpen, setIsDetailModalOpen] = useState(false)
  const [isRevisionModalOpen, setIsRevisionModalOpen] = useState(false)
  const [isPreviewModalOpen, setIsPreviewModalOpen] = useState(false)
  const [isImportModalOpen, setIsImportModalOpen] = useState(false)

  const [selectedModul, setSelectedModul] = useState(null)
  const [formStep, setFormStep] = useState(1) // 1: Identitas, 2: Content, 3: Aktivitas, 4: Asesmen

  // Form State
  const [formData, setFormData] = useState({
    unit_pendidikan_id: '',
    tahun_ajaran_id: '',
    semester_id: '',
    kurikulum_id: '',
    mata_pelajaran_id: '',
    guru_id: '',
    kelas_id: '',
    rombel_id: '',
    cp_id: '',
    tp_id: '',
    kode_modul: '',
    judul_modul: '',
    fase: 'Fase D',
    semester: 'Ganjil',
    alokasi_waktu_jp: 4,
    tujuan_pembelajaran: '',
    profil_pelajar_pancasila: 'Beriman dan Bertakwa kepada Tuhan YME, Mandiri, Bernalar Kritis',
    target_peserta_didik: 'Peserta Didik Reguler (28-32 Siswa)',
    model_pembelajaran: 'Problem Based Learning (PBL)',
    metode_pembelajaran: 'Diskusi, Ceramah Interaktif, Presentasi Kelompok',
    media_pembelajaran: 'Slide PPT Interaktif, Video Pembelajaran, Canva, LKPD',
    sumber_belajar: 'Buku Cetak Kemendikbudristek & Portal LMS Sekolah',
    kegiatan_pendahuluan: '1. Salam, Doa pembuka, dan apersepsi.\n2. Guru menjelaskan tujuan pembelajaran harian.',
    kegiatan_inti: '1. Siswa membentuk kelompok dan mengamati materi.\n2. Diskusi dan penyusunan laporan kelompok.',
    kegiatan_penutup: '1. Refleksi pembelajaran.\n2. Kesimpulan bersama dan doa penutup.',
    asesmen_awal: 'Kuis diagnosis 5 pertanyaan singkat.',
    asesmen_proses: 'Observasi keaktifan diskusi dan kerja kelompok.',
    asesmen_akhir: 'Penilaian produk LKPD dan tes tertulis.',
    rencana_penilaian: 'Pengetahuan 40%, Keterampilan 40%, Sikap 20%',
    refleksi_guru: '',
    status: 'Draft',
    deskripsi: '',
    versi: '1.0',
    naikkan_versi: false,
    catatan_revisi: '',
  })

  // Queries
  const { data: optionsData } = useQuery({
    queryKey: ['lmsModulOptions'],
    queryFn: () => lmsModulAjarService.getOptions(),
  })

  const options = optionsData?.data || {}

  const { data: modulsData, isLoading, refetch } = useQuery({
    queryKey: ['lmsModuls', search, selectedUnitFilter, selectedMapelFilter, selectedFaseFilter, selectedStatusFilter, denganSampahFilter, page],
    queryFn: () =>
      lmsModulAjarService.getAll({
        search,
        unit_pendidikan_id: selectedUnitFilter,
        mata_pelajaran_id: selectedMapelFilter,
        fase: selectedFaseFilter,
        status: selectedStatusFilter,
        dengan_sampah: denganSampahFilter,
        page,
        per_page: perPage,
      }),
  })

  const moduls = modulsData?.data || []
  const meta = modulsData?.meta || {}
  const stats = modulsData?.statistik || {}

  const { data: revisionsData } = useQuery({
    queryKey: ['lmsModulRevisions', selectedModul?.id],
    queryFn: () => lmsModulAjarService.getRevisions(selectedModul.id),
    enabled: !!selectedModul?.id && isRevisionModalOpen,
  })

  // Mutations
  const createMutation = useMutation({
    mutationFn: (payload) => lmsModulAjarService.create(payload),
    onSuccess: () => {
      queryClient.invalidateQueries(['lmsModuls'])
      setIsFormModalOpen(false)
      Swal.fire('Berhasil!', 'Modul Ajar berhasil dibuat.', 'success')
    },
    onError: (err) => {
      Swal.fire('Gagal!', err?.response?.data?.message || 'Terjadi kesalahan saat menyimpan.', 'error')
    },
  })

  const updateMutation = useMutation({
    mutationFn: ({ id, payload }) => lmsModulAjarService.update(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries(['lmsModuls'])
      setIsFormModalOpen(false)
      Swal.fire('Berhasil!', 'Modul Ajar berhasil diperbarui.', 'success')
    },
    onError: (err) => {
      Swal.fire('Gagal!', err?.response?.data?.message || 'Terjadi kesalahan saat mengedit.', 'error')
    },
  })

  const deleteMutation = useMutation({
    mutationFn: (id) => lmsModulAjarService.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries(['lmsModuls'])
      Swal.fire('Terhapus!', 'Modul Ajar telah dipindahkan ke sampah.', 'success')
    },
  })

  const restoreMutation = useMutation({
    mutationFn: (id) => lmsModulAjarService.restore(id),
    onSuccess: () => {
      queryClient.invalidateQueries(['lmsModuls'])
      Swal.fire('Dipulihkan!', 'Modul Ajar telah dipulihkan.', 'success')
    },
  })

  const publishMutation = useMutation({
    mutationFn: (id) => lmsModulAjarService.publish(id),
    onSuccess: () => {
      queryClient.invalidateQueries(['lmsModuls'])
      Swal.fire('Dipublikasikan!', 'Modul Ajar berstatus Publish.', 'success')
    },
  })

  const duplicateMutation = useMutation({
    mutationFn: (id) => lmsModulAjarService.duplicate(id),
    onSuccess: () => {
      queryClient.invalidateQueries(['lmsModuls'])
      Swal.fire('Tersalin!', 'Salinan Modul Ajar berhasil dibuat sebagai Draft.', 'success')
    },
  })

  // Handlers
  const handleOpenAddModal = () => {
    setSelectedModul(null)
    setFormData({
      unit_pendidikan_id: options.education_units?.[0]?.id || '',
      tahun_ajaran_id: options.academic_years?.[0]?.id || '',
      semester_id: options.semesters?.[0]?.id || '',
      kurikulum_id: options.kurikulums?.[0]?.id || '',
      mata_pelajaran_id: options.subjects?.[0]?.id || '',
      guru_id: options.teachers?.[0]?.id || '',
      kelas_id: options.classes?.[0]?.id || '',
      rombel_id: '',
      cp_id: options.capaian_pembelajaran?.[0]?.id || '',
      tp_id: options.tujuan_pembelajaran?.[0]?.id || '',
      kode_modul: 'MA-' + Math.floor(100 + Math.random() * 900),
      judul_modul: '',
      fase: 'Fase D',
      semester: 'Ganjil',
      alokasi_waktu_jp: 4,
      tujuan_pembelajaran: '',
      profil_pelajar_pancasila: 'Beriman dan Bertakwa kepada Tuhan YME, Mandiri, Bernalar Kritis',
      target_peserta_didik: 'Peserta Didik Reguler (28-32 Siswa)',
      model_pembelajaran: 'Problem Based Learning (PBL)',
      metode_pembelajaran: 'Diskusi, Ceramah Interaktif, Presentasi Kelompok',
      media_pembelajaran: 'Slide PPT Interaktif, Video Pembelajaran, Canva, LKPD',
      sumber_belajar: 'Buku Cetak Kemendikbudristek & Portal LMS Sekolah',
      kegiatan_pendahuluan: '1. Salam, Doa pembuka, dan apersepsi.\n2. Guru menjelaskan tujuan pembelajaran harian.',
      kegiatan_inti: '1. Siswa membentuk kelompok dan mengamati materi.\n2. Diskusi dan penyusunan laporan kelompok.',
      kegiatan_penutup: '1. Refleksi pembelajaran.\n2. Kesimpulan bersama dan doa penutup.',
      asesmen_awal: 'Kuis diagnosis 5 pertanyaan singkat.',
      asesmen_proses: 'Observasi keaktifan diskusi dan kerja kelompok.',
      asesmen_akhir: 'Penilaian produk LKPD dan tes tertulis.',
      rencana_penilaian: 'Pengetahuan 40%, Keterampilan 40%, Sikap 20%',
      refleksi_guru: '',
      status: 'Draft',
      deskripsi: '',
      versi: '1.0',
      naikkan_versi: false,
      catatan_revisi: '',
    })
    setFormStep(1)
    setIsFormModalOpen(true)
  }

  const handleOpenEditModal = (item) => {
    setSelectedModul(item)
    setFormData({
      unit_pendidikan_id: item.unit_pendidikan_id || '',
      tahun_ajaran_id: item.tahun_ajaran_id || '',
      semester_id: item.semester_id || '',
      kurikulum_id: item.kurikulum_id || '',
      mata_pelajaran_id: item.mata_pelajaran_id || '',
      guru_id: item.guru_id || '',
      kelas_id: item.kelas_id || '',
      rombel_id: item.rombel_id || '',
      cp_id: item.cp_id || '',
      tp_id: item.tp_id || '',
      kode_modul: item.kode_modul || '',
      judul_modul: item.judul_modul || '',
      fase: item.fase || 'Fase D',
      semester: item.semester || 'Ganjil',
      alokasi_waktu_jp: item.alokasi_waktu_jp || 4,
      tujuan_pembelajaran: item.tujuan_pembelajaran || '',
      profil_pelajar_pancasila: item.profil_pelajar_pancasila || '',
      target_peserta_didik: item.target_peserta_didik || '',
      model_pembelajaran: item.model_pembelajaran || '',
      metode_pembelajaran: item.metode_pembelajaran || '',
      media_pembelajaran: item.media_pembelajaran || '',
      sumber_belajar: item.sumber_belajar || '',
      kegiatan_pendahuluan: item.kegiatan_pendahuluan || '',
      kegiatan_inti: item.kegiatan_inti || '',
      kegiatan_penutup: item.kegiatan_penutup || '',
      asesmen_awal: item.asesmen_awal || '',
      asesmen_proses: item.asesmen_proses || '',
      asesmen_akhir: item.asesmen_akhir || '',
      rencana_penilaian: item.rencana_penilaian || '',
      refleksi_guru: item.refleksi_guru || '',
      status: item.status || 'Draft',
      deskripsi: item.deskripsi || '',
      versi: item.versi || '1.0',
      naikkan_versi: false,
      catatan_revisi: '',
    })
    setFormStep(1)
    setIsFormModalOpen(true)
  }

  const handleSubmitForm = (e) => {
    e.preventDefault()
    if (!formData.judul_modul) {
      Swal.fire('Peringatan', 'Judul Modul wajib diisi!', 'warning')
      return
    }

    if (selectedModul) {
      updateMutation.mutate({ id: selectedModul.id, payload: formData })
    } else {
      createMutation.mutate(formData)
    }
  }

  const handleDelete = (id) => {
    Swal.fire({
      title: 'Hapus Modul Ajar?',
      text: 'Modul ajar ini akan dipindahkan ke folder sampah.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      confirmButtonText: 'Ya, Hapus',
      cancelButtonText: 'Batal',
    }).then((res) => {
      if (res.isConfirmed) {
        deleteMutation.mutate(id)
      }
    })
  }

  const handleExportExcel = () => {
    window.open(`${import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api'}/lms/modul-ajar/export/excel`, '_blank')
  }

  return (
    <div className="min-h-screen bg-[#F7F9FC] dark:bg-[#0F172A] p-4 lg:p-8 text-slate-800 dark:text-slate-100 transition-colors duration-200">
      {/* Header Banner Modern Enterprise SaaS */}
      <div className="relative mb-8 overflow-hidden rounded-[18px] bg-gradient-to-r from-[#0E5C44] via-[#1E8E5A] to-[#3FBF75] p-6 lg:p-8 text-white shadow-xl">
        <div className="absolute right-0 top-0 -mr-12 -mt-12 h-64 w-64 rounded-full bg-white/10 blur-2xl pointer-events-none" />
        <div className="relative z-10 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
          <div>
            <div className="inline-flex items-center gap-2 rounded-full bg-white/20 px-3 py-1 text-xs font-semibold uppercase tracking-wider backdrop-blur-md mb-3">
              <Sparkles className="h-3.5 w-3.5 text-yellow-300" />
              Perencanaan Pembelajaran Merdeka & SIT
            </div>
            <h1 className="text-2xl lg:text-3xl font-extrabold tracking-tight">Modul Ajar (RPP Digital)</h1>
            <p className="mt-2 max-w-2xl text-sm lg:text-base text-emerald-100">
              Pusat perencanaan aktivitas harian Guru yang terintegrasi dengan Kurikulum, CP/TP, Penugasan, Kisi-kisi Ujian, dan Rapor Digital.
            </p>
          </div>

          <div className="flex flex-wrap items-center gap-3">
            <button
              onClick={handleExportExcel}
              className="inline-flex items-center gap-2 rounded-xl bg-white/10 hover:bg-white/20 border border-white/30 px-4 py-2.5 text-sm font-medium text-white transition-all shadow-sm hover:scale-[1.02] active:scale-95"
            >
              <Download className="h-4 w-4" /> Export CSV
            </button>

            <button
              onClick={handleOpenAddModal}
              className="inline-flex items-center gap-2 rounded-xl bg-white text-[#0E5C44] hover:bg-emerald-50 px-5 py-2.5 text-sm font-bold shadow-lg transition-all hover:scale-[1.03] active:scale-95"
            >
              <Plus className="h-4 w-4 stroke-[3]" /> Buat Modul Ajar
            </button>
          </div>
        </div>
      </div>

      {/* KPI Cards Grid */}
      <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
        <div className="rounded-[18px] bg-white dark:bg-[#1B2433] p-4 shadow-sm border border-slate-100 dark:border-slate-800">
          <p className="text-xs text-slate-500 font-medium">Total Modul</p>
          <p className="text-2xl font-bold text-[#0E5C44] dark:text-emerald-400 mt-1">{stats.total_modul || 0}</p>
        </div>

        <div className="rounded-[18px] bg-white dark:bg-[#1B2433] p-4 shadow-sm border border-slate-100 dark:border-slate-800">
          <p className="text-xs text-slate-500 font-medium">Draft</p>
          <p className="text-2xl font-bold text-amber-500 mt-1">{stats.total_draft || 0}</p>
        </div>

        <div className="rounded-[18px] bg-white dark:bg-[#1B2433] p-4 shadow-sm border border-slate-100 dark:border-slate-800">
          <p className="text-xs text-slate-500 font-medium">Review</p>
          <p className="text-2xl font-bold text-blue-500 mt-1">{stats.total_review || 0}</p>
        </div>

        <div className="rounded-[18px] bg-white dark:bg-[#1B2433] p-4 shadow-sm border border-slate-100 dark:border-slate-800">
          <p className="text-xs text-slate-500 font-medium">Published</p>
          <p className="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{stats.total_published || 0}</p>
        </div>

        <div className="rounded-[18px] bg-white dark:bg-[#1B2433] p-4 shadow-sm border border-slate-100 dark:border-slate-800">
          <p className="text-xs text-slate-500 font-medium">Arsip</p>
          <p className="text-2xl font-bold text-slate-400 mt-1">{stats.total_archived || 0}</p>
        </div>

        <div className="rounded-[18px] bg-white dark:bg-[#1B2433] p-4 shadow-sm border border-slate-100 dark:border-slate-800">
          <p className="text-xs text-slate-500 font-medium">TP Ter-cover</p>
          <p className="text-2xl font-bold text-indigo-500 mt-1">{stats.total_tp_tercover || 0}</p>
        </div>
      </div>

      {/* Main Card Data Table */}
      <div className="rounded-[18px] bg-white dark:bg-[#1B2433] shadow-xl border border-slate-100 dark:border-slate-800 overflow-hidden">
        {/* Filter Bar */}
        <div className="p-4 lg:p-6 border-b border-slate-100 dark:border-slate-800 space-y-4">
          <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            {/* Search */}
            <div className="relative flex-1 max-w-md">
              <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
              <input
                type="text"
                placeholder="Cari modul, kode, atau judul..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                className="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44]"
              />
            </div>

            {/* Filter Dropdowns */}
            <div className="flex flex-wrap items-center gap-3">
              {options.education_units && options.education_units.length > 0 && (
                <select
                  value={selectedUnitFilter}
                  onChange={(e) => setSelectedUnitFilter(e.target.value)}
                  className="px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-xs font-medium focus:outline-none"
                >
                  <option value="">-- Semua Unit --</option>
                  {options.education_units.map((u) => (
                    <option key={u.id} value={u.id}>{u.name || u.nama}</option>
                  ))}
                </select>
              )}

              {options.subjects && options.subjects.length > 0 && (
                <select
                  value={selectedMapelFilter}
                  onChange={(e) => setSelectedMapelFilter(e.target.value)}
                  className="px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-xs font-medium focus:outline-none"
                >
                  <option value="">-- Semua Mapel --</option>
                  {options.subjects.map((s) => (
                    <option key={s.id} value={s.id}>{s.nama_mapel || s.name || s.kode_mapel || s.code}</option>
                  ))}
                </select>
              )}

              <select
                value={selectedFaseFilter}
                onChange={(e) => setSelectedFaseFilter(e.target.value)}
                className="px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-xs font-medium focus:outline-none"
              >
                <option value="">-- Semua Fase --</option>
                {FASE_LIST.map((f) => (
                  <option key={f} value={f}>{f}</option>
                ))}
              </select>

              <select
                value={selectedStatusFilter}
                onChange={(e) => setSelectedStatusFilter(e.target.value)}
                className="px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-xs font-medium focus:outline-none"
              >
                <option value="">-- Semua Status --</option>
                {STATUS_LIST.map((s) => (
                  <option key={s} value={s}>{s}</option>
                ))}
              </select>

              <select
                value={denganSampahFilter}
                onChange={(e) => setDenganSampahFilter(e.target.value)}
                className="px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-xs font-medium focus:outline-none"
              >
                <option value="">Data Aktif</option>
                <option value="1">Termasuk Sampah</option>
              </select>

              <button
                onClick={() => refetch()}
                className="p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                title="Refresh"
              >
                <RefreshCw className="h-4 w-4 text-slate-500" />
              </button>
            </div>
          </div>
        </div>

        {/* Table Content */}
        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm">
            <thead className="bg-slate-50 dark:bg-slate-900/50 text-slate-500 dark:text-slate-400 font-semibold text-xs uppercase tracking-wider">
              <tr>
                <th className="px-6 py-4">Kode & Judul Modul</th>
                <th className="px-6 py-4">Mata Pelajaran & Guru</th>
                <th className="px-6 py-4">Kelas & Fase</th>
                <th className="px-6 py-4 text-center">Alokasi JP</th>
                <th className="px-6 py-4 text-center">Versi</th>
                <th className="px-6 py-4 text-center">Status</th>
                <th className="px-6 py-4 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
              {isLoading ? (
                <tr>
                  <td colSpan="7" className="px-6 py-12 text-center text-slate-400">
                    <div className="flex justify-center items-center gap-2">
                      <RefreshCw className="h-5 w-5 animate-spin text-[#0E5C44]" />
                      Memuat data Modul Ajar...
                    </div>
                  </td>
                </tr>
              ) : moduls.length === 0 ? (
                <tr>
                  <td colSpan="7" className="px-6 py-12 text-center text-slate-400">
                    Tidak ada data Modul Ajar ditemukan.
                  </td>
                </tr>
              ) : (
                moduls.map((item) => (
                  <tr key={item.id} className="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                    <td className="px-6 py-4">
                      <div className="font-bold text-slate-900 dark:text-white">{item.judul_modul}</div>
                      <div className="text-xs text-slate-400 font-mono mt-0.5">{item.kode_modul || 'MA-AUTO'}</div>
                    </td>
                    <td className="px-6 py-4">
                      <div className="font-medium text-slate-800 dark:text-slate-200">
                        {item.subject?.nama_mapel || item.subject?.name || '-'}
                      </div>
                      <div className="text-xs text-slate-400 flex items-center gap-1 mt-0.5">
                        <User className="h-3 w-3" />
                        {item.guru?.nama_lengkap || item.guru?.nama || item.guru?.name || 'Guru Pengampu'}
                      </div>
                    </td>
                    <td className="px-6 py-4">
                      <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 dark:bg-emerald-950/50 text-[#0E5C44] dark:text-emerald-400 border border-emerald-200/50 dark:border-emerald-800/50">
                        {item.fase}
                      </span>
                      <div className="text-xs text-slate-400 mt-1">{item.kelas?.nama_kelas || item.kelas?.name || 'Semua Kelas'}</div>
                    </td>
                    <td className="px-6 py-4 text-center font-semibold text-slate-700 dark:text-slate-300">
                      {item.alokasi_waktu_jp || 2} JP
                    </td>
                    <td className="px-6 py-4 text-center">
                      <span className="text-xs font-mono bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded-md">
                        v{item.versi || '1.0'}
                      </span>
                    </td>
                    <td className="px-6 py-4 text-center">
                      <span
                        className={`inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold ${
                          item.status === 'Publish'
                            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'
                            : item.status === 'Review'
                            ? 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300'
                            : item.status === 'Arsip'
                            ? 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'
                            : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300'
                        }`}
                      >
                        {item.status}
                      </span>
                    </td>
                    <td className="px-6 py-4 text-right">
                      <div className="flex items-center justify-end gap-1.5">
                        <button
                          onClick={() => {
                            setSelectedModul(item)
                            setIsDetailModalOpen(true)
                          }}
                          className="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 transition-colors"
                          title="Detail overview"
                        >
                          <Eye className="h-4 w-4" />
                        </button>

                        <button
                          onClick={() => {
                            setSelectedModul(item)
                            setIsPreviewModalOpen(true)
                          }}
                          className="p-1.5 rounded-lg hover:bg-emerald-50 dark:hover:bg-emerald-950 text-[#0E5C44] dark:text-emerald-400 transition-colors"
                          title="Cetak/Preview RPP"
                        >
                          <Printer className="h-4 w-4" />
                        </button>

                        <button
                          onClick={() => {
                            setSelectedModul(item)
                            setIsRevisionModalOpen(true)
                          }}
                          className="p-1.5 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-950 text-blue-600 transition-colors"
                          title="Riwayat Revisi"
                        >
                          <History className="h-4 w-4" />
                        </button>

                        <button
                          onClick={() => duplicateMutation.mutate(item.id)}
                          className="p-1.5 rounded-lg hover:bg-purple-50 dark:hover:bg-purple-950 text-purple-600 transition-colors"
                          title="Duplikasi Modul"
                        >
                          <Copy className="h-4 w-4" />
                        </button>

                        {item.status !== 'Publish' && (
                          <button
                            onClick={() => publishMutation.mutate(item.id)}
                            className="p-1.5 rounded-lg hover:bg-emerald-50 text-emerald-600 transition-colors"
                            title="Publikasikan Modul"
                          >
                            <Send className="h-4 w-4" />
                          </button>
                        )}

                        <button
                          onClick={() => handleOpenEditModal(item)}
                          className="p-1.5 rounded-lg hover:bg-amber-50 dark:hover:bg-amber-950 text-amber-600 transition-colors"
                          title="Edit Modul"
                        >
                          <Edit2 className="h-4 w-4" />
                        </button>

                        {item.deleted_at ? (
                          <button
                            onClick={() => restoreMutation.mutate(item.id)}
                            className="p-1.5 rounded-lg hover:bg-emerald-50 text-emerald-600 transition-colors"
                            title="Pulihkan"
                          >
                            <RefreshCw className="h-4 w-4" />
                          </button>
                        ) : (
                          <button
                            onClick={() => handleDelete(item.id)}
                            className="p-1.5 rounded-lg hover:bg-rose-50 dark:hover:bg-rose-950 text-rose-600 transition-colors"
                            title="Hapus"
                          >
                            <Trash2 className="h-4 w-4" />
                          </button>
                        )}
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination Bar */}
        <div className="p-4 border-t border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-500">
          <div>
            Menampilkan {meta.from || 0} - {meta.to || 0} dari total {meta.total || 0} modul
          </div>
          <div className="flex items-center gap-2">
            <button
              disabled={page <= 1}
              onClick={() => setPage((p) => Math.max(1, p - 1))}
              className="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 disabled:opacity-40 hover:bg-slate-50 dark:hover:bg-slate-800"
            >
              Sebelumnya
            </button>
            <span className="font-semibold text-slate-700 dark:text-slate-300">
              {meta.current_page || 1} / {meta.last_page || 1}
            </span>
            <button
              disabled={page >= (meta.last_page || 1)}
              onClick={() => setPage((p) => p + 1)}
              className="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 disabled:opacity-40 hover:bg-slate-50 dark:hover:bg-slate-800"
            >
              Selanjutnya
            </button>
          </div>
        </div>
      </div>

      {/* Modal Add / Edit (Multi-Step Form Wizard) */}
      {isFormModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm overflow-y-auto">
          <div className="w-full max-w-4xl rounded-[18px] bg-white dark:bg-[#1B2433] shadow-2xl overflow-hidden border border-slate-100 dark:border-slate-800 my-8">
            <div className="bg-[#0E5C44] px-6 py-4 text-white flex items-center justify-between">
              <h3 className="font-bold text-lg flex items-center gap-2">
                <BookOpen className="h-5 w-5" />
                {selectedModul ? 'Edit Modul Ajar' : 'Buat Modul Ajar Baru'}
              </h3>
              <button onClick={() => setIsFormModalOpen(false)} className="hover:opacity-80">
                <XCircle className="h-6 w-6" />
              </button>
            </div>

            {/* Step Wizard Header */}
            <div className="flex border-b border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 text-xs">
              {[
                { id: 1, name: 'Identitas & Pemetaan' },
                { id: 2, name: 'Rancangan & Media' },
                { id: 3, name: 'Skenario Aktivitas' },
                { id: 4, name: 'Asesmen & Revisi' },
              ].map((step) => (
                <button
                  key={step.id}
                  type="button"
                  onClick={() => setFormStep(step.id)}
                  className={`flex-1 py-3 px-4 text-center font-bold border-b-2 transition-all ${
                    formStep === step.id
                      ? 'border-[#0E5C44] text-[#0E5C44] dark:text-emerald-400 bg-white dark:bg-[#1B2433]'
                      : 'border-transparent text-slate-400 hover:text-slate-600'
                  }`}
                >
                  {step.id}. {step.name}
                </button>
              ))}
            </div>

            <form onSubmit={handleSubmitForm} className="p-6 space-y-6 max-h-[75vh] overflow-y-auto">
              {/* Step 1: Identitas */}
              {formStep === 1 && (
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div className="md:col-span-2">
                    <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Judul Modul Ajar *</label>
                    <input
                      type="text"
                      required
                      value={formData.judul_modul}
                      onChange={(e) => setFormData({ ...formData, judul_modul: e.target.value })}
                      placeholder="Contoh: Toleransi & Indahnya Keberagaman dalam Islam"
                      className="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-sm focus:ring-2 focus:ring-[#0E5C44]"
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Kode Modul</label>
                    <input
                      type="text"
                      value={formData.kode_modul}
                      onChange={(e) => setFormData({ ...formData, kode_modul: e.target.value })}
                      className="w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-sm"
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Fase Pembelajaran</label>
                    <select
                      value={formData.fase}
                      onChange={(e) => setFormData({ ...formData, fase: e.target.value })}
                      className="w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-sm"
                    >
                      {FASE_LIST.map((f) => (
                        <option key={f} value={f}>{f}</option>
                      ))}
                    </select>
                  </div>

                  <div>
                    <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Mata Pelajaran</label>
                    <select
                      value={formData.mata_pelajaran_id}
                      onChange={(e) => setFormData({ ...formData, mata_pelajaran_id: e.target.value })}
                      className="w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-sm"
                    >
                      <option value="">-- Pilih Mata Pelajaran --</option>
                      {options.subjects?.map((s) => (
                        <option key={s.id} value={s.id}>
                          {s.nama_mapel || s.name || s.kode_mapel || s.code}
                        </option>
                      ))}
                    </select>
                  </div>

                  <div>
                    <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Guru Pengampu</label>
                    <select
                      value={formData.guru_id}
                      onChange={(e) => setFormData({ ...formData, guru_id: e.target.value })}
                      className="w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-sm"
                    >
                      <option value="">-- Pilih Guru Pengampu --</option>
                      {options.teachers?.map((t) => (
                        <option key={t.id} value={t.id}>
                          {t.nama_lengkap || t.nama || t.name || t.full_name}
                        </option>
                      ))}
                    </select>
                  </div>

                  <div>
                    <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Kelas Target</label>
                    <select
                      value={formData.kelas_id}
                      onChange={(e) => setFormData({ ...formData, kelas_id: e.target.value })}
                      className="w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-sm"
                    >
                      <option value="">-- Pilih Kelas Target --</option>
                      {options.classes?.map((c) => (
                        <option key={c.id} value={c.id}>
                          {c.nama_kelas || c.name || c.kode_kelas}
                        </option>
                      ))}
                    </select>
                  </div>

                  <div>
                    <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Alokasi Jam (JP)</label>
                    <input
                      type="number"
                      min="1"
                      value={formData.alokasi_waktu_jp}
                      onChange={(e) => setFormData({ ...formData, alokasi_waktu_jp: parseInt(e.target.value) || 2 })}
                      className="w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-sm"
                    />
                  </div>
                </div>
              )}

              {/* Step 2: Rancangan */}
              {formStep === 2 && (
                <div className="space-y-4">
                  <div>
                    <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Tujuan Pembelajaran (TP)</label>
                    <textarea
                      rows="3"
                      value={formData.tujuan_pembelajaran}
                      onChange={(e) => setFormData({ ...formData, tujuan_pembelajaran: e.target.value })}
                      placeholder="Tuliskan poin-poin Alur Tujuan Pembelajaran..."
                      className="w-full p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-sm"
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Profil Pelajar Pancasila & Rahmatan Lil Alamin</label>
                    <input
                      type="text"
                      value={formData.profil_pelajar_pancasila}
                      onChange={(e) => setFormData({ ...formData, profil_pelajar_pancasila: e.target.value })}
                      className="w-full p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-sm"
                    />
                  </div>

                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Model Pembelajaran</label>
                      <input
                        type="text"
                        value={formData.model_pembelajaran}
                        onChange={(e) => setFormData({ ...formData, model_pembelajaran: e.target.value })}
                        className="w-full p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-sm"
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Metode Pembelajaran</label>
                      <input
                        type="text"
                        value={formData.metode_pembelajaran}
                        onChange={(e) => setFormData({ ...formData, metode_pembelajaran: e.target.value })}
                        className="w-full p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-sm"
                      />
                    </div>
                  </div>

                  <div>
                    <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Media & Sumber Belajar</label>
                    <input
                      type="text"
                      value={formData.media_pembelajaran}
                      onChange={(e) => setFormData({ ...formData, media_pembelajaran: e.target.value })}
                      placeholder="Slide PPT, Video YouTube, LKPD..."
                      className="w-full p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-sm"
                    />
                  </div>
                </div>
              )}

              {/* Step 3: Skenario Activity */}
              {formStep === 3 && (
                <div className="space-y-4">
                  <div>
                    <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Kegiatan Pendahuluan</label>
                    <textarea
                      rows="3"
                      value={formData.kegiatan_pendahuluan}
                      onChange={(e) => setFormData({ ...formData, kegiatan_pendahuluan: e.target.value })}
                      className="w-full p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-sm"
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Kegiatan Inti Pembelajaran</label>
                    <textarea
                      rows="4"
                      value={formData.kegiatan_inti}
                      onChange={(e) => setFormData({ ...formData, kegiatan_inti: e.target.value })}
                      className="w-full p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-sm"
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Kegiatan Penutup</label>
                    <textarea
                      rows="3"
                      value={formData.kegiatan_penutup}
                      onChange={(e) => setFormData({ ...formData, kegiatan_penutup: e.target.value })}
                      className="w-full p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-sm"
                    />
                  </div>
                </div>
              )}

              {/* Step 4: Asesmen & Status */}
              {formStep === 4 && (
                <div className="space-y-4">
                  <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div>
                      <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Asesmen Awal</label>
                      <textarea
                        rows="2"
                        value={formData.asesmen_awal}
                        onChange={(e) => setFormData({ ...formData, asesmen_awal: e.target.value })}
                        className="w-full p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-xs"
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Asesmen Proses</label>
                      <textarea
                        rows="2"
                        value={formData.asesmen_proses}
                        onChange={(e) => setFormData({ ...formData, asesmen_proses: e.target.value })}
                        className="w-full p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-xs"
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Asesmen Akhir</label>
                      <textarea
                        rows="2"
                        value={formData.asesmen_akhir}
                        onChange={(e) => setFormData({ ...formData, asesmen_akhir: e.target.value })}
                        className="w-full p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-xs"
                      />
                    </div>
                  </div>

                  <div>
                    <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Status Publikasi</label>
                    <select
                      value={formData.status}
                      onChange={(e) => setFormData({ ...formData, status: e.target.value })}
                      className="w-full p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-sm font-bold"
                    >
                      {STATUS_LIST.map((st) => (
                        <option key={st} value={st}>{st}</option>
                      ))}
                    </select>
                  </div>

                  {selectedModul && (
                    <div className="p-3 bg-amber-50 dark:bg-amber-950/40 rounded-xl border border-amber-200 dark:border-amber-800 space-y-2">
                      <label className="flex items-center gap-2 text-xs font-bold text-amber-800 dark:text-amber-300">
                        <input
                          type="checkbox"
                          checked={formData.naikkan_versi}
                          onChange={(e) => setFormData({ ...formData, naikkan_versi: e.target.checked })}
                          className="rounded text-[#0E5C44]"
                        />
                        Naikkan Versi Modul (Increment Version)
                      </label>
                      {formData.naikkan_versi && (
                        <input
                          type="text"
                          placeholder="Catatan revisi versi baru..."
                          value={formData.catatan_revisi}
                          onChange={(e) => setFormData({ ...formData, catatan_revisi: e.target.value })}
                          className="w-full p-2 rounded-lg text-xs border border-amber-300"
                        />
                      )}
                    </div>
                  )}
                </div>
              )}

              {/* Form Action Buttons */}
              <div className="flex items-center justify-between pt-4 border-t border-slate-100 dark:border-slate-800">
                <div>
                  {formStep > 1 && (
                    <button
                      type="button"
                      onClick={() => setFormStep((s) => s - 1)}
                      className="px-4 py-2 rounded-xl border border-slate-300 text-sm font-semibold hover:bg-slate-100"
                    >
                      Kembali
                    </button>
                  )}
                </div>

                <div className="flex items-center gap-3">
                  {formStep < 4 ? (
                    <button
                      type="button"
                      onClick={() => setFormStep((s) => s + 1)}
                      className="px-5 py-2 rounded-xl bg-[#0E5C44] text-white text-sm font-bold shadow-md hover:bg-emerald-700"
                    >
                      Lanjut Step {formStep + 1}
                    </button>
                  ) : (
                    <button
                      type="submit"
                      disabled={createMutation.isPending || updateMutation.isPending}
                      className="px-6 py-2.5 rounded-xl bg-[#0E5C44] text-white text-sm font-bold shadow-lg hover:bg-emerald-800 transition-transform active:scale-95"
                    >
                      {selectedModul ? 'Simpan Perubahan' : 'Simpan Modul Ajar'}
                    </button>
                  )}
                </div>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Modal Detail Overview */}
      {isDetailModalOpen && selectedModul && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm overflow-y-auto">
          <div className="w-full max-w-3xl rounded-[18px] bg-white dark:bg-[#1B2433] shadow-2xl overflow-hidden border border-slate-100 dark:border-slate-800 my-8">
            <div className="bg-[#0E5C44] p-6 text-white flex items-center justify-between">
              <div>
                <span className="text-xs uppercase tracking-widest bg-white/20 px-2.5 py-0.5 rounded-full font-bold">
                  {selectedModul.fase}
                </span>
                <h2 className="text-xl font-extrabold mt-1">{selectedModul.judul_modul}</h2>
                <p className="text-xs text-emerald-100 mt-0.5">{selectedModul.kode_modul} • Versi {selectedModul.versi}</p>
              </div>
              <button onClick={() => setIsDetailModalOpen(false)} className="text-white hover:opacity-80">
                <XCircle className="h-6 w-6" />
              </button>
            </div>

            <div className="p-6 space-y-6 max-h-[70vh] overflow-y-auto text-sm">
              <div className="grid grid-cols-2 md:grid-cols-4 gap-4 p-4 bg-slate-50 dark:bg-slate-900 rounded-xl">
                <div>
                  <p className="text-xs text-slate-400 font-bold uppercase">Mata Pelajaran</p>
                  <p className="font-semibold text-slate-800 dark:text-slate-200">
                    {selectedModul.subject?.nama_mapel || selectedModul.subject?.name || '-'}
                  </p>
                </div>
                <div>
                  <p className="text-xs text-slate-400 font-bold uppercase">Guru Pengampu</p>
                  <p className="font-semibold text-slate-800 dark:text-slate-200">
                    {selectedModul.guru?.nama_lengkap || selectedModul.guru?.nama || selectedModul.guru?.name || '-'}
                  </p>
                </div>
                <div>
                  <p className="text-xs text-slate-400 font-bold uppercase">Kelas / Rombel</p>
                  <p className="font-semibold text-slate-800 dark:text-slate-200">
                    {selectedModul.kelas?.nama_kelas || selectedModul.kelas?.name || '-'}
                  </p>
                </div>
                <div>
                  <p className="text-xs text-slate-400 font-bold uppercase">Alokasi Waktu</p>
                  <p className="font-semibold text-slate-800 dark:text-slate-200">{selectedModul.alokasi_waktu_jp} JP</p>
                </div>
              </div>

              <div>
                <h4 className="font-bold text-[#0E5C44] dark:text-emerald-400 uppercase text-xs tracking-wider mb-2">
                  Tujuan Pembelajaran (TP)
                </h4>
                <p className="whitespace-pre-line text-slate-700 dark:text-slate-300 leading-relaxed bg-slate-50 dark:bg-slate-900/50 p-3 rounded-xl border">
                  {selectedModul.tujuan_pembelajaran || 'Belum diisi.'}
                </p>
              </div>

              <div>
                <h4 className="font-bold text-[#0E5C44] dark:text-emerald-400 uppercase text-xs tracking-wider mb-2">
                  Profil Pelajar Pancasila
                </h4>
                <p className="text-slate-700 dark:text-slate-300 bg-slate-50 dark:bg-slate-900/50 p-3 rounded-xl border">
                  {selectedModul.profil_pelajar_pancasila || '-'}
                </p>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div className="p-3 bg-slate-50 dark:bg-slate-900 rounded-xl border">
                  <h5 className="font-bold text-xs uppercase text-slate-500 mb-1">Kegiatan Pendahuluan</h5>
                  <p className="whitespace-pre-line text-xs">{selectedModul.kegiatan_pendahuluan || '-'}</p>
                </div>
                <div className="p-3 bg-slate-50 dark:bg-slate-900 rounded-xl border">
                  <h5 className="font-bold text-xs uppercase text-slate-500 mb-1">Kegiatan Inti</h5>
                  <p className="whitespace-pre-line text-xs">{selectedModul.kegiatan_inti || '-'}</p>
                </div>
                <div className="p-3 bg-slate-50 dark:bg-slate-900 rounded-xl border">
                  <h5 className="font-bold text-xs uppercase text-slate-500 mb-1">Kegiatan Penutup</h5>
                  <p className="whitespace-pre-line text-xs">{selectedModul.kegiatan_penutup || '-'}</p>
                </div>
              </div>
            </div>

            <div className="p-4 border-t border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 flex justify-end">
              <button
                onClick={() => setIsDetailModalOpen(false)}
                className="px-5 py-2 bg-slate-200 dark:bg-slate-800 rounded-xl text-xs font-bold hover:bg-slate-300"
              >
                Tutup Overview
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Modal Riwayat Revisi */}
      {isRevisionModalOpen && selectedModul && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
          <div className="w-full max-w-2xl rounded-[18px] bg-white dark:bg-[#1B2433] shadow-2xl overflow-hidden border border-slate-100 dark:border-slate-800">
            <div className="bg-[#0E5C44] p-5 text-white flex items-center justify-between">
              <h3 className="font-bold flex items-center gap-2">
                <History className="h-5 w-5" /> Riwayat Revisi Versi - {selectedModul.kode_modul}
              </h3>
              <button onClick={() => setIsRevisionModalOpen(false)} className="text-white hover:opacity-80">
                <XCircle className="h-6 w-6" />
              </button>
            </div>

            <div className="p-6 space-y-4 max-h-[60vh] overflow-y-auto">
              {!revisionsData?.data || revisionsData.data.length === 0 ? (
                <p className="text-center text-slate-400 py-8">Belum ada riwayat revisi tercatat.</p>
              ) : (
                revisionsData.data.map((rev, idx) => (
                  <div key={rev.id || idx} className="p-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 space-y-1">
                    <div className="flex items-center justify-between">
                      <span className="font-mono text-xs bg-[#0E5C44] text-white px-2 py-0.5 rounded font-bold">
                        v{rev.versi}
                      </span>
                      <span className="text-xs text-slate-400">{rev.created_at || 'Baru saja'}</span>
                    </div>
                    <p className="font-semibold text-sm text-slate-900 dark:text-white mt-1">{rev.judul_modul}</p>
                    <p className="text-xs text-slate-500 italic">{rev.catatan_revisi || 'Pembaruan data modul.'}</p>
                  </div>
                ))
              )}
            </div>
          </div>
        </div>
      )}

      {/* Modal Preview / Cetak Dokumen RPP PDF */}
      {isPreviewModalOpen && selectedModul && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-md overflow-y-auto">
          <div className="w-full max-w-4xl rounded-[18px] bg-white text-slate-900 shadow-2xl overflow-hidden my-8 border">
            <div className="bg-slate-900 text-white p-4 flex items-center justify-between print:hidden">
              <span className="text-xs font-mono">PREVIEW DOKUMEN CETAK RPP DIGITAL</span>
              <div className="flex items-center gap-2">
                <button
                  onClick={() => window.print()}
                  className="px-4 py-1.5 bg-[#0E5C44] hover:bg-emerald-600 rounded-lg text-xs font-bold flex items-center gap-1.5"
                >
                  <Printer className="h-4 w-4" /> Cetak / Download PDF
                </button>
                <button onClick={() => setIsPreviewModalOpen(false)} className="text-white hover:opacity-80">
                  <XCircle className="h-6 w-6" />
                </button>
              </div>
            </div>

            {/* Printable Document Sheet */}
            <div className="p-8 space-y-6 text-sm font-sans bg-white leading-relaxed">
              <div className="text-center border-b-2 border-slate-900 pb-4">
                <h2 className="text-xl font-extrabold uppercase tracking-wide">PERENCANAAN PELAKSANAAN PEMBELAJARAN (MODUL AJAR)</h2>
                <h3 className="text-lg font-bold text-[#0E5C44]">{selectedModul.judul_modul}</h3>
                <p className="text-xs text-slate-500 mt-1">Kode: {selectedModul.kode_modul} | Versi {selectedModul.versi} | Status: {selectedModul.status}</p>
              </div>

              <div className="grid grid-cols-2 gap-4 text-xs bg-slate-50 p-4 rounded-lg border">
                <div><strong>Satuan Pendidikan:</strong> {selectedModul.unit_pendidikan?.nama || selectedModul.unit_pendidikan?.name || 'Sekolah Terpadu'}</div>
                <div><strong>Mata Pelajaran:</strong> {selectedModul.subject?.nama_mapel || selectedModul.subject?.name || '-'}</div>
                <div><strong>Fase / Semester:</strong> {selectedModul.fase} / {selectedModul.semester}</div>
                <div><strong>Guru Pengampu:</strong> {selectedModul.guru?.nama_lengkap || selectedModul.guru?.nama || selectedModul.guru?.name || '-'}</div>
                <div><strong>Alokasi Waktu:</strong> {selectedModul.alokasi_waktu_jp} JP</div>
                <div><strong>Target Peserta Didik:</strong> {selectedModul.target_peserta_didik || 'Reguler'}</div>
              </div>

              <div>
                <h4 className="font-bold border-b border-slate-300 pb-1 uppercase text-xs text-[#0E5C44]">I. TUJUAN PEMBELAJARAN & PROFIL PELAJAR</h4>
                <p className="mt-2 text-xs whitespace-pre-line">{selectedModul.tujuan_pembelajaran || '-'}</p>
                <p className="mt-2 text-xs italic">Profil Pelajar Pancasila: {selectedModul.profil_pelajar_pancasila || '-'}</p>
              </div>

              <div>
                <h4 className="font-bold border-b border-slate-300 pb-1 uppercase text-xs text-[#0E5C44]">II. KEGIATAN PEMBELAJARAN</h4>
                <div className="mt-2 space-y-3 text-xs">
                  <div>
                    <strong>A. Pendahuluan:</strong>
                    <p className="whitespace-pre-line text-slate-700">{selectedModul.kegiatan_pendahuluan || '-'}</p>
                  </div>
                  <div>
                    <strong>B. Kegiatan Inti ({selectedModul.model_pembelajaran}):</strong>
                    <p className="whitespace-pre-line text-slate-700">{selectedModul.kegiatan_inti || '-'}</p>
                  </div>
                  <div>
                    <strong>C. Penutup:</strong>
                    <p className="whitespace-pre-line text-slate-700">{selectedModul.kegiatan_penutup || '-'}</p>
                  </div>
                </div>
              </div>

              <div>
                <h4 className="font-bold border-b border-slate-300 pb-1 uppercase text-xs text-[#0E5C44]">III. ASESMEN & PENILAIAN</h4>
                <div className="mt-2 grid grid-cols-3 gap-2 text-xs bg-slate-50 p-3 rounded border">
                  <div><strong>Asesmen Awal:</strong> <p>{selectedModul.asesmen_awal || '-'}</p></div>
                  <div><strong>Asesmen Proses:</strong> <p>{selectedModul.asesmen_proses || '-'}</p></div>
                  <div><strong>Asesmen Akhir:</strong> <p>{selectedModul.asesmen_akhir || '-'}</p></div>
                </div>
              </div>

              <div className="pt-8 flex justify-between text-xs text-center">
                <div>
                  <p>Mengetahui,</p>
                  <p className="font-bold mt-12">Kepala Sekolah</p>
                </div>
                <div>
                  <p>Guru Pengampu,</p>
                  <p className="font-bold mt-12">{selectedModul.guru?.nama_lengkap || selectedModul.guru?.nama || selectedModul.guru?.name || 'Guru Mapel'}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
