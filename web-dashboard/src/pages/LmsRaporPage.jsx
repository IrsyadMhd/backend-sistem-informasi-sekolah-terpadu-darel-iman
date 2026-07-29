import React, { useState, useEffect } from 'react'
import {
  FileText,
  Award,
  BookOpen,
  Printer,
  Sparkles,
  Plus,
  Search,
  Filter,
  Edit3,
  Trash2,
  CheckCircle2,
  Clock,
  Eye,
  RefreshCw,
  X,
  Users,
  Building2,
  Calendar,
  AlertCircle,
  FileSpreadsheet,
  Download,
  Share2,
  Check,
  GraduationCap,
} from 'lucide-react'
import { lmsRaporService } from '../services/lmsRaporService'

export default function LmsRaporPage() {
  const [dataList, setDataList] = useState([])
  const [loading, setLoading] = useState(true)
  const [pagination, setPagination] = useState({ currentPage: 1, lastPage: 1, total: 0 })
  const [stats, setStats] = useState({
    total_rapor: 0,
    diterbitkan: 0,
    draft: 0,
    final: 0,
    direvisi: 0,
    rata_rata_sekolah: 0,
  })
  const [options, setOptions] = useState({
    students: [],
    kelases: [],
    semesters: [],
    tahun_ajarans: [],
    employees: [],
  })

  const [filters, setFilters] = useState({
    search: '',
    kelas_id: '',
    semester_id: '',
    tahun_ajaran_id: '',
    status_rapor: '',
  })

  // Modals state
  const [showFormModal, setShowFormModal] = useState(false)
  const [showGenerateModal, setShowGenerateModal] = useState(false)
  const [showDigitalModal, setShowDigitalModal] = useState(false)
  const [showPrintModal, setShowPrintModal] = useState(false)

  const [editingItem, setEditingItem] = useState(null)
  const [digitalData, setDigitalData] = useState(null)
  const [loadingDigital, setLoadingDigital] = useState(false)
  const [toast, setToast] = useState({ show: false, message: '', type: 'success' })

  // Form State
  const [formData, setFormData] = useState({
    siswa_id: '',
    kelas_id: '',
    semester_id: '',
    tahun_ajaran_id: '',
    guru_wali_id: '',
    catatan_wali_kelas: '',
    catatan_kepala_sekolah: '',
    status_rapor: 'draft',
    tanggal_terbit: new Date().toISOString().split('T')[0],
  })

  // Generate State
  const [generateData, setGenerateData] = useState({
    kelas_id: '',
    semester_id: '',
    tahun_ajaran_id: '',
  })
  const [generating, setGenerating] = useState(false)

  useEffect(() => {
    fetchOptions()
    fetchStats()
    fetchData(1)
  }, [])

  useEffect(() => {
    fetchStats()
    fetchData(1)
  }, [filters])

  const showToastNotification = (message, type = 'success') => {
    setToast({ show: true, message, type })
    setTimeout(() => setToast({ show: false, message: '', type: 'success' }), 4000)
  }

  const fetchOptions = async () => {
    try {
      const res = await lmsRaporService.getOptions()
      if (res.success) {
        setOptions(res.data)
        if (res.data.kelases.length > 0 && res.data.semesters.length > 0 && res.data.tahun_ajarans.length > 0) {
          setGenerateData({
            kelas_id: res.data.kelases[0].id,
            semester_id: res.data.semesters[0].id,
            tahun_ajaran_id: res.data.tahun_ajarans[0].id,
          })
        }
      }
    } catch (err) {
      console.error('Failed to load options', err)
    }
  }

  const fetchStats = async () => {
    try {
      const res = await lmsRaporService.getStats(filters)
      if (res.success) {
        setStats(res.data)
      }
    } catch (err) {
      console.error('Failed to load stats', err)
    }
  }

  const fetchData = async (page = 1) => {
    setLoading(true)
    try {
      const params = {
        page,
        per_page: 15,
        ...filters,
      }
      const res = await lmsRaporService.getDaftar(params)
      if (res.data) {
        setDataList(res.data)
        if (res.meta) {
          setPagination({
            currentPage: res.meta.current_page,
            lastPage: res.meta.last_page,
            total: res.meta.total,
          })
        }
      }
    } catch (err) {
      showToastNotification('Gagal memuat data Rapor Digital.', 'error')
    } finally {
      setLoading(false)
    }
  }

  const handleOpenCreateModal = () => {
    setEditingItem(null)
    setFormData({
      siswa_id: options.students[0]?.id || '',
      kelas_id: options.kelases[0]?.id || '',
      semester_id: options.semesters[0]?.id || '',
      tahun_ajaran_id: options.tahun_ajarans[0]?.id || '',
      guru_wali_id: options.employees[0]?.id || '',
      catatan_wali_kelas: 'Tingkatkan semangat belajar dan terus berprestasi.',
      catatan_kepala_sekolah: 'Pertahankan pencapaian yang baik ini.',
      status_rapor: 'draft',
      tanggal_terbit: new Date().toISOString().split('T')[0],
    })
    setShowFormModal(true)
  }

  const handleOpenEditModal = (item) => {
    setEditingItem(item)
    setFormData({
      siswa_id: item.siswa_id || '',
      kelas_id: item.kelas_id || '',
      semester_id: item.semester_id || '',
      tahun_ajaran_id: item.tahun_ajaran_id || '',
      guru_wali_id: item.guru_wali_id || '',
      catatan_wali_kelas: item.catatan_wali_kelas || '',
      catatan_kepala_sekolah: item.catatan_kepala_sekolah || '',
      status_rapor: item.status_rapor || 'draft',
      tanggal_terbit: item.tanggal_terbit || new Date().toISOString().split('T')[0],
    })
    setShowFormModal(true)
  }

  const handleSaveRapor = async (e) => {
    e.preventDefault()
    try {
      if (editingItem) {
        const res = await lmsRaporService.update(editingItem.id, formData)
        if (res.success) {
          showToastNotification('Catatan & Status Rapor Digital berhasil diperbarui.')
        }
      } else {
        const res = await lmsRaporService.create(formData)
        if (res.success) {
          showToastNotification('Rapor Digital berhasil dibuat.')
        }
      }
      setShowFormModal(false)
      fetchData(pagination.currentPage)
      fetchStats()
    } catch (err) {
      showToastNotification('Gagal menyimpan data Rapor Digital.', 'error')
    }
  }

  const handleGenerateClass = async (e) => {
    e.preventDefault()
    setGenerating(true)
    try {
      const res = await lmsRaporService.generateClass(generateData)
      if (res.success) {
        showToastNotification(res.message || 'Auto-generate Rapor kelas berhasil!')
        setShowGenerateModal(false)
        fetchData(1)
        fetchStats()
      }
    } catch (err) {
      showToastNotification('Gagal melakukan auto-generate Rapor kelas.', 'error')
    } finally {
      setGenerating(false)
    }
  }

  const handleDelete = async (id) => {
    if (!window.confirm('Apakah Anda yakin ingin menghapus Rapor Digital ini?')) return
    try {
      const res = await lmsRaporService.delete(id)
      if (res.success) {
        showToastNotification('Rapor Digital berhasil dihapus.')
        fetchData(pagination.currentPage)
        fetchStats()
      }
    } catch (err) {
      showToastNotification('Gagal menghapus Rapor Digital.', 'error')
    }
  }

  const handleViewDigitalRapor = async (id) => {
    setLoadingDigital(true)
    setShowDigitalModal(true)
    try {
      const res = await lmsRaporService.getPdf(id)
      if (res.success) {
        setDigitalData(res.data)
      }
    } catch (err) {
      showToastNotification('Gagal memuat Rapor Digital.', 'error')
    } finally {
      setLoadingDigital(false)
    }
  }

  const handleOpenPrintPreview = async (id) => {
    setLoadingDigital(true)
    setShowPrintModal(true)
    try {
      const res = await lmsRaporService.getPdf(id)
      if (res.success) {
        setDigitalData(res.data)
      }
    } catch (err) {
      showToastNotification('Gagal memuat data cetak Rapor.', 'error')
    } finally {
      setLoadingDigital(false)
    }
  }

  const triggerBrowserPrint = () => {
    window.print()
  }

  const getStatusBadge = (status) => {
    switch (status) {
      case 'diterbitkan':
        return (
          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
            <CheckCircle2 className="w-3.5 h-3.5" /> Diterbitkan
          </span>
        )
      case 'final':
        return (
          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
            <Check className="w-3.5 h-3.5" /> Final Validated
          </span>
        )
      case 'direvisi':
        return (
          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
            <AlertCircle className="w-3.5 h-3.5" /> Direvisi
          </span>
        )
      default:
        return (
          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
            <Clock className="w-3.5 h-3.5" /> Draft
          </span>
        )
    }
  }

  return (
    <div className="space-y-6 pb-12">
      {/* Toast Notification */}
      {toast.show && (
        <div
          className={`fixed top-5 right-5 z-50 flex items-center gap-3 px-4 py-3 rounded-xl shadow-xl text-white font-medium transition-all transform duration-300 ${
            toast.type === 'error' ? 'bg-rose-600' : 'bg-[#0E5C44]'
          }`}
        >
          <Sparkles className="w-5 h-5 text-amber-300" />
          <span>{toast.message}</span>
        </div>
      )}

      {/* Header Banner */}
      <div className="relative overflow-hidden rounded-3xl bg-gradient-to-r from-[#0E5C44] via-[#1E8E5A] to-[#3FBF75] p-6 md:p-8 text-white shadow-xl">
        <div className="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
          <div>
            <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 text-emerald-100 text-xs font-semibold backdrop-blur-md mb-3">
              <GraduationCap className="w-4 h-4 text-emerald-300" /> Modul Rapor Digital & Cetak PDF Terpadu
            </div>
            <h1 className="text-2xl md:text-3xl font-extrabold tracking-tight text-white">
              Pengolahan Rapor Digital Siswa
            </h1>
            <p className="text-emerald-100 text-sm mt-1 max-w-2xl">
              Pusat agregasi penilaian akademik, rangkuman presensi kehadiran, peringkat kelas, serta penerbitan Rapor Digital dan PDF resmi sekolah.
            </p>
          </div>
          <div className="flex flex-wrap items-center gap-3">
            <button
              onClick={() => setShowGenerateModal(true)}
              className="inline-flex items-center gap-2 bg-amber-400 hover:bg-amber-300 text-emerald-950 font-bold px-4 py-2.5 rounded-xl shadow-lg hover:shadow-xl transition-all duration-200 text-sm"
            >
              <RefreshCw className="w-4 h-4" /> Auto-Generate Kelas
            </button>
            <button
              onClick={handleOpenCreateModal}
              className="inline-flex items-center gap-2 bg-white/20 hover:bg-white/30 text-white font-semibold px-4 py-2.5 rounded-xl backdrop-blur-md transition-all duration-200 text-sm border border-white/30"
            >
              <Plus className="w-4 h-4" /> Rapor Manual
            </button>
          </div>
        </div>
      </div>

      {/* KPI Cards */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div className="bg-white dark:bg-[#1B2433] p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm hover:shadow-md transition-shadow">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
              Total Rapor
            </span>
            <div className="p-2.5 rounded-xl bg-emerald-50 text-[#0E5C44] dark:bg-emerald-950/50 dark:text-emerald-400">
              <FileText className="w-5 h-5" />
            </div>
          </div>
          <p className="text-2xl font-bold text-slate-900 dark:text-white mt-3">{stats.total_rapor}</p>
          <p className="text-xs text-slate-500 mt-1">Dokumen Rapor terdaftar</p>
        </div>

        <div className="bg-white dark:bg-[#1B2433] p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm hover:shadow-md transition-shadow">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
              Diterbitkan
            </span>
            <div className="p-2.5 rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/50 dark:text-blue-400">
              <CheckCircle2 className="w-5 h-5" />
            </div>
          </div>
          <p className="text-2xl font-bold text-slate-900 dark:text-white mt-3">{stats.diterbitkan}</p>
          <p className="text-xs text-emerald-600 font-medium mt-1">Siap dilihat Orang Tua</p>
        </div>

        <div className="bg-white dark:bg-[#1B2433] p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm hover:shadow-md transition-shadow">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
              Status Draft / Final
            </span>
            <div className="p-2.5 rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-400">
              <Clock className="w-5 h-5" />
            </div>
          </div>
          <p className="text-2xl font-bold text-slate-900 dark:text-white mt-3">{stats.draft + stats.final}</p>
          <p className="text-xs text-slate-500 mt-1">Proses validasi Wali Kelas</p>
        </div>

        <div className="bg-white dark:bg-[#1B2433] p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm hover:shadow-md transition-shadow">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
              Rata-rata Rapor
            </span>
            <div className="p-2.5 rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/50 dark:text-indigo-400">
              <Award className="w-5 h-5" />
            </div>
          </div>
          <p className="text-2xl font-bold text-slate-900 dark:text-white mt-3">{stats.rata_rata_sekolah}</p>
          <p className="text-xs text-slate-500 mt-1">Indeks prestasi rata-rata</p>
        </div>
      </div>

      {/* Filter & Search Bar */}
      <div className="bg-white dark:bg-[#1B2433] p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
          <div className="relative">
            <Search className="w-4 h-4 absolute left-3.5 top-3.5 text-slate-400" />
            <input
              type="text"
              placeholder="Cari Siswa / NISN..."
              value={filters.search}
              onChange={(e) => setFilters({ ...filters, search: e.target.value })}
              className="w-full pl-10 pr-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44]"
            />
          </div>

          <select
            value={filters.kelas_id}
            onChange={(e) => setFilters({ ...filters, kelas_id: e.target.value })}
            className="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44]"
          >
            <option value="">Semua Kelas</option>
            {options.kelases.map((k) => (
              <option key={k.id} value={k.id}>
                {k.nama_kelas}
              </option>
            ))}
          </select>

          <select
            value={filters.semester_id}
            onChange={(e) => setFilters({ ...filters, semester_id: e.target.value })}
            className="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44]"
          >
            <option value="">Semua Semester</option>
            {options.semesters.map((s) => (
              <option key={s.id} value={s.id}>
                {s.nama}
              </option>
            ))}
          </select>

          <select
            value={filters.tahun_ajaran_id}
            onChange={(e) => setFilters({ ...filters, tahun_ajaran_id: e.target.value })}
            className="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44]"
          >
            <option value="">Semua Tahun Ajaran</option>
            {options.tahun_ajarans.map((t) => (
              <option key={t.id} value={t.id}>
                {t.year}
              </option>
            ))}
          </select>

          <select
            value={filters.status_rapor}
            onChange={(e) => setFilters({ ...filters, status_rapor: e.target.value })}
            className="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44]"
          >
            <option value="">Semua Status</option>
            <option value="draft">Draft</option>
            <option value="final">Final Validated</option>
            <option value="diterbitkan">Diterbitkan</option>
            <option value="direvisi">Direvisi</option>
          </select>
        </div>
      </div>

      {/* Main Table */}
      <div className="bg-white dark:bg-[#1B2433] rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
        {loading ? (
          <div className="p-12 text-center">
            <RefreshCw className="w-8 h-8 text-[#0E5C44] animate-spin mx-auto mb-3" />
            <p className="text-slate-500 font-medium text-sm">Memuat daftar Rapor Digital Siswa...</p>
          </div>
        ) : dataList.length === 0 ? (
          <div className="p-12 text-center">
            <BookOpen className="w-12 h-12 text-slate-300 mx-auto mb-3" />
            <p className="text-slate-700 dark:text-slate-300 font-bold">Belum ada data Rapor Digital</p>
            <p className="text-slate-500 text-xs mt-1">
              Gunakan tombol "Auto-Generate Kelas" untuk mengkalkulasi rapor otomatis dari data penilaian.
            </p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse">
              <thead>
                <tr className="bg-slate-50 dark:bg-slate-900/60 border-b border-slate-200 dark:border-slate-800 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                  <th className="py-4 px-4">Nama Siswa</th>
                  <th className="py-4 px-4">Kelas & Periode</th>
                  <th className="py-4 px-4 text-center">Mapel (Lulus/Total)</th>
                  <th className="py-4 px-4 text-center">Rata-Rata Nilai</th>
                  <th className="py-4 px-4 text-center">Ranking Kelas</th>
                  <th className="py-4 px-4 text-center">Presensi (H/I/S/A)</th>
                  <th className="py-4 px-4 text-center">Status Rapor</th>
                  <th className="py-4 px-4 text-right">Aksi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-200 dark:divide-slate-800 text-sm">
                {dataList.map((item) => (
                  <tr
                    key={item.id}
                    className="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors"
                  >
                    <td className="py-4 px-4">
                      <div className="font-bold text-slate-900 dark:text-white">
                        {item.siswa?.name || 'Siswa Tanpa Nama'}
                      </div>
                      <div className="text-xs text-slate-500">
                        NISN: {item.siswa?.nisn || '-'} | NIS: {item.siswa?.nis || '-'}
                      </div>
                    </td>

                    <td className="py-4 px-4">
                      <div className="font-semibold text-slate-800 dark:text-slate-200">
                        {item.kelas?.nama_kelas || 'Kelas -'}
                      </div>
                      <div className="text-xs text-slate-500">
                        {item.semester?.nama || 'Semester'} • {item.tahun_ajaran?.year || 'Tahun'}
                      </div>
                    </td>

                    <td className="py-4 px-4 text-center">
                      <span className="font-semibold text-emerald-700 dark:text-emerald-400">
                        {item.mapel_lulus}
                      </span>
                      <span className="text-slate-400"> / {item.total_mapel}</span>
                    </td>

                    <td className="py-4 px-4 text-center">
                      <span className="inline-block px-3 py-1 rounded-lg bg-emerald-50 text-[#0E5C44] dark:bg-emerald-950/60 dark:text-emerald-300 font-extrabold text-base border border-emerald-200 dark:border-emerald-800">
                        {item.rata_rata}
                      </span>
                    </td>

                    <td className="py-4 px-4 text-center">
                      {item.peringkat_kelas ? (
                        <span className="inline-flex items-center gap-1 font-bold text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/50 px-2.5 py-1 rounded-full text-xs">
                          🏆 Ke-{item.peringkat_kelas}
                          {item.total_siswa_kelas ? ` dari ${item.total_siswa_kelas}` : ''}
                        </span>
                      ) : (
                        <span className="text-slate-400 text-xs">-</span>
                      )}
                    </td>

                    <td className="py-4 px-4 text-center">
                      <div className="text-xs font-medium text-slate-700 dark:text-slate-300">
                        <span className="text-emerald-600 font-bold">{item.total_hadir}H</span> /{' '}
                        <span className="text-blue-600">{item.total_izin}I</span> /{' '}
                        <span className="text-amber-600">{item.total_sakit}S</span> /{' '}
                        <span className="text-rose-600 font-bold">{item.total_alpha}A</span>
                      </div>
                    </td>

                    <td className="py-4 px-4 text-center">{getStatusBadge(item.status_rapor)}</td>

                    <td className="py-4 px-4 text-right">
                      <div className="flex items-center justify-end gap-1.5">
                        <button
                          onClick={() => handleViewDigitalRapor(item.id)}
                          title="Lihat Rapor Digital"
                          className="p-2 rounded-lg bg-emerald-50 text-[#0E5C44] hover:bg-emerald-100 dark:bg-emerald-950/60 dark:text-emerald-300 transition-colors"
                        >
                          <Eye className="w-4 h-4" />
                        </button>

                        <button
                          onClick={() => handleOpenPrintPreview(item.id)}
                          title="Export / Cetak PDF"
                          className="p-2 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 dark:bg-blue-950/60 dark:text-blue-300 transition-colors"
                        >
                          <Printer className="w-4 h-4" />
                        </button>

                        <button
                          onClick={() => handleOpenEditModal(item)}
                          title="Edit Catatan & Status"
                          className="p-2 rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 transition-colors"
                        >
                          <Edit3 className="w-4 h-4" />
                        </button>

                        <button
                          onClick={() => handleDelete(item.id)}
                          title="Hapus"
                          className="p-2 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-100 dark:bg-rose-950/60 dark:text-rose-400 transition-colors"
                        >
                          <Trash2 className="w-4 h-4" />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* MODAL 1: Auto-Generate Rapor Kelas */}
      {showGenerateModal && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-white dark:bg-[#1B2433] rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-6">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-3">
                <div className="p-3 rounded-2xl bg-amber-50 text-amber-600 dark:bg-amber-950/50">
                  <RefreshCw className="w-6 h-6" />
                </div>
                <div>
                  <h3 className="font-bold text-slate-900 dark:text-white text-lg">
                    Auto-Generate Rapor Kelas
                  </h3>
                  <p className="text-xs text-slate-500">Hitung nilai & ranking siswa secara otomatis</p>
                </div>
              </div>
              <button
                onClick={() => setShowGenerateModal(false)}
                className="p-2 rounded-xl text-slate-400 hover:text-slate-600"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleGenerateClass} className="space-y-4">
              <div>
                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                  Pilih Kelas
                </label>
                <select
                  required
                  value={generateData.kelas_id}
                  onChange={(e) => setGenerateData({ ...generateData, kelas_id: e.target.value })}
                  className="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:ring-2 focus:ring-[#0E5C44]"
                >
                  {options.kelases.map((k) => (
                    <option key={k.id} value={k.id}>
                      {k.nama_kelas}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                  Semester
                </label>
                <select
                  required
                  value={generateData.semester_id}
                  onChange={(e) => setGenerateData({ ...generateData, semester_id: e.target.value })}
                  className="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:ring-2 focus:ring-[#0E5C44]"
                >
                  {options.semesters.map((s) => (
                    <option key={s.id} value={s.id}>
                      {s.nama}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                  Tahun Ajaran
                </label>
                <select
                  required
                  value={generateData.tahun_ajaran_id}
                  onChange={(e) => setGenerateData({ ...generateData, tahun_ajaran_id: e.target.value })}
                  className="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:ring-2 focus:ring-[#0E5C44]"
                >
                  {options.tahun_ajarans.map((t) => (
                    <option key={t.id} value={t.id}>
                      {t.year}
                    </option>
                  ))}
                </select>
              </div>

              <div className="pt-4 flex items-center justify-end gap-3">
                <button
                  type="button"
                  onClick={() => setShowGenerateModal(false)}
                  className="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 text-sm font-semibold hover:bg-slate-50"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={generating}
                  className="px-5 py-2.5 rounded-xl bg-[#0E5C44] text-white font-bold text-sm shadow-md hover:bg-[#1E8E5A] disabled:opacity-50 flex items-center gap-2"
                >
                  {generating ? (
                    <>
                      <RefreshCw className="w-4 h-4 animate-spin" /> Mengkalkulasi...
                    </>
                  ) : (
                    <>
                      <Sparkles className="w-4 h-4 text-amber-300" /> Proses Kalkulasi Rapor
                    </>
                  )}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* MODAL 2: Form Create / Edit Catatan & Status */}
      {showFormModal && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-white dark:bg-[#1B2433] rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-6">
            <div className="flex items-center justify-between">
              <h3 className="font-bold text-slate-900 dark:text-white text-lg">
                {editingItem ? 'Edit Catatan & Status Rapor' : 'Buat Rapor Digital Manual'}
              </h3>
              <button
                onClick={() => setShowFormModal(false)}
                className="p-2 rounded-xl text-slate-400 hover:text-slate-600"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleSaveRapor} className="space-y-4">
              {!editingItem && (
                <>
                  <div>
                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                      Pilih Siswa
                    </label>
                    <select
                      required
                      value={formData.siswa_id}
                      onChange={(e) => setFormData({ ...formData, siswa_id: e.target.value })}
                      className="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm"
                    >
                      {options.students.map((s) => (
                        <option key={s.id} value={s.id}>
                          {s.name} ({s.nisn || s.nis || 'No ID'})
                        </option>
                      ))}
                    </select>
                  </div>

                  <div className="grid grid-cols-2 gap-3">
                    <div>
                      <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        Kelas
                      </label>
                      <select
                        required
                        value={formData.kelas_id}
                        onChange={(e) => setFormData({ ...formData, kelas_id: e.target.value })}
                        className="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm"
                      >
                        {options.kelases.map((k) => (
                          <option key={k.id} value={k.id}>
                            {k.nama_kelas}
                          </option>
                        ))}
                      </select>
                    </div>

                    <div>
                      <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        Semester
                      </label>
                      <select
                        required
                        value={formData.semester_id}
                        onChange={(e) => setFormData({ ...formData, semester_id: e.target.value })}
                        className="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm"
                      >
                        {options.semesters.map((s) => (
                          <option key={s.id} value={s.id}>
                            {s.nama}
                          </option>
                        ))}
                      </select>
                    </div>
                  </div>
                </>
              )}

              <div>
                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                  Catatan Wali Kelas
                </label>
                <textarea
                  rows="3"
                  value={formData.catatan_wali_kelas}
                  onChange={(e) => setFormData({ ...formData, catatan_wali_kelas: e.target.value })}
                  className="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm"
                  placeholder="Ketik catatan perkembangan karakter & akademik siswa..."
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                  Catatan Kepala Sekolah
                </label>
                <textarea
                  rows="2"
                  value={formData.catatan_kepala_sekolah}
                  onChange={(e) => setFormData({ ...formData, catatan_kepala_sekolah: e.target.value })}
                  className="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm"
                  placeholder="Ketik catatan amanah & motivasi..."
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Status Penerbitan
                  </label>
                  <select
                    value={formData.status_rapor}
                    onChange={(e) => setFormData({ ...formData, status_rapor: e.target.value })}
                    className="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm"
                  >
                    <option value="draft">Draft</option>
                    <option value="final">Final Validated</option>
                    <option value="diterbitkan">Diterbitkan ke Ortu</option>
                    <option value="direvisi">Direvisi</option>
                  </select>
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Tanggal Terbit
                  </label>
                  <input
                    type="date"
                    value={formData.tanggal_terbit}
                    onChange={(e) => setFormData({ ...formData, tanggal_terbit: e.target.value })}
                    className="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm"
                  />
                </div>
              </div>

              <div className="pt-4 flex items-center justify-end gap-3">
                <button
                  type="button"
                  onClick={() => setShowFormModal(false)}
                  className="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 text-sm font-semibold hover:bg-slate-50"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  className="px-5 py-2.5 rounded-xl bg-[#0E5C44] text-white font-bold text-sm shadow-md hover:bg-[#1E8E5A]"
                >
                  Simpan Rapor
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* MODAL 3: Digital Rapor Preview Modal */}
      {showDigitalModal && (
        <div className="fixed inset-0 z-50 bg-slate-900/70 backdrop-blur-md flex items-center justify-center p-4 overflow-y-auto">
          <div className="bg-white dark:bg-[#111827] rounded-3xl max-w-4xl w-full p-6 md:p-8 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-6 my-8">
            <div className="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-4">
              <div className="flex items-center gap-3">
                <div className="p-3 rounded-2xl bg-emerald-50 text-[#0E5C44] dark:bg-emerald-950/60 dark:text-emerald-300">
                  <GraduationCap className="w-6 h-6" />
                </div>
                <div>
                  <h3 className="font-extrabold text-slate-900 dark:text-white text-xl">
                    Tampilan Rapor Digital Resmi
                  </h3>
                  <p className="text-xs text-slate-500">Pratinjau Hasil Pencapaian Pembelajaran Siswa</p>
                </div>
              </div>
              <div className="flex items-center gap-2">
                <button
                  onClick={() => {
                    setShowDigitalModal(false)
                    handleOpenPrintPreview(digitalData?.rapor?.id)
                  }}
                  className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-blue-50 text-blue-600 text-xs font-bold hover:bg-blue-100 transition-colors"
                >
                  <Printer className="w-4 h-4" /> Export PDF / Cetak
                </button>
                <button
                  onClick={() => setShowDigitalModal(false)}
                  className="p-2 rounded-xl text-slate-400 hover:text-slate-600"
                >
                  <X className="w-5 h-5" />
                </button>
              </div>
            </div>

            {loadingDigital ? (
              <div className="p-12 text-center">
                <RefreshCw className="w-8 h-8 text-[#0E5C44] animate-spin mx-auto mb-3" />
                <p className="text-slate-500 text-sm">Menyiapkan rincian Rapor Digital...</p>
              </div>
            ) : digitalData ? (
              <div className="space-y-6">
                {/* Header Sekolah */}
                <div className="text-center pb-4 border-b-2 border-slate-900 dark:border-slate-100">
                  <h2 className="text-lg md:text-xl font-extrabold text-slate-900 dark:text-white uppercase tracking-wider">
                    {digitalData.school_info?.nama_sekolah}
                  </h2>
                  <p className="text-xs text-slate-600 dark:text-slate-400 font-medium">
                    NPSN: {digitalData.school_info?.npsn} • {digitalData.school_info?.alamat}
                  </p>
                  <div className="mt-2 inline-block px-4 py-1 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 text-xs font-bold uppercase tracking-widest">
                    Laporan Hasil Belajar (Rapor Digital)
                  </div>
                </div>

                {/* Identitas Siswa */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4 p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 text-xs">
                  <div>
                    <span className="text-slate-500 font-semibold uppercase block">Nama Siswa</span>
                    <span className="font-extrabold text-slate-900 dark:text-white text-sm">
                      {digitalData.siswa?.name}
                    </span>
                  </div>
                  <div>
                    <span className="text-slate-500 font-semibold uppercase block">NISN / NIS</span>
                    <span className="font-bold text-slate-800 dark:text-slate-200">
                      {digitalData.siswa?.nisn || '-'} / {digitalData.siswa?.nis || '-'}
                    </span>
                  </div>
                  <div>
                    <span className="text-slate-500 font-semibold uppercase block">Kelas</span>
                    <span className="font-bold text-slate-800 dark:text-slate-200">
                      {digitalData.kelas?.nama_kelas}
                    </span>
                  </div>
                  <div>
                    <span className="text-slate-500 font-semibold uppercase block">Semester / TP</span>
                    <span className="font-bold text-slate-800 dark:text-slate-200">
                      {digitalData.semester?.nama} ({digitalData.tahun_ajaran?.year})
                    </span>
                  </div>
                </div>

                {/* Tabel Nilai Mata Pelajaran */}
                <div>
                  <h4 className="font-bold text-slate-900 dark:text-white text-sm mb-3 flex items-center gap-2">
                    <BookOpen className="w-4 h-4 text-[#0E5C44]" /> Capaian Nilai Akademik Mata Pelajaran
                  </h4>
                  <div className="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800">
                    <table className="w-full text-left text-xs border-collapse">
                      <thead>
                        <tr className="bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold">
                          <th className="p-3">No</th>
                          <th className="p-3">Mata Pelajaran</th>
                          <th className="p-3 text-center">KKM</th>
                          <th className="p-3 text-center">Nilai Akhir</th>
                          <th className="p-3 text-center">Predikat</th>
                          <th className="p-3 text-center">Status</th>
                          <th className="p-3">Catatan Deskripsi</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
                        {digitalData.grades && digitalData.grades.length > 0 ? (
                          digitalData.grades.map((g, idx) => (
                            <tr key={g.id || idx} className="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                              <td className="p-3 font-semibold text-slate-500">{idx + 1}</td>
                              <td className="p-3 font-bold text-slate-900 dark:text-white">
                                {g.subject_name}
                              </td>
                              <td className="p-3 text-center font-medium text-slate-500">{g.kkm}</td>
                              <td className="p-3 text-center font-extrabold text-base text-[#0E5C44]">
                                {g.final_score}
                              </td>
                              <td className="p-3 text-center">
                                <span className="px-2.5 py-0.5 rounded bg-emerald-100 text-emerald-800 font-extrabold">
                                  {g.grade_letter}
                                </span>
                              </td>
                              <td className="p-3 text-center">
                                {g.is_passed ? (
                                  <span className="text-emerald-600 font-bold">Tuntas</span>
                                ) : (
                                  <span className="text-rose-600 font-bold">Remedial</span>
                                )}
                              </td>
                              <td className="p-3 text-slate-600 dark:text-slate-400 italic">
                                {g.notes || 'Menunjukkan penguasaan kompetensi yang baik.'}
                              </td>
                            </tr>
                          ))
                        ) : (
                          <tr>
                            <td colSpan="7" className="p-4 text-center text-slate-400">
                              Belum ada rincian nilai mata pelajaran terdaftar.
                            </td>
                          </tr>
                        )}
                      </tbody>
                    </table>
                  </div>
                </div>

                {/* Ringkasan & Presensi */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div className="p-4 rounded-2xl bg-emerald-50/60 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-900 space-y-2">
                    <h5 className="font-bold text-[#0E5C44] dark:text-emerald-300 text-xs uppercase tracking-wider">
                      Ringkasan Prestasi Akademik
                    </h5>
                    <div className="flex justify-between text-xs py-1 border-b border-emerald-200/50">
                      <span>Total Nilai Komulatif</span>
                      <span className="font-extrabold text-slate-900 dark:text-white">
                        {digitalData.rapor?.total_nilai}
                      </span>
                    </div>
                    <div className="flex justify-between text-xs py-1 border-b border-emerald-200/50">
                      <span>Rata-Rata Nilai Rapor</span>
                      <span className="font-extrabold text-[#0E5C44] text-sm">
                        {digitalData.rapor?.rata_rata}
                      </span>
                    </div>
                    <div className="flex justify-between text-xs py-1">
                      <span>Peringkat di Kelas</span>
                      <span className="font-extrabold text-amber-600">
                        Ke-{digitalData.rapor?.peringkat_kelas} dari {digitalData.rapor?.total_siswa_kelas} Siswa
                      </span>
                    </div>
                  </div>

                  <div className="p-4 rounded-2xl bg-blue-50/60 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-900 space-y-2">
                    <h5 className="font-bold text-blue-800 dark:text-blue-300 text-xs uppercase tracking-wider">
                      Ketidakhadiran (Presensi)
                    </h5>
                    <div className="grid grid-cols-4 gap-2 text-center pt-1">
                      <div className="p-2 rounded-xl bg-white dark:bg-slate-900 shadow-xs">
                        <span className="block text-[10px] text-slate-400 uppercase font-bold">Hadir</span>
                        <span className="font-extrabold text-emerald-600 text-base">
                          {digitalData.rapor?.total_hadir}
                        </span>
                      </div>
                      <div className="p-2 rounded-xl bg-white dark:bg-slate-900 shadow-xs">
                        <span className="block text-[10px] text-slate-400 uppercase font-bold">Izin</span>
                        <span className="font-extrabold text-blue-600 text-base">
                          {digitalData.rapor?.total_izin}
                        </span>
                      </div>
                      <div className="p-2 rounded-xl bg-white dark:bg-slate-900 shadow-xs">
                        <span className="block text-[10px] text-slate-400 uppercase font-bold">Sakit</span>
                        <span className="font-extrabold text-amber-600 text-base">
                          {digitalData.rapor?.total_sakit}
                        </span>
                      </div>
                      <div className="p-2 rounded-xl bg-white dark:bg-slate-900 shadow-xs">
                        <span className="block text-[10px] text-slate-400 uppercase font-bold">Tanpa Ket.</span>
                        <span className="font-extrabold text-rose-600 text-base">
                          {digitalData.rapor?.total_alpha}
                        </span>
                      </div>
                    </div>
                  </div>
                </div>

                {/* Catatan Wali Kelas & Kepsek */}
                <div className="p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 text-xs space-y-3">
                  <div>
                    <span className="font-bold text-slate-800 dark:text-slate-200 block mb-1">
                      Catatan Wali Kelas:
                    </span>
                    <p className="italic text-slate-600 dark:text-slate-300">
                      "{digitalData.rapor?.catatan_wali_kelas || 'Selalu tingkatkan kedisiplinan dan keaktifan.'}"
                    </p>
                  </div>
                  <div>
                    <span className="font-bold text-slate-800 dark:text-slate-200 block mb-1">
                      Catatan Kepala Sekolah:
                    </span>
                    <p className="italic text-slate-600 dark:text-slate-300">
                      "{digitalData.rapor?.catatan_kepala_sekolah || 'Pertahankan prestasi belajar.'}"
                    </p>
                  </div>
                </div>
              </div>
            ) : null}
          </div>
        </div>
      )}

      {/* MODAL 4: PDF & Print Preview Engine */}
      {showPrintModal && (
        <div className="fixed inset-0 z-50 bg-slate-900/80 backdrop-blur-md flex items-center justify-center p-2 sm:p-4 overflow-y-auto">
          <div className="bg-white rounded-3xl max-w-4xl w-full p-6 md:p-10 shadow-2xl text-slate-900 space-y-6 my-8 print:p-0 print:shadow-none">
            {/* Action Bar inside Print Modal */}
            <div className="flex items-center justify-between border-b pb-4 print:hidden">
              <div className="flex items-center gap-2">
                <Printer className="w-5 h-5 text-[#0E5C44]" />
                <span className="font-bold text-sm">Mode Cetak Rapor Resmi A4</span>
              </div>
              <div className="flex items-center gap-3">
                <button
                  onClick={triggerBrowserPrint}
                  className="px-4 py-2 rounded-xl bg-[#0E5C44] text-white text-xs font-bold shadow hover:bg-[#1E8E5A] flex items-center gap-2"
                >
                  <Printer className="w-4 h-4" /> Cetak / Download PDF
                </button>
                <button
                  onClick={() => setShowPrintModal(false)}
                  className="p-2 rounded-xl text-slate-400 hover:text-slate-600"
                >
                  <X className="w-5 h-5" />
                </button>
              </div>
            </div>

            {digitalData && (
              <div className="print-area font-sans space-y-6 text-slate-900">
                {/* Printable Kop Sekolah */}
                <div className="text-center pb-4 border-b-4 border-double border-slate-900">
                  <h1 className="text-xl font-black uppercase tracking-wide">
                    {digitalData.school_info?.nama_sekolah}
                  </h1>
                  <p className="text-xs font-medium text-slate-700">
                    NPSN: {digitalData.school_info?.npsn} • {digitalData.school_info?.alamat}
                  </p>
                  <h2 className="text-sm font-bold uppercase tracking-widest mt-3 underline">
                    LAPORAN HASIL CAPAIAN PEMBELAJARAN (RAPOR)
                  </h2>
                </div>

                {/* Printable Identity Grid */}
                <table className="w-full text-xs font-medium border-none">
                  <tbody>
                    <tr>
                      <td className="w-24 py-1 font-bold">Nama Siswa</td>
                      <td className="w-4">:</td>
                      <td className="font-bold text-sm">{digitalData.siswa?.name}</td>
                      <td className="w-24 py-1 font-bold">Kelas</td>
                      <td className="w-4">:</td>
                      <td>{digitalData.kelas?.nama_kelas}</td>
                    </tr>
                    <tr>
                      <td className="py-1 font-bold">NISN / NIS</td>
                      <td>:</td>
                      <td>
                        {digitalData.siswa?.nisn || '-'} / {digitalData.siswa?.nis || '-'}
                      </td>
                      <td className="py-1 font-bold">Semester</td>
                      <td>:</td>
                      <td>
                        {digitalData.semester?.nama} ({digitalData.tahun_ajaran?.year})
                      </td>
                    </tr>
                  </tbody>
                </table>

                {/* Printable Grades Table */}
                <div>
                  <h3 className="font-bold text-xs uppercase tracking-wider mb-2">A. NILAI AKADEMIK</h3>
                  <table className="w-full border-collapse border border-slate-900 text-xs">
                    <thead>
                      <tr className="bg-slate-100 text-slate-900 font-bold border-b border-slate-900">
                        <th className="border border-slate-900 p-2 w-8 text-center">No</th>
                        <th className="border border-slate-900 p-2">Mata Pelajaran</th>
                        <th className="border border-slate-900 p-2 w-12 text-center">KKM</th>
                        <th className="border border-slate-900 p-2 w-16 text-center">Nilai</th>
                        <th className="border border-slate-900 p-2 w-12 text-center">Grade</th>
                        <th className="border border-slate-900 p-2">Capaian Kompetensi</th>
                      </tr>
                    </thead>
                    <tbody>
                      {digitalData.grades?.map((g, index) => (
                        <tr key={index}>
                          <td className="border border-slate-900 p-2 text-center">{index + 1}</td>
                          <td className="border border-slate-900 p-2 font-bold">{g.subject_name}</td>
                          <td className="border border-slate-900 p-2 text-center">{g.kkm}</td>
                          <td className="border border-slate-900 p-2 text-center font-bold text-sm">
                            {g.final_score}
                          </td>
                          <td className="border border-slate-900 p-2 text-center font-bold">{g.grade_letter}</td>
                          <td className="border border-slate-900 p-2 text-[11px]">
                            {g.notes || 'Sangat baik dalam menguasai seluruh materi dan praktik pembelajaran.'}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>

                {/* Printable Attendance & Rank */}
                <div className="grid grid-cols-2 gap-4 text-xs">
                  <div>
                    <h3 className="font-bold uppercase tracking-wider mb-2">B. KETIDAKHADIRAN</h3>
                    <table className="w-full border-collapse border border-slate-900">
                      <tbody>
                        <tr>
                          <td className="border border-slate-900 p-2">Hadir</td>
                          <td className="border border-slate-900 p-2 font-bold text-center">
                            {digitalData.rapor?.total_hadir} Hari
                          </td>
                        </tr>
                        <tr>
                          <td className="border border-slate-900 p-2">Izin</td>
                          <td className="border border-slate-900 p-2 font-bold text-center">
                            {digitalData.rapor?.total_izin} Hari
                          </td>
                        </tr>
                        <tr>
                          <td className="border border-slate-900 p-2">Sakit</td>
                          <td className="border border-slate-900 p-2 font-bold text-center">
                            {digitalData.rapor?.total_sakit} Hari
                          </td>
                        </tr>
                        <tr>
                          <td className="border border-slate-900 p-2">Tanpa Keterangan</td>
                          <td className="border border-slate-900 p-2 font-bold text-center">
                            {digitalData.rapor?.total_alpha} Hari
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>

                  <div>
                    <h3 className="font-bold uppercase tracking-wider mb-2">C. RANGKUMAN PRESTASI</h3>
                    <table className="w-full border-collapse border border-slate-900">
                      <tbody>
                        <tr>
                          <td className="border border-slate-900 p-2">Total Nilai</td>
                          <td className="border border-slate-900 p-2 font-bold text-center">
                            {digitalData.rapor?.total_nilai}
                          </td>
                        </tr>
                        <tr>
                          <td className="border border-slate-900 p-2">Rata-Rata Nilai</td>
                          <td className="border border-slate-900 p-2 font-extrabold text-center text-sm">
                            {digitalData.rapor?.rata_rata}
                          </td>
                        </tr>
                        <tr>
                          <td className="border border-slate-900 p-2">Peringkat Kelas</td>
                          <td className="border border-slate-900 p-2 font-bold text-center">
                            Ke-{digitalData.rapor?.peringkat_kelas} ({digitalData.rapor?.total_siswa_kelas} Siswa)
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>

                {/* Printable Signatures */}
                <div className="pt-8 grid grid-cols-3 gap-4 text-center text-xs font-semibold">
                  <div>
                    <p>Orang Tua / Wali Siswa</p>
                    <div className="h-16"></div>
                    <p className="border-b border-slate-900 inline-block px-8 pb-1">( ................................ )</p>
                  </div>
                  <div>
                    <p>Wali Kelas</p>
                    <div className="h-16"></div>
                    <p className="font-bold border-b border-slate-900 inline-block px-4 pb-1">
                      {digitalData.wali_kelas?.name || 'Wali Kelas, S.Pd.'}
                    </p>
                    <p className="text-[10px] text-slate-600">NIP: {digitalData.wali_kelas?.nip || '-'}</p>
                  </div>
                  <div>
                    <p>Kepala Sekolah</p>
                    <div className="h-16"></div>
                    <p className="font-bold border-b border-slate-900 inline-block px-4 pb-1">
                      {digitalData.school_info?.kepala_sekolah}
                    </p>
                    <p className="text-[10px] text-slate-600">NIP: {digitalData.school_info?.nip_kepsek}</p>
                  </div>
                </div>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  )
}
