import { useState, useEffect } from 'react'
import {
  Activity,
  BookOpen,
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
  Eye,
  Clock,
  Layers,
  ListOrdered,
  FileText,
  Tag,
  Check,
  Ban,
  SlidersHorizontal,
} from 'lucide-react'
import Swal from 'sweetalert2'
import { lmsAktivitasBelajarService } from '../services/lmsAktivitasBelajarService'

export default function LmsAktivitasBelajarPage() {
  const [dataAktivitas, setDataAktivitas] = useState([])
  const [optionsModulAjar, setOptionsModulAjar] = useState([])
  const [optionsJenis, setOptionsJenis] = useState([
    'Pendahuluan',
    'Inti',
    'Penutup',
    'Diskusi',
    'Kuis',
    'Tugas',
    'Presentasi',
    'Refleksi',
    'Eksperimen',
    'Praktikum',
  ])
  const [stats, setStats] = useState({
    total: 0,
    pendahuluan: 0,
    inti: 0,
    penutup: 0,
    aktif: 0,
    draft: 0,
  })

  const [loading, setLoading] = useState(true)
  const [errorMsg, setErrorMsg] = useState('')
  const [successMsg, setSuccessMsg] = useState('')

  // Filters & Pagination
  const [search, setSearch] = useState('')
  const [selectedModulAjar, setSelectedModulAjar] = useState('')
  const [selectedJenis, setSelectedJenis] = useState('')
  const [selectedStatus, setSelectedStatus] = useState('')
  const [page, setPage] = useState(1)
  const [pagination, setPagination] = useState({
    current_page: 1,
    last_page: 1,
    total: 0,
    per_page: 15,
  })

  // Modal Form State
  const [isModalOpen, setIsModalOpen] = useState(false)
  const [isDetailOpen, setIsDetailOpen] = useState(false)
  const [selectedDetail, setSelectedDetail] = useState(null)
  const [editId, setEditId] = useState(null)
  const [formLoading, setFormLoading] = useState(false)

  const [formData, setFormData] = useState({
    modul_ajar_id: '',
    nama_aktivitas: '',
    jenis_aktivitas: 'Inti',
    instruksi: '',
    waktu: 15,
    urutan: 1,
    status: 'aktif',
  })

  useEffect(() => {
    fetchOptions()
    fetchStats()
  }, [])

  useEffect(() => {
    fetchData()
  }, [page, search, selectedModulAjar, selectedJenis, selectedStatus])

  const fetchStats = async () => {
    try {
      const res = await lmsAktivitasBelajarService.getStats()
      if (res.success) {
        setStats(res.data)
      }
    } catch (err) {
      console.error('Gagal mengambil statistik aktivitas:', err)
    }
  }

  const fetchOptions = async () => {
    try {
      const res = await lmsAktivitasBelajarService.getOptions()
      if (res.success) {
        if (res.data.modul_ajar) setOptionsModulAjar(res.data.modul_ajar)
        if (res.data.jenis_aktivitas) setOptionsJenis(res.data.jenis_aktivitas)
      }
    } catch (err) {
      console.error('Gagal mengambil opsi data:', err)
    }
  }

  const fetchData = async () => {
    setLoading(true)
    setErrorMsg('')
    try {
      const params = {
        page,
        per_page: 15,
        search,
        modul_ajar_id: selectedModulAjar,
        jenis_aktivitas: selectedJenis,
        status: selectedStatus,
      }
      const res = await lmsAktivitasBelajarService.getDaftar(params)
      setDataAktivitas(res.data || [])
      if (res.meta) {
        setPagination({
          current_page: res.meta.current_page,
          last_page: res.meta.last_page,
          total: res.meta.total,
          per_page: res.meta.per_page,
        })
      }
    } catch (err) {
      console.error('Error fetching data aktivitas:', err)
      setErrorMsg('Gagal memuat data aktivitas belajar. Silakan coba lagi.')
    } finally {
      setLoading(false)
    }
  }

  const handleOpenCreateModal = () => {
    setEditId(null)
    setFormData({
      modul_ajar_id: optionsModulAjar.length > 0 ? optionsModulAjar[0].id : '',
      nama_aktivitas: '',
      jenis_aktivitas: 'Inti',
      instruksi: '',
      waktu: 15,
      urutan: dataAktivitas.length + 1,
      status: 'aktif',
    })
    setIsModalOpen(true)
  }

  const handleOpenEditModal = (item) => {
    setEditId(item.id)
    setFormData({
      modul_ajar_id: item.modul_ajar_id || '',
      nama_aktivitas: item.nama_aktivitas || '',
      jenis_aktivitas: item.jenis_aktivitas || 'Inti',
      instruksi: item.instruksi || '',
      waktu: item.waktu || 15,
      urutan: item.urutan || 1,
      status: item.status || 'aktif',
    })
    setIsModalOpen(true)
  }

  const handleOpenDetail = (item) => {
    setSelectedDetail(item)
    setIsDetailOpen(true)
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setFormLoading(true)
    setErrorMsg('')
    setSuccessMsg('')

    try {
      let res
      if (editId) {
        res = await lmsAktivitasBelajarService.update(editId, formData)
      } else {
        res = await lmsAktivitasBelajarService.create(formData)
      }

      if (res.success) {
        setSuccessMsg(editId ? 'Aktivitas Belajar berhasil diperbarui!' : 'Aktivitas Belajar berhasil dibuat!')
        setIsModalOpen(false)
        fetchData()
        fetchStats()
        setTimeout(() => setSuccessMsg(''), 4000)
      }
    } catch (err) {
      console.error('Error submitting form:', err)
      const errRes = err.response?.data
      if (errRes && errRes.message) {
        setErrorMsg(errRes.message)
      } else {
        setErrorMsg('Terjadi kesalahan saat menyimpan data. Periksa kembali form Anda.')
      }
    } finally {
      setFormLoading(false)
    }
  }

  const handleDelete = async (id, nama) => {
    const result = await Swal.fire({
      title: 'Hapus Aktivitas Belajar?',
      text: `Apakah Anda yakin ingin menghapus aktivitas "${nama}"?`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#0E5C44',
      cancelButtonColor: '#EF4444',
      confirmButtonText: 'Ya, Hapus!',
      cancelButtonText: 'Batal',
      customClass: {
        popup: 'rounded-2xl',
        confirmButton: 'px-5 py-2.5 rounded-xl text-white font-medium',
        cancelButton: 'px-5 py-2.5 rounded-xl font-medium',
      },
    })

    if (result.isConfirmed) {
      try {
        const res = await lmsAktivitasBelajarService.delete(id)
        if (res.success) {
          Swal.fire({
            title: 'Terhapus!',
            text: 'Aktivitas belajar berhasil dihapus.',
            icon: 'success',
            confirmButtonColor: '#0E5C44',
            customClass: { popup: 'rounded-2xl' },
          })
          fetchData()
          fetchStats()
        }
      } catch (err) {
        console.error('Gagal menghapus aktivitas:', err)
        Swal.fire({
          title: 'Gagal!',
          text: 'Gagal menghapus data aktivitas belajar.',
          icon: 'error',
          confirmButtonColor: '#0E5C44',
        })
      }
    }
  }

  const getJenisBadgeColor = (jenis) => {
    switch (jenis) {
      case 'Pendahuluan':
        return 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 border-blue-200 dark:border-blue-800'
      case 'Inti':
        return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800'
      case 'Penutup':
      case 'Refleksi':
        return 'bg-purple-50 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400 border-purple-200 dark:border-purple-800'
      case 'Diskusi':
      case 'Presentasi':
        return 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 border-amber-200 dark:border-amber-800'
      case 'Kuis':
      case 'Tugas':
        return 'bg-rose-50 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400 border-rose-200 dark:border-rose-800'
      default:
        return 'bg-teal-50 text-teal-700 dark:bg-teal-900/30 dark:text-teal-400 border-teal-200 dark:border-teal-800'
    }
  }

  return (
    <div className="space-y-6 pb-12 transition-all duration-300">
      {/* Top Banner / Hero Header */}
      <div className="relative overflow-hidden rounded-2xl bg-gradient-to-r from-[#0E5C44] via-[#1E8E5A] to-[#3FBF75] p-6 sm:p-8 text-white shadow-xl">
        <div className="absolute -right-10 -bottom-10 opacity-10 pointer-events-none">
          <Activity className="w-80 h-80 text-white" />
        </div>
        <div className="relative z-10 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
          <div>
            <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/15 backdrop-blur-md border border-white/20 text-xs font-semibold text-white/90 mb-3">
              <Sparkles className="w-3.5 h-3.5" /> LMS — Rencana Aktivitas Belajar
            </div>
            <h1 className="text-2xl sm:text-3xl font-extrabold tracking-tight">Aktivitas Belajar</h1>
            <p className="text-white/80 text-sm mt-1 max-w-xl">
              Kelola alur skenario kegiatan pembelajaran (Pendahuluan, Inti, Penutup, Diskusi, Tugas, dll.) terintegrasi dengan Modul Ajar.
            </p>
          </div>
          <button
            onClick={handleOpenCreateModal}
            className="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-white text-[#0E5C44] font-bold text-sm shadow-lg hover:bg-emerald-50 hover:scale-[1.03] active:scale-95 transition-all duration-200"
          >
            <Plus className="w-4 h-4 stroke-[3]" /> Tambah Aktivitas
          </button>
        </div>
      </div>

      {/* Alert Messages */}
      {successMsg && (
        <div className="flex items-center justify-between p-4 rounded-xl bg-emerald-50 text-emerald-800 border border-emerald-200 shadow-sm dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800/50">
          <div className="flex items-center gap-3">
            <CheckCircle className="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
            <span className="text-sm font-medium">{successMsg}</span>
          </div>
          <button onClick={() => setSuccessMsg('')} className="text-emerald-600 hover:text-emerald-800">
            <X className="w-4 h-4" />
          </button>
        </div>
      )}

      {errorMsg && (
        <div className="flex items-center justify-between p-4 rounded-xl bg-rose-50 text-rose-800 border border-rose-200 shadow-sm dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800/50">
          <div className="flex items-center gap-3">
            <AlertCircle className="w-5 h-5 text-rose-600 dark:text-rose-400" />
            <span className="text-sm font-medium">{errorMsg}</span>
          </div>
          <button onClick={() => setErrorMsg('')} className="text-rose-600 hover:text-rose-800">
            <X className="w-4 h-4" />
          </button>
        </div>
      )}

      {/* KPI Stats Cards Grid */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="p-5 rounded-2xl bg-white dark:bg-[#1B2433] border border-gray-100 dark:border-gray-800 shadow-xl/5 flex items-center justify-between hover:shadow-lg transition-all duration-200">
          <div>
            <p className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Aktivitas</p>
            <h3 className="text-2xl font-bold text-gray-900 dark:text-white mt-1">{stats.total}</h3>
            <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">Kegiatan Terdaftar</p>
          </div>
          <div className="w-12 h-12 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center text-[#0E5C44] dark:text-emerald-400">
            <Activity className="w-6 h-6" />
          </div>
        </div>

        <div className="p-5 rounded-2xl bg-white dark:bg-[#1B2433] border border-gray-100 dark:border-gray-800 shadow-xl/5 flex items-center justify-between hover:shadow-lg transition-all duration-200">
          <div>
            <p className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Pendahuluan</p>
            <h3 className="text-2xl font-bold text-blue-600 dark:text-blue-400 mt-1">{stats.pendahuluan}</h3>
            <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">Orientasi & Apersepsi</p>
          </div>
          <div className="w-12 h-12 rounded-xl bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center text-blue-600 dark:text-blue-400">
            <Clock className="w-6 h-6" />
          </div>
        </div>

        <div className="p-5 rounded-2xl bg-white dark:bg-[#1B2433] border border-gray-100 dark:border-gray-800 shadow-xl/5 flex items-center justify-between hover:shadow-lg transition-all duration-200">
          <div>
            <p className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Kegiatan Inti</p>
            <h3 className="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{stats.inti}</h3>
            <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">Eksplorasi & Praktik</p>
          </div>
          <div className="w-12 h-12 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
            <Layers className="w-6 h-6" />
          </div>
        </div>

        <div className="p-5 rounded-2xl bg-white dark:bg-[#1B2433] border border-gray-100 dark:border-gray-800 shadow-xl/5 flex items-center justify-between hover:shadow-lg transition-all duration-200">
          <div>
            <p className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Penutup & Refleksi</p>
            <h3 className="text-2xl font-bold text-purple-600 dark:text-purple-400 mt-1">{stats.penutup}</h3>
            <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">Evaluasi & Kesimpulan</p>
          </div>
          <div className="w-12 h-12 rounded-xl bg-purple-50 dark:bg-purple-900/30 flex items-center justify-center text-purple-600 dark:text-purple-400">
            <Tag className="w-6 h-6" />
          </div>
        </div>
      </div>

      {/* Main Table Card Container */}
      <div className="rounded-2xl bg-white dark:bg-[#1B2433] border border-gray-100 dark:border-gray-800 shadow-xl/5 overflow-hidden">
        {/* Filters Header Bar */}
        <div className="p-5 border-b border-gray-100 dark:border-gray-800 flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4">
          {/* Search Bar */}
          <div className="relative flex-1 min-w-[240px]">
            <Search className="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400" />
            <input
              type="text"
              placeholder="Cari aktivitas, instruksi, jenis..."
              value={search}
              onChange={(e) => {
                setSearch(e.target.value)
                setPage(1)
              }}
              className="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-[#111827] text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44]/20 focus:border-[#0E5C44] transition-all"
            />
          </div>

          {/* Select Filters */}
          <div className="flex flex-wrap items-center gap-3">
            {/* Modul Ajar Filter */}
            <div className="relative">
              <select
                value={selectedModulAjar}
                onChange={(e) => {
                  setSelectedModulAjar(e.target.value)
                  setPage(1)
                }}
                className="pl-3 pr-8 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-[#111827] text-gray-800 dark:text-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44]/20 focus:border-[#0E5C44]"
              >
                <option value="">Semua Modul Ajar</option>
                {optionsModulAjar.map((m) => (
                  <option key={m.id} value={m.id}>
                    {m.judul_modul}
                  </option>
                ))}
              </select>
            </div>

            {/* Jenis Aktivitas Filter */}
            <div className="relative">
              <select
                value={selectedJenis}
                onChange={(e) => {
                  setSelectedJenis(e.target.value)
                  setPage(1)
                }}
                className="pl-3 pr-8 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-[#111827] text-gray-800 dark:text-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44]/20 focus:border-[#0E5C44]"
              >
                <option value="">Semua Jenis</option>
                {optionsJenis.map((j) => (
                  <option key={j} value={j}>
                    {j}
                  </option>
                ))}
              </select>
            </div>

            {/* Status Filter */}
            <div className="relative">
              <select
                value={selectedStatus}
                onChange={(e) => {
                  setSelectedStatus(e.target.value)
                  setPage(1)
                }}
                className="pl-3 pr-8 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-[#111827] text-gray-800 dark:text-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44]/20 focus:border-[#0E5C44]"
              >
                <option value="">Semua Status</option>
                <option value="aktif">Aktif</option>
                <option value="draft">Draft</option>
                <option value="nonaktif">Nonaktif</option>
              </select>
            </div>

            {/* Refresh Button */}
            <button
              onClick={() => {
                fetchData()
                fetchStats()
              }}
              title="Refresh Data"
              className="p-2.5 rounded-xl border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
            >
              <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} />
            </button>
          </div>
        </div>

        {/* Data Table */}
        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm text-gray-600 dark:text-gray-300">
            <thead className="bg-gray-50/70 dark:bg-gray-950/40 text-xs uppercase font-semibold text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-800">
              <tr>
                <th className="py-3.5 px-4 text-center w-16">No / Urut</th>
                <th className="py-3.5 px-4">Nama Aktivitas Belajar</th>
                <th className="py-3.5 px-4">Jenis Kegiatan</th>
                <th className="py-3.5 px-4">Modul Ajar</th>
                <th className="py-3.5 px-4 text-center">Waktu</th>
                <th className="py-3.5 px-4 text-center">Status</th>
                <th className="py-3.5 px-4 text-right pr-6">Aksi</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
              {loading ? (
                <tr>
                  <td colSpan="7" className="py-12 text-center text-gray-400">
                    <RefreshCw className="w-6 h-6 animate-spin mx-auto mb-2 text-[#0E5C44]" />
                    <p className="text-sm">Memuat data aktivitas belajar...</p>
                  </td>
                </tr>
              ) : dataAktivitas.length === 0 ? (
                <tr>
                  <td colSpan="7" className="py-12 text-center text-gray-400">
                    <Activity className="w-10 h-10 mx-auto mb-2 opacity-40 text-gray-400" />
                    <p className="text-base font-semibold text-gray-600 dark:text-gray-300">Tidak ada data aktivitas</p>
                    <p className="text-xs text-gray-400 mt-0.5">Coba ubah kata kunci pencarian atau tambah data baru.</p>
                  </td>
                </tr>
              ) : (
                dataAktivitas.map((item, idx) => (
                  <tr key={item.id} className="hover:bg-gray-50/60 dark:hover:bg-gray-800/40 transition-colors">
                    {/* Urutan */}
                    <td className="py-3.5 px-4 text-center font-bold text-gray-700 dark:text-gray-300">
                      <span className="inline-flex items-center justify-center w-7 h-7 rounded-full bg-gray-100 dark:bg-gray-800 text-xs">
                        {item.urutan || (page - 1) * pagination.per_page + idx + 1}
                      </span>
                    </td>

                    {/* Nama Aktivitas & Preview Instruksi */}
                    <td className="py-3.5 px-4 max-w-xs">
                      <div className="font-semibold text-gray-900 dark:text-white line-clamp-1">{item.nama_aktivitas}</div>
                      {item.instruksi ? (
                        <div className="text-xs text-gray-400 line-clamp-1 mt-0.5">{item.instruksi}</div>
                      ) : (
                        <div className="text-xs text-gray-400 italic mt-0.5">Tanpa instruksi khusus</div>
                      )}
                    </td>

                    {/* Jenis Badge */}
                    <td className="py-3.5 px-4">
                      <span className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold border ${getJenisBadgeColor(item.jenis_aktivitas)}`}>
                        {item.jenis_aktivitas}
                      </span>
                    </td>

                    {/* Modul Ajar */}
                    <td className="py-3.5 px-4">
                      {item.modul_ajar ? (
                        <div>
                          <div className="text-xs font-medium text-gray-800 dark:text-gray-200 line-clamp-1">
                            {item.modul_ajar.judul_modul}
                          </div>
                          <div className="text-[11px] text-gray-400 font-mono">{item.modul_ajar.kode_modul}</div>
                        </div>
                      ) : (
                        <span className="text-xs text-gray-400 italic">-</span>
                      )}
                    </td>

                    {/* Waktu */}
                    <td className="py-3.5 px-4 text-center">
                      <span className="inline-flex items-center gap-1 text-xs font-semibold text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-800 px-2.5 py-1 rounded-lg">
                        <Clock className="w-3.5 h-3.5 text-[#0E5C44]" /> {item.waktu} menit
                      </span>
                    </td>

                    {/* Status */}
                    <td className="py-3.5 px-4 text-center">
                      {item.status === 'aktif' ? (
                        <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                          <Check className="w-3 h-3" /> Aktif
                        </span>
                      ) : item.status === 'draft' ? (
                        <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-400 border border-amber-200 dark:border-amber-800">
                          Draft
                        </span>
                      ) : (
                        <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400 border border-gray-200 dark:border-gray-700">
                          <Ban className="w-3 h-3" /> Nonaktif
                        </span>
                      )}
                    </td>

                    {/* Action Buttons */}
                    <td className="py-3.5 px-4 text-right pr-6">
                      <div className="flex items-center justify-end gap-1.5">
                        <button
                          onClick={() => handleOpenDetail(item)}
                          title="Lihat Detail"
                          className="p-1.5 rounded-lg text-gray-500 hover:text-[#0E5C44] hover:bg-emerald-50 dark:hover:bg-emerald-950/50 transition-colors"
                        >
                          <Eye className="w-4 h-4" />
                        </button>
                        <button
                          onClick={() => handleOpenEditModal(item)}
                          title="Edit Aktivitas"
                          className="p-1.5 rounded-lg text-gray-500 hover:text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-950/50 transition-colors"
                        >
                          <Edit3 className="w-4 h-4" />
                        </button>
                        <button
                          onClick={() => handleDelete(item.id, item.nama_aktivitas)}
                          title="Hapus"
                          className="p-1.5 rounded-lg text-gray-500 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/50 transition-colors"
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
        {pagination.last_page > 1 && (
          <div className="p-4 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between gap-4">
            <span className="text-xs text-gray-500 dark:text-gray-400">
              Menampilkan {dataAktivitas.length} dari {pagination.total} data aktivitas
            </span>
            <div className="flex items-center gap-1.5">
              <button
                disabled={page <= 1}
                onClick={() => setPage((p) => Math.max(p - 1, 1))}
                className="p-2 rounded-lg border border-gray-200 dark:border-gray-700 disabled:opacity-40 hover:bg-gray-50 dark:hover:bg-gray-800 text-xs font-medium"
              >
                <ChevronLeft className="w-4 h-4" />
              </button>
              <span className="text-xs font-semibold px-3 py-1 bg-gray-100 dark:bg-gray-800 rounded-lg">
                Halaman {pagination.current_page} / {pagination.last_page}
              </span>
              <button
                disabled={page >= pagination.last_page}
                onClick={() => setPage((p) => Math.min(p + 1, pagination.last_page))}
                className="p-2 rounded-lg border border-gray-200 dark:border-gray-700 disabled:opacity-40 hover:bg-gray-50 dark:hover:bg-gray-800 text-xs font-medium"
              >
                <ChevronRight className="w-4 h-4" />
              </button>
            </div>
          </div>
        )}
      </div>

      {/* Modal Form Tambah / Edit */}
      {isModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm animate-fadeIn">
          <div className="bg-white dark:bg-[#1B2433] rounded-2xl max-w-xl w-full p-6 shadow-2xl border border-gray-100 dark:border-gray-800 max-h-[90vh] overflow-y-auto">
            <div className="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-gray-800 mb-5">
              <div className="flex items-center gap-2.5">
                <div className="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-[#0E5C44] dark:text-emerald-400 flex items-center justify-center">
                  <Activity className="w-5 h-5" />
                </div>
                <div>
                  <h3 className="text-lg font-bold text-gray-900 dark:text-white">
                    {editId ? 'Edit Aktivitas Belajar' : 'Tambah Aktivitas Belajar'}
                  </h3>
                  <p className="text-xs text-gray-500 dark:text-gray-400">Isi kelengkapan data alur skenario pembelajaran</p>
                </div>
              </div>
              <button
                onClick={() => setIsModalOpen(false)}
                className="p-2 rounded-xl text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleSubmit} className="space-y-4">
              {/* Modul Ajar Dropdown */}
              <div>
                <label className="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1.5">
                  Modul Ajar <span className="text-rose-500">*</span>
                </label>
                <select
                  required
                  value={formData.modul_ajar_id}
                  onChange={(e) => setFormData({ ...formData, modul_ajar_id: e.target.value })}
                  className="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-[#111827] text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44]/20 focus:border-[#0E5C44]"
                >
                  <option value="" disabled>
                    -- Pilih Modul Ajar --
                  </option>
                  {optionsModulAjar.map((m) => (
                    <option key={m.id} value={m.id}>
                      {m.judul_modul} ({m.kode_modul})
                    </option>
                  ))}
                </select>
              </div>

              {/* Nama Aktivitas */}
              <div>
                <label className="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1.5">
                  Nama Aktivitas Belajar <span className="text-rose-500">*</span>
                </label>
                <input
                  type="text"
                  required
                  placeholder="Contoh: Eksplorasi Konsep & Studi Kasus Kelompok"
                  value={formData.nama_aktivitas}
                  onChange={(e) => setFormData({ ...formData, nama_aktivitas: e.target.value })}
                  className="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-[#111827] text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44]/20 focus:border-[#0E5C44]"
                />
              </div>

              {/* Jenis Aktivitas & Status Grid */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1.5">
                    Jenis Kegiatan <span className="text-rose-500">*</span>
                  </label>
                  <select
                    required
                    value={formData.jenis_aktivitas}
                    onChange={(e) => setFormData({ ...formData, jenis_aktivitas: e.target.value })}
                    className="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-[#111827] text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44]/20 focus:border-[#0E5C44]"
                  >
                    {optionsJenis.map((j) => (
                      <option key={j} value={j}>
                        {j}
                      </option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1.5">
                    Status Aktivitas <span className="text-rose-500">*</span>
                  </label>
                  <select
                    required
                    value={formData.status}
                    onChange={(e) => setFormData({ ...formData, status: e.target.value })}
                    className="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-[#111827] text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44]/20 focus:border-[#0E5C44]"
                  >
                    <option value="aktif">Aktif</option>
                    <option value="draft">Draft</option>
                    <option value="nonaktif">Nonaktif</option>
                  </select>
                </div>
              </div>

              {/* Waktu & Urutan Grid */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1.5">
                    Alokasi Waktu (Menit) <span className="text-rose-500">*</span>
                  </label>
                  <div className="relative">
                    <input
                      type="number"
                      min="1"
                      max="600"
                      required
                      value={formData.waktu}
                      onChange={(e) => setFormData({ ...formData, waktu: parseInt(e.target.value) || 1 })}
                      className="w-full pl-3.5 pr-12 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-[#111827] text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44]/20 focus:border-[#0E5C44]"
                    />
                    <span className="absolute right-3.5 top-1/2 -translate-y-1/2 text-xs font-medium text-gray-400">menit</span>
                  </div>
                </div>

                <div>
                  <label className="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1.5">
                    Nomor Urut <span className="text-rose-500">*</span>
                  </label>
                  <input
                    type="number"
                    min="1"
                    required
                    value={formData.urutan}
                    onChange={(e) => setFormData({ ...formData, urutan: parseInt(e.target.value) || 1 })}
                    className="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-[#111827] text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44]/20 focus:border-[#0E5C44]"
                  />
                </div>
              </div>

              {/* Instruksi Textarea */}
              <div>
                <label className="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1.5">
                  Instruksi / Langkah Kegiatan
                </label>
                <textarea
                  rows="4"
                  placeholder="Tuliskan petunjuk teknis atau instruksi detail bagi guru/siswa dalam aktivitas ini..."
                  value={formData.instruksi}
                  onChange={(e) => setFormData({ ...formData, instruksi: e.target.value })}
                  className="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-[#111827] text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44]/20 focus:border-[#0E5C44]"
                ></textarea>
              </div>

              {/* Submit Buttons */}
              <div className="flex items-center justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-800">
                <button
                  type="button"
                  onClick={() => setIsModalOpen(false)}
                  className="px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 font-medium text-sm hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={formLoading}
                  className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#0E5C44] text-white font-semibold text-sm shadow-md hover:bg-[#1E8E5A] disabled:opacity-50 transition-all duration-200"
                >
                  {formLoading ? <RefreshCw className="w-4 h-4 animate-spin" /> : editId ? 'Simpan Perubahan' : 'Tambah Aktivitas'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Modal Detail View */}
      {isDetailOpen && selectedDetail && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm animate-fadeIn">
          <div className="bg-white dark:bg-[#1B2433] rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-gray-100 dark:border-gray-800">
            <div className="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-gray-800 mb-4">
              <div className="flex items-center gap-2">
                <Activity className="w-5 h-5 text-[#0E5C44]" />
                <h3 className="text-lg font-bold text-gray-900 dark:text-white">Detail Aktivitas Belajar</h3>
              </div>
              <button onClick={() => setIsDetailOpen(false)} className="p-1.5 rounded-lg text-gray-400 hover:text-gray-600">
                <X className="w-5 h-5" />
              </button>
            </div>

            <div className="space-y-4">
              <div>
                <span className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold border ${getJenisBadgeColor(selectedDetail.jenis_aktivitas)}`}>
                  {selectedDetail.jenis_aktivitas}
                </span>
                <h4 className="text-xl font-bold text-gray-900 dark:text-white mt-2">{selectedDetail.nama_aktivitas}</h4>
              </div>

              <div className="grid grid-cols-2 gap-3 p-3.5 rounded-xl bg-gray-50 dark:bg-[#111827] text-xs">
                <div>
                  <span className="text-gray-400 block">Modul Ajar:</span>
                  <span className="font-semibold text-gray-800 dark:text-gray-200">
                    {selectedDetail.modul_ajar?.judul_modul || '-'}
                  </span>
                </div>
                <div>
                  <span className="text-gray-400 block">Alokasi Waktu:</span>
                  <span className="font-semibold text-gray-800 dark:text-gray-200">{selectedDetail.waktu} menit</span>
                </div>
                <div>
                  <span className="text-gray-400 block">Nomor Urutan:</span>
                  <span className="font-semibold text-gray-800 dark:text-gray-200">Urutan ke-{selectedDetail.urutan}</span>
                </div>
                <div>
                  <span className="text-gray-400 block">Status:</span>
                  <span className="font-semibold capitalize text-emerald-600 dark:text-emerald-400">{selectedDetail.status}</span>
                </div>
              </div>

              <div>
                <h5 className="text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1.5">Instruksi Kegiatan:</h5>
                <div className="p-3.5 rounded-xl bg-gray-50 dark:bg-[#111827] border border-gray-100 dark:border-gray-800 text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line leading-relaxed">
                  {selectedDetail.instruksi || 'Tidak ada instruksi khusus.'}
                </div>
              </div>

              <div className="flex justify-end pt-3">
                <button
                  onClick={() => setIsDetailOpen(false)}
                  className="px-4 py-2 rounded-xl bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-medium text-sm hover:bg-gray-200"
                >
                  Tutup
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
