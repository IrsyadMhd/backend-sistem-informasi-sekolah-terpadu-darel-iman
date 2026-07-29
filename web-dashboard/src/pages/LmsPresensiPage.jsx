import { useState, useEffect, useMemo } from 'react'
import {
  CalendarCheck,
  CheckCircle2,
  XCircle,
  AlertCircle,
  Clock,
  UserCheck,
  Search,
  Plus,
  Edit3,
  Trash2,
  RefreshCw,
  X,
  SlidersHorizontal,
  ChevronLeft,
  ChevronRight,
  BookOpen,
  User,
  Calendar,
  Save,
  Check,
  Users,
  Eye,
  CheckSquare,
  Award,
} from 'lucide-react'
import Swal from 'sweetalert2'
import { lmsPresensiService } from '../services/lmsPresensiService'

export default function LmsPresensiPage() {
  const [activeTab, setActiveTab] = useState('list') // 'list' | 'bulk'
  const [loading, setLoading] = useState(false)
  const [saving, setSaving] = useState(false)
  const [dataPresensi, setDataPresensi] = useState([])
  const [pagination, setPagination] = useState({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
  })

  const [options, setOptions] = useState({
    schedules: [],
    students: [],
    statuses: [
      { value: 'hadir', label: 'Hadir', color: 'emerald' },
      { value: 'izin', label: 'Izin', color: 'indigo' },
      { value: 'sakit', label: 'Sakit', color: 'sky' },
      { value: 'alpa', label: 'Alpa', color: 'rose' },
      { value: 'terlambat', label: 'Terlambat', color: 'amber' },
    ],
  })

  const [stats, setStats] = useState({
    total: 0,
    hadir: 0,
    izin: 0,
    sakit: 0,
    alpa: 0,
    terlambat: 0,
    persentase_hadir: 0,
  })

  // Filter state for list view
  const [filters, setFilters] = useState({
    search: '',
    jadwal_pelajaran_id: '',
    status_hadir: '',
    tanggal: '',
  })

  // Bulk input mode state
  const [bulkState, setBulkState] = useState({
    jadwal_pelajaran_id: '',
    tanggal: new Date().toISOString().split('T')[0],
    pertemuan_ke: 1,
    students: [],
  })

  // Single Modal State (Create / Edit)
  const [modalOpen, setModalOpen] = useState(false)
  const [modalType, setModalType] = useState('create') // 'create' | 'edit'
  const [formData, setFormData] = useState({
    id: null,
    jadwal_pelajaran_id: '',
    siswa_id: '',
    tanggal: new Date().toISOString().split('T')[0],
    status_hadir: 'hadir',
    keterangan: '',
    pertemuan_ke: 1,
  })

  // Detail Drawer state
  const [detailItem, setDetailItem] = useState(null)

  // Fetch Options & Stats
  const loadInitialOptions = async () => {
    try {
      const resOptions = await lmsPresensiService.getOptions()
      const dataPayload = resOptions?.data?.data || resOptions?.data || resOptions || {}
      setOptions((prev) => ({
        ...prev,
        schedules: Array.isArray(dataPayload.schedules) ? dataPayload.schedules : [],
        students: Array.isArray(dataPayload.students) ? dataPayload.students : [],
        statuses: Array.isArray(dataPayload.statuses) && dataPayload.statuses.length > 0
          ? dataPayload.statuses
          : prev.statuses,
      }))
    } catch (err) {
      console.error('Gagal memuat opsi:', err)
    }
  }

  const loadStats = async () => {
    try {
      const resStats = await lmsPresensiService.getStats({
        jadwal_pelajaran_id: filters.jadwal_pelajaran_id,
        tanggal: filters.tanggal,
      })
      if (resStats.success) {
        setStats(resStats.data)
      }
    } catch (err) {
      console.error('Gagal memuat statistik:', err)
    }
  }

  // Fetch Paginated List
  const fetchPresensiList = async (page = 1) => {
    setLoading(true)
    try {
      const params = {
        page,
        per_page: pagination.per_page,
        ...filters,
      }
      const response = await lmsPresensiService.getDaftar(params)
      setDataPresensi(response.data || [])
      setPagination({
        current_page: response.meta?.current_page || 1,
        last_page: response.meta?.last_page || 1,
        per_page: response.meta?.per_page || 15,
        total: response.meta?.total || 0,
      })
    } catch (err) {
      console.error('Gagal memuat data presensi:', err)
      Swal.fire({
        icon: 'error',
        title: 'Gagal Memuat Data',
        text: 'Terjadi kesalahan saat mengunduh data presensi pembelajaran.',
        confirmColor: '#0E5C44',
      })
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    loadInitialOptions()
  }, [])

  useEffect(() => {
    if (activeTab === 'list') {
      fetchPresensiList(1)
      loadStats()
    }
  }, [filters, activeTab])

  // Bulk mode: populate student list when schedule is selected
  useEffect(() => {
    if (!bulkState.jadwal_pelajaran_id) {
      setBulkState((prev) => ({ ...prev, students: [] }))
      return
    }

    const selectedSchedule = options.schedules.find(
      (s) => s.id === bulkState.jadwal_pelajaran_id
    )

    // Filter students by class_id of schedule if available, else show all active students
    let classStudents = options.students
    if (selectedSchedule?.kelas_id || selectedSchedule?.class_id) {
      const classId = selectedSchedule.kelas_id || selectedSchedule.class_id
      classStudents = options.students.filter(
        (st) => st.class_id === classId || !st.class_id
      )
      if (classStudents.length === 0) classStudents = options.students
    }

    const studentPresensiList = classStudents.map((st) => ({
      siswa_id: st.id,
      full_name: st.full_name,
      nis: st.nis,
      nisn: st.nisn,
      status_hadir: 'hadir',
      keterangan: '',
    }))

    setBulkState((prev) => ({
      ...prev,
      students: studentPresensiList,
    }))
  }, [bulkState.jadwal_pelajaran_id, options.schedules, options.students])

  // Bulk Actions
  const handleSetAllStatus = (status) => {
    setBulkState((prev) => ({
      ...prev,
      students: prev.students.map((st) => ({
        ...st,
        status_hadir: status,
      })),
    }))
  }

  const handleStudentStatusChange = (siswaId, newStatus) => {
    setBulkState((prev) => ({
      ...prev,
      students: prev.students.map((st) =>
        st.siswa_id === siswaId ? { ...st, status_hadir: newStatus } : st
      ),
    }))
  }

  const handleStudentKeteranganChange = (siswaId, text) => {
    setBulkState((prev) => ({
      ...prev,
      students: prev.students.map((st) =>
        st.siswa_id === siswaId ? { ...st, keterangan: text } : st
      ),
    }))
  }

  const handleSaveBulk = async () => {
    if (!bulkState.jadwal_pelajaran_id) {
      Swal.fire({
        icon: 'warning',
        title: 'Pilih Jadwal',
        text: 'Silakan pilih jadwal pelajaran terlebih dahulu.',
        confirmColor: '#0E5C44',
      })
      return
    }

    if (!bulkState.tanggal) {
      Swal.fire({
        icon: 'warning',
        title: 'Pilih Tanggal',
        text: 'Silakan tentukan tanggal presensi.',
        confirmColor: '#0E5C44',
      })
      return
    }

    if (bulkState.students.length === 0) {
      Swal.fire({
        icon: 'warning',
        title: 'Tidak Ada Siswa',
        text: 'Tidak ada daftar siswa untuk jadwal ini.',
        confirmColor: '#0E5C44',
      })
      return
    }

    setSaving(true)
    try {
      const payload = {
        jadwal_pelajaran_id: bulkState.jadwal_pelajaran_id,
        tanggal: bulkState.tanggal,
        pertemuan_ke: parseInt(bulkState.pertemuan_ke) || 1,
        items: bulkState.students.map((s) => ({
          siswa_id: s.siswa_id,
          status_hadir: s.status_hadir,
          keterangan: s.keterangan || null,
        })),
      }

      const res = await lmsPresensiService.createBulk(payload)
      if (res.success) {
        Swal.fire({
          icon: 'success',
          title: 'Presensi Tersimpan!',
          text: res.message || 'Presensi siswa berhasil dicatat.',
          confirmColor: '#0E5C44',
        })
        setActiveTab('list')
      }
    } catch (err) {
      console.error('Gagal menyimpan presensi bulk:', err)
      Swal.fire({
        icon: 'error',
        title: 'Gagal Menyimpan',
        text: err.response?.data?.message || 'Terjadi kesalahan saat menyimpan data presensi.',
        confirmColor: '#0E5C44',
      })
    } finally {
      setSaving(false)
    }
  }

  // Single Modal Actions
  const handleOpenCreateModal = () => {
    setModalType('create')
    setFormData({
      id: null,
      jadwal_pelajaran_id: options.schedules[0]?.id || '',
      siswa_id: options.students[0]?.id || '',
      tanggal: new Date().toISOString().split('T')[0],
      status_hadir: 'hadir',
      keterangan: '',
      pertemuan_ke: 1,
    })
    setModalOpen(true)
  }

  const handleOpenEditModal = (item) => {
    setModalType('edit')
    setFormData({
      id: item.id,
      jadwal_pelajaran_id: item.jadwal_pelajaran_id,
      siswa_id: item.siswa_id,
      tanggal: item.tanggal,
      status_hadir: item.status_hadir,
      keterangan: item.keterangan || '',
      pertemuan_ke: item.pertemuan_ke || 1,
    })
    setModalOpen(true)
  }

  const handleSubmitSingleModal = async (e) => {
    e.preventDefault()
    setSaving(true)
    try {
      if (modalType === 'create') {
        const res = await lmsPresensiService.create(formData)
        if (res.success) {
          Swal.fire({
            icon: 'success',
            title: 'Presensi Ditambahkan!',
            text: 'Data presensi berhasil disimpan.',
            confirmColor: '#0E5C44',
          })
          setModalOpen(false)
          fetchPresensiList(pagination.current_page)
          loadStats()
        }
      } else {
        const res = await lmsPresensiService.update(formData.id, formData)
        if (res.success) {
          Swal.fire({
            icon: 'success',
            title: 'Presensi Diperbarui!',
            text: 'Data presensi berhasil diperbarui.',
            confirmColor: '#0E5C44',
          })
          setModalOpen(false)
          fetchPresensiList(pagination.current_page)
          loadStats()
        }
      }
    } catch (err) {
      console.error('Gagal menyimpan single presensi:', err)
      Swal.fire({
        icon: 'error',
        title: 'Gagal Menyimpan',
        text: err.response?.data?.message || 'Gagal menyimpan data presensi.',
        confirmColor: '#0E5C44',
      })
    } finally {
      setSaving(false)
    }
  }

  const handleDelete = async (item) => {
    const confirm = await Swal.fire({
      title: 'Hapus Data Presensi?',
      text: `Presensi ${item.siswa?.full_name || 'siswa'} pada tanggal ${item.tanggal} akan dihapus.`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Ya, Hapus!',
      cancelButtonText: 'Batal',
      confirmColor: '#e11d48',
      cancelColor: '#64748b',
    })

    if (confirm.isConfirmed) {
      try {
        const res = await lmsPresensiService.delete(item.id)
        if (res.success) {
          Swal.fire({
            icon: 'success',
            title: 'Berhasil Dihapus!',
            text: 'Data presensi berhasil dihapus.',
            confirmColor: '#0E5C44',
          })
          fetchPresensiList(pagination.current_page)
          loadStats()
        }
      } catch (err) {
        console.error('Gagal menghapus presensi:', err)
        Swal.fire({
          icon: 'error',
          title: 'Gagal Hapus',
          text: 'Terjadi kesalahan saat menghapus data.',
          confirmColor: '#0E5C44',
        })
      }
    }
  }

  // Render Status Badge Component
  const renderBadge = (status, label) => {
    switch (status?.toLowerCase()) {
      case 'hadir':
        return (
          <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
            <CheckCircle2 className="w-3.5 h-3.5" />
            {label || 'Hadir'}
          </span>
        )
      case 'izin':
        return (
          <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800">
            <Clock className="w-3.5 h-3.5" />
            {label || 'Izin'}
          </span>
        )
      case 'sakit':
        return (
          <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-sky-50 text-sky-700 dark:bg-sky-900/30 dark:text-sky-400 border border-sky-200 dark:border-sky-800">
            <AlertCircle className="w-3.5 h-3.5" />
            {label || 'Sakit'}
          </span>
        )
      case 'alpa':
        return (
          <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400 border border-rose-200 dark:border-rose-800">
            <XCircle className="w-3.5 h-3.5" />
            {label || 'Alpa'}
          </span>
        )
      case 'terlambat':
        return (
          <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 border border-amber-200 dark:border-amber-800">
            <Clock className="w-3.5 h-3.5" />
            {label || 'Terlambat'}
          </span>
        )
      default:
        return (
          <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300">
            {label || status || 'N/A'}
          </span>
        )
    }
  }

  return (
    <div className="space-y-6 pb-12">
      {/* Hero Header */}
      <div className="relative overflow-hidden rounded-2xl bg-gradient-to-r from-[#0E5C44] via-[#1E8E5A] to-[#3FBF75] p-6 text-white shadow-xl">
        <div className="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <div className="flex items-center gap-2 text-emerald-100 text-sm font-medium mb-1">
              <BookOpen className="w-4 h-4" />
              <span>Modul LMS Pembelajaran</span>
            </div>
            <h1 className="text-2xl md:text-3xl font-extrabold tracking-tight">
              Presensi Pembelajaran
            </h1>
            <p className="text-emerald-100 text-sm mt-1 max-w-xl">
              Pencatatan dan pemantauan kehadiran siswa terintegrasi secara langsung dengan Jadwal Pelajaran.
            </p>
          </div>
          <div className="flex items-center gap-2">
            <button
              onClick={() => setActiveTab('list')}
              className={`px-4 py-2.5 rounded-xl font-semibold text-sm transition-all duration-200 shadow-sm flex items-center gap-2 ${
                activeTab === 'list'
                  ? 'bg-white text-[#0E5C44]'
                  : 'bg-white/20 hover:bg-white/30 text-white backdrop-blur-md'
              }`}
            >
              <CalendarCheck className="w-4 h-4" />
              Rekap Log Presensi
            </button>
            <button
              onClick={() => setActiveTab('bulk')}
              className={`px-4 py-2.5 rounded-xl font-semibold text-sm transition-all duration-200 shadow-sm flex items-center gap-2 ${
                activeTab === 'bulk'
                  ? 'bg-white text-[#0E5C44]'
                  : 'bg-white/20 hover:bg-white/30 text-white backdrop-blur-md'
              }`}
            >
              <Users className="w-4 h-4" />
              Input Per Jadwal
            </button>
          </div>
        </div>

        {/* Decorative Circle Elements */}
        <div className="absolute -right-10 -bottom-10 w-48 h-48 bg-white/10 rounded-full blur-2xl pointer-events-none" />
        <div className="absolute right-40 -top-10 w-36 h-36 bg-white/10 rounded-full blur-xl pointer-events-none" />
      </div>

      {/* Stats Cards */}
      <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <div className="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
          <div>
            <p className="text-xs font-medium text-slate-500 dark:text-slate-400">Total Log</p>
            <h3 className="text-xl font-bold text-slate-800 dark:text-white mt-0.5">{stats.total}</h3>
          </div>
          <div className="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-slate-600 dark:text-slate-300">
            <UserCheck className="w-5 h-5" />
          </div>
        </div>

        <div className="bg-white dark:bg-slate-800 p-4 rounded-xl border border-emerald-100 dark:border-emerald-900/40 shadow-sm flex items-center justify-between">
          <div>
            <p className="text-xs font-medium text-emerald-600 dark:text-emerald-400">Hadir</p>
            <h3 className="text-xl font-bold text-emerald-700 dark:text-emerald-300 mt-0.5">{stats.hadir}</h3>
          </div>
          <div className="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
            <CheckCircle2 className="w-5 h-5" />
          </div>
        </div>

        <div className="bg-white dark:bg-slate-800 p-4 rounded-xl border border-indigo-100 dark:border-indigo-900/40 shadow-sm flex items-center justify-between">
          <div>
            <p className="text-xs font-medium text-indigo-600 dark:text-indigo-400">Izin</p>
            <h3 className="text-xl font-bold text-indigo-700 dark:text-indigo-300 mt-0.5">{stats.izin}</h3>
          </div>
          <div className="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
            <Clock className="w-5 h-5" />
          </div>
        </div>

        <div className="bg-white dark:bg-slate-800 p-4 rounded-xl border border-sky-100 dark:border-sky-900/40 shadow-sm flex items-center justify-between">
          <div>
            <p className="text-xs font-medium text-sky-600 dark:text-sky-400">Sakit</p>
            <h3 className="text-xl font-bold text-sky-700 dark:text-sky-300 mt-0.5">{stats.sakit}</h3>
          </div>
          <div className="w-10 h-10 rounded-xl bg-sky-50 dark:bg-sky-900/30 flex items-center justify-center text-sky-600 dark:text-sky-400">
            <AlertCircle className="w-5 h-5" />
          </div>
        </div>

        <div className="bg-white dark:bg-slate-800 p-4 rounded-xl border border-rose-100 dark:border-rose-900/40 shadow-sm flex items-center justify-between">
          <div>
            <p className="text-xs font-medium text-rose-600 dark:text-rose-400">Alpa</p>
            <h3 className="text-xl font-bold text-rose-700 dark:text-rose-300 mt-0.5">{stats.alpa}</h3>
          </div>
          <div className="w-10 h-10 rounded-xl bg-rose-50 dark:bg-rose-900/30 flex items-center justify-center text-rose-600 dark:text-rose-400">
            <XCircle className="w-5 h-5" />
          </div>
        </div>

        <div className="bg-white dark:bg-slate-800 p-4 rounded-xl border border-amber-100 dark:border-amber-900/40 shadow-sm flex items-center justify-between">
          <div>
            <p className="text-xs font-medium text-amber-600 dark:text-amber-400">% Kehadiran</p>
            <h3 className="text-xl font-bold text-amber-700 dark:text-amber-300 mt-0.5">{stats.persentase_hadir}%</h3>
          </div>
          <div className="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-900/30 flex items-center justify-center text-amber-600 dark:text-amber-400">
            <Award className="w-5 h-5" />
          </div>
        </div>
      </div>

      {/* Main Content Areas */}
      {activeTab === 'list' ? (
        /* TAB 1: REKAP / LOG LIST VIEW */
        <div className="space-y-4">
          {/* Action & Filter Bar */}
          <div className="bg-white dark:bg-slate-800 p-4 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-3">
            <div className="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3">
              {/* Search */}
              <div className="relative flex-1">
                <Search className="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
                <input
                  type="text"
                  placeholder="Cari nama siswa, NIS, mata pelajaran..."
                  value={filters.search}
                  onChange={(e) => setFilters((prev) => ({ ...prev, search: e.target.value }))}
                  className="w-full pl-10 pr-4 py-2 rounded-xl text-sm bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-[#0E5C44]/20 focus:border-[#0E5C44]"
                />
              </div>

              {/* Action Buttons */}
              <div className="flex items-center gap-2">
                <button
                  onClick={handleOpenCreateModal}
                  className="px-4 py-2 bg-[#0E5C44] hover:bg-[#1E8E5A] text-white font-semibold text-sm rounded-xl transition-all duration-200 flex items-center gap-2 shadow-sm"
                >
                  <Plus className="w-4 h-4" />
                  Tambah Manual
                </button>
                <button
                  onClick={() => fetchPresensiList(pagination.current_page)}
                  className="p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors"
                  title="Refresh Data"
                >
                  <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} />
                </button>
              </div>
            </div>

            {/* Filter Dropdowns */}
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2 border-t border-slate-100 dark:border-slate-700/50">
              <div>
                <label className="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">
                  Jadwal Pelajaran
                </label>
                <select
                  value={filters.jadwal_pelajaran_id}
                  onChange={(e) => setFilters((prev) => ({ ...prev, jadwal_pelajaran_id: e.target.value }))}
                  className="w-full px-3 py-1.5 rounded-xl text-xs bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 focus:ring-[#0E5C44]"
                >
                  <option value="">Semua Jadwal Pelajaran</option>
                  {options.schedules.length === 0 ? (
                    <option value="" disabled>Belum ada jadwal pelajaran aktif</option>
                  ) : (
                    options.schedules.map((s) => (
                      <option key={s.id} value={s.id}>
                        {s.nama_jadwal || s.label || s.name || 'Jadwal'}
                      </option>
                    ))
                  )}
                </select>
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">
                  Status Kehadiran
                </label>
                <select
                  value={filters.status_hadir}
                  onChange={(e) => setFilters((prev) => ({ ...prev, status_hadir: e.target.value }))}
                  className="w-full px-3 py-1.5 rounded-xl text-xs bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 focus:ring-[#0E5C44]"
                >
                  <option value="">Semua Status</option>
                  {options.statuses.map((st) => (
                    <option key={st.value} value={st.value}>
                      {st.label}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">
                  Tanggal Presensi
                </label>
                <input
                  type="date"
                  value={filters.tanggal}
                  onChange={(e) => setFilters((prev) => ({ ...prev, tanggal: e.target.value }))}
                  className="w-full px-3 py-1.5 rounded-xl text-xs bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 focus:ring-[#0E5C44]"
                />
              </div>
            </div>
          </div>

          {/* Table Data */}
          <div className="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm">
            <div className="overflow-x-auto">
              <table className="w-full text-left text-sm">
                <thead className="bg-slate-50 dark:bg-slate-900/60 border-b border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 font-semibold text-xs uppercase tracking-wider">
                  <tr>
                    <th className="px-5 py-4">Tanggal & Pertemuan</th>
                    <th className="px-5 py-4">Siswa</th>
                    <th className="px-5 py-4">Jadwal / Mata Pelajaran</th>
                    <th className="px-5 py-4">Status</th>
                    <th className="px-5 py-4">Keterangan</th>
                    <th className="px-5 py-4 text-center">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 dark:divide-slate-700/60">
                  {loading ? (
                    <tr>
                      <td colSpan="6" className="py-12 text-center text-slate-400">
                        <RefreshCw className="w-6 h-6 animate-spin mx-auto mb-2 text-[#0E5C44]" />
                        <span>Memuat data presensi...</span>
                      </td>
                    </tr>
                  ) : dataPresensi.length === 0 ? (
                    <tr>
                      <td colSpan="6" className="py-12 text-center text-slate-400">
                        <CalendarCheck className="w-10 h-10 mx-auto mb-2 text-slate-300 dark:text-slate-600" />
                        <p className="font-semibold text-slate-600 dark:text-slate-300">Belum Ada Data Presensi</p>
                        <p className="text-xs text-slate-400">Silakan gunakan fitur Input Per Jadwal atau Tambah Manual.</p>
                      </td>
                    </tr>
                  ) : (
                    dataPresensi.map((item) => (
                      <tr
                        key={item.id}
                        className="hover:bg-slate-50/80 dark:hover:bg-slate-700/40 transition-colors"
                      >
                        <td className="px-5 py-4">
                          <div className="font-semibold text-slate-800 dark:text-white">
                            {item.tanggal}
                          </div>
                          <div className="text-xs text-slate-500">
                            Pertemuan ke-{item.pertemuan_ke || 1}
                          </div>
                        </td>

                        <td className="px-5 py-4">
                          <div className="font-semibold text-slate-800 dark:text-white">
                            {item.siswa?.full_name || 'Siswa N/A'}
                          </div>
                          <div className="text-xs text-slate-400">
                            NIS: {item.siswa?.nis || '-'}
                          </div>
                        </td>

                        <td className="px-5 py-4">
                          <div className="font-medium text-slate-800 dark:text-slate-200">
                            {item.jadwal?.subject?.name || 'Mata Pelajaran'}
                          </div>
                          <div className="text-xs text-slate-500">
                            {item.jadwal?.kelas?.nama_kelas} • {item.jadwal?.day_name} ({item.jadwal?.time_start}-{item.jadwal?.time_end})
                          </div>
                        </td>

                        <td className="px-5 py-4">
                          {renderBadge(item.status_hadir, item.status_label)}
                        </td>

                        <td className="px-5 py-4 max-w-xs truncate text-xs text-slate-600 dark:text-slate-300">
                          {item.keterangan || '-'}
                        </td>

                        <td className="px-5 py-4 text-center">
                          <div className="flex items-center justify-center gap-1.5">
                            <button
                              onClick={() => setDetailItem(item)}
                              className="p-1.5 rounded-lg text-slate-500 hover:text-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors"
                              title="Lihat Detail"
                            >
                              <Eye className="w-4 h-4" />
                            </button>
                            <button
                              onClick={() => handleOpenEditModal(item)}
                              className="p-1.5 rounded-lg text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/30 transition-colors"
                              title="Edit"
                            >
                              <Edit3 className="w-4 h-4" />
                            </button>
                            <button
                              onClick={() => handleDelete(item)}
                              className="p-1.5 rounded-lg text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/30 transition-colors"
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

            {/* Pagination Bar */}
            {pagination.total > 0 && (
              <div className="px-6 py-4 border-t border-slate-100 dark:border-slate-700 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500">
                <div>
                  Menampilkan Data Halaman <span className="font-semibold text-slate-700 dark:text-slate-200">{pagination.current_page}</span> dari <span className="font-semibold text-slate-700 dark:text-slate-200">{pagination.last_page}</span> (Total {pagination.total} Data)
                </div>
                <div className="flex items-center gap-2">
                  <button
                    disabled={pagination.current_page <= 1}
                    onClick={() => fetchPresensiList(pagination.current_page - 1)}
                    className="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-slate-50 dark:hover:bg-slate-700 flex items-center gap-1 font-medium"
                  >
                    <ChevronLeft className="w-3.5 h-3.5" /> Prev
                  </button>
                  <button
                    disabled={pagination.current_page >= pagination.last_page}
                    onClick={() => fetchPresensiList(pagination.current_page + 1)}
                    className="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-slate-50 dark:hover:bg-slate-700 flex items-center gap-1 font-medium"
                  >
                    Next <ChevronRight className="w-3.5 h-3.5" />
                  </button>
                </div>
              </div>
            )}
          </div>
        </div>
      ) : (
        /* TAB 2: INPUT PRESENSI PER JADWAL (BULK MODE) */
        <div className="space-y-6">
          {/* Header Card Selection */}
          <div className="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-4">
            <div className="flex items-center justify-between border-b border-slate-100 dark:border-slate-700 pb-3">
              <div>
                <h2 className="text-lg font-bold text-slate-800 dark:text-white flex items-center gap-2">
                  <Users className="w-5 h-5 text-[#0E5C44]" />
                  Form Presensi Per Jadwal Pelajaran
                </h2>
                <p className="text-xs text-slate-500 mt-0.5">
                  Pilih Jadwal Pelajaran dan tanggal untuk mencatat presensi seluruh siswa di kelas tersebut.
                </p>
              </div>
              {bulkState.students.length > 0 && (
                <button
                  onClick={handleSaveBulk}
                  disabled={saving}
                  className="px-5 py-2.5 bg-[#0E5C44] hover:bg-[#1E8E5A] text-white font-semibold text-sm rounded-xl transition-all shadow-md flex items-center gap-2 disabled:opacity-50"
                >
                  <Save className="w-4 h-4" />
                  {saving ? 'Menyimpan...' : `Simpan Presensi (${bulkState.students.length} Siswa)`}
                </button>
              )}
            </div>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                  Pilih Jadwal Pelajaran <span className="text-rose-500">*</span>
                </label>
                <select
                  value={bulkState.jadwal_pelajaran_id}
                  onChange={(e) => setBulkState((prev) => ({ ...prev, jadwal_pelajaran_id: e.target.value }))}
                  className="w-full px-3.5 py-2 rounded-xl text-sm bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 focus:ring-[#0E5C44]"
                >
                  <option value="">-- Pilih Jadwal Pelajaran --</option>
                  {options.schedules.length === 0 ? (
                    <option value="" disabled>Belum ada jadwal pelajaran aktif</option>
                  ) : (
                    options.schedules.map((s) => (
                      <option key={s.id} value={s.id}>
                        {s.nama_jadwal || s.label || s.name || 'Jadwal'}
                      </option>
                    ))
                  )}
                </select>
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                  Tanggal Presensi <span className="text-rose-500">*</span>
                </label>
                <input
                  type="date"
                  value={bulkState.tanggal}
                  onChange={(e) => setBulkState((prev) => ({ ...prev, tanggal: e.target.value }))}
                  className="w-full px-3.5 py-2 rounded-xl text-sm bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 focus:ring-[#0E5C44]"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                  Pertemuan Ke-
                </label>
                <input
                  type="number"
                  min="1"
                  value={bulkState.pertemuan_ke}
                  onChange={(e) => setBulkState((prev) => ({ ...prev, pertemuan_ke: e.target.value }))}
                  className="w-full px-3.5 py-2 rounded-xl text-sm bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 focus:ring-[#0E5C44]"
                />
              </div>
            </div>

            {/* Quick Bulk Action Buttons */}
            {bulkState.students.length > 0 && (
              <div className="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-slate-100 dark:border-slate-700">
                <div className="text-xs font-semibold text-slate-500">
                  Set Status Cepat Semua Siswa:
                </div>
                <div className="flex flex-wrap items-center gap-2">
                  <button
                    type="button"
                    onClick={() => handleSetAllStatus('hadir')}
                    className="px-3 py-1.5 rounded-lg text-xs font-semibold bg-emerald-100 text-emerald-800 hover:bg-emerald-200 dark:bg-emerald-900/40 dark:text-emerald-300 transition-colors flex items-center gap-1.5"
                  >
                    <CheckCircle2 className="w-3.5 h-3.5" />
                    Set Semua HADIR
                  </button>
                  <button
                    type="button"
                    onClick={() => handleSetAllStatus('izin')}
                    className="px-3 py-1.5 rounded-lg text-xs font-semibold bg-indigo-100 text-indigo-800 hover:bg-indigo-200 dark:bg-indigo-900/40 dark:text-indigo-300 transition-colors flex items-center gap-1.5"
                  >
                    <Clock className="w-3.5 h-3.5" />
                    Set Semua IZIN
                  </button>
                  <button
                    type="button"
                    onClick={() => handleSetAllStatus('sakit')}
                    className="px-3 py-1.5 rounded-lg text-xs font-semibold bg-sky-100 text-sky-800 hover:bg-sky-200 dark:bg-sky-900/40 dark:text-sky-300 transition-colors flex items-center gap-1.5"
                  >
                    <AlertCircle className="w-3.5 h-3.5" />
                    Set Semua SAKIT
                  </button>
                </div>
              </div>
            )}
          </div>

          {/* Student List Grid / Table */}
          {bulkState.students.length > 0 ? (
            <div className="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm">
              <div className="overflow-x-auto">
                <table className="w-full text-left text-sm">
                  <thead className="bg-slate-50 dark:bg-slate-900/60 border-b border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 font-semibold text-xs uppercase tracking-wider">
                    <tr>
                      <th className="px-5 py-4 w-12 text-center">#</th>
                      <th className="px-5 py-4">Nama Siswa</th>
                      <th className="px-5 py-4">NIS / NISN</th>
                      <th className="px-5 py-4 text-center">Status Kehadiran</th>
                      <th className="px-5 py-4">Catatan / Keterangan</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100 dark:divide-slate-700/60">
                    {bulkState.students.map((st, idx) => (
                      <tr key={st.siswa_id} className="hover:bg-slate-50/80 dark:hover:bg-slate-700/40">
                        <td className="px-5 py-4 text-center font-medium text-slate-400">{idx + 1}</td>
                        <td className="px-5 py-4 font-semibold text-slate-800 dark:text-white">
                          {st.full_name}
                        </td>
                        <td className="px-5 py-4 text-xs text-slate-500">
                          {st.nis || '-'} / {st.nisn || '-'}
                        </td>
                        <td className="px-5 py-4">
                          <div className="flex items-center justify-center gap-1.5">
                            {[
                              { val: 'hadir', label: 'Hadir', activeClass: 'bg-emerald-600 text-white shadow-sm' },
                              { val: 'izin', label: 'Izin', activeClass: 'bg-indigo-600 text-white shadow-sm' },
                              { val: 'sakit', label: 'Sakit', activeClass: 'bg-sky-600 text-white shadow-sm' },
                              { val: 'alpa', label: 'Alpa', activeClass: 'bg-rose-600 text-white shadow-sm' },
                              { val: 'terlambat', label: 'Late', activeClass: 'bg-amber-600 text-white shadow-sm' },
                            ].map((opt) => (
                              <button
                                type="button"
                                key={opt.val}
                                onClick={() => handleStudentStatusChange(st.siswa_id, opt.val)}
                                className={`px-2.5 py-1 rounded-lg text-xs font-semibold transition-all ${
                                  st.status_hadir === opt.val
                                    ? opt.activeClass
                                    : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300 hover:bg-slate-200'
                                }`}
                              >
                                {opt.label}
                              </button>
                            ))}
                          </div>
                        </td>
                        <td className="px-5 py-4">
                          <input
                            type="text"
                            placeholder="Catatan..."
                            value={st.keterangan || ''}
                            onChange={(e) => handleStudentKeteranganChange(st.siswa_id, e.target.value)}
                            className="w-full px-3 py-1 rounded-lg text-xs bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 focus:ring-[#0E5C44]"
                          />
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              <div className="p-4 bg-slate-50 dark:bg-slate-900/60 border-t border-slate-200 dark:border-slate-700 flex justify-end">
                <button
                  onClick={handleSaveBulk}
                  disabled={saving}
                  className="px-6 py-2.5 bg-[#0E5C44] hover:bg-[#1E8E5A] text-white font-semibold text-sm rounded-xl transition-all shadow-md flex items-center gap-2 disabled:opacity-50"
                >
                  <Save className="w-4 h-4" />
                  {saving ? 'Menyimpan...' : 'Simpan Semua Presensi'}
                </button>
              </div>
            </div>
          ) : (
            <div className="bg-white dark:bg-slate-800 p-12 text-center rounded-2xl border border-slate-200 dark:border-slate-700 text-slate-400">
              <BookOpen className="w-12 h-12 mx-auto mb-3 text-slate-300 dark:text-slate-600" />
              <p className="font-semibold text-slate-700 dark:text-slate-200 text-base">Silakan Pilih Jadwal Pelajaran</p>
              <p className="text-xs text-slate-400 mt-1">
                Sistem akan secara otomatis menampilkan seluruh daftar siswa sesuai kelas jadwal tersebut.
              </p>
            </div>
          )}
        </div>
      )}

      {/* SINGLE CREATE / EDIT MODAL */}
      {modalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
          <div className="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-2xl w-full max-w-lg overflow-hidden animate-in fade-in zoom-in duration-200">
            <div className="px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
              <h3 className="text-lg font-bold text-slate-800 dark:text-white">
                {modalType === 'create' ? 'Tambah Data Presensi' : 'Edit Data Presensi'}
              </h3>
              <button
                onClick={() => setModalOpen(false)}
                className="p-1 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleSubmitSingleModal} className="p-6 space-y-4">
              <div>
                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                  Jadwal Pelajaran <span className="text-rose-500">*</span>
                </label>
                <select
                  required
                  value={formData.jadwal_pelajaran_id}
                  onChange={(e) => setFormData((prev) => ({ ...prev, jadwal_pelajaran_id: e.target.value }))}
                  className="w-full px-3.5 py-2 rounded-xl text-sm bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 focus:ring-[#0E5C44]"
                >
                  <option value="">-- Pilih Jadwal --</option>
                  {options.schedules.length === 0 ? (
                    <option value="" disabled>Belum ada jadwal pelajaran aktif</option>
                  ) : (
                    options.schedules.map((s) => (
                      <option key={s.id} value={s.id}>
                        {s.nama_jadwal || s.label || s.name || 'Jadwal'}
                      </option>
                    ))
                  )}
                </select>
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                  Siswa <span className="text-rose-500">*</span>
                </label>
                <select
                  required
                  value={formData.siswa_id}
                  onChange={(e) => setFormData((prev) => ({ ...prev, siswa_id: e.target.value }))}
                  className="w-full px-3.5 py-2 rounded-xl text-sm bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 focus:ring-[#0E5C44]"
                >
                  <option value="">-- Pilih Siswa --</option>
                  {options.students.map((st) => (
                    <option key={st.id} value={st.id}>
                      {st.full_name} ({st.nis || 'No NIS'})
                    </option>
                  ))}
                </select>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Tanggal Presensi <span className="text-rose-500">*</span>
                  </label>
                  <input
                    type="date"
                    required
                    value={formData.tanggal}
                    onChange={(e) => setFormData((prev) => ({ ...prev, tanggal: e.target.value }))}
                    className="w-full px-3.5 py-2 rounded-xl text-sm bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 focus:ring-[#0E5C44]"
                  />
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Pertemuan Ke-
                  </label>
                  <input
                    type="number"
                    min="1"
                    value={formData.pertemuan_ke}
                    onChange={(e) => setFormData((prev) => ({ ...prev, pertemuan_ke: e.target.value }))}
                    className="w-full px-3.5 py-2 rounded-xl text-sm bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 focus:ring-[#0E5C44]"
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                  Status Kehadiran <span className="text-rose-500">*</span>
                </label>
                <select
                  required
                  value={formData.status_hadir}
                  onChange={(e) => setFormData((prev) => ({ ...prev, status_hadir: e.target.value }))}
                  className="w-full px-3.5 py-2 rounded-xl text-sm bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 focus:ring-[#0E5C44]"
                >
                  {options.statuses.map((st) => (
                    <option key={st.value} value={st.value}>
                      {st.label}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                  Keterangan (Opsional)
                </label>
                <textarea
                  rows="3"
                  placeholder="Keterangan alpa/sakit/izin..."
                  value={formData.keterangan}
                  onChange={(e) => setFormData((prev) => ({ ...prev, keterangan: e.target.value }))}
                  className="w-full px-3.5 py-2 rounded-xl text-sm bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 focus:ring-[#0E5C44]"
                />
              </div>

              <div className="pt-4 flex items-center justify-end gap-2 border-t border-slate-100 dark:border-slate-700">
                <button
                  type="button"
                  onClick={() => setModalOpen(false)}
                  className="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 font-semibold text-sm hover:bg-slate-50"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={saving}
                  className="px-5 py-2 bg-[#0E5C44] hover:bg-[#1E8E5A] text-white font-semibold text-sm rounded-xl transition-all shadow-md disabled:opacity-50"
                >
                  {saving ? 'Simpan...' : 'Simpan Data'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* DETAIL DRAWER */}
      {detailItem && (
        <div className="fixed inset-0 z-50 flex justify-end bg-slate-900/40 backdrop-blur-xs">
          <div className="bg-white dark:bg-slate-800 w-full max-w-md h-full shadow-2xl p-6 overflow-y-auto space-y-6 animate-in slide-in-from-right duration-200">
            <div className="flex items-center justify-between border-b border-slate-100 dark:border-slate-700 pb-4">
              <h3 className="text-lg font-bold text-slate-800 dark:text-white">
                Detail Presensi Pembelajaran
              </h3>
              <button
                onClick={() => setDetailItem(null)}
                className="p-1 rounded-lg text-slate-400 hover:text-slate-600"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <div className="space-y-4 text-sm">
              <div className="p-4 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700">
                <div className="text-xs text-slate-400 mb-1">Status Kehadiran</div>
                {renderBadge(detailItem.status_hadir, detailItem.status_label)}
              </div>

              <div>
                <label className="text-xs font-semibold text-slate-400">Siswa</label>
                <p className="font-semibold text-slate-800 dark:text-white text-base mt-0.5">
                  {detailItem.siswa?.full_name || 'N/A'}
                </p>
                <p className="text-xs text-slate-500">NIS: {detailItem.siswa?.nis || '-'}</p>
              </div>

              <div>
                <label className="text-xs font-semibold text-slate-400">Mata Pelajaran & Jadwal</label>
                <p className="font-semibold text-slate-800 dark:text-white mt-0.5">
                  {detailItem.jadwal?.subject?.name || '-'}
                </p>
                <p className="text-xs text-slate-500">
                  {detailItem.jadwal?.kelas?.nama_kelas} • {detailItem.jadwal?.day_name} ({detailItem.jadwal?.time_start}-{detailItem.jadwal?.time_end})
                </p>
                <p className="text-xs text-slate-400 mt-1">Guru: {detailItem.jadwal?.teacher?.full_name || '-'}</p>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="text-xs font-semibold text-slate-400">Tanggal</label>
                  <p className="font-semibold text-slate-800 dark:text-white mt-0.5">{detailItem.tanggal}</p>
                </div>
                <div>
                  <label className="text-xs font-semibold text-slate-400">Pertemuan Ke-</label>
                  <p className="font-semibold text-slate-800 dark:text-white mt-0.5">{detailItem.pertemuan_ke || 1}</p>
                </div>
              </div>

              <div>
                <label className="text-xs font-semibold text-slate-400">Keterangan</label>
                <p className="text-slate-700 dark:text-slate-300 mt-0.5 bg-slate-50 dark:bg-slate-900 p-3 rounded-xl border border-slate-200 dark:border-slate-700 text-xs">
                  {detailItem.keterangan || 'Tidak ada catatan.'}
                </p>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
