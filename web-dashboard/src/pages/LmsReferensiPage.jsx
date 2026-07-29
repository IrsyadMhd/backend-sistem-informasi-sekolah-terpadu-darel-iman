import { useState, useEffect } from 'react'
import {
  BookOpen,
  BookMarked,
  User,
  Building,
  Calendar,
  Link as LinkIcon,
  FileText,
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
  Download,
  Info,
  Check,
  Ban,
} from 'lucide-react'
import Swal from 'sweetalert2'
import { lmsReferensiService } from '../services/lmsReferensiService'

export default function LmsReferensiPage() {
  const [dataReferensi, setDataReferensi] = useState([])
  const [optionsModulAjar, setOptionsModulAjar] = useState([])
  const [optionsReferensiUmum, setOptionsReferensiUmum] = useState([])
  const [stats, setStats] = useState({
    total_referensi: 0,
    total_aktif: 0,
    total_non_aktif: 0,
    dengan_file: 0,
    dengan_url: 0,
    total_modul_ajar: 0,
  })

  const [loading, setLoading] = useState(true)
  const [errorMsg, setErrorMsg] = useState('')
  const [successMsg, setSuccessMsg] = useState('')

  // Filters & Pagination
  const [search, setSearch] = useState('')
  const [selectedModulAjar, setSelectedModulAjar] = useState('')
  const [selectedStatus, setSelectedStatus] = useState('')
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
  const [selectedReferensiUmumId, setSelectedReferensiUmumId] = useState('')

  const [formData, setFormData] = useState({
    modul_ajar_id: '',
    judul: '',
    penulis: '',
    penerbit: '',
    tahun: new Date().getFullYear(),
    url: '',
    status: 'aktif',
  })

  const loadInitialOptionsAndStats = async () => {
    try {
      const [optRes, statsRes] = await Promise.all([
        lmsReferensiService.getOptions(),
        lmsReferensiService.getStats(),
      ])
      if (optRes?.data?.modul_ajar_options) {
        setOptionsModulAjar(optRes.data.modul_ajar_options)
      }
      if (optRes?.data?.referensi_umum_options) {
        setOptionsReferensiUmum(optRes.data.referensi_umum_options)
      }
      if (statsRes?.data) {
        setStats(statsRes.data)
      }
    } catch (err) {
      console.error('Gagal memuat opsi/statistik referensi:', err)
    }
  }

  const fetchDaftarReferensi = async () => {
    setLoading(true)
    setErrorMsg('')
    try {
      const response = await lmsReferensiService.getDaftar({
        page,
        search,
        modul_ajar_id: selectedModulAjar,
        status: selectedStatus,
        per_page: 15,
      })
      if (response?.data) {
        setDataReferensi(response.data)
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
      setErrorMsg('Gagal memuat daftar Referensi Pembelajaran. Silakan coba lagi.')
      console.error(err)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    loadInitialOptionsAndStats()
  }, [])

  useEffect(() => {
    fetchDaftarReferensi()
  }, [page, search, selectedModulAjar, selectedStatus])

  const handleOpenAddModal = () => {
    setEditingItem(null)
    setSelectedFile(null)
    setSelectedReferensiUmumId('')
    setFormData({
      modul_ajar_id: '',
      judul: '',
      penulis: '',
      penerbit: '',
      tahun: new Date().getFullYear(),
      url: '',
      status: 'aktif',
    })
    setModalOpen(true)
  }

  const handleOpenEditModal = (item) => {
    setEditingItem(item)
    setSelectedFile(null)
    setSelectedReferensiUmumId('')
    setFormData({
      modul_ajar_id: item.modul_ajar_id || '',
      judul: item.judul || '',
      penulis: item.penulis || '',
      penerbit: item.penerbit || '',
      tahun: item.tahun || new Date().getFullYear(),
      url: item.url || '',
      status: item.status || 'aktif',
    })
    setModalOpen(true)
  }

  const handleSelectReferensiUmum = (refId) => {
    setSelectedReferensiUmumId(refId)
    if (!refId) return
    const selectedRef = optionsReferensiUmum.find((r) => r.id === refId)
    if (selectedRef) {
      setFormData((prev) => ({
        ...prev,
        judul: selectedRef.judul || prev.judul,
        penulis: selectedRef.penulis || prev.penulis,
        penerbit: selectedRef.penerbit || prev.penerbit,
        tahun: selectedRef.tahun || prev.tahun,
        url: selectedRef.url || prev.url,
      }))
    }
  }

  const handleOpenPreviewModal = (item) => {
    setPreviewItem(item)
    setPreviewModalOpen(true)
  }

  const handleFormChange = (e) => {
    const { name, value } = e.target
    setFormData((prev) => ({ ...prev, [name]: value }))
  }

  const handleFileChange = (e) => {
    if (e.target.files && e.target.files[0]) {
      setSelectedFile(e.target.files[0])
    }
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    if (!formData.judul.trim()) {
      Swal.fire('Peringatan', 'Judul referensi wajib diisi!', 'warning')
      return
    }

    setFormSubmitting(true)
    try {
      const payload = new FormData()
      payload.append('modul_ajar_id', formData.modul_ajar_id || '')
      payload.append('judul', formData.judul)
      if (formData.penulis) payload.append('penulis', formData.penulis)
      if (formData.penerbit) payload.append('penerbit', formData.penerbit)
      if (formData.tahun) payload.append('tahun', formData.tahun)
      if (formData.url) payload.append('url', formData.url)
      payload.append('status', formData.status)

      if (selectedFile) {
        payload.append('file', selectedFile)
      }

      if (editingItem) {
        await lmsReferensiService.update(editingItem.id, payload)
        setSuccessMsg('Referensi Pembelajaran berhasil diperbarui!')
      } else {
        await lmsReferensiService.create(payload)
        setSuccessMsg('Referensi Pembelajaran berhasil ditambahkan!')
      }

      setModalOpen(false)
      fetchDaftarReferensi()
      loadInitialOptionsAndStats()
    } catch (err) {
      console.error(err)
      const errRes = err.response?.data
      const pesan = errRes?.message || 'Gagal menyimpan data Referensi Pembelajaran.'
      Swal.fire('Error', pesan, 'error')
    } finally {
      setFormSubmitting(false)
    }
  }

  const handleDelete = async (id, judul) => {
    const result = await Swal.fire({
      title: 'Konfirmasi Hapus',
      text: `Apakah Anda yakin ingin menghapus referensi "${judul}"?`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: 'Ya, Hapus!',
      cancelButtonText: 'Batal',
    })

    if (result.isConfirmed) {
      try {
        await lmsReferensiService.delete(id)
        Swal.fire('Berhasil', 'Referensi Pembelajaran berhasil dihapus.', 'success')
        fetchDaftarReferensi()
        loadInitialOptionsAndStats()
      } catch (err) {
        console.error(err)
        Swal.fire('Error', 'Gagal menghapus data Referensi Pembelajaran.', 'error')
      }
    }
  }

  return (
    <div className="space-y-6 p-6 min-h-screen bg-[#F7F9FC] dark:bg-[#0F172A] text-slate-800 dark:text-slate-100 font-sans transition-colors duration-300">
      {/* Header Banner */}
      <div className="relative overflow-hidden rounded-[18px] bg-gradient-to-r from-[#0E5C44] via-[#1E8E5A] to-[#3FBF75] p-8 text-white shadow-xl">
        <div className="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <div className="flex items-center gap-3">
              <div className="p-3 bg-white/10 backdrop-blur-md rounded-xl border border-white/20">
                <BookMarked className="w-8 h-8 text-amber-300 animate-pulse" />
              </div>
              <div>
                <h1 className="text-3xl font-bold tracking-tight">Referensi Pembelajaran</h1>
                <p className="text-emerald-100 text-sm mt-1">
                  Kelola buku acuan, jurnal, modul luar, dan sumber kepustakaan digital terpadu (Modul Ajar 1:N Referensi).
                </p>
              </div>
            </div>
          </div>

          <div className="flex items-center gap-3">
            <button
              onClick={fetchDaftarReferensi}
              className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 backdrop-blur-md border border-white/20 text-white font-medium text-sm transition-all duration-200 hover:scale-105 active:scale-95"
            >
              <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} />
              Segarkan
            </button>
            <button
              onClick={handleOpenAddModal}
              className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-white text-[#0E5C44] hover:bg-emerald-50 font-semibold text-sm transition-all duration-200 shadow-lg hover:shadow-xl hover:scale-105 active:scale-95"
            >
              <Plus className="w-5 h-5" />
              Tambah Referensi
            </button>
          </div>
        </div>
      </div>

      {/* Alert Messages */}
      {successMsg && (
        <div className="flex items-center justify-between p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 text-sm shadow-sm animate-fade-in">
          <div className="flex items-center gap-3">
            <CheckCircle className="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0" />
            <span>{successMsg}</span>
          </div>
          <button onClick={() => setSuccessMsg('')} className="hover:opacity-75">
            <X className="w-4 h-4" />
          </button>
        </div>
      )}

      {errorMsg && (
        <div className="flex items-center justify-between p-4 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-300 text-sm shadow-sm animate-fade-in">
          <div className="flex items-center gap-3">
            <AlertCircle className="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0" />
            <span>{errorMsg}</span>
          </div>
          <button onClick={() => setErrorMsg('')} className="hover:opacity-75">
            <X className="w-4 h-4" />
          </button>
        </div>
      )}

      {/* KPI Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <div className="bg-white dark:bg-[#1B2433] rounded-[18px] p-5 border border-slate-200/80 dark:border-slate-800 shadow-sm hover:shadow-md transition-all duration-200">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
              Total Referensi
            </span>
            <div className="p-2.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 text-[#0E5C44] dark:text-emerald-400">
              <BookMarked className="w-5 h-5" />
            </div>
          </div>
          <div className="mt-3">
            <span className="text-2xl font-bold text-slate-900 dark:text-white">{stats.total_referensi || 0}</span>
            <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">Item referensi terdaftar</p>
          </div>
        </div>

        <div className="bg-white dark:bg-[#1B2433] rounded-[18px] p-5 border border-slate-200/80 dark:border-slate-800 shadow-sm hover:shadow-md transition-all duration-200">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
              Status Aktif
            </span>
            <div className="p-2.5 rounded-xl bg-teal-50 dark:bg-teal-950/40 text-teal-600 dark:text-teal-400">
              <CheckCircle className="w-5 h-5" />
            </div>
          </div>
          <div className="mt-3">
            <span className="text-2xl font-bold text-slate-900 dark:text-white">{stats.total_aktif || 0}</span>
            <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">Siap digunakan mengajar</p>
          </div>
        </div>

        <div className="bg-white dark:bg-[#1B2433] rounded-[18px] p-5 border border-slate-200/80 dark:border-slate-800 shadow-sm hover:shadow-md transition-all duration-200">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
              Dengan File Dokumen
            </span>
            <div className="p-2.5 rounded-xl bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400">
              <FileText className="w-5 h-5" />
            </div>
          </div>
          <div className="mt-3">
            <span className="text-2xl font-bold text-slate-900 dark:text-white">{stats.dengan_file || 0}</span>
            <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">Berkas PDF/Dokumen terunggah</p>
          </div>
        </div>

        <div className="bg-white dark:bg-[#1B2433] rounded-[18px] p-5 border border-slate-200/80 dark:border-slate-800 shadow-sm hover:shadow-md transition-all duration-200">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
              Tautan URL Web
            </span>
            <div className="p-2.5 rounded-xl bg-purple-50 dark:bg-purple-950/40 text-purple-600 dark:text-purple-400">
              <LinkIcon className="w-5 h-5" />
            </div>
          </div>
          <div className="mt-3">
            <span className="text-2xl font-bold text-slate-900 dark:text-white">{stats.dengan_url || 0}</span>
            <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">Link portal/jurnal eksternal</p>
          </div>
        </div>

        <div className="bg-white dark:bg-[#1B2433] rounded-[18px] p-5 border border-slate-200/80 dark:border-slate-800 shadow-sm hover:shadow-md transition-all duration-200">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
              Modul Ajar Terkait
            </span>
            <div className="p-2.5 rounded-xl bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400">
              <Layers className="w-5 h-5" />
            </div>
          </div>
          <div className="mt-3">
            <span className="text-2xl font-bold text-slate-900 dark:text-white">{stats.total_modul_ajar || 0}</span>
            <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">Induk Modul Ajar aktif</p>
          </div>
        </div>
      </div>

      {/* Filter Bar */}
      <div className="bg-white dark:bg-[#1B2433] rounded-[18px] p-5 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-4">
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div className="flex items-center gap-2">
            <Filter className="w-5 h-5 text-[#0E5C44] dark:text-emerald-400" />
            <h2 className="text-base font-semibold text-slate-800 dark:text-slate-100">Filter & Pencarian</h2>
          </div>

          {(search || selectedModulAjar || selectedStatus) && (
            <button
              onClick={() => {
                setSearch('')
                setSelectedModulAjar('')
                setSelectedStatus('')
                setPage(1)
              }}
              className="text-xs text-rose-600 dark:text-rose-400 hover:underline flex items-center gap-1 self-start md:self-auto"
            >
              <X className="w-3.5 h-3.5" />
              Reset Filter
            </button>
          )}
        </div>

        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          {/* Search Box */}
          <div className="relative">
            <Search className="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
            <input
              type="text"
              placeholder="Cari judul, penulis, penerbit, tahun..."
              value={search}
              onChange={(e) => {
                setSearch(e.target.value)
                setPage(1)
              }}
              className="w-full pl-10 pr-4 py-2.5 text-sm rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-[#0E5C44] transition-all"
            />
          </div>

          {/* Filter Modul Ajar */}
          <div>
            <select
              value={selectedModulAjar}
              onChange={(e) => {
                setSelectedModulAjar(e.target.value)
                setPage(1)
              }}
              className="w-full px-4 py-2.5 text-sm rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-[#0E5C44] transition-all"
            >
              <option value="">-- Semua Modul Ajar --</option>
              <option value="umum">-- Referensi Umum (Tanpa Modul) --</option>
              {optionsModulAjar.map((modul) => (
                <option key={modul.id} value={modul.id}>
                  {modul.kode_modul ? `[${modul.kode_modul}] ` : ''}
                  {modul.judul_modul}
                </option>
              ))}
            </select>
          </div>

          {/* Filter Status */}
          <div>
            <select
              value={selectedStatus}
              onChange={(e) => {
                setSelectedStatus(e.target.value)
                setPage(1)
              }}
              className="w-full px-4 py-2.5 text-sm rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-[#0E5C44] transition-all"
            >
              <option value="">-- Semua Status --</option>
              <option value="aktif">Aktif</option>
              <option value="non-aktif">Non-Aktif</option>
            </select>
          </div>
        </div>
      </div>

      {/* Main Table Card */}
      <div className="bg-white dark:bg-[#1B2433] rounded-[18px] border border-slate-200/80 dark:border-slate-800 shadow-sm overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse">
            <thead>
              <tr className="bg-slate-50/80 dark:bg-slate-900/60 border-b border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 text-xs font-semibold uppercase tracking-wider">
                <th className="py-4 px-6">No</th>
                <th className="py-4 px-6">Judul Referensi</th>
                <th className="py-4 px-6">Penulis & Penerbit</th>
                <th className="py-4 px-6">Tahun</th>
                <th className="py-4 px-6">Modul Ajar Terkait</th>
                <th className="py-4 px-6">Lampiran & URL</th>
                <th className="py-4 px-6">Status</th>
                <th className="py-4 px-6 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60 text-sm">
              {loading ? (
                <tr>
                  <td colSpan="8" className="py-12 text-center text-slate-500 dark:text-slate-400">
                    <div className="flex flex-col items-center justify-center gap-2">
                      <RefreshCw className="w-6 h-6 animate-spin text-[#0E5C44]" />
                      <span>Memuat data Referensi Pembelajaran...</span>
                    </div>
                  </td>
                </tr>
              ) : dataReferensi.length === 0 ? (
                <tr>
                  <td colSpan="8" className="py-12 text-center text-slate-500 dark:text-slate-400">
                    <div className="flex flex-col items-center justify-center gap-2">
                      <BookMarked className="w-10 h-10 text-slate-300 dark:text-slate-600" />
                      <span className="font-medium text-slate-600 dark:text-slate-300">Belum ada Referensi Pembelajaran</span>
                      <p className="text-xs text-slate-400">Klik tombol "Tambah Referensi" untuk menambahkan data baru.</p>
                    </div>
                  </td>
                </tr>
              ) : (
                dataReferensi.map((item, index) => {
                  const number = (pagination.current_page - 1) * pagination.per_page + index + 1
                  return (
                    <tr
                      key={item.id}
                      className="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors duration-150 group"
                    >
                      <td className="py-4 px-6 font-medium text-slate-400 text-xs">{number}</td>
                      <td className="py-4 px-6">
                        <div className="flex items-start gap-3">
                          <div className="p-2 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 text-[#0E5C44] dark:text-emerald-400 shrink-0 mt-0.5">
                            <BookOpen className="w-4 h-4" />
                          </div>
                          <div>
                            <span className="font-semibold text-slate-900 dark:text-white line-clamp-2">
                              {item.judul}
                            </span>
                          </div>
                        </div>
                      </td>
                      <td className="py-4 px-6 text-slate-600 dark:text-slate-300">
                        <div className="space-y-0.5">
                          <div className="flex items-center gap-1.5 text-xs text-slate-700 dark:text-slate-200 font-medium">
                            <User className="w-3.5 h-3.5 text-slate-400" />
                            <span>{item.penulis || '-'}</span>
                          </div>
                          <div className="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                            <Building className="w-3.5 h-3.5 text-slate-400" />
                            <span>{item.penerbit || '-'}</span>
                          </div>
                        </div>
                      </td>
                      <td className="py-4 px-6 text-slate-600 dark:text-slate-300">
                        <span className="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                          <Calendar className="w-3 h-3 text-slate-400" />
                          {item.tahun || '-'}
                        </span>
                      </td>
                      <td className="py-4 px-6">
                        {item.modul_ajar ? (
                          <div className="space-y-0.5">
                            <span className="inline-block font-medium text-xs text-emerald-800 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/50 px-2.5 py-1 rounded-lg border border-emerald-200/60 dark:border-emerald-800/60 line-clamp-1">
                              {item.modul_ajar.judul_modul}
                            </span>
                            {item.modul_ajar.kode_modul && (
                              <p className="text-[11px] text-slate-400 px-1">
                                Kode: {item.modul_ajar.kode_modul}
                              </p>
                            )}
                          </div>
                        ) : (
                          <span className="text-xs text-slate-400 italic">Umum / Tanpa Modul</span>
                        )}
                      </td>
                      <td className="py-4 px-6">
                        <div className="flex flex-col gap-1.5">
                          {item.file_url && (
                            <a
                              href={item.file_url}
                              target="_blank"
                              rel="noopener noreferrer"
                              className="inline-flex items-center gap-1.5 text-xs font-medium text-blue-600 dark:text-blue-400 hover:underline"
                            >
                              <Download className="w-3.5 h-3.5" />
                              Unduh File
                            </a>
                          )}
                          {item.url && (
                            <a
                              href={item.url}
                              target="_blank"
                              rel="noopener noreferrer"
                              className="inline-flex items-center gap-1.5 text-xs font-medium text-teal-600 dark:text-teal-400 hover:underline line-clamp-1"
                            >
                              <ExternalLink className="w-3.5 h-3.5 shrink-0" />
                              Buka Link URL
                            </a>
                          )}
                          {!item.file_url && !item.url && (
                            <span className="text-xs text-slate-400">-</span>
                          )}
                        </div>
                      </td>
                      <td className="py-4 px-6">
                        <span
                          className={`inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full border ${
                            item.status === 'aktif'
                              ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800'
                              : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border-slate-200 dark:border-slate-700'
                          }`}
                        >
                          {item.status === 'aktif' ? (
                            <>
                              <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                              Aktif
                            </>
                          ) : (
                            <>
                              <span className="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                              Non-Aktif
                            </>
                          )}
                        </span>
                      </td>
                      <td className="py-4 px-6 text-right">
                        <div className="flex items-center justify-end gap-2">
                          <button
                            onClick={() => handleOpenPreviewModal(item)}
                            title="Detail Referensi"
                            className="p-2 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-[#0E5C44] hover:text-white dark:hover:bg-[#0E5C44] transition-all duration-200"
                          >
                            <Eye className="w-4 h-4" />
                          </button>
                          <button
                            onClick={() => handleOpenEditModal(item)}
                            title="Edit Referensi"
                            className="p-2 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-amber-500 hover:text-white dark:hover:bg-amber-500 transition-all duration-200"
                          >
                            <Edit3 className="w-4 h-4" />
                          </button>
                          <button
                            onClick={() => handleDelete(item.id, item.judul)}
                            title="Hapus Referensi"
                            className="p-2 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-rose-600 hover:text-white dark:hover:bg-rose-600 transition-all duration-200"
                          >
                            <Trash2 className="w-4 h-4" />
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
        {pagination.last_page > 1 && (
          <div className="flex flex-col sm:flex-row items-center justify-between p-4 bg-slate-50/50 dark:bg-slate-900/40 border-t border-slate-200 dark:border-slate-800 text-xs text-slate-500 dark:text-slate-400 gap-3">
            <span>
              Menampilkan {pagination.from || 0} - {pagination.to || 0} dari total {pagination.total || 0} referensi
            </span>
            <div className="flex items-center gap-1">
              <button
                disabled={pagination.current_page === 1}
                onClick={() => setPage((p) => Math.max(1, p - 1))}
                className="p-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 disabled:opacity-40 disabled:cursor-not-allowed transition-all"
              >
                <ChevronLeft className="w-4 h-4" />
              </button>
              <span className="px-3 py-1 font-semibold text-slate-700 dark:text-slate-200">
                Halaman {pagination.current_page} dari {pagination.last_page}
              </span>
              <button
                disabled={pagination.current_page === pagination.last_page}
                onClick={() => setPage((p) => Math.min(pagination.last_page, p + 1))}
                className="p-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 disabled:opacity-40 disabled:cursor-not-allowed transition-all"
              >
                <ChevronRight className="w-4 h-4" />
              </button>
            </div>
          </div>
        )}
      </div>

      {/* Add/Edit Modal */}
      {modalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-fade-in">
          <div className="relative w-full max-w-2xl bg-white dark:bg-[#1B2433] rounded-[24px] shadow-2xl border border-slate-200 dark:border-slate-800 overflow-hidden flex flex-col max-h-[90vh]">
            {/* Modal Header */}
            <div className="flex items-center justify-between px-6 py-5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/30">
              <div className="flex items-center gap-3">
                <div className="p-2.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 text-[#0E5C44] dark:text-emerald-400">
                  <BookMarked className="w-6 h-6" />
                </div>
                <div>
                  <h3 className="text-lg font-bold text-slate-900 dark:text-white">
                    {editingItem ? 'Edit Referensi Pembelajaran' : 'Tambah Referensi Pembelajaran Baru'}
                  </h3>
                  <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    Lengkapi informasi buku, jurnal, atau referensi digital.
                  </p>
                </div>
              </div>
              <button
                onClick={() => setModalOpen(false)}
                className="p-2 rounded-xl text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-700 dark:hover:text-slate-200 transition-all"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            {/* Modal Body / Form */}
            <form onSubmit={handleSubmit} className="p-6 overflow-y-auto space-y-4">
              {/* Pilih dari Data Referensi Umum (Pre-fill Opsional) */}
              {!editingItem && optionsReferensiUmum.length > 0 && (
                <div className="p-3.5 rounded-xl bg-emerald-50/70 dark:bg-emerald-950/30 border border-emerald-200/80 dark:border-emerald-800/60 space-y-1.5">
                  <label className="block text-xs font-semibold uppercase tracking-wider text-[#0E5C44] dark:text-emerald-400">
                    Salin dari Data Referensi Umum (Opsional)
                  </label>
                  <select
                    value={selectedReferensiUmumId}
                    onChange={(e) => handleSelectReferensiUmum(e.target.value)}
                    className="w-full px-3.5 py-2 text-xs rounded-lg bg-white dark:bg-slate-900 border border-emerald-300 dark:border-emerald-700 text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-[#0E5C44] focus:outline-none transition-all"
                  >
                    <option value="">-- Pilih Referensi Umum yang Tersedia --</option>
                    {optionsReferensiUmum.map((ref) => (
                      <option key={ref.id} value={ref.id}>
                        {ref.judul} {ref.penulis ? `(${ref.penulis})` : ''}
                      </option>
                    ))}
                  </select>
                  <p className="text-[11px] text-emerald-700 dark:text-emerald-300">
                    Pilih referensi umum terdaftar untuk mengisi otomatis Judul, Penulis, Penerbit, Tahun, dan URL.
                  </p>
                </div>
              )}

              {/* Select Modul Ajar */}
              <div>
                <label className="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1.5">
                  Tautkan Modul Ajar (Opsional)
                </label>
                <select
                  name="modul_ajar_id"
                  value={formData.modul_ajar_id}
                  onChange={handleFormChange}
                  className="w-full px-4 py-2.5 text-sm rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-[#0E5C44] focus:outline-none transition-all"
                >
                  <option value="">-- Referensi Umum (Tanpa Modul) --</option>
                  {optionsModulAjar.map((modul) => (
                    <option key={modul.id} value={modul.id}>
                      {modul.kode_modul ? `[${modul.kode_modul}] ` : ''}
                      {modul.judul_modul}
                    </option>
                  ))}
                </select>
              </div>

              {/* Judul Referensi */}
              <div>
                <label className="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1.5">
                  Judul Referensi <span className="text-rose-500">*</span>
                </label>
                <input
                  type="text"
                  name="judul"
                  placeholder="Contoh: Buku Paket Matematika Kurikulum Merdeka SMP Kelas VII"
                  value={formData.judul}
                  onChange={handleFormChange}
                  required
                  className="w-full px-4 py-2.5 text-sm rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-[#0E5C44] focus:outline-none transition-all"
                />
              </div>

              {/* Penulis & Penerbit */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1.5">
                    Penulis / Pengarang
                  </label>
                  <input
                    type="text"
                    name="penulis"
                    placeholder="Contoh: Dr. Ahmad Dahlan"
                    value={formData.penulis}
                    onChange={handleFormChange}
                    className="w-full px-4 py-2.5 text-sm rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-[#0E5C44] focus:outline-none transition-all"
                  />
                </div>

                <div>
                  <label className="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1.5">
                    Penerbit
                  </label>
                  <input
                    type="text"
                    name="penerbit"
                    placeholder="Contoh: Kemendikbudristek / Erlangga"
                    value={formData.penerbit}
                    onChange={handleFormChange}
                    className="w-full px-4 py-2.5 text-sm rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-[#0E5C44] focus:outline-none transition-all"
                  />
                </div>
              </div>

              {/* Tahun & Status */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1.5">
                    Tahun Terbit
                  </label>
                  <input
                    type="number"
                    name="tahun"
                    min="1900"
                    max="2100"
                    placeholder="2024"
                    value={formData.tahun}
                    onChange={handleFormChange}
                    className="w-full px-4 py-2.5 text-sm rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-[#0E5C44] focus:outline-none transition-all"
                  />
                </div>

                <div>
                  <label className="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1.5">
                    Status Referensi
                  </label>
                  <select
                    name="status"
                    value={formData.status}
                    onChange={handleFormChange}
                    className="w-full px-4 py-2.5 text-sm rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-[#0E5C44] focus:outline-none transition-all"
                  >
                    <option value="aktif">Aktif</option>
                    <option value="non-aktif">Non-Aktif</option>
                  </select>
                </div>
              </div>

              {/* URL Eksternal Link */}
              <div>
                <label className="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1.5">
                  Tautan URL Eksternal / Website / Jurnal
                </label>
                <input
                  type="url"
                  name="url"
                  placeholder="https://..."
                  value={formData.url}
                  onChange={handleFormChange}
                  className="w-full px-4 py-2.5 text-sm rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-[#0E5C44] focus:outline-none transition-all"
                />
              </div>

              {/* Upload File PDF / Dokumen */}
              <div>
                <label className="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1.5">
                  Unggah Berkas File (PDF/DOC/Image, maks 20MB)
                </label>
                <div className="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-slate-200 dark:border-slate-700 border-dashed rounded-xl bg-slate-50/50 dark:bg-slate-900/30 hover:border-[#0E5C44] transition-all">
                  <div className="space-y-1 text-center">
                    <FileUp className="mx-auto h-10 w-10 text-slate-400" />
                    <div className="flex text-sm text-slate-600 dark:text-slate-400">
                      <label
                        htmlFor="file-upload"
                        className="relative cursor-pointer font-medium text-[#0E5C44] dark:text-emerald-400 hover:underline"
                      >
                        <span>Pilih berkas file</span>
                        <input
                          id="file-upload"
                          name="file-upload"
                          type="file"
                          onChange={handleFileChange}
                          className="sr-only"
                        />
                      </label>
                      <p className="pl-1">atau drag and drop di sini</p>
                    </div>
                    <p className="text-xs text-slate-500 dark:text-slate-500">PDF, DOC, DOCX, PNG, JPG hingga 20MB</p>
                    {selectedFile && (
                      <div className="mt-2 text-xs font-semibold text-emerald-600 dark:text-emerald-400 flex items-center justify-center gap-1">
                        <Check className="w-4 h-4" /> Terpilih: {selectedFile.name}
                      </div>
                    )}
                    {editingItem?.file_url && !selectedFile && (
                      <p className="text-xs text-blue-600 dark:text-blue-400 mt-1">
                        File saat ini sudah ada. Pilih file baru jika ingin menggantinya.
                      </p>
                    )}
                  </div>
                </div>
              </div>

              {/* Submit Buttons */}
              <div className="pt-4 flex items-center justify-end gap-3 border-t border-slate-100 dark:border-slate-800">
                <button
                  type="button"
                  onClick={() => setModalOpen(false)}
                  className="px-5 py-2.5 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl transition-all"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={formSubmitting}
                  className="inline-flex items-center gap-2 px-6 py-2.5 text-sm font-semibold text-white bg-[#0E5C44] hover:bg-[#1E8E5A] active:scale-95 rounded-xl shadow-lg hover:shadow-xl transition-all disabled:opacity-50"
                >
                  {formSubmitting && <RefreshCw className="w-4 h-4 animate-spin" />}
                  {editingItem ? 'Simpan Perubahan' : 'Tambah Referensi'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Preview / Detail Modal */}
      {previewModalOpen && previewItem && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-fade-in">
          <div className="relative w-full max-w-xl bg-white dark:bg-[#1B2433] rounded-[24px] shadow-2xl border border-slate-200 dark:border-slate-800 overflow-hidden flex flex-col">
            <div className="flex items-center justify-between px-6 py-5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/30">
              <div className="flex items-center gap-3">
                <div className="p-2.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 text-[#0E5C44] dark:text-emerald-400">
                  <Eye className="w-6 h-6" />
                </div>
                <div>
                  <h3 className="text-lg font-bold text-slate-900 dark:text-white">Detail Referensi Pembelajaran</h3>
                  <p className="text-xs text-slate-500 dark:text-slate-400">Informasi lengkap kepustakaan & modul</p>
                </div>
              </div>
              <button
                onClick={() => setPreviewModalOpen(false)}
                className="p-2 rounded-xl text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <div className="p-6 space-y-4 overflow-y-auto max-h-[75vh]">
              <div>
                <span className="text-xs font-semibold text-slate-400 uppercase tracking-wider">Judul Referensi</span>
                <h4 className="text-base font-bold text-slate-900 dark:text-white mt-1">{previewItem.judul}</h4>
              </div>

              <div className="grid grid-cols-2 gap-4 bg-slate-50 dark:bg-slate-900/50 p-4 rounded-xl border border-slate-100 dark:border-slate-800">
                <div>
                  <span className="text-xs text-slate-400 block">Penulis</span>
                  <span className="text-sm font-semibold text-slate-700 dark:text-slate-200">
                    {previewItem.penulis || '-'}
                  </span>
                </div>
                <div>
                  <span className="text-xs text-slate-400 block">Penerbit</span>
                  <span className="text-sm font-semibold text-slate-700 dark:text-slate-200">
                    {previewItem.penerbit || '-'}
                  </span>
                </div>
                <div>
                  <span className="text-xs text-slate-400 block">Tahun Terbit</span>
                  <span className="text-sm font-semibold text-slate-700 dark:text-slate-200">
                    {previewItem.tahun || '-'}
                  </span>
                </div>
                <div>
                  <span className="text-xs text-slate-400 block">Status</span>
                  <span className="text-sm font-semibold capitalize text-emerald-600 dark:text-emerald-400">
                    {previewItem.status || 'aktif'}
                  </span>
                </div>
              </div>

              {previewItem.modul_ajar && (
                <div className="p-4 rounded-xl bg-emerald-50/50 dark:bg-emerald-950/20 border border-emerald-100 dark:border-emerald-900/40">
                  <span className="text-xs font-semibold text-emerald-800 dark:text-emerald-400 uppercase tracking-wider block">
                    Modul Ajar Terkait
                  </span>
                  <p className="text-sm font-bold text-slate-800 dark:text-slate-200 mt-1">
                    {previewItem.modul_ajar.judul_modul}
                  </p>
                  {previewItem.modul_ajar.kode_modul && (
                    <p className="text-xs text-emerald-600 dark:text-emerald-400 mt-0.5">
                      Kode: {previewItem.modul_ajar.kode_modul}
                    </p>
                  )}
                </div>
              )}

              <div className="space-y-2 pt-2">
                {previewItem.file_url && (
                  <a
                    href={previewItem.file_url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="w-full flex items-center justify-center gap-2 py-2.5 px-4 bg-[#0E5C44] hover:bg-[#1E8E5A] text-white font-semibold text-sm rounded-xl transition-all shadow-md"
                  >
                    <Download className="w-4 h-4" /> Unduh Berkas File
                  </a>
                )}
                {previewItem.url && (
                  <a
                    href={previewItem.url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="w-full flex items-center justify-center gap-2 py-2.5 px-4 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 font-semibold text-sm rounded-xl transition-all border border-slate-200 dark:border-slate-700"
                  >
                    <ExternalLink className="w-4 h-4" /> Buka Tautan URL Web
                  </a>
                )}
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
