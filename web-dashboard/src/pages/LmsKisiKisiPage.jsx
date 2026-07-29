import React, { useState, useEffect } from 'react'
import {
  FileText,
  BookOpen,
  Target,
  Award,
  Plus,
  Search,
  Filter,
  Edit3,
  Trash2,
  Copy,
  CheckCircle2,
  XCircle,
  Clock,
  Sparkles,
  Layers,
  Eye,
  RefreshCw,
  X,
  HelpCircle,
  PieChart,
} from 'lucide-react'
import { lmsKisiKisiService } from '../services/lmsKisiKisiService'

const normalizeArray = (value) => {
  if (Array.isArray(value)) return value
  if (Array.isArray(value?.data)) return value.data
  return []
}

const getSubjectLabel = (subject) =>
  subject?.label ||
  subject?.nama_mapel ||
  subject?.nama ||
  subject?.name ||
  subject?.kode_mapel ||
  'Mata Pelajaran'

export default function LmsKisiKisiPage() {
  const [dataList, setDataList] = useState([])
  const [loading, setLoading] = useState(true)
  const [pagination, setPagination] = useState({ currentPage: 1, lastPage: 1, total: 0 })
  const [stats, setStats] = useState({ total: 0, aktif: 0, nonaktif: 0, total_soal_target: 0, uh: 0, pts: 0, pas: 0 })
  const [loadingOptions, setLoadingOptions] = useState(false)
  const [options, setOptions] = useState({
    subjects: [],
    kurikulum: [],
    kelas: [],
    semesters: [],
    tahun_ajaran: [],
    guru: [],
    capaian_pembelajaran: [],
    tujuan_pembelajaran: [],
    jenis_ujian_options: [],
    level_kognitif_options: [],
  })

  const [filters, setFilters] = useState({
    search: '',
    mata_pelajaran_id: '',
    jenis_ujian: '',
    status: '',
  })

  const [showModal, setShowModal] = useState(false)
  const [showDetailModal, setShowDetailModal] = useState(false)
  const [editingItem, setEditingItem] = useState(null)
  const [viewingItem, setViewingItem] = useState(null)
  const [toast, setToast] = useState({ show: false, message: '', type: 'success' })

  const [formData, setFormData] = useState({
    judul_kisi: '',
    mata_pelajaran_id: '',
    cp_id: '',
    tp_id: '',
    kurikulum_id: '',
    kelas_id: '',
    semester_id: '',
    tahun_ajaran_id: '',
    guru_id: '',
    jenis_ujian: 'UH',
    jumlah_soal: 20,
    alokasi_waktu_menit: 60,
    kompetensi_dasar: '',
    level_kognitif: 'C3 - Mengaplikasikan',
    distribusi_bobot: { pg: 60, isian: 20, esai: 20 },
    status: true,
  })

  useEffect(() => {
    fetchStats()
    fetchOptions()
  }, [])

  useEffect(() => {
    fetchData(1)
  }, [filters])

  const showNotification = (message, type = 'success') => {
    setToast({ show: true, message, type })
    setTimeout(() => setToast({ show: false, message: '', type: 'success' }), 4000)
  }

  const fetchData = async (page = 1) => {
    setLoading(true)
    try {
      const params = {
        page,
        per_page: 10,
        ...filters,
      }
      const response = await lmsKisiKisiService.getDaftar(params)
      if (response && response.data) {
        setDataList(response.data)
        setPagination({
          currentPage: response.meta?.current_page || 1,
          lastPage: response.meta?.last_page || 1,
          total: response.meta?.total || response.data.length,
        })
      }
    } catch (error) {
      console.error('Gagal mengambil data kisi-kisi:', error)
      showNotification('Gagal memuat data kisi-kisi ujian.', 'error')
    } finally {
      setLoading(false)
    }
  }

  const fetchStats = async () => {
    try {
      const response = await lmsKisiKisiService.getStats()
      if (response && response.data) {
        setStats(response.data)
      }
    } catch (error) {
      console.error('Gagal mengambil statistik:', error)
    }
  }

  const fetchOptions = async (mapelId = null, cpId = null) => {
    setLoadingOptions(true)
    try {
      const params = {}
      if (mapelId) params.mata_pelajaran_id = mapelId
      if (cpId) params.cp_id = cpId

      const response = await lmsKisiKisiService.getOptions(params)
      const resData =
        response?.data?.data ??
        response?.data ??
        response ??
        {}

      const subjects = normalizeArray(
        resData.subjects ??
        resData.mata_pelajaran ??
        resData.mata_pelajarans
      )

      const cpOptions = normalizeArray(
        resData.capaian_pembelajaran ??
        resData.cp ??
        resData.learning_outcomes
      )

      const tpOptions = normalizeArray(
        resData.tujuan_pembelajaran ??
        resData.tp ??
        resData.learning_objectives
      )

      setOptions((prev) => ({
        ...prev,

        subjects:
          resData.subjects !== undefined ||
          resData.mata_pelajaran !== undefined ||
          resData.mata_pelajarans !== undefined
            ? subjects
            : prev.subjects,

        kurikulum:
          resData.kurikulum !== undefined
            ? normalizeArray(resData.kurikulum)
            : prev.kurikulum,

        kelas:
          resData.kelas !== undefined
            ? normalizeArray(resData.kelas)
            : prev.kelas,

        semesters:
          resData.semesters !== undefined
            ? normalizeArray(resData.semesters)
            : prev.semesters,

        tahun_ajaran:
          resData.tahun_ajaran !== undefined
            ? normalizeArray(resData.tahun_ajaran)
            : prev.tahun_ajaran,

        guru:
          resData.guru !== undefined
            ? normalizeArray(resData.guru)
            : prev.guru,

        capaian_pembelajaran:
          resData.capaian_pembelajaran !== undefined ||
          resData.cp !== undefined ||
          resData.learning_outcomes !== undefined
            ? cpOptions
            : prev.capaian_pembelajaran,

        tujuan_pembelajaran:
          resData.tujuan_pembelajaran !== undefined ||
          resData.tp !== undefined ||
          resData.learning_objectives !== undefined
            ? tpOptions
            : prev.tujuan_pembelajaran,

        jenis_ujian_options:
          resData.jenis_ujian_options !== undefined
            ? normalizeArray(resData.jenis_ujian_options)
            : prev.jenis_ujian_options,

        level_kognitif_options:
          resData.level_kognitif_options !== undefined
            ? normalizeArray(resData.level_kognitif_options)
            : prev.level_kognitif_options,
      }))
    } catch (error) {
      console.error(
        'Gagal mengambil opsi dropdown Kisi-kisi:',
        error?.response?.status,
        error?.response?.data ?? error
      )
      showNotification(
        error?.response?.data?.message ||
          'Gagal memuat data pilihan Kisi-kisi Ujian.',
        'error'
      )
    } finally {
      setLoadingOptions(false)
    }
  }

  const handleMataPelajaranChange = async (mapelId) => {
    setFormData((prev) => ({
      ...prev,
      mata_pelajaran_id: mapelId,
      cp_id: '',
      tp_id: '',
    }))

    setOptions((prev) => ({
      ...prev,
      capaian_pembelajaran: [],
      tujuan_pembelajaran: [],
    }))

    if (!mapelId) return

    await fetchOptions(mapelId, null)
  }

  const handleCpChange = async (cpId) => {
    setFormData((prev) => ({
      ...prev,
      cp_id: cpId,
      tp_id: '',
    }))

    setOptions((prev) => ({
      ...prev,
      tujuan_pembelajaran: [],
    }))

    if (!cpId) return

    await fetchOptions(formData.mata_pelajaran_id, cpId)
  }

  const handleOpenModal = (item = null) => {
    if (item) {
      setEditingItem(item)
      setFormData({
        judul_kisi: item.judul_kisi || '',
        mata_pelajaran_id: item.mata_pelajaran_id || '',
        cp_id: item.cp_id || '',
        tp_id: item.tp_id || '',
        kurikulum_id: item.kurikulum_id || '',
        kelas_id: item.kelas_id || '',
        semester_id: item.semester_id || '',
        tahun_ajaran_id: item.tahun_ajaran_id || '',
        guru_id: item.guru_id || '',
        jenis_ujian: item.jenis_ujian || 'UH',
        jumlah_soal: item.jumlah_soal || 20,
        alokasi_waktu_menit: item.alokasi_waktu_menit || 60,
        kompetensi_dasar: item.kompetensi_dasar || '',
        level_kognitif: item.level_kognitif || 'C3 - Mengaplikasikan',
        distribusi_bobot: item.distribusi_bobot || { pg: 60, isian: 20, esai: 20 },
        status: item.status !== undefined ? item.status : true,
      })
      fetchOptions(item.mata_pelajaran_id || null, item.cp_id || null)
    } else {
      setEditingItem(null)
      setFormData({
        judul_kisi: '',
        mata_pelajaran_id: '',
        cp_id: '',
        tp_id: '',
        kurikulum_id: options.kurikulum[0]?.id || '',
        kelas_id: options.kelas[0]?.id || '',
        semester_id: options.semesters[0]?.id || '',
        tahun_ajaran_id: options.tahun_ajaran[0]?.id || '',
        guru_id: options.guru[0]?.id || '',
        jenis_ujian: 'UH',
        jumlah_soal: 20,
        alokasi_waktu_menit: 60,
        kompetensi_dasar: '',
        level_kognitif: 'C3 - Mengaplikasikan',
        distribusi_bobot: { pg: 60, isian: 20, esai: 20 },
        status: true,
      })
      fetchOptions(null, null)
    }
    setShowModal(true)
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    try {
      if (editingItem) {
        await lmsKisiKisiService.update(editingItem.id, formData)
        showNotification('Kisi-kisi Ujian berhasil diperbarui.')
      } else {
        await lmsKisiKisiService.create(formData)
        showNotification('Kisi-kisi Ujian berhasil dibuat.')
      }
      setShowModal(false)
      fetchData(pagination.currentPage)
      fetchStats()
    } catch (error) {
      console.error('Gagal menyimpan kisi-kisi:', error)
      showNotification('Gagal menyimpan kisi-kisi ujian. Periksa inputan Anda.', 'error')
    }
  }

  const handleDelete = async (id) => {
    if (window.confirm('Apakah Anda yakin ingin menghapus kisi-kisi ujian ini?')) {
      try {
        await lmsKisiKisiService.delete(id)
        showNotification('Kisi-kisi Ujian berhasil dihapus.')
        fetchData(pagination.currentPage)
        fetchStats()
      } catch (error) {
        console.error('Gagal menghapus kisi-kisi:', error)
        showNotification('Gagal menghapus kisi-kisi ujian.', 'error')
      }
    }
  }

  const handleDuplicate = async (id) => {
    try {
      await lmsKisiKisiService.duplicate(id)
      showNotification('Kisi-kisi Ujian berhasil diduplikasi.')
      fetchData(pagination.currentPage)
      fetchStats()
    } catch (error) {
      console.error('Gagal menduplikasi kisi-kisi:', error)
      showNotification('Gagal menduplikasi kisi-kisi ujian.', 'error')
    }
  }

  const getJenisBadgeColor = (jenis) => {
    switch (jenis) {
      case 'UH':
        return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border-emerald-300 dark:border-emerald-800'
      case 'PTS':
      case 'UTS':
        return 'bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300 border-blue-300 dark:border-blue-800'
      case 'PAS':
      case 'UAS':
        return 'bg-purple-100 text-purple-800 dark:bg-purple-950/60 dark:text-purple-300 border-purple-300 dark:border-purple-800'
      case 'CBT':
        return 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border-amber-300 dark:border-amber-800'
      default:
        return 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300 border-gray-300'
    }
  }

  return (
    <div className="space-y-6 pb-12 font-sans">
      {/* Toast Notification */}
      {toast.show && (
        <div
          className={`fixed top-6 right-6 z-50 flex items-center gap-3 px-5 py-3 rounded-2xl shadow-xl text-white font-medium text-sm transition-all duration-200 ${
            toast.type === 'error' ? 'bg-rose-600' : 'bg-[#0E5C44]'
          }`}
        >
          {toast.type === 'error' ? <XCircle className="w-5 h-5" /> : <CheckCircle2 className="w-5 h-5" />}
          <span>{toast.message}</span>
        </div>
      )}

      {/* Hero Banner */}
      <div className="relative overflow-hidden rounded-[24px] bg-gradient-to-r from-[#0E5C44] via-[#1E8E5A] to-[#3FBF75] p-8 text-white shadow-xl">
        <div className="absolute right-0 top-0 -mr-16 -mt-16 h-64 w-64 rounded-full bg-white/10 blur-2xl" />
        <div className="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
          <div>
            <div className="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold backdrop-blur-md mb-3">
              <Sparkles className="h-3.5 w-3.5" />
              <span>Modul Evaluasi & Penilaian LMS</span>
            </div>
            <h1 className="text-3xl font-bold tracking-tight">Kisi-kisi Ujian (Exam Blueprint)</h1>
            <p className="mt-2 text-emerald-100 text-sm max-w-2xl leading-relaxed">
              Kelola cetak biru kisi-kisi soal ujian terpadu dengan penyelarasan Capaian Pembelajaran (CP), Tujuan Pembelajaran (TP), dan Taksonomi Bloom.
            </p>
          </div>
          <button
            onClick={() => handleOpenModal()}
            className="inline-flex items-center gap-2 rounded-2xl bg-white px-5 py-3 text-sm font-semibold text-[#0E5C44] shadow-lg hover:bg-emerald-50 active:scale-95 transition-all duration-200"
          >
            <Plus className="h-4 w-4 stroke-[2.5]" />
            <span>Buat Kisi-kisi Baru</span>
          </button>
        </div>
      </div>

      {/* KPI Stats Grid */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <div className="bg-white dark:bg-slate-800 p-5 rounded-[18px] border border-gray-100 dark:border-slate-700 shadow-sm flex items-center justify-between">
          <div>
            <p className="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Kisi-kisi</p>
            <h3 className="text-2xl font-bold text-slate-800 dark:text-white mt-1">{stats.total}</h3>
            <p className="text-xs text-emerald-600 dark:text-emerald-400 mt-1 font-medium">{stats.aktif} Status Aktif</p>
          </div>
          <div className="p-3.5 rounded-2xl bg-emerald-50 dark:bg-emerald-950/50 text-[#0E5C44] dark:text-emerald-400">
            <FileText className="w-6 h-6" />
          </div>
        </div>

        <div className="bg-white dark:bg-slate-800 p-5 rounded-[18px] border border-gray-100 dark:border-slate-700 shadow-sm flex items-center justify-between">
          <div>
            <p className="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Target Butir Soal</p>
            <h3 className="text-2xl font-bold text-slate-800 dark:text-white mt-1">{stats.total_soal_target}</h3>
            <p className="text-xs text-blue-600 dark:text-blue-400 mt-1 font-medium">Accumulated Questions</p>
          </div>
          <div className="p-3.5 rounded-2xl bg-blue-50 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400">
            <Target className="w-6 h-6" />
          </div>
        </div>

        <div className="bg-white dark:bg-slate-800 p-5 rounded-[18px] border border-gray-100 dark:border-slate-700 shadow-sm flex items-center justify-between">
          <div>
            <p className="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Kisi-kisi UH</p>
            <h3 className="text-2xl font-bold text-slate-800 dark:text-white mt-1">{stats.uh}</h3>
            <p className="text-xs text-purple-600 dark:text-purple-400 mt-1 font-medium">Ulangan Harian</p>
          </div>
          <div className="p-3.5 rounded-2xl bg-purple-50 dark:bg-purple-950/50 text-purple-600 dark:text-purple-400">
            <BookOpen className="w-6 h-6" />
          </div>
        </div>

        <div className="bg-white dark:bg-slate-800 p-5 rounded-[18px] border border-gray-100 dark:border-slate-700 shadow-sm flex items-center justify-between">
          <div>
            <p className="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">PTS & PAS / UAS</p>
            <h3 className="text-2xl font-bold text-slate-800 dark:text-white mt-1">{stats.pts + stats.pas}</h3>
            <p className="text-xs text-amber-600 dark:text-amber-400 mt-1 font-medium">Ujian Semester</p>
          </div>
          <div className="p-3.5 rounded-2xl bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400">
            <Award className="w-6 h-6" />
          </div>
        </div>
      </div>

      {/* Filter & Search Bar */}
      <div className="bg-white dark:bg-slate-800 p-4 rounded-[18px] border border-gray-100 dark:border-slate-700 shadow-sm flex flex-col md:flex-row items-center justify-between gap-4">
        <div className="relative w-full md:w-80">
          <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
          <input
            type="text"
            placeholder="Cari judul kisi / KD / level kognitif..."
            value={filters.search}
            onChange={(e) => setFilters({ ...filters, search: e.target.value })}
            className="w-full pl-10 pr-4 py-2.5 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44] transition-all"
          />
        </div>

        <div className="flex flex-wrap items-center gap-3 w-full md:w-auto">
          <select
            value={filters.mata_pelajaran_id}
            onChange={(e) => setFilters({ ...filters, mata_pelajaran_id: e.target.value })}
            className="px-3.5 py-2.5 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44]"
          >
            <option value="">Semua Mata Pelajaran</option>
            {options.subjects.map((sub) => (
              <option key={sub.id} value={sub.id}>
                {getSubjectLabel(sub)}
              </option>
            ))}
          </select>

          <select
            value={filters.jenis_ujian}
            onChange={(e) => setFilters({ ...filters, jenis_ujian: e.target.value })}
            className="px-3.5 py-2.5 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44]"
          >
            <option value="">Semua Jenis Ujian</option>
            {options.jenis_ujian_options.map((j) => (
              <option key={j.id} value={j.id}>
                {j.nama}
              </option>
            ))}
          </select>

          <button
            onClick={() => {
              setFilters({ search: '', mata_pelajaran_id: '', jenis_ujian: '', status: '' })
              fetchData(1)
            }}
            className="p-2.5 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-xl hover:bg-gray-100 transition"
            title="Reset Filter"
          >
            <RefreshCw className="w-4 h-4" />
          </button>
        </div>
      </div>

      {/* Main Table */}
      <div className="bg-white dark:bg-slate-800 rounded-[18px] border border-gray-100 dark:border-slate-700 shadow-sm overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm">
            <thead className="bg-gray-50 dark:bg-slate-900 text-gray-600 dark:text-gray-300 font-semibold border-b border-gray-100 dark:border-slate-700">
              <tr>
                <th className="px-6 py-4">Judul & Mata Pelajaran</th>
                <th className="px-6 py-4">Capaian & Tujuan (CP / TP)</th>
                <th className="px-6 py-4">Jenis Ujian & Kognitif</th>
                <th className="px-6 py-4">Jumlah Soal / Durasi</th>
                <th className="px-6 py-4">Status</th>
                <th className="px-6 py-4 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100 dark:divide-slate-700">
              {loading ? (
                <tr>
                  <td colSpan="6" className="text-center py-12 text-gray-500 dark:text-gray-400">
                    <div className="flex flex-col items-center gap-2">
                      <RefreshCw className="w-6 h-6 animate-spin text-[#0E5C44]" />
                      <span>Memuat data kisi-kisi...</span>
                    </div>
                  </td>
                </tr>
              ) : dataList.length === 0 ? (
                <tr>
                  <td colSpan="6" className="text-center py-12 text-gray-500 dark:text-gray-400">
                    Belum ada data kisi-kisi ujian yang ditemukan.
                  </td>
                </tr>
              ) : (
                dataList.map((item) => (
                  <tr key={item.id} className="hover:bg-gray-50/60 dark:hover:bg-slate-700/50 transition-colors">
                    <td className="px-6 py-4">
                      <div className="font-semibold text-slate-800 dark:text-white">{item.judul_kisi}</div>
                      <div className="text-xs text-[#0E5C44] dark:text-emerald-400 font-medium mt-0.5">
                        {item.mata_pelajaran?.name || 'Mata Pelajaran N/A'}
                      </div>
                    </td>
                    <td className="px-6 py-4 max-w-xs">
                      {item.cp ? (
                        <div className="inline-block px-2.5 py-1 bg-emerald-50 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 rounded-lg text-xs font-medium mb-1">
                          {item.cp.kode_cp ? `CP: ${item.cp.kode_cp}` : item.cp.nama_cp}
                        </div>
                      ) : (
                        <div className="text-xs text-gray-400 italic">CP belum ditentukan</div>
                      )}
                      {item.tp && (
                        <div className="text-xs text-slate-600 dark:text-slate-300 truncate">
                          TP: {item.tp.kode_tp || item.tp.nama_tp}
                        </div>
                      )}
                    </td>
                    <td className="px-6 py-4">
                      <span className={`inline-block px-3 py-1 text-xs font-semibold rounded-full border ${getJenisBadgeColor(item.jenis_ujian)}`}>
                        {item.jenis_ujian}
                      </span>
                      {item.level_kognitif && (
                        <div className="text-xs text-gray-500 dark:text-gray-400 mt-1 font-medium">
                          {item.level_kognitif}
                        </div>
                      )}
                    </td>
                    <td className="px-6 py-4">
                      <div className="font-semibold text-slate-800 dark:text-white">{item.jumlah_soal} Soal</div>
                      <div className="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1 mt-0.5">
                        <Clock className="w-3 h-3" />
                        <span>{item.alokasi_waktu_menit} Menit</span>
                      </div>
                    </td>
                    <td className="px-6 py-4">
                      {item.status ? (
                        <span className="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 rounded-full text-xs font-medium">
                          <span className="w-1.5 h-1.5 rounded-full bg-emerald-500" />
                          Aktif
                        </span>
                      ) : (
                        <span className="inline-flex items-center gap-1.5 px-3 py-1 bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400 rounded-full text-xs font-medium">
                          <span className="w-1.5 h-1.5 rounded-full bg-gray-400" />
                          Nonaktif
                        </span>
                      )}
                    </td>
                    <td className="px-6 py-4 text-right">
                      <div className="flex items-center justify-end gap-1.5">
                        <button
                          onClick={() => {
                            setViewingItem(item)
                            setShowDetailModal(true)
                          }}
                          className="p-2 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-950/50 rounded-xl transition"
                          title="Lihat Detail"
                        >
                          <Eye className="w-4 h-4" />
                        </button>
                        <button
                          onClick={() => handleDuplicate(item.id)}
                          className="p-2 text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-950/50 rounded-xl transition"
                          title="Duplikasi Kisi-kisi"
                        >
                          <Copy className="w-4 h-4" />
                        </button>
                        <button
                          onClick={() => handleOpenModal(item)}
                          className="p-2 text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-950/50 rounded-xl transition"
                          title="Edit"
                        >
                          <Edit3 className="w-4 h-4" />
                        </button>
                        <button
                          onClick={() => handleDelete(item.id)}
                          className="p-2 text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/50 rounded-xl transition"
                          title="Hapus"
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
        <div className="px-6 py-4 bg-gray-50 dark:bg-slate-900 border-t border-gray-100 dark:border-slate-700 flex items-center justify-between">
          <span className="text-xs text-gray-500 dark:text-gray-400">
            Halaman {pagination.currentPage} dari {pagination.lastPage} ({pagination.total} Data Total)
          </span>
          <div className="flex gap-2">
            <button
              disabled={pagination.currentPage <= 1}
              onClick={() => fetchData(pagination.currentPage - 1)}
              className="px-3 py-1.5 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl text-xs font-medium disabled:opacity-50 hover:bg-gray-50 transition"
            >
              Sebelumnya
            </button>
            <button
              disabled={pagination.currentPage >= pagination.lastPage}
              onClick={() => fetchData(pagination.currentPage + 1)}
              className="px-3 py-1.5 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl text-xs font-medium disabled:opacity-50 hover:bg-gray-50 transition"
            >
              Selanjutnya
            </button>
          </div>
        </div>
      </div>

      {/* Modal Form Create/Edit */}
      {showModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm overflow-y-auto">
          <div className="relative w-full max-w-2xl bg-white dark:bg-slate-800 rounded-[24px] shadow-2xl overflow-hidden my-8">
            <div className="flex items-center justify-between px-6 py-5 border-b border-gray-100 dark:border-slate-700 bg-gray-50/50 dark:bg-slate-900/50">
              <h3 className="text-lg font-bold text-slate-800 dark:text-white">
                {editingItem ? 'Edit Kisi-kisi Ujian' : 'Buat Kisi-kisi Ujian Baru'}
              </h3>
              <button
                onClick={() => setShowModal(false)}
                className="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 rounded-xl transition"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleSubmit} className="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
              <div>
                <label className="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-1">
                  Judul Kisi-kisi Ujian *
                </label>
                <input
                  type="text"
                  required
                  placeholder="Contoh: Kisi-kisi Penilaian Tengah Semester Gasal Matematika X"
                  value={formData.judul_kisi}
                  onChange={(e) => setFormData({ ...formData, judul_kisi: e.target.value })}
                  className="w-full px-4 py-2.5 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-xl text-sm focus:ring-2 focus:ring-[#0E5C44] focus:outline-none"
                />
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-1">
                    Mata Pelajaran *
                  </label>
                  <select
                    required
                    value={formData.mata_pelajaran_id}
                    onChange={(e) => handleMataPelajaranChange(e.target.value)}
                    className="w-full px-4 py-2.5 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-xl text-sm focus:ring-2 focus:ring-[#0E5C44]"
                  >
                    <option value="">-- Pilih Mata Pelajaran --</option>
                    {options.subjects && options.subjects.length > 0 ? (
                      options.subjects.map((sub) => (
                        <option key={sub.id} value={sub.id}>
                          {getSubjectLabel(sub)}
                        </option>
                      ))
                    ) : (
                      <option value="" disabled>Belum ada mata pelajaran aktif</option>
                    )}
                  </select>
                </div>

                <div>
                  <label className="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-1">
                    Jenis Ujian *
                  </label>
                  <select
                    required
                    value={formData.jenis_ujian}
                    onChange={(e) => setFormData({ ...formData, jenis_ujian: e.target.value })}
                    className="w-full px-4 py-2.5 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-xl text-sm focus:ring-2 focus:ring-[#0E5C44]"
                  >
                    {options.jenis_ujian_options.map((j) => (
                      <option key={j.id} value={j.id}>
                        {j.nama}
                      </option>
                    ))}
                  </select>
                </div>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-1">
                    Capaian Pembelajaran (CP)
                  </label>
                  <select
                    value={formData.cp_id}
                    disabled={!formData.mata_pelajaran_id}
                    onChange={(e) => handleCpChange(e.target.value)}
                    className="w-full px-4 py-2.5 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-xl text-sm focus:ring-2 focus:ring-[#0E5C44] disabled:opacity-60"
                  >
                    {!formData.mata_pelajaran_id ? (
                      <option value="">Pilih mata pelajaran terlebih dahulu</option>
                    ) : loadingOptions ? (
                      <option value="">Memuat capaian pembelajaran...</option>
                    ) : options.capaian_pembelajaran && options.capaian_pembelajaran.length > 0 ? (
                      <>
                        <option value="">-- Pilih CP (Opsional) --</option>
                        {options.capaian_pembelajaran.map((cp) => {
                          const label = cp.label || (cp.kode_cp ? `[${cp.kode_cp}] ${cp.nama_cp || cp.deskripsi}` : cp.nama_cp || cp.deskripsi)
                          return (
                            <option key={cp.id} value={cp.id}>
                              {label}
                            </option>
                          )
                        })}
                      </>
                    ) : (
                      <option value="">Belum ada CP untuk mata pelajaran ini</option>
                    )}
                  </select>
                </div>

                <div>
                  <label className="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-1">
                    Tujuan Pembelajaran (TP)
                  </label>
                  <select
                    value={formData.tp_id}
                    disabled={!formData.cp_id}
                    onChange={(e) => setFormData({ ...formData, tp_id: e.target.value })}
                    className="w-full px-4 py-2.5 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-xl text-sm focus:ring-2 focus:ring-[#0E5C44] disabled:opacity-60"
                  >
                    {!formData.cp_id ? (
                      <option value="">Pilih CP terlebih dahulu</option>
                    ) : loadingOptions ? (
                      <option value="">Memuat tujuan pembelajaran...</option>
                    ) : options.tujuan_pembelajaran && options.tujuan_pembelajaran.length > 0 ? (
                      <>
                        <option value="">-- Pilih TP (Opsional) --</option>
                        {options.tujuan_pembelajaran.map((tp) => {
                          const label = tp.label || (tp.kode_tp ? `[${tp.kode_tp}] ${tp.nama_tp || tp.deskripsi}` : tp.nama_tp || tp.deskripsi)
                          return (
                            <option key={tp.id} value={tp.id}>
                              {label}
                            </option>
                          )
                        })}
                      </>
                    ) : (
                      <option value="">Belum ada TP untuk CP ini</option>
                    )}
                  </select>
                </div>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-1">
                    Jumlah Soal *
                  </label>
                  <input
                    type="number"
                    required
                    min="1"
                    value={formData.jumlah_soal}
                    onChange={(e) => setFormData({ ...formData, jumlah_soal: parseInt(e.target.value) || 0 })}
                    className="w-full px-4 py-2.5 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-xl text-sm focus:ring-2 focus:ring-[#0E5C44]"
                  />
                </div>

                <div>
                  <label className="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-1">
                    Alokasi Waktu (Menit) *
                  </label>
                  <input
                    type="number"
                    required
                    min="5"
                    value={formData.alokasi_waktu_menit}
                    onChange={(e) => setFormData({ ...formData, alokasi_waktu_menit: parseInt(e.target.value) || 0 })}
                    className="w-full px-4 py-2.5 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-xl text-sm focus:ring-2 focus:ring-[#0E5C44]"
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-1">
                  Level Kognitif Bloom
                </label>
                <select
                  value={formData.level_kognitif}
                  onChange={(e) => setFormData({ ...formData, level_kognitif: e.target.value })}
                  className="w-full px-4 py-2.5 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-xl text-sm focus:ring-2 focus:ring-[#0E5C44]"
                >
                  {options.level_kognitif_options.map((l) => (
                    <option key={l.id} value={l.id}>
                      {l.nama}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-1">
                  Uraian Indikator / Kompetensi Dasar
                </label>
                <textarea
                  rows="3"
                  placeholder="Tuliskan materi pokok atau indikator pencapaian kisi-kisi..."
                  value={formData.kompetensi_dasar}
                  onChange={(e) => setFormData({ ...formData, kompetensi_dasar: e.target.value })}
                  className="w-full px-4 py-2.5 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-xl text-sm focus:ring-2 focus:ring-[#0E5C44]"
                />
              </div>

              <div className="pt-4 border-t border-gray-100 dark:border-slate-700 flex justify-end gap-3">
                <button
                  type="button"
                  onClick={() => setShowModal(false)}
                  className="px-5 py-2.5 bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-200 text-sm font-semibold rounded-xl hover:bg-gray-200 transition"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  className="px-5 py-2.5 bg-[#0E5C44] text-white text-sm font-semibold rounded-xl hover:bg-[#1E8E5A] transition shadow-lg shadow-emerald-900/20"
                >
                  {editingItem ? 'Simpan Perubahan' : 'Buat Kisi-kisi'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Modal Detail Drawer */}
      {showDetailModal && viewingItem && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
          <div className="relative w-full max-w-xl bg-white dark:bg-slate-800 rounded-[24px] shadow-2xl overflow-hidden">
            <div className="flex items-center justify-between px-6 py-5 border-b border-gray-100 dark:border-slate-700 bg-gray-50/50 dark:bg-slate-900/50">
              <h3 className="text-lg font-bold text-slate-800 dark:text-white">Detail Kisi-kisi Ujian</h3>
              <button
                onClick={() => setShowDetailModal(false)}
                className="p-2 text-gray-400 hover:text-gray-600 rounded-xl transition"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <div className="p-6 space-y-4 text-sm">
              <div>
                <span className="text-xs uppercase text-gray-400 font-bold">Judul Kisi-kisi</span>
                <h4 className="text-base font-bold text-slate-800 dark:text-white">{viewingItem.judul_kisi}</h4>
              </div>

              <div className="grid grid-cols-2 gap-4 bg-gray-50 dark:bg-slate-900 p-4 rounded-xl">
                <div>
                  <span className="text-xs text-gray-400 font-medium">Mata Pelajaran</span>
                  <p className="font-semibold text-slate-700 dark:text-slate-200">{viewingItem.mata_pelajaran?.name || '-'}</p>
                </div>
                <div>
                  <span className="text-xs text-gray-400 font-medium">Jenis Ujian</span>
                  <p className="font-semibold text-emerald-600 dark:text-emerald-400">{viewingItem.jenis_ujian}</p>
                </div>
                <div>
                  <span className="text-xs text-gray-400 font-medium">Jumlah Soal</span>
                  <p className="font-semibold text-slate-700 dark:text-slate-200">{viewingItem.jumlah_soal} Butir</p>
                </div>
                <div>
                  <span className="text-xs text-gray-400 font-medium">Alokasi Waktu</span>
                  <p className="font-semibold text-slate-700 dark:text-slate-200">{viewingItem.alokasi_waktu_menit} Menit</p>
                </div>
              </div>

              {viewingItem.cp && (
                <div className="border border-emerald-100 dark:border-emerald-950 p-4 rounded-xl bg-emerald-50/50 dark:bg-emerald-950/20">
                  <span className="text-xs text-emerald-800 dark:text-emerald-300 font-bold uppercase">Capaian Pembelajaran (CP)</span>
                  <p className="mt-1 text-slate-700 dark:text-slate-200">
                    {viewingItem.cp.kode_cp ? `[${viewingItem.cp.kode_cp}] ` : ''}{viewingItem.cp.nama_cp}
                  </p>
                </div>
              )}

              {viewingItem.tp && (
                <div className="border border-blue-100 dark:border-blue-950 p-4 rounded-xl bg-blue-50/50 dark:bg-blue-950/20">
                  <span className="text-xs text-blue-800 dark:text-blue-300 font-bold uppercase">Tujuan Pembelajaran (TP)</span>
                  <p className="mt-1 text-slate-700 dark:text-slate-200">
                    {viewingItem.tp.kode_tp ? `[${viewingItem.tp.kode_tp}] ` : ''}{viewingItem.tp.nama_tp || viewingItem.tp.deskripsi}
                  </p>
                </div>
              )}

              {viewingItem.kompetensi_dasar && (
                <div>
                  <span className="text-xs uppercase text-gray-400 font-bold">Indikator & Level Kognitif</span>
                  <p className="mt-1 text-slate-600 dark:text-slate-300 leading-relaxed bg-gray-50 dark:bg-slate-900 p-3 rounded-xl">
                    {viewingItem.kompetensi_dasar}
                  </p>
                </div>
              )}
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
