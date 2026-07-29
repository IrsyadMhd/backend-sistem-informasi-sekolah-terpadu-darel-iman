import React, { useState, useEffect } from 'react'
import {
  Award,
  BookOpen,
  Calculator,
  Sliders,
  Sparkles,
  Plus,
  Search,
  Filter,
  Edit3,
  Trash2,
  CheckCircle2,
  XCircle,
  Eye,
  RefreshCw,
  X,
  Layers,
  Users,
  FileSpreadsheet,
  Settings,
  ChevronDown,
  Info,
  Check,
} from 'lucide-react'
import { lmsPenilaianService } from '../services/lmsPenilaianService'

export default function LmsPenilaianPage() {
  const [dataList, setDataList] = useState([])
  const [loading, setLoading] = useState(true)
  const [pagination, setPagination] = useState({ currentPage: 1, lastPage: 1, total: 0 })
  const [stats, setStats] = useState({
    total_siswa: 0,
    total_lulus: 0,
    total_remedial: 0,
    persentase_kelulusan: 0,
    rata_nilai_akhir: 0,
    rata_assignment: 0,
    rata_cbt: 0,
    grade_distribution: { A: 0, B: 0, C: 0, D: 0 },
  })
  const [options, setOptions] = useState({
    kelas: [],
    subjects: [],
    semesters: [],
    default_formula: {
      bobot_tugas: 20.0,
      bobot_uh: 25.0,
      bobot_uts: 25.0,
      bobot_uas: 30.0,
      nilai_kkm: 75.0,
    },
  })

  const [filters, setFilters] = useState({
    search: '',
    kelas_id: '',
    subject_id: '',
    semester_id: '',
    is_passed: '',
  })

  // Configurable Weights Engine State
  const [weights, setWeights] = useState({
    bobot_tugas: 20.0,
    bobot_uh: 25.0,
    bobot_uts: 25.0,
    bobot_uas: 30.0,
    nilai_kkm: 75.0,
  })
  const [showConfigPanel, setShowConfigPanel] = useState(false)
  const [calculating, setCalculating] = useState(false)

  // Modals
  const [showModal, setShowModal] = useState(false)
  const [showDetailModal, setShowDetailModal] = useState(false)
  const [editingItem, setEditingItem] = useState(null)
  const [viewingItem, setViewingItem] = useState(null)
  const [toast, setToast] = useState({ show: false, message: '', type: 'success' })

  // Form Data
  const [formData, setFormData] = useState({
    student_id: '',
    subject_id: '',
    semester_id: '',
    kelas_id: '',
    score_assignment: 80,
    score_quiz: 85,
    score_midterm: 85,
    score_final: 90,
    bobot_tugas: 20,
    bobot_uh: 25,
    bobot_uts: 25,
    bobot_uas: 30,
    nilai_kkm: 75,
    notes: '',
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
      const params = { page, per_page: 10, ...filters }
      const response = await lmsPenilaianService.getDaftar(params)
      if (response && response.data) {
        setDataList(response.data)
        setPagination({
          currentPage: response.meta?.current_page || 1,
          lastPage: response.meta?.last_page || 1,
          total: response.meta?.total || response.data.length,
        })
      }
    } catch (error) {
      console.error('Error loading Penilaian data:', error)
      showNotification('Gagal memuat data Penilaian', 'error')
    } finally {
      setLoading(false)
    }
  }

  const fetchStats = async () => {
    try {
      const response = await lmsPenilaianService.getStats(filters)
      if (response && response.data) setStats(response.data)
    } catch (error) {
      console.error('Error loading stats:', error)
    }
  }

  const fetchOptions = async () => {
    try {
      const response = await lmsPenilaianService.getOptions()
      if (response && response.data) {
        setOptions(response.data)
        if (response.data.default_formula) {
          setWeights(response.data.default_formula)
        }
      }
    } catch (error) {
      console.error('Error loading options:', error)
    }
  }

  const handleRunAutoCalculation = async () => {
    if (!filters.kelas_id || !filters.subject_id || !filters.semester_id) {
      showNotification('Silakan pilih Kelas, Mata Pelajaran, dan Semester terlebih dahulu pada filter.', 'error')
      return
    }

    setCalculating(true)
    try {
      const payload = {
        kelas_id: filters.kelas_id,
        subject_id: filters.subject_id,
        semester_id: filters.semester_id,
        ...weights,
      }
      const response = await lmsPenilaianService.calculateAuto(payload)
      if (response && response.data) {
        showNotification(response.message || 'Auto-kalkulasi nilai CBT + Penugasan berhasil!')
        fetchData(1)
        fetchStats()
      }
    } catch (error) {
      console.error('Auto calculation error:', error)
      const errorMsg = error.response?.data?.message || 'Gagal melakukan kalkulasi nilai otomatis.'
      showNotification(errorMsg, 'error')
    } finally {
      setCalculating(false)
    }
  }

  const handleOpenModal = (item = null) => {
    if (item) {
      setEditingItem(item)
      setFormData({
        student_id: item.student_id || '',
        subject_id: item.subject_id || '',
        semester_id: item.semester_id || '',
        kelas_id: item.kelas_id || '',
        score_assignment: item.score_assignment || 0,
        score_quiz: item.score_quiz || 0,
        score_midterm: item.score_midterm || 0,
        score_final: item.score_final || 0,
        bobot_tugas: item.weights_config?.bobot_tugas || 20,
        bobot_uh: item.weights_config?.bobot_uh || 25,
        bobot_uts: item.weights_config?.bobot_uts || 25,
        bobot_uas: item.weights_config?.bobot_uas || 30,
        nilai_kkm: item.weights_config?.nilai_kkm || 75,
        notes: item.notes || '',
      })
    } else {
      setEditingItem(null)
      setFormData({
        student_id: '',
        subject_id: options.subjects.length > 0 ? options.subjects[0].id : '',
        semester_id: options.semesters.length > 0 ? options.semesters[0].id : '',
        kelas_id: options.kelas.length > 0 ? options.kelas[0].id : '',
        score_assignment: 80,
        score_quiz: 85,
        score_midterm: 85,
        score_final: 90,
        bobot_tugas: weights.bobot_tugas,
        bobot_uh: weights.bobot_uh,
        bobot_uts: weights.bobot_uts,
        bobot_uas: weights.bobot_uas,
        nilai_kkm: weights.nilai_kkm,
        notes: '',
      })
    }
    setShowModal(true)
  }

  const handleSubmit = async (e) => {
    e.preventDefault()

    try {
      if (editingItem) {
        await lmsPenilaianService.update(editingItem.id, formData)
        showNotification('Manual override nilai siswa berhasil diperbarui!')
      } else {
        await lmsPenilaianService.create(formData)
        showNotification('Rekap penilaian siswa baru berhasil disimpan!')
      }
      setShowModal(false)
      fetchData(pagination.currentPage)
      fetchStats()
    } catch (error) {
      console.error('Error saving grade:', error)
      const errorMsg = error.response?.data?.message || 'Gagal menyimpan nilai.'
      showNotification(errorMsg, 'error')
    }
  }

  const handleDelete = async (id) => {
    if (!window.confirm('Apakah Anda yakin ingin menghapus rekap nilai ini?')) return
    try {
      await lmsPenilaianService.delete(id)
      showNotification('Rekap nilai berhasil dihapus!')
      fetchData(pagination.currentPage)
      fetchStats()
    } catch (error) {
      console.error('Error deleting item:', error)
      showNotification('Gagal menghapus nilai.', 'error')
    }
  }

  const totalWeightSum = weights.bobot_tugas + weights.bobot_uh + weights.bobot_uts + weights.bobot_uas

  const getGradeBadge = (letter) => {
    switch (letter) {
      case 'A':
        return <span className="px-2.5 py-1 text-xs font-black rounded-lg bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">Predikat A</span>
      case 'B':
        return <span className="px-2.5 py-1 text-xs font-bold rounded-lg bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300">Predikat B</span>
      case 'C':
        return <span className="px-2.5 py-1 text-xs font-bold rounded-lg bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">Predikat C</span>
      case 'D':
      case 'E':
        return <span className="px-2.5 py-1 text-xs font-bold rounded-lg bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300">Predikat D</span>
      default:
        return <span className="px-2.5 py-1 text-xs font-bold rounded-lg bg-gray-100 text-gray-700">{letter}</span>
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
          <Calculator className="w-72 h-72 text-white" />
        </div>
        <div className="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div>
            <div className="flex items-center gap-2 text-emerald-200 text-xs font-semibold uppercase tracking-wider mb-1">
              <Sparkles className="w-4 h-4" /> Pengolahan Nilai & Rekap Rapor Digital
            </div>
            <h1 className="text-2xl md:text-3xl font-bold tracking-tight">Penilaian & Rumus Rapor</h1>
            <p className="text-emerald-100 text-sm mt-1 max-w-xl">
              Engine agregasi nilai otomatis mengaitkan skor CBT Online & Penugasan LMS dengan bobot rumus yang dapat dikonfigurasi secara dinamis.
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            <button
              onClick={() => setShowConfigPanel(!showConfigPanel)}
              className="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-900/60 hover:bg-emerald-900 border border-emerald-400 text-white font-semibold text-xs transition"
            >
              <Sliders className="w-4 h-4" /> Konfigurasi Bobot & Rumus
            </button>
            <button
              onClick={() => handleOpenModal()}
              className="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-white text-[#0E5C44] font-semibold text-xs shadow-md hover:bg-emerald-50 transition hover:scale-[1.02] active:scale-[0.98]"
            >
              <Plus className="w-4 h-4" /> Input Nilai Manual
            </button>
          </div>
        </div>
      </div>

      {/* Configurable Formula & Weights Control Panel */}
      {showConfigPanel && (
        <div className="bg-white dark:bg-[#1B2433] p-5 rounded-[18px] border border-emerald-200 dark:border-emerald-800 shadow-md space-y-4 animate-fadeIn">
          <div className="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3">
            <h3 className="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2">
              <Settings className="w-4 h-4 text-[#0E5C44]" /> Pengaturan Bobot Rumus Penilaian (Nilai Akhir)
            </h3>
            <span
              className={`px-3 py-1 rounded-full text-xs font-bold ${
                totalWeightSum === 100
                  ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'
                  : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300'
              }`}
            >
              Total Bobot: {totalWeightSum}% {totalWeightSum === 100 ? '✓ (Ideal)' : '(Disarankan 100%)'}
            </span>
          </div>

          <div className="grid grid-cols-2 md:grid-cols-5 gap-3">
            <div>
              <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Bobot Penugasan (%)</label>
              <input
                type="number"
                min="0"
                max="100"
                value={weights.bobot_tugas}
                onChange={(e) => setWeights((prev) => ({ ...prev, bobot_tugas: parseFloat(e.target.value) || 0 }))}
                className="w-full px-3 py-2 text-xs rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-[#111827]"
              />
            </div>
            <div>
              <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Bobot CBT UH (%)</label>
              <input
                type="number"
                min="0"
                max="100"
                value={weights.bobot_uh}
                onChange={(e) => setWeights((prev) => ({ ...prev, bobot_uh: parseFloat(e.target.value) || 0 }))}
                className="w-full px-3 py-2 text-xs rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-[#111827]"
              />
            </div>
            <div>
              <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Bobot CBT UTS (%)</label>
              <input
                type="number"
                min="0"
                max="100"
                value={weights.bobot_uts}
                onChange={(e) => setWeights((prev) => ({ ...prev, bobot_uts: parseFloat(e.target.value) || 0 }))}
                className="w-full px-3 py-2 text-xs rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-[#111827]"
              />
            </div>
            <div>
              <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Bobot CBT UAS (%)</label>
              <input
                type="number"
                min="0"
                max="100"
                value={weights.bobot_uas}
                onChange={(e) => setWeights((prev) => ({ ...prev, bobot_uas: parseFloat(e.target.value) || 0 }))}
                className="w-full px-3 py-2 text-xs rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-[#111827]"
              />
            </div>
            <div>
              <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Batas KKM Lulus</label>
              <input
                type="number"
                step="0.5"
                value={weights.nilai_kkm}
                onChange={(e) => setWeights((prev) => ({ ...prev, nilai_kkm: parseFloat(e.target.value) || 75.0 }))}
                className="w-full px-3 py-2 text-xs rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-[#111827]"
              />
            </div>
          </div>

          <div className="flex flex-col md:flex-row items-center justify-between gap-3 pt-2">
            <p className="text-xs text-gray-500 font-mono italic">
              Rumus: N_Akhir = ({weights.bobot_tugas}% * Tugas) + ({weights.bobot_uh}% * CBT UH) + ({weights.bobot_uts}% * CBT UTS) + ({weights.bobot_uas}% * CBT UAS)
            </p>
            <button
              onClick={handleRunAutoCalculation}
              disabled={calculating}
              className="w-full md:w-auto px-4 py-2 rounded-xl bg-[#0E5C44] text-white text-xs font-bold hover:bg-emerald-700 flex items-center justify-center gap-2 shadow transition"
            >
              {calculating ? <RefreshCw className="w-4 h-4 animate-spin" /> : <Calculator className="w-4 h-4" />}
              Jalankan Auto-Kalkulasi CBT + Penugasan
            </button>
          </div>
        </div>
      )}

      {/* KPI Stats Cards */}
      <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
        <div className="bg-white dark:bg-[#1B2433] p-4 rounded-[18px] border border-gray-100 dark:border-gray-800 shadow-sm hover:shadow-md transition-shadow">
          <div className="flex items-center justify-between">
            <span className="text-xs text-gray-500 dark:text-gray-400 font-medium">Rata-rata Nilai</span>
            <div className="p-2 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 text-[#0E5C44] dark:text-emerald-400">
              <Award className="w-4 h-4" />
            </div>
          </div>
          <div className="text-xl md:text-2xl font-bold mt-2 text-emerald-800 dark:text-emerald-300">{stats.rata_nilai_akhir}</div>
          <span className="text-[11px] text-gray-500 mt-1 block">Skor Akhir Terkalkulasi</span>
        </div>

        <div className="bg-white dark:bg-[#1B2433] p-4 rounded-[18px] border border-gray-100 dark:border-gray-800 shadow-sm hover:shadow-md transition-shadow">
          <div className="flex items-center justify-between">
            <span className="text-xs text-gray-500 dark:text-gray-400 font-medium">Tingkat Lulus KKM</span>
            <div className="p-2 rounded-xl bg-blue-50 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400">
              <CheckCircle2 className="w-4 h-4" />
            </div>
          </div>
          <div className="text-xl md:text-2xl font-bold mt-2 text-blue-700 dark:text-blue-400">{stats.persentase_kelulusan}%</div>
          <span className="text-[11px] text-gray-400 mt-1 block">{stats.total_lulus} Siswa Tuntas</span>
        </div>

        <div className="bg-white dark:bg-[#1B2433] p-4 rounded-[18px] border border-gray-100 dark:border-gray-800 shadow-sm hover:shadow-md transition-shadow">
          <div className="flex items-center justify-between">
            <span className="text-xs text-gray-500 dark:text-gray-400 font-medium">Perlu Remedial</span>
            <div className="p-2 rounded-xl bg-rose-50 dark:bg-rose-950/50 text-rose-600 dark:text-rose-400">
              <XCircle className="w-4 h-4" />
            </div>
          </div>
          <div className="text-xl md:text-2xl font-bold mt-2 text-rose-700 dark:text-rose-400">{stats.total_remedial}</div>
          <span className="text-[11px] text-gray-400 mt-1 block">Di Bawah KKM</span>
        </div>

        <div className="bg-white dark:bg-[#1B2433] p-4 rounded-[18px] border border-gray-100 dark:border-gray-800 shadow-sm hover:shadow-md transition-shadow">
          <div className="flex items-center justify-between">
            <span className="text-xs text-gray-500 dark:text-gray-400 font-medium">Rata-rata CBT</span>
            <div className="p-2 rounded-xl bg-purple-50 dark:bg-purple-950/50 text-purple-600 dark:text-purple-400">
              <Layers className="w-4 h-4" />
            </div>
          </div>
          <div className="text-xl md:text-2xl font-bold mt-2 text-purple-700 dark:text-purple-400">{stats.rata_cbt}</div>
          <span className="text-[11px] text-gray-400 mt-1 block">Ujian CBT Online</span>
        </div>

        <div className="bg-white dark:bg-[#1B2433] p-4 rounded-[18px] border border-gray-100 dark:border-gray-800 shadow-sm hover:shadow-md transition-shadow col-span-2 md:col-span-1">
          <div className="flex items-center justify-between">
            <span className="text-xs text-gray-500 dark:text-gray-400 font-medium">Rata-rata Penugasan</span>
            <div className="p-2 rounded-xl bg-teal-50 dark:bg-teal-950/50 text-teal-600 dark:text-teal-400">
              <FileSpreadsheet className="w-4 h-4" />
            </div>
          </div>
          <div className="text-xl md:text-2xl font-bold mt-2 text-teal-700 dark:text-teal-400">{stats.rata_assignment}</div>
          <span className="text-[11px] text-gray-400 mt-1 block">Tugas LMS</span>
        </div>
      </div>

      {/* Filter and Search Bar */}
      <div className="bg-white dark:bg-[#1B2433] p-4 rounded-[18px] border border-gray-100 dark:border-gray-800 shadow-sm flex flex-col md:flex-row gap-3 items-center justify-between">
        <div className="relative w-full md:w-80">
          <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
          <input
            type="text"
            placeholder="Cari siswa, NIS, mapel..."
            value={filters.search}
            onChange={(e) => setFilters((prev) => ({ ...prev, search: e.target.value }))}
            className="w-full pl-9 pr-4 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-[#111827] focus:outline-none focus:ring-2 focus:ring-[#0E5C44]"
          />
        </div>

        <div className="flex flex-wrap gap-2 w-full md:w-auto">
          <select
            value={filters.kelas_id}
            onChange={(e) => setFilters((prev) => ({ ...prev, kelas_id: e.target.value }))}
            className="px-3 py-2 text-xs rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-[#111827] text-gray-700 dark:text-gray-300 focus:ring-2 focus:ring-[#0E5C44]"
          >
            <option value="">Semua Kelas</option>
            {options.kelas.map((k) => (
              <option key={k.id} value={k.id}>
                {k.nama_kelas}
              </option>
            ))}
          </select>

          <select
            value={filters.subject_id}
            onChange={(e) => setFilters((prev) => ({ ...prev, subject_id: e.target.value }))}
            className="px-3 py-2 text-xs rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-[#111827] text-gray-700 dark:text-gray-300 focus:ring-2 focus:ring-[#0E5C44]"
          >
            <option value="">Semua Mata Pelajaran</option>
            {options.subjects.map((s) => (
              <option key={s.id} value={s.id}>
                {s.name}
              </option>
            ))}
          </select>

          <select
            value={filters.semester_id}
            onChange={(e) => setFilters((prev) => ({ ...prev, semester_id: e.target.value }))}
            className="px-3 py-2 text-xs rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-[#111827] text-gray-700 dark:text-gray-300 focus:ring-2 focus:ring-[#0E5C44]"
          >
            <option value="">Semua Semester</option>
            {options.semesters.map((s) => (
              <option key={s.id} value={s.id}>
                {s.nama_semester}
              </option>
            ))}
          </select>

          <button
            onClick={() => {
              setFilters({ search: '', kelas_id: '', subject_id: '', semester_id: '', is_passed: '' })
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
            <p className="text-sm text-gray-500 dark:text-gray-400">Memuat rekap penilaian...</p>
          </div>
        ) : dataList.length === 0 ? (
          <div className="p-12 text-center space-y-3">
            <Award className="w-12 h-12 text-gray-300 dark:text-gray-600 mx-auto" />
            <h3 className="text-base font-semibold text-gray-800 dark:text-gray-200">Belum Ada Data Penilaian</h3>
            <p className="text-xs text-gray-500 dark:text-gray-400 max-w-sm mx-auto">
              Pilih filter Kelas & Mata Pelajaran lalu klik tombol <b>Konfigurasi Bobot & Rumus</b> untuk auto-kalkulasi dari CBT & Penugasan.
            </p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm border-collapse">
              <thead>
                <tr className="border-b border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-[#111827]/50 text-gray-500 dark:text-gray-400 text-xs font-semibold uppercase tracking-wider">
                  <th className="py-3.5 px-4">Nama Siswa & NIS</th>
                  <th className="py-3.5 px-4">Mapel & Kelas</th>
                  <th className="py-3.5 px-4 text-center">N. Tugas</th>
                  <th className="py-3.5 px-4 text-center">CBT UH</th>
                  <th className="py-3.5 px-4 text-center">CBT UTS</th>
                  <th className="py-3.5 px-4 text-center">CBT UAS</th>
                  <th className="py-3.5 px-4 text-center">Nilai Akhir</th>
                  <th className="py-3.5 px-4 text-center">Grade & Status</th>
                  <th className="py-3.5 px-4 text-right">Aksi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                {dataList.map((item) => (
                  <tr key={item.id} className="hover:bg-emerald-50/30 dark:hover:bg-emerald-950/10 transition-colors duration-150">
                    <td className="py-3.5 px-4 align-top">
                      <div className="font-bold text-gray-900 dark:text-white mb-0.5">{item.student?.full_name || 'Siswa'}</div>
                      <div className="text-xs font-mono text-gray-400">NIS: {item.student?.nis || '-'}</div>
                    </td>

                    <td className="py-3.5 px-4 align-top">
                      <div className="font-semibold text-gray-800 dark:text-gray-200">{item.subject?.name || '-'}</div>
                      <div className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{item.kelas?.nama_kelas || '-'}</div>
                    </td>

                    <td className="py-3.5 px-4 text-center font-medium text-gray-700 dark:text-gray-300">{item.score_assignment}</td>
                    <td className="py-3.5 px-4 text-center font-medium text-gray-700 dark:text-gray-300">{item.score_quiz}</td>
                    <td className="py-3.5 px-4 text-center font-medium text-gray-700 dark:text-gray-300">{item.score_midterm}</td>
                    <td className="py-3.5 px-4 text-center font-medium text-gray-700 dark:text-gray-300">{item.score_final}</td>

                    <td className="py-3.5 px-4 text-center font-black text-base text-[#0E5C44] dark:text-emerald-400">
                      {item.final_score}
                    </td>

                    <td className="py-3.5 px-4 text-center align-top space-y-1">
                      <div>{getGradeBadge(item.grade_letter)}</div>
                      <span
                        className={`inline-block px-2 py-0.5 rounded text-[10px] font-bold ${
                          item.is_passed
                            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'
                            : 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300'
                        }`}
                      >
                        {item.is_passed ? 'TUNTAS KKM' : 'REMEDIAL'}
                      </span>
                    </td>

                    <td className="py-3.5 px-4 text-right align-top">
                      <div className="flex items-center justify-end gap-1">
                        <button
                          onClick={() => {
                            setViewingItem(item)
                            setShowDetailModal(true)
                          }}
                          className="p-1.5 rounded-lg text-gray-500 hover:text-emerald-700 hover:bg-emerald-50 dark:hover:bg-emerald-950/50 transition"
                          title="Detail Rincian Formula"
                        >
                          <Eye className="w-4 h-4" />
                        </button>
                        <button
                          onClick={() => handleOpenModal(item)}
                          className="p-1.5 rounded-lg text-gray-500 hover:text-blue-700 hover:bg-blue-50 dark:hover:bg-blue-950/50 transition"
                          title="Edit Override Manual"
                        >
                          <Edit3 className="w-4 h-4" />
                        </button>
                        <button
                          onClick={() => handleDelete(item.id)}
                          className="p-1.5 rounded-lg text-gray-500 hover:text-rose-700 hover:bg-rose-50 dark:hover:bg-rose-950/50 transition"
                          title="Hapus Nilai"
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
              Halaman {pagination.currentPage} dari {pagination.lastPage} ({pagination.total} Rekap)
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

      {/* CRUD Form Modal (Override Manual) */}
      {showModal && (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto">
          <div className="bg-white dark:bg-[#1B2433] rounded-[18px] max-w-xl w-full shadow-2xl border border-gray-100 dark:border-gray-800 flex flex-col">
            <div className="px-6 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between sticky top-0 bg-white dark:bg-[#1B2433] z-10">
              <h2 className="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                <Edit3 className="w-5 h-5 text-[#0E5C44]" /> Edit / Override Manual Nilai Siswa
              </h2>
              <button onClick={() => setShowModal(false)} className="p-1.5 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-400">
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleSubmit} className="p-6 space-y-4">
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Nilai Penugasan LMS</label>
                  <input
                    type="number"
                    step="0.1"
                    min="0"
                    max="100"
                    value={formData.score_assignment}
                    onChange={(e) => setFormData((prev) => ({ ...prev, score_assignment: parseFloat(e.target.value) || 0 }))}
                    className="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-[#111827]"
                  />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Nilai CBT UH</label>
                  <input
                    type="number"
                    step="0.1"
                    min="0"
                    max="100"
                    value={formData.score_quiz}
                    onChange={(e) => setFormData((prev) => ({ ...prev, score_quiz: parseFloat(e.target.value) || 0 }))}
                    className="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-[#111827]"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Nilai CBT UTS / PTS</label>
                  <input
                    type="number"
                    step="0.1"
                    min="0"
                    max="100"
                    value={formData.score_midterm}
                    onChange={(e) => setFormData((prev) => ({ ...prev, score_midterm: parseFloat(e.target.value) || 0 }))}
                    className="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-[#111827]"
                  />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Nilai CBT UAS / PAS</label>
                  <input
                    type="number"
                    step="0.1"
                    min="0"
                    max="100"
                    value={formData.score_final}
                    onChange={(e) => setFormData((prev) => ({ ...prev, score_final: parseFloat(e.target.value) || 0 }))}
                    className="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-[#111827]"
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Catatan Guru / Wali Kelas</label>
                <textarea
                  rows={2}
                  value={formData.notes}
                  onChange={(e) => setFormData((prev) => ({ ...prev, notes: e.target.value }))}
                  className="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-[#111827]"
                  placeholder="Catatan kemajuan akademik siswa..."
                />
              </div>

              <div className="pt-4 border-t border-gray-100 dark:border-gray-800 flex justify-end gap-2">
                <button
                  type="button"
                  onClick={() => setShowModal(false)}
                  className="px-4 py-2 rounded-xl border border-gray-200 dark:border-gray-700 text-xs font-semibold text-gray-600 dark:text-gray-300"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  className="px-5 py-2 rounded-xl bg-[#0E5C44] text-white text-xs font-semibold hover:bg-emerald-700 shadow transition"
                >
                  Simpan Perubahan
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Grade Breakdown Detail Drawer / Modal */}
      {showDetailModal && viewingItem && (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-white dark:bg-[#1B2433] rounded-[18px] max-w-xl w-full shadow-2xl border border-gray-100 dark:border-gray-800 p-6 space-y-5">
            <div className="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3">
              <div>
                <span className="text-xs font-bold text-emerald-700 dark:text-emerald-400 uppercase tracking-wider">
                  {viewingItem.subject?.name} — {viewingItem.kelas?.nama_kelas}
                </span>
                <h3 className="text-lg font-bold text-gray-900 dark:text-white mt-0.5">
                  {viewingItem.student?.full_name}
                </h3>
              </div>
              <button onClick={() => setShowDetailModal(false)} className="p-1.5 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-400">
                <X className="w-5 h-5" />
              </button>
            </div>

            <div className="space-y-4">
              <div className="bg-emerald-50/60 dark:bg-emerald-950/40 p-4 rounded-xl border border-emerald-200 dark:border-emerald-800 flex items-center justify-between">
                <div>
                  <span className="text-xs font-medium text-gray-500 dark:text-gray-400 block">Nilai Akhir Terkalkulasi</span>
                  <span className="text-3xl font-black text-[#0E5C44] dark:text-emerald-400">{viewingItem.final_score}</span>
                </div>
                <div className="text-right space-y-1">
                  {getGradeBadge(viewingItem.grade_letter)}
                  <span
                    className={`block px-2.5 py-0.5 rounded text-xs font-bold ${
                      viewingItem.is_passed ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'
                    }`}
                  >
                    {viewingItem.is_passed ? 'TUNTAS KKM' : 'REMEDIAL'}
                  </span>
                </div>
              </div>

              {/* Formula Component Breakdown */}
              <div className="space-y-2 text-xs">
                <h4 className="font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Rincian Komponen & Bobot:</h4>
                <div className="bg-gray-50 dark:bg-[#111827] p-3 rounded-xl space-y-2 border border-gray-100 dark:border-gray-800">
                  <div className="flex justify-between items-center">
                    <span>Tugas LMS ({viewingItem.weights_config?.bobot_tugas}%):</span>
                    <span className="font-bold text-gray-800 dark:text-gray-200">{viewingItem.score_assignment}</span>
                  </div>
                  <div className="flex justify-between items-center">
                    <span>CBT UH ({viewingItem.weights_config?.bobot_uh}%):</span>
                    <span className="font-bold text-gray-800 dark:text-gray-200">{viewingItem.score_quiz}</span>
                  </div>
                  <div className="flex justify-between items-center">
                    <span>CBT UTS ({viewingItem.weights_config?.bobot_uts}%):</span>
                    <span className="font-bold text-gray-800 dark:text-gray-200">{viewingItem.score_midterm}</span>
                  </div>
                  <div className="flex justify-between items-center">
                    <span>CBT UAS ({viewingItem.weights_config?.bobot_uas}%):</span>
                    <span className="font-bold text-gray-800 dark:text-gray-200">{viewingItem.score_final}</span>
                  </div>
                </div>
              </div>

              {viewingItem.notes && (
                <div className="p-3 bg-amber-50 dark:bg-amber-950/30 rounded-xl border border-amber-200 dark:border-amber-900 text-xs">
                  <span className="font-bold text-amber-800 dark:text-amber-300 block mb-0.5">Catatan Guru:</span>
                  <p className="text-gray-700 dark:text-gray-300">{viewingItem.notes}</p>
                </div>
              )}
            </div>

            <div className="pt-3 border-t border-gray-100 dark:border-gray-800 flex justify-end">
              <button
                onClick={() => setShowDetailModal(false)}
                className="px-4 py-2 rounded-xl bg-gray-100 dark:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-200"
              >
                Tutup Detail
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
