import React, { useState, useEffect } from 'react'
import {
  HelpCircle,
  CheckSquare,
  FileText,
  ToggleLeft,
  GitCommit,
  Plus,
  Search,
  Filter,
  Edit3,
  Trash2,
  Copy,
  CheckCircle2,
  XCircle,
  Eye,
  RefreshCw,
  X,
  Layers,
  Sparkles,
  Award,
  BookOpen,
  ChevronRight,
  ArrowRight,
  List,
} from 'lucide-react'
import { lmsBankSoalService } from '../services/lmsBankSoalService'

export default function LmsBankSoalPage() {
  const [dataList, setDataList] = useState([])
  const [loading, setLoading] = useState(true)
  const [pagination, setPagination] = useState({ currentPage: 1, lastPage: 1, total: 0 })
  const [stats, setStats] = useState({
    total_soal: 0,
    total_pg: 0,
    total_esai: 0,
    total_benar_salah: 0,
    total_menjodohkan: 0,
    total_aktif: 0,
  })
  const [options, setOptions] = useState({
    kisi_kisi: [],
    tipe_soal: [],
    tingkat_kesulitan: [],
  })

  const [filters, setFilters] = useState({
    search: '',
    kisi_kisi_id: '',
    tipe_soal: '',
    tingkat_kesulitan: '',
    status: '',
  })

  const [showModal, setShowModal] = useState(false)
  const [showDetailModal, setShowDetailModal] = useState(false)
  const [editingItem, setEditingItem] = useState(null)
  const [viewingItem, setViewingItem] = useState(null)
  const [toast, setToast] = useState({ show: false, message: '', type: 'success' })

  // Pair state for Menjodohkan type
  const [matchingPairs, setMatchingPairs] = useState([
    { kiri: '', kanan: '' },
    { kiri: '', kanan: '' },
  ])

  const [formData, setFormData] = useState({
    kisi_kisi_id: '',
    mata_pelajaran_id: '',
    kode_soal: '',
    pertanyaan: '',
    tipe_soal: 'pg',
    opsi_a: '',
    opsi_b: '',
    opsi_c: '',
    opsi_d: '',
    opsi_e: '',
    kunci_jawaban: 'A',
    pembahasan: '',
    poin: 2.5,
    tingkat_kesulitan: 'sedang',
    indikator: '',
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
      const response = await lmsBankSoalService.getDaftar(params)
      if (response && response.data) {
        setDataList(response.data)
        setPagination({
          currentPage: response.meta?.current_page || 1,
          lastPage: response.meta?.last_page || 1,
          total: response.meta?.total || response.data.length,
        })
      }
    } catch (error) {
      console.error('Error loading Bank Soal data:', error)
      showNotification('Gagal memuat data Bank Soal', 'error')
    } finally {
      setLoading(false)
    }
  }

  const fetchStats = async () => {
    try {
      const response = await lmsBankSoalService.getStats()
      if (response && response.data) {
        setStats(response.data)
      }
    } catch (error) {
      console.error('Error loading Bank Soal stats:', error)
    }
  }

  const fetchOptions = async () => {
    try {
      const response = await lmsBankSoalService.getOptions()
      if (response && response.data) {
        setOptions(response.data)
      }
    } catch (error) {
      console.error('Error loading options:', error)
    }
  }

  const handleOpenModal = (item = null) => {
    if (item) {
      setEditingItem(item)
      let defaultKunci = item.kunci_jawaban || ''
      let pairs = [
        { kiri: '', kanan: '' },
        { kiri: '', kanan: '' },
      ]

      if (item.tipe_soal === 'menjodohkan' && item.pasangan_menjodohkan) {
        pairs = item.pasangan_menjodohkan
      }

      setMatchingPairs(pairs)

      setFormData({
        kisi_kisi_id: item.kisi_kisi_id || '',
        mata_pelajaran_id: item.mata_pelajaran_id || '',
        kode_soal: item.kode_soal || '',
        pertanyaan: item.pertanyaan || '',
        tipe_soal: item.tipe_soal || 'pg',
        opsi_a: item.opsi_a || '',
        opsi_b: item.opsi_b || '',
        opsi_c: item.opsi_c || '',
        opsi_d: item.opsi_d || '',
        opsi_e: item.opsi_e || '',
        kunci_jawaban: defaultKunci,
        pembahasan: item.pembahasan || '',
        poin: item.poin || 2.5,
        tingkat_kesulitan: item.tingkat_kesulitan || 'sedang',
        indikator: item.indikator || '',
        status: item.status !== undefined ? item.status : true,
      })
    } else {
      setEditingItem(null)
      const defaultKisi = options.kisi_kisi.length > 0 ? options.kisi_kisi[0].id : ''
      const defaultMapel = options.kisi_kisi.length > 0 ? options.kisi_kisi[0].mata_pelajaran_id : ''

      setMatchingPairs([
        { kiri: '', kanan: '' },
        { kiri: '', kanan: '' },
      ])

      setFormData({
        kisi_kisi_id: defaultKisi,
        mata_pelajaran_id: defaultMapel,
        kode_soal: '',
        pertanyaan: '',
        tipe_soal: 'pg',
        opsi_a: '',
        opsi_b: '',
        opsi_c: '',
        opsi_d: '',
        opsi_e: '',
        kunci_jawaban: 'A',
        pembahasan: '',
        poin: 2.5,
        tingkat_kesulitan: 'sedang',
        indikator: '',
        status: true,
      })
    }
    setShowModal(true)
  }

  const handleCloseModal = () => {
    setShowModal(false)
    setEditingItem(null)
  }

  const handleTypeChange = (newType) => {
    let defaultKunci = ''
    if (newType === 'pg') defaultKunci = 'A'
    if (newType === 'benar_salah') defaultKunci = 'Benar'
    if (newType === 'esai') defaultKunci = ''
    if (newType === 'menjodohkan') defaultKunci = ''

    setFormData((prev) => ({
      ...prev,
      tipe_soal: newType,
      kunci_jawaban: defaultKunci,
    }))
  }

  const handleKisiChange = (kisiId) => {
    const selectedKisi = options.kisi_kisi.find((k) => k.id === kisiId)
    setFormData((prev) => ({
      ...prev,
      kisi_kisi_id: kisiId,
      mata_pelajaran_id: selectedKisi ? selectedKisi.mata_pelajaran_id : prev.mata_pelajaran_id,
    }))
  }

  const handleAddPair = () => {
    setMatchingPairs((prev) => [...prev, { kiri: '', kanan: '' }])
  }

  const handleRemovePair = (index) => {
    if (matchingPairs.length <= 1) return
    setMatchingPairs((prev) => prev.filter((_, i) => i !== index))
  }

  const handlePairChange = (index, field, value) => {
    setMatchingPairs((prev) => {
      const copy = [...prev]
      copy[index][field] = value
      return copy
    })
  }

  const handleSubmit = async (e) => {
    e.preventDefault()

    if (!formData.kisi_kisi_id) {
      showNotification('Pilih Kisi-kisi Ujian terlebih dahulu.', 'error')
      return
    }

    if (!formData.pertanyaan.trim()) {
      showNotification('Teks pertanyaan/soal tidak boleh kosong.', 'error')
      return
    }

    let payload = { ...formData }

    // Format kunci_jawaban for Menjodohkan
    if (formData.tipe_soal === 'menjodohkan') {
      const validPairs = matchingPairs.filter((p) => p.kiri.trim() && p.kanan.trim())
      if (validPairs.length === 0) {
        showNotification('Masukkan minimal 1 pasangan yang valid untuk tipe Menjodohkan.', 'error')
        return
      }
      payload.kunci_jawaban = JSON.stringify(validPairs)
    }

    try {
      if (editingItem) {
        await lmsBankSoalService.update(editingItem.id, payload)
        showNotification('Butir soal berhasil diperbarui!')
      } else {
        await lmsBankSoalService.create(payload)
        showNotification('Butir soal baru berhasil disimpan!')
      }
      handleCloseModal()
      fetchData(pagination.currentPage)
      fetchStats()
    } catch (error) {
      console.error('Error saving Bank Soal:', error)
      const errorMsg = error.response?.data?.message || 'Gagal menyimpan butir soal.'
      showNotification(errorMsg, 'error')
    }
  }

  const handleDelete = async (id) => {
    if (!window.confirm('Apakah Anda yakin ingin menghapus butir soal ini?')) return
    try {
      await lmsBankSoalService.delete(id)
      showNotification('Butir soal berhasil dihapus!')
      fetchData(pagination.currentPage)
      fetchStats()
    } catch (error) {
      console.error('Error deleting item:', error)
      showNotification('Gagal menghapus butir soal.', 'error')
    }
  }

  const handleDuplicate = async (id) => {
    try {
      await lmsBankSoalService.duplicate(id)
      showNotification('Butir soal berhasil diduplikasi!')
      fetchData(pagination.currentPage)
      fetchStats()
    } catch (error) {
      console.error('Error duplicating item:', error)
      showNotification('Gagal menduplikasi butir soal.', 'error')
    }
  }

  const handleToggleStatus = async (item) => {
    try {
      await lmsBankSoalService.update(item.id, { status: !item.status })
      showNotification(`Status butir soal diubah menjadi ${!item.status ? 'Aktif' : 'Non-Aktif'}`)
      fetchData(pagination.currentPage)
      fetchStats()
    } catch (error) {
      console.error('Error toggling status:', error)
      showNotification('Gagal mengubah status.', 'error')
    }
  }

  const getTipeBadge = (tipe) => {
    switch (tipe) {
      case 'pg':
        return (
          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
            <CheckSquare className="w-3.5 h-3.5" /> Pilihan Ganda
          </span>
        )
      case 'esai':
        return (
          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-950/60 dark:text-purple-300 border border-purple-200 dark:border-purple-800">
            <FileText className="w-3.5 h-3.5" /> Essay / Esai
          </span>
        )
      case 'benar_salah':
        return (
          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
            <ToggleLeft className="w-3.5 h-3.5" /> Benar / Salah
          </span>
        )
      case 'menjodohkan':
        return (
          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
            <GitCommit className="w-3.5 h-3.5" /> Menjodohkan
          </span>
        )
      default:
        return (
          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300">
            {tipe}
          </span>
        )
    }
  }

  const getKesulitanBadge = (level) => {
    switch (level) {
      case 'mudah':
        return <span className="px-2 py-0.5 text-xs rounded-md bg-teal-100 text-teal-800 dark:bg-teal-900/40 dark:text-teal-300 font-medium">Mudah</span>
      case 'sedang':
        return <span className="px-2 py-0.5 text-xs rounded-md bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 font-medium">Sedang</span>
      case 'sulit':
        return <span className="px-2 py-0.5 text-xs rounded-md bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300 font-medium">Sulit</span>
      default:
        return <span className="px-2 py-0.5 text-xs rounded-md bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300">{level}</span>
    }
  }

  return (
    <div className="min-h-screen bg-[#F7F9FC] dark:bg-[#0F172A] p-4 md:p-6 lg:p-8 space-y-6 font-sans text-gray-900 dark:text-gray-100">
      {/* Toast Notification */}
      {toast.show && (
        <div
          className={`fixed top-5 right-5 z-50 flex items-center gap-3 px-4 py-3 rounded-2xl shadow-xl text-white transition-all transform duration-300 ${
            toast.type === 'error' ? 'bg-rose-600' : 'bg-[#0E5C44]'
          }`}
        >
          {toast.type === 'error' ? <XCircle className="w-5 h-5" /> : <CheckCircle2 className="w-5 h-5" />}
          <span className="text-sm font-medium">{toast.message}</span>
        </div>
      )}

      {/* Hero Banner Header */}
      <div className="bg-gradient-to-r from-[#0E5C44] via-[#1E8E5A] to-[#3FBF75] rounded-[18px] p-6 text-white shadow-xl relative overflow-hidden">
        <div className="absolute right-0 top-0 translate-x-10 -translate-y-10 opacity-15 pointer-events-none">
          <HelpCircle className="w-72 h-72 text-white" />
        </div>
        <div className="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div>
            <div className="flex items-center gap-2 text-emerald-200 text-xs font-semibold uppercase tracking-wider mb-1">
              <Sparkles className="w-4 h-4" /> Evaluasi & Penilaian Digital
            </div>
            <h1 className="text-2xl md:text-3xl font-bold tracking-tight">Bank Soal Ujian</h1>
            <p className="text-emerald-100 text-sm mt-1 max-w-xl">
              Kelola repositori butir soal terintegrasi Kisi-kisi Ujian dengan dukungan Pilihan Ganda, Esai, Benar-Salah, dan Menjodohkan.
            </p>
          </div>
          <button
            onClick={() => handleOpenModal()}
            className="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl bg-white text-[#0E5C44] font-semibold text-sm shadow-md hover:bg-emerald-50 transition-all duration-200 hover:scale-[1.02] active:scale-[0.98]"
          >
            <Plus className="w-4 h-4" /> Tambah Soal Baru
          </button>
        </div>
      </div>

      {/* KPI Stats Cards */}
      <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
        <div className="bg-white dark:bg-[#1B2433] p-4 rounded-[18px] border border-gray-100 dark:border-gray-800 shadow-sm hover:shadow-md transition-shadow">
          <div className="flex items-center justify-between">
            <span className="text-xs text-gray-500 dark:text-gray-400 font-medium">Total Soal</span>
            <div className="p-2 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 text-[#0E5C44] dark:text-emerald-400">
              <Layers className="w-4 h-4" />
            </div>
          </div>
          <div className="text-xl md:text-2xl font-bold mt-2 text-gray-900 dark:text-white">{stats.total_soal}</div>
          <span className="text-[11px] text-gray-500 dark:text-gray-400 mt-1 block">{stats.total_aktif} Status Aktif</span>
        </div>

        <div className="bg-white dark:bg-[#1B2433] p-4 rounded-[18px] border border-gray-100 dark:border-gray-800 shadow-sm hover:shadow-md transition-shadow">
          <div className="flex items-center justify-between">
            <span className="text-xs text-gray-500 dark:text-gray-400 font-medium">Pilihan Ganda</span>
            <div className="p-2 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400">
              <CheckSquare className="w-4 h-4" />
            </div>
          </div>
          <div className="text-xl md:text-2xl font-bold mt-2 text-emerald-700 dark:text-emerald-400">{stats.total_pg}</div>
          <span className="text-[11px] text-gray-400 mt-1 block">Tipe PG</span>
        </div>

        <div className="bg-white dark:bg-[#1B2433] p-4 rounded-[18px] border border-gray-100 dark:border-gray-800 shadow-sm hover:shadow-md transition-shadow">
          <div className="flex items-center justify-between">
            <span className="text-xs text-gray-500 dark:text-gray-400 font-medium">Essay / Esai</span>
            <div className="p-2 rounded-xl bg-purple-50 dark:bg-purple-950/50 text-purple-600 dark:text-purple-400">
              <FileText className="w-4 h-4" />
            </div>
          </div>
          <div className="text-xl md:text-2xl font-bold mt-2 text-purple-700 dark:text-purple-400">{stats.total_esai}</div>
          <span className="text-[11px] text-gray-400 mt-1 block">Uraian / Manual</span>
        </div>

        <div className="bg-white dark:bg-[#1B2433] p-4 rounded-[18px] border border-gray-100 dark:border-gray-800 shadow-sm hover:shadow-md transition-shadow">
          <div className="flex items-center justify-between">
            <span className="text-xs text-gray-500 dark:text-gray-400 font-medium">Benar / Salah</span>
            <div className="p-2 rounded-xl bg-blue-50 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400">
              <ToggleLeft className="w-4 h-4" />
            </div>
          </div>
          <div className="text-xl md:text-2xl font-bold mt-2 text-blue-700 dark:text-blue-400">{stats.total_benar_salah}</div>
          <span className="text-[11px] text-gray-400 mt-1 block">Tipe B/S</span>
        </div>

        <div className="bg-white dark:bg-[#1B2433] p-4 rounded-[18px] border border-gray-100 dark:border-gray-800 shadow-sm hover:shadow-md transition-shadow col-span-2 md:col-span-1">
          <div className="flex items-center justify-between">
            <span className="text-xs text-gray-500 dark:text-gray-400 font-medium">Menjodohkan</span>
            <div className="p-2 rounded-xl bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400">
              <GitCommit className="w-4 h-4" />
            </div>
          </div>
          <div className="text-xl md:text-2xl font-bold mt-2 text-amber-700 dark:text-amber-400">{stats.total_menjodohkan}</div>
          <span className="text-[11px] text-gray-400 mt-1 block">Matching Pairs</span>
        </div>
      </div>

      {/* Filter and Search Bar */}
      <div className="bg-white dark:bg-[#1B2433] p-4 rounded-[18px] border border-gray-100 dark:border-gray-800 shadow-sm flex flex-col md:flex-row gap-3 items-center justify-between">
        <div className="relative w-full md:w-80">
          <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
          <input
            type="text"
            placeholder="Cari soal, kode, indikator..."
            value={filters.search}
            onChange={(e) => setFilters((prev) => ({ ...prev, search: e.target.value }))}
            className="w-full pl-9 pr-4 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-[#111827] focus:outline-none focus:ring-2 focus:ring-[#0E5C44]"
          />
        </div>

        <div className="flex flex-wrap gap-2 w-full md:w-auto">
          <select
            value={filters.kisi_kisi_id}
            onChange={(e) => setFilters((prev) => ({ ...prev, kisi_kisi_id: e.target.value }))}
            className="px-3 py-2 text-xs rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-[#111827] text-gray-700 dark:text-gray-300 focus:ring-2 focus:ring-[#0E5C44]"
          >
            <option value="">Semua Kisi-kisi Ujian</option>
            {options.kisi_kisi.map((k) => (
              <option key={k.id} value={k.id}>
                {k.judul_kisi} ({k.jenis_ujian})
              </option>
            ))}
          </select>

          <select
            value={filters.tipe_soal}
            onChange={(e) => setFilters((prev) => ({ ...prev, tipe_soal: e.target.value }))}
            className="px-3 py-2 text-xs rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-[#111827] text-gray-700 dark:text-gray-300 focus:ring-2 focus:ring-[#0E5C44]"
          >
            <option value="">Semua Tipe Soal</option>
            <option value="pg">Pilihan Ganda</option>
            <option value="esai">Essay / Esai</option>
            <option value="benar_salah">Benar / Salah</option>
            <option value="menjodohkan">Menjodohkan</option>
          </select>

          <select
            value={filters.tingkat_kesulitan}
            onChange={(e) => setFilters((prev) => ({ ...prev, tingkat_kesulitan: e.target.value }))}
            className="px-3 py-2 text-xs rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-[#111827] text-gray-700 dark:text-gray-300 focus:ring-2 focus:ring-[#0E5C44]"
          >
            <option value="">Tingkat Kesulitan</option>
            <option value="mudah">Mudah</option>
            <option value="sedang">Sedang</option>
            <option value="sulit">Sulit</option>
          </select>

          <button
            onClick={() => {
              setFilters({ search: '', kisi_kisi_id: '', tipe_soal: '', tingkat_kesulitan: '', status: '' })
              fetchData(1)
            }}
            className="p-2 rounded-xl border border-gray-200 dark:border-gray-700 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"
            title="Reset Filter"
          >
            <RefreshCw className="w-4 h-4" />
          </button>
        </div>
      </div>

      {/* Main Table Card */}
      <div className="bg-white dark:bg-[#1B2433] rounded-[18px] border border-gray-100 dark:border-gray-800 shadow-sm overflow-hidden">
        {loading ? (
          <div className="p-8 text-center space-y-3">
            <RefreshCw className="w-8 h-8 text-[#0E5C44] animate-spin mx-auto" />
            <p className="text-sm text-gray-500 dark:text-gray-400">Memuat repositori Bank Soal...</p>
          </div>
        ) : dataList.length === 0 ? (
          <div className="p-12 text-center space-y-3">
            <HelpCircle className="w-12 h-12 text-gray-300 dark:text-gray-600 mx-auto" />
            <h3 className="text-base font-semibold text-gray-800 dark:text-gray-200">Belum Ada Butir Soal</h3>
            <p className="text-xs text-gray-500 dark:text-gray-400 max-w-sm mx-auto">
              Silakan tambahkan butir soal baru atau ubah kata kunci filter Anda.
            </p>
            <button
              onClick={() => handleOpenModal()}
              className="mt-2 inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-[#0E5C44] text-white text-xs font-medium hover:bg-emerald-700 transition"
            >
              <Plus className="w-3.5 h-3.5" /> Buat Soal Pertama
            </button>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm border-collapse">
              <thead>
                <tr className="border-b border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-[#111827]/50 text-gray-500 dark:text-gray-400 text-xs font-semibold uppercase tracking-wider">
                  <th className="py-3.5 px-4">Kode & Tipe</th>
                  <th className="py-3.5 px-4">Pertanyaan / Soal</th>
                  <th className="py-3.5 px-4">Kisi-kisi & Mapel</th>
                  <th className="py-3.5 px-4 text-center">Tingkat & Poin</th>
                  <th className="py-3.5 px-4 text-center">Status</th>
                  <th className="py-3.5 px-4 text-right">Aksi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                {dataList.map((item) => (
                  <tr
                    key={item.id}
                    className="hover:bg-emerald-50/30 dark:hover:bg-emerald-950/10 transition-colors duration-150"
                  >
                    <td className="py-3.5 px-4 align-top">
                      <div className="font-mono text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                        {item.kode_soal || 'SOAL-SYS'}
                      </div>
                      {getTipeBadge(item.tipe_soal)}
                    </td>

                    <td className="py-3.5 px-4 align-top max-w-md">
                      <p className="text-gray-900 dark:text-gray-100 font-medium line-clamp-2 leading-relaxed">
                        {item.pertanyaan}
                      </p>
                      {item.indikator && (
                        <p className="text-[11px] text-gray-400 dark:text-gray-500 mt-1 truncate">
                          Indikator: {item.indikator}
                        </p>
                      )}
                    </td>

                    <td className="py-3.5 px-4 align-top">
                      <div className="font-medium text-gray-800 dark:text-gray-200">
                        {item.kisi_kisi?.judul_kisi || 'Umum'}
                      </div>
                      <div className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        {item.kisi_kisi?.mata_pelajaran || item.subject?.name || '-'}
                      </div>
                    </td>

                    <td className="py-3.5 px-4 text-center align-top">
                      <div className="space-y-1">
                        <div>{getKesulitanBadge(item.tingkat_kesulitan)}</div>
                        <span className="text-xs font-semibold text-emerald-700 dark:text-emerald-400 block">
                          {item.poin} Poin
                        </span>
                      </div>
                    </td>

                    <td className="py-3.5 px-4 text-center align-top">
                      <button
                        onClick={() => handleToggleStatus(item)}
                        className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold transition-all ${
                          item.status
                            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'
                            : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'
                        }`}
                      >
                        <span className={`w-1.5 h-1.5 rounded-full ${item.status ? 'bg-emerald-500' : 'bg-gray-400'}`} />
                        {item.status ? 'Aktif' : 'Non-Aktif'}
                      </button>
                    </td>

                    <td className="py-3.5 px-4 text-right align-top">
                      <div className="flex items-center justify-end gap-1">
                        <button
                          onClick={() => {
                            setViewingItem(item)
                            setShowDetailModal(true)
                          }}
                          className="p-1.5 rounded-lg text-gray-500 hover:text-emerald-700 hover:bg-emerald-50 dark:hover:bg-emerald-950/50 transition"
                          title="Preview Detail"
                        >
                          <Eye className="w-4 h-4" />
                        </button>
                        <button
                          onClick={() => handleOpenModal(item)}
                          className="p-1.5 rounded-lg text-gray-500 hover:text-blue-700 hover:bg-blue-50 dark:hover:bg-blue-950/50 transition"
                          title="Edit"
                        >
                          <Edit3 className="w-4 h-4" />
                        </button>
                        <button
                          onClick={() => handleDuplicate(item.id)}
                          className="p-1.5 rounded-lg text-gray-500 hover:text-purple-700 hover:bg-purple-50 dark:hover:bg-purple-950/50 transition"
                          title="Duplikasi"
                        >
                          <Copy className="w-4 h-4" />
                        </button>
                        <button
                          onClick={() => handleDelete(item.id)}
                          className="p-1.5 rounded-lg text-gray-500 hover:text-rose-700 hover:bg-rose-50 dark:hover:bg-rose-950/50 transition"
                          title="Hapus"
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

        {/* Pagination Footer */}
        {!loading && pagination.lastPage > 1 && (
          <div className="px-4 py-3 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between text-xs text-gray-500">
            <span>
              Halaman {pagination.currentPage} dari {pagination.lastPage} ({pagination.total} Soal)
            </span>
            <div className="flex gap-1">
              <button
                disabled={pagination.currentPage <= 1}
                onClick={() => fetchData(pagination.currentPage - 1)}
                className="px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 disabled:opacity-40 hover:bg-gray-50 dark:hover:bg-gray-800"
              >
                Sebelumnya
              </button>
              <button
                disabled={pagination.currentPage >= pagination.lastPage}
                onClick={() => fetchData(pagination.currentPage + 1)}
                className="px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 disabled:opacity-40 hover:bg-gray-50 dark:hover:bg-gray-800"
              >
                Selanjutnya
              </button>
            </div>
          </div>
        )}
      </div>

      {/* CRUD Form Modal */}
      {showModal && (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto">
          <div className="bg-white dark:bg-[#1B2433] rounded-[18px] max-w-3xl w-full max-h-[90vh] overflow-y-auto shadow-2xl border border-gray-100 dark:border-gray-800 flex flex-col">
            <div className="px-6 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between sticky top-0 bg-white dark:bg-[#1B2433] z-10">
              <h2 className="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                {editingItem ? <Edit3 className="w-5 h-5 text-[#0E5C44]" /> : <Plus className="w-5 h-5 text-[#0E5C44]" />}
                {editingItem ? 'Edit Butir Soal' : 'Tambah Butir Soal Baru'}
              </h2>
              <button
                onClick={handleCloseModal}
                className="p-1.5 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-400"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleSubmit} className="p-6 space-y-5 flex-1">
              {/* Select Kisi-kisi & Kode */}
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                    Kisi-kisi Ujian <span className="text-rose-500">*</span>
                  </label>
                  <select
                    value={formData.kisi_kisi_id}
                    onChange={(e) => handleKisiChange(e.target.value)}
                    className="w-full px-3 py-2.5 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-[#111827] focus:ring-2 focus:ring-[#0E5C44]"
                    required
                  >
                    <option value="">-- Pilih Kisi-kisi --</option>
                    {options.kisi_kisi.map((k) => (
                      <option key={k.id} value={k.id}>
                        {k.judul_kisi} ({k.jenis_ujian} - {k.subject_name})
                      </option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                    Kode Soal (Opsional)
                  </label>
                  <input
                    type="text"
                    placeholder="Contoh: PG-001 / SOAL-MATH"
                    value={formData.kode_soal}
                    onChange={(e) => setFormData((prev) => ({ ...prev, kode_soal: e.target.value }))}
                    className="w-full px-3 py-2.5 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-[#111827] focus:ring-2 focus:ring-[#0E5C44]"
                  />
                </div>
              </div>

              {/* Tipe Soal Selection */}
              <div>
                <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-2">
                  Tipe Soal <span className="text-rose-500">*</span>
                </label>
                <div className="grid grid-cols-2 md:grid-cols-4 gap-2">
                  {[
                    { id: 'pg', label: 'Pilihan Ganda', icon: CheckSquare },
                    { id: 'esai', label: 'Essay / Esai', icon: FileText },
                    { id: 'benar_salah', label: 'Benar / Salah', icon: ToggleLeft },
                    { id: 'menjodohkan', label: 'Menjodohkan', icon: GitCommit },
                  ].map((t) => {
                    const IconComp = t.icon
                    const isSelected = formData.tipe_soal === t.id
                    return (
                      <button
                        key={t.id}
                        type="button"
                        onClick={() => handleTypeChange(t.id)}
                        className={`flex items-center gap-2 p-3 rounded-xl border text-left text-xs font-semibold transition-all ${
                          isSelected
                            ? 'border-[#0E5C44] bg-emerald-50 dark:bg-emerald-950/60 text-[#0E5C44] dark:text-emerald-300 ring-2 ring-[#0E5C44]'
                            : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-[#111827] text-gray-700 dark:text-gray-300 hover:bg-gray-50'
                        }`}
                      >
                        <IconComp className="w-4 h-4 shrink-0" />
                        <span>{t.label}</span>
                      </button>
                    )
                  })}
                </div>
              </div>

              {/* Pertanyaan / Teks Soal */}
              <div>
                <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                  Teks Pertanyaan / Soal <span className="text-rose-500">*</span>
                </label>
                <textarea
                  rows={3}
                  placeholder="Tuliskan butir soal atau instruksi pertanyaan di sini..."
                  value={formData.pertanyaan}
                  onChange={(e) => setFormData((prev) => ({ ...prev, pertanyaan: e.target.value }))}
                  className="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-[#111827] focus:ring-2 focus:ring-[#0E5C44]"
                  required
                />
              </div>

              {/* DYNAMIC FORM SECTION BASED ON TIPE_SOAL */}
              {/* 1. PILIHAN GANDA */}
              {formData.tipe_soal === 'pg' && (
                <div className="bg-emerald-50/50 dark:bg-emerald-950/20 p-4 rounded-xl border border-emerald-100 dark:border-emerald-900/50 space-y-3">
                  <h4 className="text-xs font-bold text-[#0E5C44] dark:text-emerald-300 uppercase tracking-wider">
                    Opsi Jawaban & Kunci PG
                  </h4>

                  {['a', 'b', 'c', 'd', 'e'].map((optKey) => {
                    const fieldKey = `opsi_${optKey}`
                    const isCorrect = formData.kunci_jawaban === optKey.toUpperCase()
                    return (
                      <div key={optKey} className="flex items-center gap-2">
                        <label className="flex items-center gap-2 cursor-pointer shrink-0">
                          <input
                            type="radio"
                            name="kunci_jawaban_pg"
                            checked={isCorrect}
                            onChange={() => setFormData((prev) => ({ ...prev, kunci_jawaban: optKey.toUpperCase() }))}
                            className="w-4 h-4 text-[#0E5C44] focus:ring-[#0E5C44]"
                          />
                          <span
                            className={`w-6 h-6 rounded-full font-bold text-xs flex items-center justify-center ${
                              isCorrect
                                ? 'bg-[#0E5C44] text-white'
                                : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300'
                            }`}
                          >
                            {optKey.toUpperCase()}
                          </span>
                        </label>
                        <input
                          type="text"
                          placeholder={`Teks Opsi ${optKey.toUpperCase()}`}
                          value={formData[fieldKey]}
                          onChange={(e) => setFormData((prev) => ({ ...prev, [fieldKey]: e.target.value }))}
                          className="w-full px-3 py-1.5 text-sm rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-[#111827]"
                        />
                      </div>
                    )
                  })}
                  <p className="text-[11px] text-gray-500 italic mt-1">
                    * Pilih radio button untuk menandai kunci jawaban yang benar.
                  </p>
                </div>
              )}

              {/* 2. ESSAY */}
              {formData.tipe_soal === 'esai' && (
                <div className="bg-purple-50/50 dark:bg-purple-950/20 p-4 rounded-xl border border-purple-100 dark:border-purple-900/50 space-y-2">
                  <h4 className="text-xs font-bold text-purple-800 dark:text-purple-300 uppercase tracking-wider">
                    Kunci Jawaban / Pedoman Penskoran Essay
                  </h4>
                  <textarea
                    rows={3}
                    placeholder="Tuliskan kunci acuan, poin penting, atau kata kunci jawaban siswa..."
                    value={formData.kunci_jawaban}
                    onChange={(e) => setFormData((prev) => ({ ...prev, kunci_jawaban: e.target.value }))}
                    className="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-[#111827]"
                  />
                </div>
              )}

              {/* 3. BENAR SALAH */}
              {formData.tipe_soal === 'benar_salah' && (
                <div className="bg-blue-50/50 dark:bg-blue-950/20 p-4 rounded-xl border border-blue-100 dark:border-blue-900/50 space-y-3">
                  <h4 className="text-xs font-bold text-blue-800 dark:text-blue-300 uppercase tracking-wider">
                    Pernyataan Kunci Jawaban
                  </h4>
                  <div className="flex gap-4">
                    {['Benar', 'Salah'].map((val) => (
                      <label
                        key={val}
                        className={`flex-1 flex items-center justify-center gap-2 p-3 rounded-xl border cursor-pointer font-bold text-sm transition-all ${
                          formData.kunci_jawaban === val
                            ? 'bg-blue-600 text-white border-blue-600 shadow-sm'
                            : 'bg-white dark:bg-[#111827] text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-700'
                        }`}
                      >
                        <input
                          type="radio"
                          name="kunci_bs"
                          checked={formData.kunci_jawaban === val}
                          onChange={() => setFormData((prev) => ({ ...prev, kunci_jawaban: val }))}
                          className="hidden"
                        />
                        {val === 'Benar' ? <CheckCircle2 className="w-4 h-4" /> : <XCircle className="w-4 h-4" />}
                        {val}
                      </label>
                    ))}
                  </div>
                </div>
              )}

              {/* 4. MENJODOHKAN */}
              {formData.tipe_soal === 'menjodohkan' && (
                <div className="bg-amber-50/50 dark:bg-amber-950/20 p-4 rounded-xl border border-amber-100 dark:border-amber-900/50 space-y-3">
                  <div className="flex items-center justify-between">
                    <h4 className="text-xs font-bold text-amber-800 dark:text-amber-300 uppercase tracking-wider">
                      Daftar Pasangan Menjodohkan
                    </h4>
                    <button
                      type="button"
                      onClick={handleAddPair}
                      className="inline-flex items-center gap-1 px-2.5 py-1 text-xs rounded-lg bg-amber-600 text-white font-medium hover:bg-amber-700 transition"
                    >
                      <Plus className="w-3.5 h-3.5" /> Tambah Pasangan
                    </button>
                  </div>

                  {matchingPairs.map((pair, idx) => (
                    <div key={idx} className="flex items-center gap-2 bg-white dark:bg-[#111827] p-2 rounded-xl border border-gray-200 dark:border-gray-700">
                      <span className="text-xs font-bold text-amber-700 dark:text-amber-400 w-6 text-center">{idx + 1}.</span>
                      <input
                        type="text"
                        placeholder="Pernyataan / Item Kiri"
                        value={pair.kiri}
                        onChange={(e) => handlePairChange(idx, 'kiri', e.target.value)}
                        className="w-1/2 px-2.5 py-1.5 text-xs rounded-lg border border-gray-200 dark:border-gray-700"
                      />
                      <ArrowRight className="w-4 h-4 text-amber-500 shrink-0" />
                      <input
                        type="text"
                        placeholder="Pasangan / Item Kanan"
                        value={pair.kanan}
                        onChange={(e) => handlePairChange(idx, 'kanan', e.target.value)}
                        className="w-1/2 px-2.5 py-1.5 text-xs rounded-lg border border-gray-200 dark:border-gray-700"
                      />
                      {matchingPairs.length > 1 && (
                        <button
                          type="button"
                          onClick={() => handleRemovePair(idx)}
                          className="p-1 rounded-lg text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/50"
                        >
                          <Trash2 className="w-4 h-4" />
                        </button>
                      )}
                    </div>
                  ))}
                </div>
              )}

              {/* Metadata Fields: Poin, Tingkat Kesulitan, Indikator, Pembahasan */}
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                    Poin / Bobot Soal
                  </label>
                  <input
                    type="number"
                    step="0.1"
                    min="0"
                    max="100"
                    value={formData.poin}
                    onChange={(e) => setFormData((prev) => ({ ...prev, poin: parseFloat(e.target.value) || 0 }))}
                    className="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-[#111827]"
                  />
                </div>

                <div>
                  <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                    Tingkat Kesulitan
                  </label>
                  <select
                    value={formData.tingkat_kesulitan}
                    onChange={(e) => setFormData((prev) => ({ ...prev, tingkat_kesulitan: e.target.value }))}
                    className="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-[#111827]"
                  >
                    <option value="mudah">Mudah</option>
                    <option value="sedang">Sedang</option>
                    <option value="sulit">Sulit</option>
                  </select>
                </div>
              </div>

              <div>
                <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                  Indikator Soal / Kompetensi
                </label>
                <input
                  type="text"
                  placeholder="Contoh: Peserta didik mampu menganalisis Pancasila Sila I"
                  value={formData.indikator}
                  onChange={(e) => setFormData((prev) => ({ ...prev, indikator: e.target.value }))}
                  className="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-[#111827]"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                  Pembahasan / Penjelasan Jawaban
                </label>
                <textarea
                  rows={2}
                  placeholder="Tuliskan pembahasan singkat atau alasan kunci jawaban..."
                  value={formData.pembahasan}
                  onChange={(e) => setFormData((prev) => ({ ...prev, pembahasan: e.target.value }))}
                  className="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-[#111827]"
                />
              </div>

              <div className="flex items-center gap-2 pt-2">
                <input
                  type="checkbox"
                  id="statusToggle"
                  checked={formData.status}
                  onChange={(e) => setFormData((prev) => ({ ...prev, status: e.target.checked }))}
                  className="w-4 h-4 text-[#0E5C44] rounded focus:ring-[#0E5C44]"
                />
                <label htmlFor="statusToggle" className="text-xs font-medium text-gray-700 dark:text-gray-300 cursor-pointer">
                  Aktifkan Butir Soal ini di Bank Soal
                </label>
              </div>

              <div className="pt-4 border-t border-gray-100 dark:border-gray-800 flex justify-end gap-2 sticky bottom-0 bg-white dark:bg-[#1B2433] py-2 z-10">
                <button
                  type="button"
                  onClick={handleCloseModal}
                  className="px-4 py-2 rounded-xl border border-gray-200 dark:border-gray-700 text-xs font-semibold text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  className="px-5 py-2 rounded-xl bg-[#0E5C44] text-white text-xs font-semibold hover:bg-emerald-700 shadow-md transition-all"
                >
                  {editingItem ? 'Simpan Perubahan' : 'Simpan Soal'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Detail / Preview Modal */}
      {showDetailModal && viewingItem && (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-white dark:bg-[#1B2433] rounded-[18px] max-w-2xl w-full max-h-[85vh] overflow-y-auto shadow-2xl border border-gray-100 dark:border-gray-800 p-6 space-y-5">
            <div className="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3">
              <div>
                <span className="text-xs font-mono text-gray-400">{viewingItem.kode_soal || 'SOAL-SYS'}</span>
                <h3 className="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2 mt-0.5">
                  Preview Butir Soal
                </h3>
              </div>
              <button
                onClick={() => setShowDetailModal(false)}
                className="p-1.5 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-400"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <div className="space-y-4 text-sm">
              <div className="flex items-center gap-2">
                {getTipeBadge(viewingItem.tipe_soal)}
                {getKesulitanBadge(viewingItem.tingkat_kesulitan)}
                <span className="text-xs font-bold text-emerald-700 dark:text-emerald-400">
                  {viewingItem.poin} Poin
                </span>
              </div>

              <div className="bg-gray-50 dark:bg-[#111827] p-4 rounded-xl border border-gray-100 dark:border-gray-800">
                <p className="font-semibold text-gray-900 dark:text-gray-100 leading-relaxed">
                  {viewingItem.pertanyaan}
                </p>
              </div>

              {/* RENDER OPTIONS BY TYPE */}
              {viewingItem.tipe_soal === 'pg' && (
                <div className="space-y-2">
                  <h4 className="text-xs font-bold text-gray-500 uppercase tracking-wider">Opsi Pilihan:</h4>
                  {['a', 'b', 'c', 'd', 'e'].map((optKey) => {
                    const text = viewingItem[`opsi_${optKey}`]
                    if (!text) return null
                    const isKey = viewingItem.kunci_jawaban === optKey.toUpperCase()
                    return (
                      <div
                        key={optKey}
                        className={`flex items-center gap-3 p-3 rounded-xl border text-xs ${
                          isKey
                            ? 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-300 dark:border-emerald-700 text-emerald-900 dark:text-emerald-200 font-semibold'
                            : 'bg-white dark:bg-[#111827] border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300'
                        }`}
                      >
                        <span
                          className={`w-6 h-6 rounded-full font-bold text-xs flex items-center justify-center ${
                            isKey ? 'bg-[#0E5C44] text-white' : 'bg-gray-200 dark:bg-gray-700'
                          }`}
                        >
                          {optKey.toUpperCase()}
                        </span>
                        <span>{text}</span>
                        {isKey && <CheckCircle2 className="w-4 h-4 ml-auto text-emerald-600" />}
                      </div>
                    )
                  })}
                </div>
              )}

              {viewingItem.tipe_soal === 'benar_salah' && (
                <div className="p-3 bg-blue-50 dark:bg-blue-950/30 rounded-xl border border-blue-200 dark:border-blue-800 flex items-center justify-between text-xs">
                  <span className="font-semibold text-blue-900 dark:text-blue-200">Kunci Jawaban Benar/Salah:</span>
                  <span className="px-3 py-1 bg-blue-600 text-white rounded-lg font-bold">{viewingItem.kunci_jawaban}</span>
                </div>
              )}

              {viewingItem.tipe_soal === 'esai' && (
                <div className="p-3 bg-purple-50 dark:bg-purple-950/30 rounded-xl border border-purple-200 dark:border-purple-800 space-y-1 text-xs">
                  <span className="font-semibold text-purple-900 dark:text-purple-200 block">Kunci / Rubrik Jawaban:</span>
                  <p className="text-purple-950 dark:text-purple-300 leading-relaxed">
                    {viewingItem.kunci_jawaban || 'Penilaian manual oleh guru.'}
                  </p>
                </div>
              )}

              {viewingItem.tipe_soal === 'menjodohkan' && (
                <div className="space-y-2">
                  <h4 className="text-xs font-bold text-amber-700 dark:text-amber-400 uppercase tracking-wider">Pasangan Menjodohkan:</h4>
                  <div className="space-y-1.5">
                    {(viewingItem.pasangan_menjodohkan || []).map((p, idx) => (
                      <div key={idx} className="flex items-center gap-2 p-2 bg-amber-50/60 dark:bg-amber-950/30 rounded-xl border border-amber-200 dark:border-amber-900 text-xs">
                        <span className="font-medium text-gray-800 dark:text-gray-200 w-1/2">{p.kiri}</span>
                        <ArrowRight className="w-4 h-4 text-amber-600 shrink-0" />
                        <span className="font-medium text-emerald-700 dark:text-emerald-400 w-1/2">{p.kanan}</span>
                      </div>
                    ))}
                  </div>
                </div>
              )}

              {viewingItem.pembahasan && (
                <div className="bg-amber-50/40 dark:bg-amber-950/20 p-3.5 rounded-xl border border-amber-100 dark:border-amber-900/40 space-y-1 text-xs">
                  <span className="font-bold text-amber-800 dark:text-amber-300 block">Pembahasan:</span>
                  <p className="text-gray-700 dark:text-gray-300">{viewingItem.pembahasan}</p>
                </div>
              )}
            </div>

            <div className="pt-4 border-t border-gray-100 dark:border-gray-800 flex justify-end">
              <button
                onClick={() => setShowDetailModal(false)}
                className="px-4 py-2 rounded-xl bg-gray-100 dark:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-200"
              >
                Tutup Preview
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
