import { useState, useEffect } from 'react'
import {
  FileText,
  Video,
  Music,
  Presentation,
  FileCode,
  Image as ImageIcon,
  Link as LinkIcon,
  Plus,
  Search,
  Edit3,
  Trash2,
  RefreshCw,
  CheckCircle,
  AlertCircle,
  X,
  Sparkles,
  Filter,
  ChevronLeft,
  ChevronRight,
  ExternalLink,
  Eye,
  FileUp,
  Clock,
  Layers,
  BookOpen,
  Download,
  PlayCircle,
  Info,
} from 'lucide-react'
import { lmsMediaService } from '../services/lmsMediaService'

export default function LmsMediaPage() {
  const [dataMedia, setDataMedia] = useState([])
  const [optionsMateri, setOptionsMateri] = useState([])
  const [tipeOptions, setTipeOptions] = useState([
    { id: 'pdf', nama: 'Dokumen PDF', icon: FileText, color: 'text-red-500 bg-red-50 dark:bg-red-950/40 border-red-200' },
    { id: 'video', nama: 'Video Pembelajaran', icon: Video, color: 'text-blue-500 bg-blue-50 dark:bg-blue-950/40 border-blue-200' },
    { id: 'audio', nama: 'Audio / Podcast', icon: Music, color: 'text-purple-500 bg-purple-50 dark:bg-purple-950/40 border-purple-200' },
    { id: 'ppt', nama: 'Presentation / PPT', icon: Presentation, color: 'text-amber-500 bg-amber-50 dark:bg-amber-950/40 border-amber-200' },
    { id: 'word', nama: 'Dokumen Word / Doc', icon: FileCode, color: 'text-emerald-500 bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200' },
    { id: 'image', nama: 'Gambar / Infografis', icon: ImageIcon, color: 'text-pink-500 bg-pink-50 dark:bg-pink-950/40 border-pink-200' },
    { id: 'link', nama: 'Tautan / Link URL', icon: LinkIcon, color: 'text-teal-500 bg-teal-50 dark:bg-teal-950/40 border-teal-200' },
  ])

  const [stats, setStats] = useState({
    total_media: 0,
    total_pdf: 0,
    total_video: 0,
    total_audio: 0,
    total_ppt: 0,
    total_word: 0,
    total_image: 0,
    total_link: 0,
    total_materi: 0,
  })

  const [loading, setLoading] = useState(true)
  const [errorMsg, setErrorMsg] = useState('')
  const [successMsg, setSuccessMsg] = useState('')

  // Filters & Pagination
  const [search, setSearch] = useState('')
  const [selectedMateri, setSelectedMateri] = useState('')
  const [selectedTipe, setSelectedTipe] = useState('')
  const [page, setPage] = useState(1)
  const [pagination, setPagination] = useState({
    current_page: 1,
    last_page: 1,
    total: 0,
    per_page: 15,
  })

  // Modal State
  const [modalOpen, setModalOpen] = useState(false)
  const [previewModalOpen, setPreviewModalOpen] = useState(false)
  const [previewItem, setPreviewItem] = useState(null)
  const [editingItem, setEditingItem] = useState(null)
  const [formSubmitting, setFormSubmitting] = useState(false)
  const [selectedFile, setSelectedFile] = useState(null)

  const [formData, setFormData] = useState({
    materi_id: '',
    nama_file: '',
    tipe_file: 'pdf',
    url_eksternal: '',
    ukuran_bytes: '',
    durasi_detik: '',
    deskripsi: '',
    urutan: 1,
  })

  const loadInitialOptionsAndStats = async () => {
    try {
      const [optRes, statsRes] = await Promise.all([
        lmsMediaService.getOptions(),
        lmsMediaService.getStats(),
      ])
      if (optRes?.data?.materi_options) {
        setOptionsMateri(optRes.data.materi_options)
      }
      if (statsRes?.data) {
        setStats(statsRes.data)
      }
    } catch (err) {
      console.error('Gagal memuat opsi/statistik media:', err)
    }
  }

  const fetchDaftarMedia = async () => {
    setLoading(true)
    setErrorMsg('')
    try {
      const response = await lmsMediaService.getDaftar({
        page,
        search,
        materi_id: selectedMateri,
        tipe_file: selectedTipe,
        per_page: 15,
      })
      if (response?.data) {
        setDataMedia(response.data)
        if (response.meta) {
          setPagination({
            current_page: response.meta.current_page || 1,
            last_page: response.meta.last_page || 1,
            total: response.meta.total || 0,
            per_page: response.meta.per_page || 15,
          })
        }
        if (response.statistik) {
          setStats(response.statistik)
        }
      }
    } catch (err) {
      setErrorMsg('Gagal memuat daftar Media Pembelajaran. Silakan coba lagi.')
      console.error(err)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    loadInitialOptionsAndStats()
  }, [])

  useEffect(() => {
    fetchDaftarMedia()
  }, [page, search, selectedMateri, selectedTipe])

  const handleOpenCreateModal = () => {
    setEditingItem(null)
    setSelectedFile(null)
    setFormData({
      materi_id: optionsMateri[0]?.id || '',
      nama_file: '',
      tipe_file: 'pdf',
      url_eksternal: '',
      ukuran_bytes: '',
      durasi_detik: '',
      deskripsi: '',
      urutan: 1,
    })
    setModalOpen(true)
  }

  const handleOpenEditModal = (item) => {
    setEditingItem(item)
    setSelectedFile(null)
    setFormData({
      materi_id: item.materi_id || '',
      nama_file: item.nama_file || '',
      tipe_file: item.tipe_file || 'pdf',
      url_eksternal: item.url_eksternal || '',
      ukuran_bytes: item.ukuran_bytes || '',
      durasi_detik: item.durasi_detik || '',
      deskripsi: item.deskripsi || '',
      urutan: item.urutan || 1,
    })
    setModalOpen(true)
  }

  const handleOpenPreview = (item) => {
    setPreviewItem(item)
    setPreviewModalOpen(true)
  }

  const handleSubmitForm = async (e) => {
    e.preventDefault()
    if (!formData.materi_id) {
      setErrorMsg('Silakan pilih Materi Pembelajaran terlebih dahulu.')
      return
    }
    if (!formData.nama_file.trim()) {
      setErrorMsg('Nama file/media tidak boleh kosong.')
      return
    }

    setFormSubmitting(true)
    setErrorMsg('')
    try {
      const payload = new FormData()
      payload.append('materi_id', formData.materi_id)
      payload.append('nama_file', formData.nama_file)
      payload.append('tipe_file', formData.tipe_file)
      if (formData.url_eksternal) payload.append('url_eksternal', formData.url_eksternal)
      if (formData.durasi_detik) payload.append('durasi_detik', formData.durasi_detik)
      if (formData.deskripsi) payload.append('deskripsi', formData.deskripsi)
      if (formData.urutan) payload.append('urutan', formData.urutan)
      if (selectedFile) payload.append('file', selectedFile)

      if (editingItem) {
        await lmsMediaService.update(editingItem.id, payload)
        setSuccessMsg('Media Pembelajaran berhasil diperbarui.')
      } else {
        await lmsMediaService.create(payload)
        setSuccessMsg('Media Pembelajaran berhasil ditambahkan.')
      }

      setModalOpen(false)
      fetchDaftarMedia()
      setTimeout(() => setSuccessMsg(''), 4000)
    } catch (err) {
      const respData = err.response?.data
      if (respData?.errors) {
        const firstErr = Object.values(respData.errors)[0]?.[0]
        setErrorMsg(firstErr || 'Terjadi kesalahan pada validasi data.')
      } else {
        setErrorMsg(respData?.message || 'Gagal menyimpan Media Pembelajaran.')
      }
    } finally {
      setFormSubmitting(false)
    }
  }

  const handleDeleteItem = async (item) => {
    if (!window.confirm(`Apakah Anda yakin ingin menghapus media "${item.nama_file}"?`)) {
      return
    }
    try {
      await lmsMediaService.delete(item.id)
      setSuccessMsg('Media Pembelajaran berhasil dihapus.')
      fetchDaftarMedia()
      setTimeout(() => setSuccessMsg(''), 4000)
    } catch (err) {
      setErrorMsg('Gagal menghapus Media Pembelajaran.')
    }
  }

  const getTipeBadge = (tipe) => {
    switch (tipe?.toLowerCase()) {
      case 'pdf':
        return (
          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300 border border-red-200/60">
            <FileText className="w-3.5 h-3.5" /> PDF
          </span>
        )
      case 'video':
        return (
          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300 border border-blue-200/60">
            <Video className="w-3.5 h-3.5" /> Video
          </span>
        )
      case 'audio':
        return (
          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-purple-50 text-purple-700 dark:bg-purple-950/40 dark:text-purple-300 border border-purple-200/60">
            <Music className="w-3.5 h-3.5" /> Audio
          </span>
        )
      case 'ppt':
        return (
          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300 border border-amber-200/60">
            <Presentation className="w-3.5 h-3.5" /> PPT Slide
          </span>
        )
      case 'word':
        return (
          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-200/60">
            <FileCode className="w-3.5 h-3.5" /> Word Doc
          </span>
        )
      case 'image':
        return (
          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-pink-50 text-pink-700 dark:bg-pink-950/40 dark:text-pink-300 border border-pink-200/60">
            <ImageIcon className="w-3.5 h-3.5" /> Gambar
          </span>
        )
      case 'link':
      default:
        return (
          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-teal-50 text-teal-700 dark:bg-teal-950/40 dark:text-teal-300 border border-teal-200/60">
            <LinkIcon className="w-3.5 h-3.5" /> Link URL
          </span>
        )
    }
  }

  return (
    <div className="p-4 sm:p-6 lg:p-8 space-y-8 max-w-[1600px] mx-auto min-h-screen bg-[#F7F9FC] dark:bg-[#0F172A] text-slate-800 dark:text-slate-100 font-sans transition-colors duration-300">
      {/* Hero Banner */}
      <div className="relative overflow-hidden rounded-[18px] bg-gradient-to-r from-[#0E5C44] via-[#1E8E5A] to-[#3FBF75] p-6 sm:p-8 text-white shadow-xl shadow-[#0E5C44]/15">
        <div className="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-6">
          <div className="space-y-2">
            <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/15 backdrop-blur-md text-xs font-medium tracking-wide">
              <Sparkles className="w-4 h-4 text-emerald-200" />
              <span>Modul LMS Terpadu</span>
            </div>
            <h1 className="text-2xl sm:text-3xl font-extrabold tracking-tight">
              Media Pembelajaran (Multi-Format)
            </h1>
            <p className="text-emerald-50 text-sm max-w-2xl leading-relaxed opacity-90">
              Kelola seluruh lampiran berkas dan sumber media pembelajaran terpadu (PDF, Video, Audio, Presentasi PPT, Dokumen Word, Gambar, & Link Eksternal) untuk mendukung proses belajar mengajar interaktif.
            </p>
          </div>
          <div className="flex items-center gap-3 self-start md:self-auto">
            <button
              onClick={handleOpenCreateModal}
              className="inline-flex items-center gap-2 px-5 py-3 bg-white text-[#0E5C44] font-bold text-sm rounded-[14px] shadow-lg hover:bg-emerald-50 transition-all transform hover:-translate-y-0.5 active:translate-y-0 focus:outline-none focus:ring-2 focus:ring-white/40"
            >
              <Plus className="w-5 h-5 stroke-[2.5]" />
              <span>Tambah Media Baru</span>
            </button>
          </div>
        </div>
        {/* Decorative blur elements */}
        <div className="absolute -right-12 -bottom-12 w-64 h-64 bg-white/10 rounded-full blur-3xl pointer-events-none" />
        <div className="absolute left-1/2 top-0 w-48 h-48 bg-emerald-300/10 rounded-full blur-2xl pointer-events-none" />
      </div>

      {/* Alert Messages */}
      {successMsg && (
        <div className="flex items-center justify-between p-4 rounded-[14px] bg-emerald-50 border border-emerald-200 text-emerald-800 dark:bg-emerald-950/50 dark:border-emerald-800 dark:text-emerald-200 shadow-sm animate-fadeIn">
          <div className="flex items-center gap-3">
            <CheckCircle className="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0" />
            <p className="text-sm font-medium">{successMsg}</p>
          </div>
          <button onClick={() => setSuccessMsg('')} className="p-1 hover:bg-emerald-100 dark:hover:bg-emerald-900 rounded-lg">
            <X className="w-4 h-4" />
          </button>
        </div>
      )}

      {errorMsg && (
        <div className="flex items-center justify-between p-4 rounded-[14px] bg-red-50 border border-red-200 text-red-800 dark:bg-red-950/50 dark:border-red-800 dark:text-red-200 shadow-sm animate-fadeIn">
          <div className="flex items-center gap-3">
            <AlertCircle className="w-5 h-5 text-red-600 dark:text-red-400 shrink-0" />
            <p className="text-sm font-medium">{errorMsg}</p>
          </div>
          <button onClick={() => setErrorMsg('')} className="p-1 hover:bg-red-100 dark:hover:bg-red-900 rounded-lg">
            <X className="w-4 h-4" />
          </button>
        </div>
      )}

      {/* KPI Stats Cards */}
      <div className="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-4">
        <div className="p-4 rounded-[18px] bg-white dark:bg-[#1B2433] border border-slate-200/80 dark:border-slate-800 shadow-sm hover:shadow-md transition-all">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total</span>
            <Layers className="w-4 h-4 text-[#0E5C44]" />
          </div>
          <p className="text-2xl font-extrabold mt-2 text-slate-900 dark:text-white">{stats.total_media || 0}</p>
          <span className="text-[11px] text-slate-400">Media Pembelajaran</span>
        </div>

        <div className="p-4 rounded-[18px] bg-white dark:bg-[#1B2433] border border-slate-200/80 dark:border-slate-800 shadow-sm hover:shadow-md transition-all">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-red-500 uppercase tracking-wider">PDF</span>
            <FileText className="w-4 h-4 text-red-500" />
          </div>
          <p className="text-2xl font-extrabold mt-2 text-slate-900 dark:text-white">{stats.total_pdf || 0}</p>
          <span className="text-[11px] text-slate-400">Dokumen PDF</span>
        </div>

        <div className="p-4 rounded-[18px] bg-white dark:bg-[#1B2433] border border-slate-200/80 dark:border-slate-800 shadow-sm hover:shadow-md transition-all">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-blue-500 uppercase tracking-wider">Video</span>
            <Video className="w-4 h-4 text-blue-500" />
          </div>
          <p className="text-2xl font-extrabold mt-2 text-slate-900 dark:text-white">{stats.total_video || 0}</p>
          <span className="text-[11px] text-slate-400">Video Pembelajaran</span>
        </div>

        <div className="p-4 rounded-[18px] bg-white dark:bg-[#1B2433] border border-slate-200/80 dark:border-slate-800 shadow-sm hover:shadow-md transition-all">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-purple-500 uppercase tracking-wider">Audio</span>
            <Music className="w-4 h-4 text-purple-500" />
          </div>
          <p className="text-2xl font-extrabold mt-2 text-slate-900 dark:text-white">{stats.total_audio || 0}</p>
          <span className="text-[11px] text-slate-400">Podcast / Audio</span>
        </div>

        <div className="p-4 rounded-[18px] bg-white dark:bg-[#1B2433] border border-slate-200/80 dark:border-slate-800 shadow-sm hover:shadow-md transition-all">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-amber-500 uppercase tracking-wider">PPT</span>
            <Presentation className="w-4 h-4 text-amber-500" />
          </div>
          <p className="text-2xl font-extrabold mt-2 text-slate-900 dark:text-white">{stats.total_ppt || 0}</p>
          <span className="text-[11px] text-slate-400">Slide Presentasi</span>
        </div>

        <div className="p-4 rounded-[18px] bg-white dark:bg-[#1B2433] border border-slate-200/80 dark:border-slate-800 shadow-sm hover:shadow-md transition-all">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-emerald-500 uppercase tracking-wider">Word</span>
            <FileCode className="w-4 h-4 text-emerald-500" />
          </div>
          <p className="text-2xl font-extrabold mt-2 text-slate-900 dark:text-white">{stats.total_word || 0}</p>
          <span className="text-[11px] text-slate-400">Dokumen Office</span>
        </div>

        <div className="p-4 rounded-[18px] bg-white dark:bg-[#1B2433] border border-slate-200/80 dark:border-slate-800 shadow-sm hover:shadow-md transition-all">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-teal-500 uppercase tracking-wider">Link</span>
            <LinkIcon className="w-4 h-4 text-teal-500" />
          </div>
          <p className="text-2xl font-extrabold mt-2 text-slate-900 dark:text-white">{stats.total_link || 0}</p>
          <span className="text-[11px] text-slate-400">Tautan Eksternal</span>
        </div>
      </div>

      {/* Filter Bar & Controls */}
      <div className="p-4 rounded-[18px] bg-white dark:bg-[#1B2433] border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-4">
        <div className="flex flex-col md:flex-row gap-4 items-center justify-between">
          {/* Search Box */}
          <div className="relative w-full md:w-80">
            <Search className="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
            <input
              type="text"
              placeholder="Cari nama media/deskripsi..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="w-full pl-10 pr-4 py-2.5 rounded-[12px] bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44]"
            />
          </div>

          {/* Filter Selects */}
          <div className="flex flex-wrap items-center gap-3 w-full md:w-auto">
            <div className="flex items-center gap-2">
              <Filter className="w-4 h-4 text-slate-400 shrink-0" />
              <select
                value={selectedMateri}
                onChange={(e) => {
                  setSelectedMateri(e.target.value)
                  setPage(1)
                }}
                className="px-3 py-2.5 rounded-[12px] bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44]"
              >
                <option value="">Semua Materi Pembelajaran</option>
                {optionsMateri.map((mat) => (
                  <option key={mat.id} value={mat.id}>
                    {mat.judul} ({mat.modul_ajar?.judul_modul || 'Modul'})
                  </option>
                ))}
              </select>
            </div>

            <select
              value={selectedTipe}
              onChange={(e) => {
                setSelectedTipe(e.target.value)
                setPage(1)
              }}
              className="px-3 py-2.5 rounded-[12px] bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44]"
            >
              <option value="">Semua Tipe Media</option>
              {tipeOptions.map((opt) => (
                <option key={opt.id} value={opt.id}>
                  {opt.nama}
                </option>
              ))}
            </select>

            <button
              onClick={() => fetchDaftarMedia()}
              className="p-2.5 rounded-[12px] border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
              title="Refresh Data"
            >
              <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} />
            </button>
          </div>
        </div>
      </div>

      {/* Main Table Card */}
      <div className="rounded-[18px] bg-white dark:bg-[#1B2433] border border-slate-200/80 dark:border-slate-800 shadow-sm overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm">
            <thead className="bg-slate-50 dark:bg-slate-900/60 border-b border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-semibold">
              <tr>
                <th className="px-6 py-4 w-12 text-center">Urutan</th>
                <th className="px-6 py-4">Nama Media / Lampiran</th>
                <th className="px-6 py-4">Tipe Media</th>
                <th className="px-6 py-4">Materi Pembelajaran</th>
                <th className="px-6 py-4">Ukuran / Durasi</th>
                <th className="px-6 py-4">Deskripsi</th>
                <th className="px-6 py-4 text-center">Aksi</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60">
              {loading ? (
                Array.from({ length: 5 }).map((_, idx) => (
                  <tr key={idx} className="animate-pulse">
                    <td className="px-6 py-4 text-center">
                      <div className="h-4 bg-slate-200 dark:bg-slate-700 rounded w-6 mx-auto" />
                    </td>
                    <td className="px-6 py-4">
                      <div className="h-4 bg-slate-200 dark:bg-slate-700 rounded w-48" />
                    </td>
                    <td className="px-6 py-4">
                      <div className="h-6 bg-slate-200 dark:bg-slate-700 rounded-full w-24" />
                    </td>
                    <td className="px-6 py-4">
                      <div className="h-4 bg-slate-200 dark:bg-slate-700 rounded w-36" />
                    </td>
                    <td className="px-6 py-4">
                      <div className="h-4 bg-slate-200 dark:bg-slate-700 rounded w-20" />
                    </td>
                    <td className="px-6 py-4">
                      <div className="h-4 bg-slate-200 dark:bg-slate-700 rounded w-40" />
                    </td>
                    <td className="px-6 py-4 text-center">
                      <div className="h-8 bg-slate-200 dark:bg-slate-700 rounded-lg w-20 mx-auto" />
                    </td>
                  </tr>
                ))
              ) : dataMedia.length === 0 ? (
                <tr>
                  <td colSpan={7} className="px-6 py-12 text-center text-slate-400">
                    <div className="flex flex-col items-center justify-center gap-2">
                      <Layers className="w-10 h-10 text-slate-300 stroke-[1.5]" />
                      <p className="font-semibold text-slate-600 dark:text-slate-300">Belum ada Media Pembelajaran</p>
                      <p className="text-xs text-slate-400">Silakan tambahkan berkas media baru untuk materi pembelajaran.</p>
                    </div>
                  </td>
                </tr>
              ) : (
                dataMedia.map((item) => (
                  <tr
                    key={item.id}
                    className="hover:bg-slate-50/80 dark:hover:bg-slate-800/50 transition-colors group"
                  >
                    <td className="px-6 py-4 text-center font-bold text-slate-400 group-hover:text-[#0E5C44]">
                      #{item.urutan || 1}
                    </td>
                    <td className="px-6 py-4">
                      <div className="flex items-center gap-3">
                        <div className="p-2 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 text-[#0E5C44] dark:text-emerald-400 border border-emerald-100 dark:border-emerald-900/50">
                          {item.tipe_file === 'pdf' && <FileText className="w-5 h-5 text-red-500" />}
                          {item.tipe_file === 'video' && <Video className="w-5 h-5 text-blue-500" />}
                          {item.tipe_file === 'audio' && <Music className="w-5 h-5 text-purple-500" />}
                          {item.tipe_file === 'ppt' && <Presentation className="w-5 h-5 text-amber-500" />}
                          {item.tipe_file === 'word' && <FileCode className="w-5 h-5 text-emerald-500" />}
                          {item.tipe_file === 'image' && <ImageIcon className="w-5 h-5 text-pink-500" />}
                          {item.tipe_file === 'link' && <LinkIcon className="w-5 h-5 text-teal-500" />}
                        </div>
                        <div>
                          <p className="font-bold text-slate-900 dark:text-white line-clamp-1">{item.nama_file}</p>
                          {item.url_eksternal && (
                            <a
                              href={item.url_eksternal}
                              target="_blank"
                              rel="noreferrer"
                              className="text-xs text-emerald-600 dark:text-emerald-400 hover:underline inline-flex items-center gap-1 mt-0.5"
                            >
                              <ExternalLink className="w-3 h-3" /> Link Eksternal
                            </a>
                          )}
                        </div>
                      </div>
                    </td>
                    <td className="px-6 py-4">{getTipeBadge(item.tipe_file)}</td>
                    <td className="px-6 py-4">
                      <div className="flex items-center gap-2 text-slate-700 dark:text-slate-300">
                        <BookOpen className="w-4 h-4 text-emerald-600 shrink-0" />
                        <span className="font-medium line-clamp-1">
                          {item.materi?.judul || 'Tanpa Judul Materi'}
                        </span>
                      </div>
                    </td>
                    <td className="px-6 py-4 text-xs font-medium text-slate-500 dark:text-slate-400">
                      {item.ukuran_formatted ? (
                        <span className="block">{item.ukuran_formatted}</span>
                      ) : null}
                      {item.durasi_formatted ? (
                        <span className="inline-flex items-center gap-1 text-slate-600 dark:text-slate-300">
                          <Clock className="w-3 h-3" /> {item.durasi_formatted}
                        </span>
                      ) : null}
                      {!item.ukuran_formatted && !item.durasi_formatted && '-'}
                    </td>
                    <td className="px-6 py-4 text-xs text-slate-500 dark:text-slate-400 max-w-xs truncate">
                      {item.deskripsi || '-'}
                    </td>
                    <td className="px-6 py-4 text-center">
                      <div className="flex items-center justify-center gap-1">
                        <button
                          onClick={() => handleOpenPreview(item)}
                          className="p-2 text-slate-500 hover:text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-950/50 rounded-lg transition-colors"
                          title="Pratinjau Media"
                        >
                          <Eye className="w-4 h-4" />
                        </button>
                        <button
                          onClick={() => handleOpenEditModal(item)}
                          className="p-2 text-slate-500 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-950/50 rounded-lg transition-colors"
                          title="Edit Media"
                        >
                          <Edit3 className="w-4 h-4" />
                        </button>
                        <button
                          onClick={() => handleDeleteItem(item)}
                          className="p-2 text-slate-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/50 rounded-lg transition-colors"
                          title="Hapus Media"
                        >
                          <Trash2 className="w-4 h-4" />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination Footer */}
        <div className="px-6 py-4 bg-slate-50 dark:bg-slate-900/60 border-t border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-500 dark:text-slate-400">
          <div>
            Menampilkan <span className="font-bold text-slate-700 dark:text-slate-200">{dataMedia.length}</span> dari{' '}
            <span className="font-bold text-slate-700 dark:text-slate-200">{pagination.total}</span> data
          </div>
          <div className="flex items-center gap-2">
            <button
              disabled={page <= 1}
              onClick={() => setPage((p) => Math.max(1, p - 1))}
              className="p-2 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-white dark:hover:bg-slate-800 disabled:opacity-40 disabled:hover:bg-transparent transition-colors"
            >
              <ChevronLeft className="w-4 h-4" />
            </button>
            <span className="px-3 font-semibold">
              Halaman {pagination.current_page} dari {pagination.last_page}
            </span>
            <button
              disabled={page >= pagination.last_page}
              onClick={() => setPage((p) => Math.min(pagination.last_page, p + 1))}
              className="p-2 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-white dark:hover:bg-slate-800 disabled:opacity-40 disabled:hover:bg-transparent transition-colors"
            >
              <ChevronRight className="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>

      {/* Modal Form Create / Edit */}
      {modalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-fadeIn">
          <div className="relative w-full max-w-2xl rounded-[22px] bg-white dark:bg-[#1B2433] border border-slate-200 dark:border-slate-800 shadow-2xl overflow-hidden max-h-[90vh] flex flex-col">
            {/* Modal Header */}
            <div className="px-6 py-5 bg-slate-50 dark:bg-slate-900/80 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between shrink-0">
              <div className="flex items-center gap-3">
                <div className="p-2.5 rounded-xl bg-[#0E5C44] text-white">
                  <FileUp className="w-5 h-5" />
                </div>
                <div>
                  <h3 className="text-lg font-bold text-slate-900 dark:text-white">
                    {editingItem ? 'Edit Media Pembelajaran' : 'Tambah Media Pembelajaran Baru'}
                  </h3>
                  <p className="text-xs text-slate-500 dark:text-slate-400">
                    Lengkapi detail media atau unggah file pendukung
                  </p>
                </div>
              </div>
              <button
                onClick={() => setModalOpen(false)}
                className="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-lg"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            {/* Modal Body */}
            <form onSubmit={handleSubmitForm} className="p-6 overflow-y-auto space-y-5 flex-1">
              <div>
                <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
                  Materi Pembelajaran <span className="text-red-500">*</span>
                </label>
                <select
                  required
                  value={formData.materi_id}
                  onChange={(e) => setFormData({ ...formData, materi_id: e.target.value })}
                  className="w-full px-4 py-2.5 rounded-[12px] bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44]"
                >
                  <option value="">-- Pilih Materi Pembelajaran --</option>
                  {optionsMateri.map((mat) => (
                    <option key={mat.id} value={mat.id}>
                      {mat.judul} ({mat.modul_ajar?.judul_modul || 'Modul'})
                    </option>
                  ))}
                </select>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
                    Nama File / Media <span className="text-red-500">*</span>
                  </label>
                  <input
                    type="text"
                    required
                    placeholder="Contoh: Modul Panduan PDF Bab 1"
                    value={formData.nama_file}
                    onChange={(e) => setFormData({ ...formData, nama_file: e.target.value })}
                    className="w-full px-4 py-2.5 rounded-[12px] bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44]"
                  />
                </div>

                <div>
                  <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
                    Tipe Media <span className="text-red-500">*</span>
                  </label>
                  <select
                    value={formData.tipe_file}
                    onChange={(e) => setFormData({ ...formData, tipe_file: e.target.value })}
                    className="w-full px-4 py-2.5 rounded-[12px] bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44]"
                  >
                    {tipeOptions.map((opt) => (
                      <option key={opt.id} value={opt.id}>
                        {opt.nama}
                      </option>
                    ))}
                  </select>
                </div>
              </div>

              {/* Upload File Input */}
              <div className="p-4 rounded-[14px] bg-slate-50 dark:bg-slate-900/40 border border-dashed border-slate-300 dark:border-slate-700">
                <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
                  Unggah Berkas File (Opsional)
                </label>
                <input
                  type="file"
                  onChange={(e) => setSelectedFile(e.target.files[0] || null)}
                  className="block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-[#0E5C44] file:text-white hover:file:bg-[#1E8E5A] cursor-pointer"
                />
                <span className="text-[11px] text-slate-400 mt-1 block">
                  Mendukung PDF, Video (MP4/WebM), Audio (MP3), PPTX, DOCX, PNG/JPG (Maks 50MB)
                </span>
              </div>

              {/* External Link */}
              <div>
                <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
                  Tautan Eksternal / URL (YouTube, Drive, Web, dll)
                </label>
                <div className="relative">
                  <LinkIcon className="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
                  <input
                    type="url"
                    placeholder="https://..."
                    value={formData.url_eksternal}
                    onChange={(e) => setFormData({ ...formData, url_eksternal: e.target.value })}
                    className="w-full pl-10 pr-4 py-2.5 rounded-[12px] bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44]"
                  />
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
                    Durasi (Detik) - Untuk Audio/Video
                  </label>
                  <input
                    type="number"
                    min="0"
                    placeholder="Contoh: 300 (5 menit)"
                    value={formData.durasi_detik}
                    onChange={(e) => setFormData({ ...formData, durasi_detik: e.target.value })}
                    className="w-full px-4 py-2.5 rounded-[12px] bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44]"
                  />
                </div>

                <div>
                  <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
                    Urutan Tampil
                  </label>
                  <input
                    type="number"
                    min="1"
                    value={formData.urutan}
                    onChange={(e) => setFormData({ ...formData, urutan: e.target.value })}
                    className="w-full px-4 py-2.5 rounded-[12px] bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44]"
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
                  Catatan / Deskripsi Penjelas
                </label>
                <textarea
                  rows={3}
                  placeholder="Keterangan isi file media atau instruksi pengajuan..."
                  value={formData.deskripsi}
                  onChange={(e) => setFormData({ ...formData, deskripsi: e.target.value })}
                  className="w-full px-4 py-2.5 rounded-[12px] bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44]"
                />
              </div>

              {/* Modal Footer */}
              <div className="pt-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-end gap-3">
                <button
                  type="button"
                  onClick={() => setModalOpen(false)}
                  className="px-5 py-2.5 rounded-[12px] border border-slate-200 dark:border-slate-700 text-sm font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={formSubmitting}
                  className="inline-flex items-center gap-2 px-6 py-2.5 bg-[#0E5C44] hover:bg-[#1E8E5A] text-white font-bold text-sm rounded-[12px] shadow-md transition-all disabled:opacity-50"
                >
                  {formSubmitting ? <RefreshCw className="w-4 h-4 animate-spin" /> : <CheckCircle className="w-4 h-4" />}
                  <span>{editingItem ? 'Simpan Perubahan' : 'Tambah Media'}</span>
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Modal Preview Media */}
      {previewModalOpen && previewItem && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/70 backdrop-blur-sm animate-fadeIn">
          <div className="relative w-full max-w-3xl rounded-[22px] bg-white dark:bg-[#1B2433] border border-slate-200 dark:border-slate-800 shadow-2xl overflow-hidden max-h-[90vh] flex flex-col">
            <div className="px-6 py-4 bg-slate-50 dark:bg-slate-900/80 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
              <div className="flex items-center gap-3">
                {getTipeBadge(previewItem.tipe_file)}
                <h3 className="text-base font-bold text-slate-900 dark:text-white truncate max-w-md">
                  {previewItem.nama_file}
                </h3>
              </div>
              <button
                onClick={() => setPreviewModalOpen(false)}
                className="p-1.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-lg"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <div className="p-6 overflow-y-auto space-y-4 flex-1">
              {/* Media Player / Embed Preview based on type */}
              {previewItem.tipe_file === 'image' && (previewItem.file_url || previewItem.url_eksternal) && (
                <div className="flex justify-center bg-slate-900/80 rounded-xl p-4">
                  <img
                    src={previewItem.file_url || previewItem.url_eksternal}
                    alt={previewItem.nama_file}
                    className="max-h-[400px] object-contain rounded-lg shadow-lg"
                  />
                </div>
              )}

              {previewItem.tipe_file === 'video' && (
                <div className="aspect-video bg-black rounded-xl overflow-hidden flex items-center justify-center">
                  {previewItem.url_eksternal?.includes('youtube.com') || previewItem.url_eksternal?.includes('youtu.be') ? (
                    <iframe
                      src={previewItem.url_eksternal.replace('watch?v=', 'embed/')}
                      title={previewItem.nama_file}
                      className="w-full h-full border-0"
                      allowFullScreen
                    />
                  ) : previewItem.file_url ? (
                    <video controls className="w-full h-full">
                      <source src={previewItem.file_url} />
                      Browser Anda tidak mendukung tag video.
                    </video>
                  ) : (
                    <div className="text-center p-6 text-slate-400">
                      <PlayCircle className="w-12 h-12 mx-auto text-blue-500 mb-2" />
                      <p className="text-sm font-medium">Video Eksternal</p>
                      <a
                        href={previewItem.url_eksternal}
                        target="_blank"
                        rel="noreferrer"
                        className="text-xs text-blue-400 underline mt-2 block"
                      >
                        Buka Video di Tab Baru
                      </a>
                    </div>
                  )}
                </div>
              )}

              {previewItem.tipe_file === 'audio' && (
                <div className="p-6 rounded-xl bg-slate-900 text-white flex flex-col items-center gap-4">
                  <Music className="w-12 h-12 text-purple-400" />
                  <p className="text-sm font-semibold">{previewItem.nama_file}</p>
                  <audio controls className="w-full max-w-md">
                    <source src={previewItem.file_url || previewItem.url_eksternal} />
                    Browser Anda tidak mendukung elemen audio.
                  </audio>
                </div>
              )}

              {previewItem.tipe_file === 'pdf' && (previewItem.file_url || previewItem.url_eksternal) && (
                <div className="w-full h-[400px] rounded-xl overflow-hidden border border-slate-200 dark:border-slate-700 bg-slate-100">
                  <iframe
                    src={previewItem.file_url || previewItem.url_eksternal}
                    title={previewItem.nama_file}
                    className="w-full h-full border-0"
                  />
                </div>
              )}

              {/* General Info Card */}
              <div className="p-4 rounded-[14px] bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 space-y-2 text-sm">
                <div className="flex items-center justify-between text-xs text-slate-500">
                  <span>Materi Pembelajaran:</span>
                  <span className="font-bold text-slate-800 dark:text-slate-200">
                    {previewItem.materi?.judul || '-'}
                  </span>
                </div>
                {previewItem.deskripsi && (
                  <div className="pt-2 border-t border-slate-200 dark:border-slate-800">
                    <p className="text-xs font-semibold text-slate-400 uppercase">Deskripsi / Catatan:</p>
                    <p className="text-slate-700 dark:text-slate-300 mt-1">{previewItem.deskripsi}</p>
                  </div>
                )}
              </div>
            </div>

            <div className="px-6 py-4 bg-slate-50 dark:bg-slate-900/80 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between">
              {(previewItem.file_url || previewItem.url_eksternal) && (
                <a
                  href={previewItem.file_url || previewItem.url_eksternal}
                  target="_blank"
                  rel="noreferrer"
                  className="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-[#0E5C44] text-white text-xs font-bold hover:bg-[#1E8E5A]"
                >
                  <Download className="w-4 h-4" /> Buka / Unduh Berkas
                </a>
              )}
              <button
                onClick={() => setPreviewModalOpen(false)}
                className="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-600 dark:text-slate-300"
              >
                Tutup
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
