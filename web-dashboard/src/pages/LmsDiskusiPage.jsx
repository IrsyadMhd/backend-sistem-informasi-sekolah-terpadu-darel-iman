import { useState, useEffect } from 'react'
import {
  MessageSquare,
  BookOpen,
  Plus,
  Search,
  Edit3,
  Trash2,
  RefreshCw,
  CheckCircle,
  AlertCircle,
  X,
  Pin,
  Lock,
  Unlock,
  Send,
  GraduationCap,
  MessageCircle,
  ChevronLeft,
  ChevronRight,
  Sparkles,
  Tag,
  Clock,
  SlidersHorizontal,
  CornerDownRight,
} from 'lucide-react'
import Swal from 'sweetalert2'
import { lmsDiskusiService } from '../services/lmsDiskusiService'
import { lmsModulAjarService } from '../services/lmsModulAjarService'

export default function LmsDiskusiPage() {
  const [dataDiskusi, setDataDiskusi] = useState([])
  const [optionsModulAjar, setOptionsModulAjar] = useState([])
  const [optionsKategori, setOptionsKategori] = useState([
    'Umum',
    'Tanya Jawab',
    'Tugas',
    'Materi',
    'Proyek',
    'Refleksi',
  ])
  const [stats, setStats] = useState({
    total_diskusi: 0,
    diskusi_aktif: 0,
    diskusi_ditutup: 0,
    diskusi_pinned: 0,
    total_komentar: 0,
    komentar_guru: 0,
    komentar_siswa: 0,
  })

  const [loading, setLoading] = useState(true)
  const [errorMsg, setErrorMsg] = useState('')
  const [successMsg, setSuccessMsg] = useState('')

  // Filters & Pagination
  const [search, setSearch] = useState('')
  const [selectedModulAjar, setSelectedModulAjar] = useState('')
  const [selectedKategori, setSelectedKategori] = useState('')
  const [selectedStatus, setSelectedStatus] = useState('')
  const [page, setPage] = useState(1)
  const [pagination, setPagination] = useState({
    current_page: 1,
    last_page: 1,
    total: 0,
    per_page: 15,
  })

  // Modal Form State (Create / Edit Diskusi)
  const [isModalOpen, setIsModalOpen] = useState(false)
  const [editId, setEditId] = useState(null)
  const [formLoading, setFormLoading] = useState(false)

  const [formData, setFormData] = useState({
    modul_ajar_id: '',
    judul: '',
    deskripsi: '',
    kategori: 'Umum',
    tanggal_mulai: '',
    tanggal_tutup: '',
    status: 'aktif',
  })

  // Drawer / Modal State for Comments Thread
  const [isThreadOpen, setIsThreadOpen] = useState(false)
  const [selectedDiskusi, setSelectedDiskusi] = useState(null)
  const [threadLoading, setThreadLoading] = useState(false)
  const [replyParentId, setReplyParentId] = useState(null)

  const [komentarForm, setKomentarForm] = useState({
    konten: '',
    peran_pengirim: 'Guru',
  })
  const [submittingKomentar, setSubmittingKomentar] = useState(false)

  useEffect(() => {
    fetchOptions()
    fetchStats()
  }, [])

  useEffect(() => {
    fetchData()
  }, [page, search, selectedModulAjar, selectedKategori, selectedStatus])

  const fetchStats = async () => {
    try {
      const res = await lmsDiskusiService.getStats()
      if (res.success) {
        setStats(res.data)
      }
    } catch (err) {
      console.error('Gagal mengambil statistik diskusi:', err)
    }
  }

  const fetchOptions = async () => {
    try {
      const res = await lmsDiskusiService.getOptions()
      let modulList = []

      const rawModul = res?.data?.modul_ajar || res?.data?.modul_ajar_options || res?.modul_ajar
      if (Array.isArray(rawModul) && rawModul.length > 0) {
        modulList = rawModul.map((opt) => ({
          value: opt.value || opt.id,
          id: opt.value || opt.id,
          label: opt.label || (opt.kode_modul ? `[${opt.kode_modul}] ${opt.judul_modul || opt.judul}` : (opt.judul_modul || opt.judul)),
          judul: opt.judul_modul || opt.judul,
        }))
      } else {
        // Fallback: Fetch directly from lmsModulAjarService
        try {
          const maRes = await lmsModulAjarService.getAll({ per_page: 100 })
          const rawList = maRes?.data || maRes || []
          if (Array.isArray(rawList) && rawList.length > 0) {
            modulList = rawList.map((opt) => ({
              value: opt.id,
              id: opt.id,
              label: opt.kode_modul ? `[${opt.kode_modul}] ${opt.judul_modul || opt.judul}` : (opt.judul_modul || opt.judul),
              judul: opt.judul_modul || opt.judul,
            }))
          }
        } catch (maErr) {
          console.error('Fallback fetch modul ajar failed:', maErr)
        }
      }

      setOptionsModulAjar(modulList)
      if (res?.data?.kategori) setOptionsKategori(res.data.kategori)
    } catch (err) {
      console.error('Gagal mengambil opsi data diskusi:', err)
      try {
        const maRes = await lmsModulAjarService.getAll({ per_page: 100 })
        const rawList = maRes?.data || maRes || []
        if (Array.isArray(rawList) && rawList.length > 0) {
          const modulList = rawList.map((opt) => ({
            value: opt.id,
            id: opt.id,
            label: opt.kode_modul ? `[${opt.kode_modul}] ${opt.judul_modul || opt.judul}` : (opt.judul_modul || opt.judul),
            judul: opt.judul_modul || opt.judul,
          }))
          setOptionsModulAjar(modulList)
        }
      } catch (fallbackErr) {
        console.error('Fallback fetch modul ajar failed:', fallbackErr)
      }
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
        kategori: selectedKategori,
        status: selectedStatus,
      }
      const res = await lmsDiskusiService.getDaftar(params)
      setDataDiskusi(res.data || [])
      if (res.meta) {
        setPagination({
          current_page: res.meta.current_page,
          last_page: res.meta.last_page,
          total: res.meta.total,
          per_page: res.meta.per_page,
        })
      }
    } catch (err) {
      console.error('Error fetching data diskusi:', err)
      setErrorMsg('Gagal memuat data diskusi kelas. Silakan coba lagi.')
    } finally {
      setLoading(false)
    }
  }

  const handleOpenCreateModal = () => {
    setEditId(null)
    setFormData({
      modul_ajar_id: '',
      judul: '',
      deskripsi: '',
      kategori: 'Umum',
      tanggal_mulai: '',
      tanggal_tutup: '',
      status: 'aktif',
    })
    setIsModalOpen(true)
  }

  const handleOpenEditModal = (item) => {
    setEditId(item.id)
    setFormData({
      modul_ajar_id: item.modul_ajar_id || '',
      judul: item.judul || '',
      deskripsi: item.deskripsi || '',
      kategori: item.kategori || 'Umum',
      tanggal_mulai: item.tanggal_mulai ? item.tanggal_mulai.replace(' ', 'T') : '',
      tanggal_tutup: item.tanggal_tutup ? item.tanggal_tutup.replace(' ', 'T') : '',
      status: item.status || 'aktif',
    })
    setIsModalOpen(true)
  }

  const handleSaveForm = async (e) => {
    e.preventDefault()
    setFormLoading(true)
    try {
      let res
      if (editId) {
        res = await lmsDiskusiService.update(editId, formData)
      } else {
        res = await lmsDiskusiService.create(formData)
      }

      if (res.success) {
        setSuccessMsg(res.message || 'Data diskusi berhasil disimpan.')
        setIsModalOpen(false)
        fetchData()
        fetchStats()
        setTimeout(() => setSuccessMsg(''), 4000)
      }
    } catch (err) {
      const msg = err.response?.data?.message || 'Gagal menyimpan data diskusi.'
      Swal.fire({
        icon: 'error',
        title: 'Gagal',
        text: msg,
        confirmButtonColor: '#0E5C44',
      })
    } finally {
      setFormLoading(false)
    }
  }

  const handleDelete = async (id, judul) => {
    const result = await Swal.fire({
      title: 'Hapus Diskusi?',
      text: `Apakah Anda yakin ingin menghapus diskusi "${judul}"?`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#ef4444',
      cancelButtonColor: '#6b7280',
      confirmButtonText: 'Ya, Hapus',
      cancelButtonText: 'Batal',
    })

    if (result.isConfirmed) {
      try {
        const res = await lmsDiskusiService.delete(id)
        if (res.success) {
          Swal.fire({
            icon: 'success',
            title: 'Terhapus!',
            text: res.message || 'Diskusi berhasil dihapus.',
            confirmButtonColor: '#0E5C44',
          })
          fetchData()
          fetchStats()
        }
      } catch (err) {
        Swal.fire({
          icon: 'error',
          title: 'Gagal',
          text: err.response?.data?.message || 'Gagal menghapus diskusi.',
          confirmButtonColor: '#0E5C44',
        })
      }
    }
  }

  const handleTogglePin = async (id) => {
    try {
      const res = await lmsDiskusiService.togglePin(id)
      if (res.success) {
        fetchData()
        fetchStats()
      }
    } catch (err) {
      console.error('Error toggling pin:', err)
    }
  }

  const handleToggleClose = async (id) => {
    try {
      const res = await lmsDiskusiService.toggleClose(id)
      if (res.success) {
        fetchData()
        fetchStats()
        if (selectedDiskusi && selectedDiskusi.id === id) {
          fetchThreadDetail(id)
        }
      }
    } catch (err) {
      console.error('Error toggling close:', err)
    }
  }

  // Open Comment Thread Detail Drawer
  const handleOpenThread = async (diskusiId) => {
    setIsThreadOpen(true)
    setReplyParentId(null)
    setKomentarForm({ konten: '', peran_pengirim: 'Guru' })
    await fetchThreadDetail(diskusiId)
  }

  const fetchThreadDetail = async (diskusiId) => {
    setThreadLoading(true)
    try {
      const res = await lmsDiskusiService.getById(diskusiId)
      if (res.success) {
        setSelectedDiskusi(res.data)
      }
    } catch (err) {
      console.error('Error fetching thread detail:', err)
    } finally {
      setThreadLoading(false)
    }
  }

  const handlePostKomentar = async (e) => {
    e.preventDefault()
    if (!komentarForm.konten.trim()) return

    setSubmittingKomentar(true)
    try {
      const payload = {
        konten: komentarForm.konten,
        peran_pengirim: komentarForm.peran_pengirim,
        parent_id: replyParentId,
      }
      const res = await lmsDiskusiService.tambahKomentar(selectedDiskusi.id, payload)
      if (res.success) {
        setKomentarForm({ ...komentarForm, konten: '' })
        setReplyParentId(null)
        await fetchThreadDetail(selectedDiskusi.id)
        fetchStats()
      }
    } catch (err) {
      Swal.fire({
        icon: 'error',
        title: 'Gagal',
        text: err.response?.data?.message || 'Gagal mengirim komentar.',
        confirmButtonColor: '#0E5C44',
      })
    } finally {
      setSubmittingKomentar(false)
    }
  }

  const handleDeleteKomentar = async (komentarId) => {
    const result = await Swal.fire({
      title: 'Hapus Komentar?',
      text: 'Apakah Anda yakin ingin menghapus komentar ini?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#ef4444',
      cancelButtonColor: '#6b7280',
      confirmButtonText: 'Hapus',
      cancelButtonText: 'Batal',
    })

    if (result.isConfirmed) {
      try {
        const res = await lmsDiskusiService.hapusKomentar(selectedDiskusi.id, komentarId)
        if (res.success) {
          await fetchThreadDetail(selectedDiskusi.id)
          fetchStats()
        }
      } catch (err) {
        console.error('Gagal menghapus komentar:', err)
      }
    }
  }

  return (
    <div className="min-h-screen bg-[#F7F9FC] dark:bg-[#0F172A] p-4 md:p-8 font-sans text-slate-800 dark:text-slate-100 transition-colors duration-300">
      {/* Header Banner */}
      <div className="relative overflow-hidden rounded-[18px] bg-gradient-to-r from-[#0E5C44] via-[#1E8E5A] to-[#3FBF75] p-6 md:p-8 text-white shadow-xl mb-8">
        <div className="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <div className="flex items-center gap-2 text-emerald-200 text-sm font-semibold tracking-wide uppercase mb-1">
              <Sparkles className="w-4 h-4" /> LMS Modul Ajar Terpadu
            </div>
            <h1 className="text-2xl md:text-3xl font-extrabold tracking-tight">
              Forum Diskusi Kelas
            </h1>
            <p className="mt-2 text-emerald-100 max-w-2xl text-sm md:text-base leading-relaxed">
              Ruang kolaborasi dan tanya jawab interaktif antara Guru dan Siswa berbasis Modul Ajar untuk meningkatkan keterlibatan belajar.
            </p>
          </div>
          <div>
            <button
              onClick={handleOpenCreateModal}
              className="inline-flex items-center gap-2 bg-white text-[#0E5C44] font-bold px-5 py-3 rounded-xl shadow-lg hover:bg-emerald-50 hover:scale-[1.02] active:scale-[0.98] transition-all text-sm md:text-base"
            >
              <Plus className="w-5 h-5" /> Buat Diskusi Baru
            </button>
          </div>
        </div>
      </div>

      {/* Notifications Alert */}
      {successMsg && (
        <div className="mb-6 p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 flex items-center justify-between shadow-sm animate-fadeIn">
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
        <div className="mb-6 p-4 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-200 flex items-center justify-between shadow-sm animate-fadeIn">
          <div className="flex items-center gap-3">
            <AlertCircle className="w-5 h-5 text-rose-600 dark:text-rose-400" />
            <span className="text-sm font-medium">{errorMsg}</span>
          </div>
          <button onClick={() => setErrorMsg('')} className="text-rose-600 hover:text-rose-800">
            <X className="w-4 h-4" />
          </button>
        </div>
      )}

      {/* KPI Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div className="bg-white dark:bg-[#1B2433] p-5 rounded-[18px] border border-slate-200/80 dark:border-slate-800 shadow-sm hover:-translate-y-1 transition-all duration-300">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400 tracking-wider">
                Total Diskusi
              </p>
              <h3 className="text-2xl font-black mt-1 text-slate-900 dark:text-white">
                {stats.total_diskusi}
              </h3>
            </div>
            <div className="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 text-[#0E5C44] dark:text-emerald-400">
              <MessageSquare className="w-6 h-6" />
            </div>
          </div>
          <div className="mt-3 flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
            <span className="font-semibold text-emerald-600 dark:text-emerald-400">{stats.diskusi_pinned} pinned</span>
            <span>•</span>
            <span>{stats.diskusi_ditutup} ditutup</span>
          </div>
        </div>

        <div className="bg-white dark:bg-[#1B2433] p-5 rounded-[18px] border border-slate-200/80 dark:border-slate-800 shadow-sm hover:-translate-y-1 transition-all duration-300">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400 tracking-wider">
                Diskusi Aktif
              </p>
              <h3 className="text-2xl font-black mt-1 text-emerald-600 dark:text-emerald-400">
                {stats.diskusi_aktif}
              </h3>
            </div>
            <div className="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 text-[#1E8E5A] dark:text-emerald-400">
              <BookOpen className="w-6 h-6" />
            </div>
          </div>
          <div className="mt-3 flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
            <span>Siap menerima tanggapan</span>
          </div>
        </div>

        <div className="bg-white dark:bg-[#1B2433] p-5 rounded-[18px] border border-slate-200/80 dark:border-slate-800 shadow-sm hover:-translate-y-1 transition-all duration-300">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400 tracking-wider">
                Total Komentar
              </p>
              <h3 className="text-2xl font-black mt-1 text-slate-900 dark:text-white">
                {stats.total_komentar}
              </h3>
            </div>
            <div className="p-3 rounded-xl bg-blue-50 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400">
              <MessageCircle className="w-6 h-6" />
            </div>
          </div>
          <div className="mt-3 flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
            <span>Tanggapan & Pertanyaan</span>
          </div>
        </div>

        <div className="bg-white dark:bg-[#1B2433] p-5 rounded-[18px] border border-slate-200/80 dark:border-slate-800 shadow-sm hover:-translate-y-1 transition-all duration-300">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400 tracking-wider">
                Interaksi Guru & Siswa
              </p>
              <div className="flex items-baseline gap-2 mt-1">
                <span className="text-lg font-bold text-emerald-700 dark:text-emerald-400">{stats.komentar_guru} Guru</span>
                <span className="text-slate-400">/</span>
                <span className="text-lg font-bold text-blue-600 dark:text-blue-400">{stats.komentar_siswa} Siswa</span>
              </div>
            </div>
            <div className="p-3 rounded-xl bg-purple-50 dark:bg-purple-950/50 text-purple-600 dark:text-purple-400">
              <GraduationCap className="w-6 h-6" />
            </div>
          </div>
          <div className="mt-3 flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
            <span>Keaktifan forum terpadu</span>
          </div>
        </div>
      </div>

      {/* Filter & Search Bar */}
      <div className="bg-white dark:bg-[#1B2433] p-5 rounded-[18px] border border-slate-200/80 dark:border-slate-800 shadow-sm mb-6">
        <div className="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-4">
          <div className="relative flex-1">
            <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
            <input
              type="text"
              placeholder="Cari judul diskusi, deskripsi, atau kategori..."
              value={search}
              onChange={(e) => {
                setSearch(e.target.value)
                setPage(1)
              }}
              className="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44] transition-all"
            />
          </div>

          <div className="flex flex-wrap items-center gap-3">
            <div className="flex items-center gap-2 bg-slate-50 dark:bg-slate-900/50 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700">
              <SlidersHorizontal className="w-4 h-4 text-slate-400" />
              <select
                value={selectedModulAjar}
                onChange={(e) => {
                  setSelectedModulAjar(e.target.value)
                  setPage(1)
                }}
                className="bg-transparent text-xs md:text-sm font-medium focus:outline-none text-slate-700 dark:text-slate-200"
              >
                <option value="">Semua Modul Ajar</option>
                {optionsModulAjar.map((opt) => (
                  <option key={opt.value || opt.id} value={opt.value || opt.id}>
                    {opt.label || opt.judul_modul || opt.judul}
                  </option>
                ))}
              </select>
            </div>

            <div className="flex items-center gap-2 bg-slate-50 dark:bg-slate-900/50 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700">
              <Tag className="w-4 h-4 text-slate-400" />
              <select
                value={selectedKategori}
                onChange={(e) => {
                  setSelectedKategori(e.target.value)
                  setPage(1)
                }}
                className="bg-transparent text-xs md:text-sm font-medium focus:outline-none text-slate-700 dark:text-slate-200"
              >
                <option value="">Semua Kategori</option>
                {optionsKategori.map((kat) => (
                  <option key={kat} value={kat}>
                    {kat}
                  </option>
                ))}
              </select>
            </div>

            <div className="flex items-center gap-2 bg-slate-50 dark:bg-slate-900/50 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700">
              <select
                value={selectedStatus}
                onChange={(e) => {
                  setSelectedStatus(e.target.value)
                  setPage(1)
                }}
                className="bg-transparent text-xs md:text-sm font-medium focus:outline-none text-slate-700 dark:text-slate-200"
              >
                <option value="">Semua Status</option>
                <option value="aktif">Aktif</option>
                <option value="draft">Draft</option>
                <option value="nonaktif">Nonaktif</option>
              </select>
            </div>

            <button
              onClick={() => {
                setSearch('')
                setSelectedModulAjar('')
                setSelectedKategori('')
                setSelectedStatus('')
                setPage(1)
              }}
              className="p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 transition-all"
              title="Reset Filter"
            >
              <RefreshCw className="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>

      {/* Main Content Table / List */}
      <div className="bg-white dark:bg-[#1B2433] rounded-[18px] border border-slate-200/80 dark:border-slate-800 shadow-sm overflow-hidden mb-6">
        {loading ? (
          <div className="p-12 text-center">
            <RefreshCw className="w-8 h-8 animate-spin mx-auto text-[#0E5C44] mb-3" />
            <p className="text-sm font-medium text-slate-500 dark:text-slate-400">
              Memuat data diskusi kelas...
            </p>
          </div>
        ) : dataDiskusi.length === 0 ? (
          <div className="p-12 text-center">
            <MessageSquare className="w-12 h-12 mx-auto text-slate-300 dark:text-slate-600 mb-3" />
            <h4 className="text-lg font-bold text-slate-700 dark:text-slate-200">
              Belum Ada Diskusi
            </h4>
            <p className="text-sm text-slate-500 dark:text-slate-400 max-w-md mx-auto mt-1">
              Belum ada forum diskusi yang ditambahkan untuk kriteria filter ini. Silakan buat diskusi kelas baru.
            </p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm text-slate-600 dark:text-slate-300">
              <thead className="bg-slate-50/80 dark:bg-slate-900/60 text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                <tr>
                  <th className="py-4 px-6 font-semibold">Diskusi & Modul Ajar</th>
                  <th className="py-4 px-6 font-semibold">Kategori</th>
                  <th className="py-4 px-6 font-semibold">Status & Akses</th>
                  <th className="py-4 px-6 font-semibold text-center">Komentar</th>
                  <th className="py-4 px-6 font-semibold text-right">Aksi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60">
                {dataDiskusi.map((item) => (
                  <tr
                    key={item.id}
                    className="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition-colors"
                  >
                    <td className="py-4 px-6">
                      <div className="flex items-start gap-3">
                        <button
                          onClick={() => handleTogglePin(item.id)}
                          className={`mt-1 p-1 rounded-lg transition-colors ${
                            item.is_pinned
                              ? 'text-amber-500 bg-amber-50 dark:bg-amber-950/40'
                              : 'text-slate-300 hover:text-slate-500 dark:text-slate-600'
                          }`}
                          title={item.is_pinned ? 'Lepas Pin' : 'Sematkan di Atas'}
                        >
                          <Pin className="w-4 h-4 fill-current" />
                        </button>
                        <div>
                          <div className="flex items-center gap-2">
                            <h4
                              onClick={() => handleOpenThread(item.id)}
                              className="font-bold text-slate-900 dark:text-white hover:text-[#0E5C44] cursor-pointer transition-colors"
                            >
                              {item.judul}
                            </h4>
                            {item.is_closed && (
                              <span className="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
                                <Lock className="w-3 h-3" /> Ditutup
                              </span>
                            )}
                          </div>
                          {item.modul_ajar ? (
                            <p className="text-xs text-emerald-700 dark:text-emerald-400 font-medium mt-0.5 flex items-center gap-1">
                              <BookOpen className="w-3 h-3 inline" /> {item.modul_ajar.judul}
                            </p>
                          ) : (
                            <p className="text-xs text-slate-400 mt-0.5">Umum (Tanpa Modul)</p>
                          )}
                          <p className="text-xs text-slate-500 dark:text-slate-400 mt-1 line-clamp-1 max-w-xl">
                            {item.deskripsi || 'Tidak ada deskripsi'}
                          </p>
                        </div>
                      </div>
                    </td>

                    <td className="py-4 px-6">
                      <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-semibold bg-emerald-50 dark:bg-emerald-950/40 text-[#0E5C44] dark:text-emerald-300 border border-emerald-200/60 dark:border-emerald-800/60">
                        <Tag className="w-3 h-3" /> {item.kategori}
                      </span>
                    </td>

                    <td className="py-4 px-6">
                      <div className="space-y-1">
                        <span
                          className={`inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold ${
                            item.status === 'aktif'
                              ? 'bg-emerald-100 dark:bg-emerald-900/60 text-emerald-800 dark:text-emerald-200'
                              : item.status === 'draft'
                              ? 'bg-amber-100 dark:bg-amber-900/60 text-amber-800 dark:text-amber-200'
                              : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300'
                          }`}
                        >
                          {item.status === 'aktif' ? 'Aktif' : item.status === 'draft' ? 'Draft' : 'Nonaktif'}
                        </span>
                        {item.created_at_formatted && (
                          <p className="text-[11px] text-slate-400 flex items-center gap-1">
                            <Clock className="w-3 h-3" /> {item.created_at_formatted}
                          </p>
                        )}
                      </div>
                    </td>

                    <td className="py-4 px-6 text-center">
                      <button
                        onClick={() => handleOpenThread(item.id)}
                        className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-emerald-50 dark:hover:bg-emerald-950 text-slate-700 dark:text-slate-200 hover:text-[#0E5C44] dark:hover:text-emerald-400 text-xs font-bold transition-all"
                      >
                        <MessageCircle className="w-4 h-4" />
                        <span>{item.jumlah_komentar} Komentar</span>
                      </button>
                    </td>

                    <td className="py-4 px-6 text-right space-x-1">
                      <button
                        onClick={() => handleToggleClose(item.id)}
                        className={`p-2 rounded-xl border transition-all ${
                          item.is_closed
                            ? 'border-amber-200 dark:border-amber-800 text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-950'
                            : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'
                        }`}
                        title={item.is_closed ? 'Buka Kunci Diskusi' : 'Kunci / Tutup Diskusi'}
                      >
                        {item.is_closed ? <Unlock className="w-4 h-4" /> : <Lock className="w-4 h-4" />}
                      </button>

                      <button
                        onClick={() => handleOpenEditModal(item)}
                        className="p-2 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all"
                        title="Edit Diskusi"
                      >
                        <Edit3 className="w-4 h-4" />
                      </button>

                      <button
                        onClick={() => handleDelete(item.id, item.judul)}
                        className="p-2 rounded-xl border border-rose-200 dark:border-rose-900/60 text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950 transition-all"
                        title="Hapus Diskusi"
                      >
                        <Trash2 className="w-4 h-4" />
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {/* Pagination Footer */}
        {pagination.last_page > 1 && (
          <div className="p-4 bg-slate-50/50 dark:bg-slate-900/40 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between">
            <p className="text-xs text-slate-500 dark:text-slate-400">
              Menampilkan Halaman <span className="font-bold">{pagination.current_page}</span> dari{' '}
              <span className="font-bold">{pagination.last_page}</span> ({pagination.total} Diskusi)
            </p>
            <div className="flex items-center gap-2">
              <button
                disabled={page <= 1}
                onClick={() => setPage((prev) => Math.max(prev - 1, 1))}
                className="p-2 rounded-lg border border-slate-200 dark:border-slate-700 disabled:opacity-40 hover:bg-white dark:hover:bg-slate-800 transition-all"
              >
                <ChevronLeft className="w-4 h-4" />
              </button>
              <button
                disabled={page >= pagination.last_page}
                onClick={() => setPage((prev) => Math.min(prev + 1, pagination.last_page))}
                className="p-2 rounded-lg border border-slate-200 dark:border-slate-700 disabled:opacity-40 hover:bg-white dark:hover:bg-slate-800 transition-all"
              >
                <ChevronRight className="w-4 h-4" />
              </button>
            </div>
          </div>
        )}
      </div>

      {/* Modal CRUD Form */}
      {isModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
          <div className="bg-white dark:bg-[#1B2433] rounded-[18px] shadow-2xl border border-slate-200 dark:border-slate-800 w-full max-w-2xl overflow-hidden animate-fadeIn">
            <div className="p-6 bg-slate-50 dark:bg-slate-900/60 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
              <div className="flex items-center gap-3">
                <div className="p-2.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-[#0E5C44] dark:text-emerald-400">
                  <MessageSquare className="w-5 h-5" />
                </div>
                <div>
                  <h3 className="text-lg font-bold text-slate-900 dark:text-white">
                    {editId ? 'Edit Diskusi Kelas' : 'Buat Diskusi Kelas Baru'}
                  </h3>
                  <p className="text-xs text-slate-500 dark:text-slate-400">
                    Isi informasi diskusi untuk dipublikasikan ke forum modul ajar
                  </p>
                </div>
              </div>
              <button
                onClick={() => setIsModalOpen(false)}
                className="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleSaveForm} className="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
              <div>
                <label className="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">
                  Modul Ajar Relasi
                </label>
                <select
                  value={formData.modul_ajar_id}
                  onChange={(e) => setFormData({ ...formData, modul_ajar_id: e.target.value })}
                  className="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm focus:ring-2 focus:ring-[#0E5C44] outline-none"
                >
                  <option value="">-- Tanpa Modul (Diskusi Umum) --</option>
                  {optionsModulAjar.map((opt) => (
                    <option key={opt.value || opt.id} value={opt.value || opt.id}>
                      {opt.label || opt.judul_modul || opt.judul}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">
                  Judul Diskusi <span className="text-rose-500">*</span>
                </label>
                <input
                  type="text"
                  required
                  placeholder="Contoh: Pemahaman Kasus Hukum Newton II..."
                  value={formData.judul}
                  onChange={(e) => setFormData({ ...formData, judul: e.target.value })}
                  className="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm focus:ring-2 focus:ring-[#0E5C44] outline-none"
                />
              </div>

              <div>
                <label className="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">
                  Kategori Diskusi
                </label>
                <select
                  value={formData.kategori}
                  onChange={(e) => setFormData({ ...formData, kategori: e.target.value })}
                  className="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm focus:ring-2 focus:ring-[#0E5C44] outline-none"
                >
                  {optionsKategori.map((kat) => (
                    <option key={kat} value={kat}>
                      {kat}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">
                  Deskripsi / Pertanyaan Pemicu
                </label>
                <textarea
                  rows={4}
                  placeholder="Tuliskan petunjuk diskusi, topik utama, atau pemantik pertanyaan bagi siswa..."
                  value={formData.deskripsi}
                  onChange={(e) => setFormData({ ...formData, deskripsi: e.target.value })}
                  className="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm focus:ring-2 focus:ring-[#0E5C44] outline-none"
                />
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">
                    Tanggal Mulai
                  </label>
                  <input
                    type="datetime-local"
                    value={formData.tanggal_mulai}
                    onChange={(e) => setFormData({ ...formData, tanggal_mulai: e.target.value })}
                    className="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm focus:ring-2 focus:ring-[#0E5C44] outline-none"
                  />
                </div>

                <div>
                  <label className="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">
                    Tanggal Tutup
                  </label>
                  <input
                    type="datetime-local"
                    value={formData.tanggal_tutup}
                    onChange={(e) => setFormData({ ...formData, tanggal_tutup: e.target.value })}
                    className="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm focus:ring-2 focus:ring-[#0E5C44] outline-none"
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">
                  Status Diskusi
                </label>
                <select
                  value={formData.status}
                  onChange={(e) => setFormData({ ...formData, status: e.target.value })}
                  className="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm focus:ring-2 focus:ring-[#0E5C44] outline-none"
                >
                  <option value="aktif">Aktif</option>
                  <option value="draft">Draft</option>
                  <option value="nonaktif">Nonaktif</option>
                </select>
              </div>

              <div className="pt-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-end gap-3">
                <button
                  type="button"
                  onClick={() => setIsModalOpen(false)}
                  className="px-5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 text-sm font-semibold hover:bg-slate-100 dark:hover:bg-slate-800 transition-all"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={formLoading}
                  className="px-6 py-2.5 rounded-xl bg-[#0E5C44] hover:bg-[#0B4A36] text-white text-sm font-bold shadow-lg shadow-emerald-900/20 disabled:opacity-50 transition-all flex items-center gap-2"
                >
                  {formLoading && <RefreshCw className="w-4 h-4 animate-spin" />}
                  <span>{editId ? 'Perbarui Diskusi' : 'Simpan Diskusi'}</span>
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Interactive Thread & Comments Drawer/Modal */}
      {isThreadOpen && selectedDiskusi && (
        <div className="fixed inset-0 z-50 flex justify-end bg-slate-900/60 backdrop-blur-sm">
          <div className="bg-white dark:bg-[#1B2433] w-full max-w-2xl h-full shadow-2xl flex flex-col justify-between border-l border-slate-200 dark:border-slate-800 animate-slideLeft">
            {/* Drawer Header */}
            <div className="p-6 bg-slate-50 dark:bg-slate-900/60 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
              <div className="flex items-center gap-3">
                <div className="p-2.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-[#0E5C44] dark:text-emerald-400">
                  <MessageCircle className="w-5 h-5" />
                </div>
                <div>
                  <span className="text-xs font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">
                    Utasan Komentar Diskusi
                  </span>
                  <h3 className="text-lg font-bold text-slate-900 dark:text-white line-clamp-1">
                    {selectedDiskusi.judul}
                  </h3>
                </div>
              </div>
              <button
                onClick={() => setIsThreadOpen(false)}
                className="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            {/* Main Thread Body */}
            <div className="p-6 flex-1 overflow-y-auto space-y-6">
              {/* Discussion Prompt Card */}
              <div className="p-5 rounded-[18px] bg-slate-50 dark:bg-slate-900/60 border border-slate-200/80 dark:border-slate-800">
                <div className="flex items-center justify-between mb-2">
                  <span className="px-2.5 py-0.5 rounded-md text-[11px] font-bold bg-emerald-100 dark:bg-emerald-950 text-[#0E5C44] dark:text-emerald-300">
                    {selectedDiskusi.kategori}
                  </span>
                  {selectedDiskusi.modul_ajar && (
                    <span className="text-xs text-slate-500 font-medium">
                      {selectedDiskusi.modul_ajar.judul}
                    </span>
                  )}
                </div>
                <p className="text-sm text-slate-700 dark:text-slate-200 leading-relaxed whitespace-pre-line">
                  {selectedDiskusi.deskripsi || 'Tidak ada deskripsi rinci.'}
                </p>
                {selectedDiskusi.is_closed && (
                  <div className="mt-4 p-3 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-200 text-xs flex items-center gap-2">
                    <Lock className="w-4 h-4 shrink-0 text-amber-600" />
                    <span>Diskusi telah dikunci oleh pengajar. Komentar baru ditutup.</span>
                  </div>
                )}
              </div>

              {/* Comments List */}
              <div>
                <h4 className="text-xs font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-4 flex items-center justify-between">
                  <span>Tanggapan & Komentar ({selectedDiskusi.jumlah_komentar || 0})</span>
                  {threadLoading && <RefreshCw className="w-3.5 h-3.5 animate-spin text-[#0E5C44]" />}
                </h4>

                {selectedDiskusi.komentar && selectedDiskusi.komentar.length > 0 ? (
                  <div className="space-y-4">
                    {selectedDiskusi.komentar.map((kom) => (
                      <div
                        key={kom.id}
                        className="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm"
                      >
                        <div className="flex items-center justify-between mb-2">
                          <div className="flex items-center gap-2">
                            <span
                              className={`px-2 py-0.5 rounded-full text-[10px] font-bold ${
                                kom.peran_pengirim === 'Guru'
                                  ? 'bg-emerald-100 dark:bg-emerald-950 text-[#0E5C44] dark:text-emerald-300'
                                  : 'bg-blue-100 dark:bg-blue-950 text-blue-700 dark:text-blue-300'
                              }`}
                            >
                              {kom.peran_pengirim}
                            </span>
                            <span className="text-xs font-bold text-slate-800 dark:text-slate-200">
                              {kom.nama_pengirim}
                            </span>
                          </div>
                          <div className="flex items-center gap-2 text-[11px] text-slate-400">
                            <span>{kom.created_at_formatted}</span>
                            <button
                              onClick={() => handleDeleteKomentar(kom.id)}
                              className="text-slate-400 hover:text-rose-500 transition-colors"
                              title="Hapus Komentar"
                            >
                              <Trash2 className="w-3.5 h-3.5" />
                            </button>
                          </div>
                        </div>

                        <p className="text-sm text-slate-700 dark:text-slate-300 leading-relaxed">
                          {kom.konten}
                        </p>

                        {/* Reply Action */}
                        {!selectedDiskusi.is_closed && (
                          <button
                            onClick={() => setReplyParentId(replyParentId === kom.id ? null : kom.id)}
                            className="mt-2 text-xs font-bold text-[#0E5C44] dark:text-emerald-400 hover:underline flex items-center gap-1"
                          >
                            <CornerDownRight className="w-3.5 h-3.5" /> Balas
                          </button>
                        )}

                        {/* Nested Replies */}
                        {kom.replies && kom.replies.length > 0 && (
                          <div className="mt-3 pl-4 border-l-2 border-emerald-500/30 space-y-3">
                            {kom.replies.map((reply) => (
                              <div key={reply.id} className="pt-2">
                                <div className="flex items-center justify-between mb-1">
                                  <div className="flex items-center gap-2">
                                    <span
                                      className={`px-1.5 py-0.5 rounded text-[9px] font-bold ${
                                        reply.peran_pengirim === 'Guru'
                                          ? 'bg-emerald-100 dark:bg-emerald-950 text-[#0E5C44] dark:text-emerald-300'
                                          : 'bg-blue-100 dark:bg-blue-950 text-blue-700 dark:text-blue-300'
                                      }`}
                                    >
                                      {reply.peran_pengirim}
                                    </span>
                                    <span className="text-xs font-semibold text-slate-800 dark:text-slate-200">
                                      {reply.nama_pengirim}
                                    </span>
                                  </div>
                                  <button
                                    onClick={() => handleDeleteKomentar(reply.id)}
                                    className="text-slate-400 hover:text-rose-500 transition-colors"
                                  >
                                    <Trash2 className="w-3 h-3" />
                                  </button>
                                </div>
                                <p className="text-xs text-slate-600 dark:text-slate-300">
                                  {reply.konten}
                                </p>
                              </div>
                            ))}
                          </div>
                        )}
                      </div>
                    ))}
                  </div>
                ) : (
                  <p className="text-xs text-slate-400 text-center py-6 italic">
                    Belum ada komentar pada diskusi ini. Jadilah yang pertama memberikan tanggapan!
                  </p>
                )}
              </div>
            </div>

            {/* Drawer Footer / Add Comment Form */}
            {!selectedDiskusi.is_closed && (
              <div className="p-4 bg-slate-50 dark:bg-slate-900/80 border-t border-slate-200 dark:border-slate-800">
                {replyParentId && (
                  <div className="mb-2 p-2 rounded-lg bg-emerald-50 dark:bg-emerald-950/50 text-xs text-emerald-800 dark:text-emerald-300 flex items-center justify-between">
                    <span>Membalas komentar terpilih...</span>
                    <button
                      onClick={() => setReplyParentId(null)}
                      className="text-emerald-600 font-bold hover:underline"
                    >
                      Batal
                    </button>
                  </div>
                )}

                <form onSubmit={handlePostKomentar} className="space-y-3">
                  <div className="flex items-center justify-between gap-2">
                    <div className="flex items-center gap-2">
                      <span className="text-xs font-bold text-slate-600 dark:text-slate-400">
                        Kirim Sebagai:
                      </span>
                      <select
                        value={komentarForm.peran_pengirim}
                        onChange={(e) =>
                          setKomentarForm({ ...komentarForm, peran_pengirim: e.target.value })
                        }
                        className="px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-bold focus:outline-none"
                      >
                        <option value="Guru">Guru (Pengajar)</option>
                        <option value="Siswa">Siswa (Peserta)</option>
                        <option value="Admin">Admin</option>
                      </select>
                    </div>
                  </div>

                  <div className="flex items-center gap-2">
                    <input
                      type="text"
                      required
                      placeholder="Tuliskan komentar atau jawaban Anda..."
                      value={komentarForm.konten}
                      onChange={(e) =>
                        setKomentarForm({ ...komentarForm, konten: e.target.value })
                      }
                      className="flex-1 px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm focus:ring-2 focus:ring-[#0E5C44] outline-none"
                    />
                    <button
                      type="submit"
                      disabled={submittingKomentar || !komentarForm.konten.trim()}
                      className="px-5 py-2.5 rounded-xl bg-[#0E5C44] hover:bg-[#0B4A36] text-white font-bold text-sm shadow-md disabled:opacity-50 flex items-center gap-1.5 shrink-0 transition-all hover:scale-[1.02] active:scale-[0.98]"
                    >
                      {submittingKomentar ? (
                        <RefreshCw className="w-4 h-4 animate-spin" />
                      ) : (
                        <Send className="w-4 h-4" />
                      )}
                      <span>Kirim</span>
                    </button>
                  </div>
                </form>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  )
}
