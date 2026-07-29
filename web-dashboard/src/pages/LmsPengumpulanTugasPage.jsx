import { useState, useEffect } from 'react'
import {
  UploadCloud,
  FileText,
  Link as LinkIcon,
  Plus,
  Search,
  Edit3,
  Trash2,
  RefreshCw,
  CheckCircle2,
  AlertCircle,
  X,
  Send,
  GraduationCap,
  ChevronLeft,
  ChevronRight,
  Clock,
  SlidersHorizontal,
  Award,
  BookOpen,
  User,
  Check,
  Eye,
  ExternalLink,
  File,
  AlertTriangle,
} from 'lucide-react'
import Swal from 'sweetalert2'
import { lmsPengumpulanTugasService } from '../services/lmsPengumpulanTugasService'
import { api } from '../services/api'

export default function LmsPengumpulanTugasPage() {
  const [dataPengumpulan, setDataPengumpulan] = useState([])
  const [options, setOptions] = useState({
    penugasan: [],
    siswa: [],
    status: [
      { id: 'belum', label: 'Belum Kumpul' },
      { id: 'dikumpulkan', label: 'Dikumpulkan' },
      { id: 'terlambat', label: 'Terlambat' },
      { id: 'dinilai', label: 'Sudah Dinilai' },
      { id: 'revisi', label: 'Perlu Revisi' },
    ],
  })

  const [stats, setStats] = useState({
    total: 0,
    dikumpulkan: 0,
    terlambat: 0,
    dinilai: 0,
    belum_dinilai: 0,
    revisi: 0,
  })

  const [loading, setLoading] = useState(true)
  const [errorMsg, setErrorMsg] = useState('')
  const [successMsg, setSuccessMsg] = useState('')

  // Filters & Pagination
  const [search, setSearch] = useState('')
  const [selectedPenugasan, setSelectedPenugasan] = useState('')
  const [selectedSiswa, setSelectedSiswa] = useState('')
  const [selectedStatus, setSelectedStatus] = useState('')
  const [page, setPage] = useState(1)
  const [pagination, setPagination] = useState({
    current_page: 1,
    last_page: 1,
    total: 0,
    per_page: 15,
  })

  // Modal Form State (Submit / Grade / Edit)
  const [isModalOpen, setIsModalOpen] = useState(false)
  const [editId, setEditId] = useState(null)
  const [formLoading, setFormLoading] = useState(false)

  const [formData, setFormData] = useState({
    penugasan_id: '',
    siswa_id: '',
    file: '',
    link: '',
    catatan: '',
    nilai: '',
    status: 'dikumpulkan',
  })

  // Detail Drawer State
  const [selectedDetail, setSelectedDetail] = useState(null)
  const [isDetailOpen, setIsDetailOpen] = useState(false)

  const fetchOptionsAndStats = async () => {
    try {
      let penugasanOptions = []
      let siswaOptions = []

      try {
        const optRes = await lmsPengumpulanTugasService.getOptions()
        const optData = optRes?.data || optRes
        if (optData) {
          if (Array.isArray(optData.penugasan) && optData.penugasan.length > 0) {
            penugasanOptions = optData.penugasan
          }
          if (Array.isArray(optData.siswa) && optData.siswa.length > 0) {
            siswaOptions = optData.siswa
          }
        }
      } catch (errOpt) {
        console.warn('Error from main options endpoint:', errOpt)
      }

      // Fallback for Penugasan if empty
      if (penugasanOptions.length === 0) {
        try {
          const penRes = await api.get('/lms/penugasan', { params: { per_page: 100 } })
          const penList = penRes?.data?.data || penRes?.data || []
          if (Array.isArray(penList)) {
            penugasanOptions = penList.map((p) => ({
              id: p.id,
              label: p.judul_tugas || p.judul || 'Penugasan',
              subject: p.subject?.nama_mapel || p.subject?.name || (typeof p.subject === 'string' ? p.subject : null),
              kelas: p.kelas?.nama_kelas || p.kelas?.kode_kelas || (typeof p.kelas === 'string' ? p.kelas : null),
            }))
          }
        } catch (errPen) {
          console.warn('Fallback penugasan error:', errPen)
        }
      }

      // Fallback for Siswa if empty
      if (siswaOptions.length === 0) {
        try {
          const stdRes = await api.get('/students', { params: { per_page: 100 } })
          const stdList = stdRes?.data?.data || stdRes?.data || []
          if (Array.isArray(stdList)) {
            siswaOptions = stdList.map((s) => ({
              id: s.id,
              label: s.full_name || s.name || s.nama_lengkap || 'Siswa',
              nisn: s.nisn || s.metadata?.nisn || null,
            }))
          }
        } catch (errStd) {
          console.warn('Fallback student error:', errStd)
        }
      }

      setOptions((prev) => ({
        ...prev,
        penugasan: penugasanOptions,
        siswa: siswaOptions,
      }))

      try {
        const statRes = await lmsPengumpulanTugasService.getStats()
        if (statRes && (statRes.data || statRes.total !== undefined)) {
          setStats(statRes.data || statRes)
        }
      } catch (errStat) {
        console.warn('Error fetching stats:', errStat)
      }
    } catch (err) {
      console.error('Error in fetchOptionsAndStats:', err)
    }
  }

  const fetchData = async () => {
    setLoading(true)
    setErrorMsg('')
    try {
      const params = {
        page,
        per_page: 15,
        search: search || undefined,
        penugasan_id: selectedPenugasan || undefined,
        siswa_id: selectedSiswa || undefined,
        status: selectedStatus || undefined,
      }
      const res = await lmsPengumpulanTugasService.getDaftar(params)
      if (res && res.data) {
        setDataPengumpulan(res.data)
        if (res.meta) {
          setPagination({
            current_page: res.meta.current_page || 1,
            last_page: res.meta.last_page || 1,
            total: res.meta.total || res.data.length,
            per_page: res.meta.per_page || 15,
          })
        }
      }
    } catch (err) {
      setErrorMsg('Gagal memuat data pengumpulan tugas.')
      console.error(err)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    fetchOptionsAndStats()
  }, [])

  useEffect(() => {
    fetchData()
  }, [page, selectedPenugasan, selectedSiswa, selectedStatus])

  const handleSearchSubmit = (e) => {
    e.preventDefault()
    setPage(1)
    fetchData()
  }

  const handleResetFilter = () => {
    setSearch('')
    setSelectedPenugasan('')
    setSelectedSiswa('')
    setSelectedStatus('')
    setPage(1)
  }

  const handleOpenCreateModal = () => {
    setEditId(null)
    setFormData({
      penugasan_id: '',
      siswa_id: '',
      file: '',
      link: '',
      catatan: '',
      nilai: '',
      status: 'dikumpulkan',
    })
    if (options.penugasan.length === 0 || options.siswa.length === 0) {
      fetchOptionsAndStats()
    }
    setIsModalOpen(true)
  }

  const handleOpenEditModal = (item) => {
    setEditId(item.id)
    setFormData({
      penugasan_id: item.penugasan_id || '',
      siswa_id: item.siswa_id || '',
      file: item.file || item.file_path || '',
      link: item.link || item.url_link || '',
      catatan: item.catatan || item.catatan_guru || item.jawaban_teks || '',
      nilai: item.nilai !== null && item.nilai !== undefined ? item.nilai : (item.nilai_guru ?? ''),
      status: item.status || 'dikumpulkan',
    })
    setIsModalOpen(true)
  }

  const handleSubmitForm = async (e) => {
    e.preventDefault()
    setFormLoading(true)
    setErrorMsg('')
    setSuccessMsg('')

    try {
      const payload = {
        penugasan_id: formData.penugasan_id,
        siswa_id: formData.siswa_id,
        file: formData.file,
        link: formData.link,
        catatan: formData.catatan,
        nilai: formData.nilai !== '' ? parseFloat(formData.nilai) : null,
        status: formData.status,
      }

      if (editId) {
        await lmsPengumpulanTugasService.update(editId, payload)
        setSuccessMsg('Pengumpulan tugas & penilaian berhasil diperbarui.')
      } else {
        await lmsPengumpulanTugasService.create(payload)
        setSuccessMsg('Submission pengumpulan tugas baru berhasil ditambahkan.')
      }

      setIsModalOpen(false)
      fetchData()
      fetchOptionsAndStats()
    } catch (err) {
      console.error(err)
      const errRes = err.response?.data?.message || 'Gagal menyimpan pengumpulan tugas.'
      setErrorMsg(errRes)
    } finally {
      setFormLoading(false)
    }
  }

  const handleDelete = async (id, namaSiswa) => {
    const result = await Swal.fire({
      title: 'Hapus Submission?',
      text: `Apakah Anda yakin ingin menghapus data pengumpulan tugas dari ${namaSiswa || 'Siswa'}?`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#0E5C44',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Ya, Hapus!',
      cancelButtonText: 'Batal',
      customClass: {
        popup: 'rounded-2xl shadow-2xl border border-slate-100',
      },
    })

    if (result.isConfirmed) {
      try {
        await lmsPengumpulanTugasService.delete(id)
        Swal.fire({
          title: 'Berhasil!',
          text: 'Data pengumpulan tugas berhasil dihapus.',
          icon: 'success',
          timer: 2000,
          showConfirmButton: false,
        })
        fetchData()
        fetchOptionsAndStats()
      } catch (err) {
        console.error(err)
        Swal.fire('Error!', 'Gagal menghapus data pengumpulan tugas.', 'error')
      }
    }
  }

  const handleOpenDetail = (item) => {
    setSelectedDetail(item)
    setIsDetailOpen(true)
  }

  const getStatusBadge = (status) => {
    switch (status) {
      case 'dinilai':
        return (
          <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
            <CheckCircle2 className="w-3.5 h-3.5" />
            Sudah Dinilai
          </span>
        )
      case 'dikumpulkan':
        return (
          <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200">
            <UploadCloud className="w-3.5 h-3.5" />
            Dikumpulkan
          </span>
        )
      case 'terlambat':
        return (
          <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">
            <Clock className="w-3.5 h-3.5" />
            Terlambat
          </span>
        )
      case 'revisi':
        return (
          <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-purple-50 text-purple-700 border border-purple-200">
            <AlertTriangle className="w-3.5 h-3.5" />
            Perlu Revisi
          </span>
        )
      default:
        return (
          <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-600 border border-slate-200">
            <Clock className="w-3.5 h-3.5" />
            Belum Kumpul
          </span>
        )
    }
  }

  return (
    <div className="space-y-6 pb-12">
      {/* Top Banner / Header */}
      <div className="relative overflow-hidden rounded-[18px] bg-gradient-to-r from-[#0E5C44] via-[#1E8E5A] to-[#3FBF75] p-6 sm:p-8 text-white shadow-xl">
        <div className="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 backdrop-blur-md text-emerald-100 text-xs font-medium mb-3">
              <UploadCloud className="w-4 h-4" />
              LMS Pelaksanaan Pembelajaran
            </div>
            <h1 className="text-2xl sm:text-3xl font-bold tracking-tight text-white">
              Pengumpulan Tugas Siswa
            </h1>
            <p className="mt-1 text-sm text-emerald-100 max-w-2xl">
              Kelola submission tugas siswa, riwayat pengumpulan file & link, serta proses koreksi dan penilaian hasil kerja secara terpadu.
            </p>
          </div>
          <div className="flex items-center gap-3 self-start md:self-auto">
            <button
              onClick={handleOpenCreateModal}
              className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white text-[#0E5C44] font-semibold text-sm shadow-lg hover:bg-emerald-50 transition-all duration-200 active:scale-95"
            >
              <Plus className="w-4 h-4" />
              Input / Kumpul Tugas
            </button>
          </div>
        </div>
      </div>

      {/* Alert Notifications */}
      {successMsg && (
        <div className="flex items-center justify-between p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium animate-fadeIn">
          <div className="flex items-center gap-3">
            <CheckCircle2 className="w-5 h-5 text-emerald-600 flex-shrink-0" />
            <span>{successMsg}</span>
          </div>
          <button onClick={() => setSuccessMsg('')} className="text-emerald-600 hover:text-emerald-800">
            <X className="w-4 h-4" />
          </button>
        </div>
      )}

      {errorMsg && (
        <div className="flex items-center justify-between p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm font-medium animate-fadeIn">
          <div className="flex items-center gap-3">
            <AlertCircle className="w-5 h-5 text-rose-600 flex-shrink-0" />
            <span>{errorMsg}</span>
          </div>
          <button onClick={() => setErrorMsg('')} className="text-rose-600 hover:text-rose-800">
            <X className="w-4 h-4" />
          </button>
        </div>
      )}

      {/* KPI Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="bg-white p-5 rounded-[18px] border border-slate-100 shadow-sm hover:shadow-md transition-all duration-200">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-xs font-medium text-slate-500 uppercase tracking-wider">Total Pengumpulan</p>
              <h3 className="text-2xl font-bold text-slate-800 mt-1">{stats.total || 0}</h3>
            </div>
            <div className="w-12 h-12 rounded-xl bg-emerald-50 flex items-center justify-center text-[#0E5C44]">
              <UploadCloud className="w-6 h-6" />
            </div>
          </div>
          <p className="text-xs text-slate-500 mt-2">Seluruh submission tugas siswa</p>
        </div>

        <div className="bg-white p-5 rounded-[18px] border border-slate-100 shadow-sm hover:shadow-md transition-all duration-200">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-xs font-medium text-slate-500 uppercase tracking-wider">Sudah Dinilai</p>
              <h3 className="text-2xl font-bold text-emerald-600 mt-1">{stats.dinilai || 0}</h3>
            </div>
            <div className="w-12 h-12 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-600">
              <Award className="w-6 h-6" />
            </div>
          </div>
          <p className="text-xs text-emerald-600 mt-2">Telah diberi nilai oleh guru</p>
        </div>

        <div className="bg-white p-5 rounded-[18px] border border-slate-100 shadow-sm hover:shadow-md transition-all duration-200">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-xs font-medium text-slate-500 uppercase tracking-wider">Belum Dinilai</p>
              <h3 className="text-2xl font-bold text-blue-600 mt-1">{stats.dikumpulkan || stats.belum_dinilai || 0}</h3>
            </div>
            <div className="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600">
              <Clock className="w-6 h-6" />
            </div>
          </div>
          <p className="text-xs text-blue-600 mt-2">Menunggu proses pemeriksaan guru</p>
        </div>

        <div className="bg-white p-5 rounded-[18px] border border-slate-100 shadow-sm hover:shadow-md transition-all duration-200">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-xs font-medium text-slate-500 uppercase tracking-wider">Terlambat Kumpul</p>
              <h3 className="text-2xl font-bold text-amber-600 mt-1">{stats.terlambat || 0}</h3>
            </div>
            <div className="w-12 h-12 rounded-xl bg-amber-50 flex items-center justify-center text-amber-600">
              <AlertTriangle className="w-6 h-6" />
            </div>
          </div>
          <p className="text-xs text-amber-600 mt-2">Melewati batas waktu deadline</p>
        </div>
      </div>

      {/* Filter Toolbar */}
      <div className="bg-white p-5 rounded-[18px] border border-slate-100 shadow-sm space-y-4">
        <form onSubmit={handleSearchSubmit} className="flex flex-col md:flex-row gap-3">
          <div className="relative flex-1">
            <Search className="w-4 h-4 absolute left-3.5 top-3.5 text-slate-400" />
            <input
              type="text"
              placeholder="Cari siswa, judul tugas, catatan, atau berkas..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44]/20 focus:border-[#0E5C44]"
            />
          </div>

          <div className="flex flex-wrap items-center gap-3">
            <select
              value={selectedPenugasan}
              onChange={(e) => setSelectedPenugasan(e.target.value)}
              className="px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm text-slate-700 bg-white focus:outline-none focus:ring-2 focus:ring-[#0E5C44]/20 focus:border-[#0E5C44]"
            >
              <option value="">Semua Penugasan</option>
              {options.penugasan.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.label}
                </option>
              ))}
            </select>

            <select
              value={selectedSiswa}
              onChange={(e) => setSelectedSiswa(e.target.value)}
              className="px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm text-slate-700 bg-white focus:outline-none focus:ring-2 focus:ring-[#0E5C44]/20 focus:border-[#0E5C44]"
            >
              <option value="">Semua Siswa</option>
              {options.siswa.map((s) => (
                <option key={s.id} value={s.id}>
                  {s.label}
                </option>
              ))}
            </select>

            <select
              value={selectedStatus}
              onChange={(e) => setSelectedStatus(e.target.value)}
              className="px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm text-slate-700 bg-white focus:outline-none focus:ring-2 focus:ring-[#0E5C44]/20 focus:border-[#0E5C44]"
            >
              <option value="">Semua Status</option>
              {options.status.map((st) => (
                <option key={st.id} value={st.id}>
                  {st.label}
                </option>
              ))}
            </select>

            <button
              type="submit"
              className="px-4 py-2.5 bg-[#0E5C44] text-white rounded-xl text-sm font-medium hover:bg-[#1E8E5A] transition-colors"
            >
              Cari
            </button>

            <button
              type="button"
              onClick={handleResetFilter}
              className="p-2.5 border border-slate-200 rounded-xl text-slate-600 hover:bg-slate-50 transition-colors"
              title="Reset Filter"
            >
              <RefreshCw className="w-4 h-4" />
            </button>
          </div>
        </form>
      </div>

      {/* Main Table Card */}
      <div className="bg-white rounded-[18px] border border-slate-100 shadow-sm overflow-hidden">
        <div className="p-5 border-b border-slate-100 flex items-center justify-between">
          <h2 className="text-base font-semibold text-slate-800 flex items-center gap-2">
            <FileText className="w-5 h-5 text-[#0E5C44]" />
            Daftar Pengumpulan & Submission Tugas
          </h2>
          <span className="text-xs text-slate-500 font-medium">
            Total {pagination.total} item
          </span>
        </div>

        {loading ? (
          <div className="p-12 text-center text-slate-500 space-y-3">
            <RefreshCw className="w-8 h-8 animate-spin mx-auto text-[#0E5C44]" />
            <p className="text-sm font-medium">Memuat data pengumpulan tugas...</p>
          </div>
        ) : dataPengumpulan.length === 0 ? (
          <div className="p-12 text-center text-slate-500 space-y-3">
            <BookOpen className="w-12 h-12 mx-auto text-slate-300" />
            <p className="text-base font-semibold text-slate-700">Belum ada pengumpulan tugas</p>
            <p className="text-sm text-slate-400">Silakan tambahkan submission atau ubah kata kunci filter Anda.</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse">
              <thead>
                <tr className="bg-slate-50/70 border-b border-slate-100 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                  <th className="py-3.5 px-4">Siswa</th>
                  <th className="py-3.5 px-4">Penugasan & Mapel</th>
                  <th className="py-3.5 px-4">File / Link</th>
                  <th className="py-3.5 px-4">Waktu Kumpul</th>
                  <th className="py-3.5 px-4">Status</th>
                  <th className="py-3.5 px-4">Nilai & Catatan</th>
                  <th className="py-3.5 px-4 text-right">Aksi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 text-sm">
                {dataPengumpulan.map((item) => (
                  <tr key={item.id} className="hover:bg-slate-50/60 transition-colors">
                    <td className="py-3.5 px-4">
                      <div className="flex items-center gap-3">
                        <div className="w-9 h-9 rounded-full bg-emerald-100 text-[#0E5C44] flex items-center justify-center font-bold text-sm">
                          {(item.siswa?.nama || 'S').charAt(0).toUpperCase()}
                        </div>
                        <div>
                          <div className="font-semibold text-slate-800">
                            {item.siswa?.nama || 'Siswa'}
                          </div>
                          <div className="text-xs text-slate-400 font-mono">
                            {item.siswa?.nisn ? `NISN: ${item.siswa.nisn}` : 'NISN -'}
                          </div>
                        </div>
                      </div>
                    </td>

                    <td className="py-3.5 px-4 max-w-xs">
                      <div className="font-medium text-slate-800 truncate">
                        {item.penugasan?.judul || item.penugasan?.judul_tugas || 'Penugasan'}
                      </div>
                      <div className="text-xs text-emerald-700 font-medium">
                        {item.penugasan?.subject || 'Mata Pelajaran'} • {item.penugasan?.kelas || 'Kelas'}
                      </div>
                    </td>

                    <td className="py-3.5 px-4">
                      <div className="flex flex-col gap-1 text-xs">
                        {item.file || item.file_path ? (
                          <a
                            href={item.file || item.file_path}
                            target="_blank"
                            rel="noreferrer"
                            className="inline-flex items-center gap-1.5 text-emerald-700 hover:text-emerald-900 font-medium hover:underline"
                          >
                            <File className="w-3.5 h-3.5" />
                            Berkas Lampiran
                          </a>
                        ) : null}

                        {item.link || item.url_link ? (
                          <a
                            href={item.link || item.url_link}
                            target="_blank"
                            rel="noreferrer"
                            className="inline-flex items-center gap-1.5 text-blue-600 hover:text-blue-800 font-medium hover:underline"
                          >
                            <ExternalLink className="w-3.5 h-3.5" />
                            Link Tautan External
                          </a>
                        ) : null}

                        {!item.file && !item.file_path && !item.link && !item.url_link && (
                          <span className="text-slate-400 italic">Hanya Jawaban Teks</span>
                        )}
                      </div>
                    </td>

                    <td className="py-3.5 px-4 text-xs text-slate-600">
                      <div>{item.waktu_kumpul || '-'}</div>
                      {item.waktu_kumpul_formatted && (
                        <div className="text-[11px] text-slate-400">{item.waktu_kumpul_formatted}</div>
                      )}
                    </td>

                    <td className="py-3.5 px-4">
                      {getStatusBadge(item.status)}
                    </td>

                    <td className="py-3.5 px-4">
                      {item.nilai !== null && item.nilai !== undefined ? (
                        <div className="flex items-center gap-2">
                          <span className="px-2.5 py-1 rounded-lg bg-emerald-100 text-[#0E5C44] font-bold text-sm">
                            {item.nilai} / 100
                          </span>
                        </div>
                      ) : (
                        <span className="text-xs text-slate-400 italic">Belum Dinilai</span>
                      )}
                      {item.catatan && (
                        <p className="text-xs text-slate-500 mt-1 line-clamp-1 italic">
                          "{item.catatan}"
                        </p>
                      )}
                    </td>

                    <td className="py-3.5 px-4 text-right space-x-1">
                      <button
                        onClick={() => handleOpenDetail(item)}
                        className="p-1.5 text-slate-500 hover:text-[#0E5C44] hover:bg-emerald-50 rounded-lg transition-colors"
                        title="Lihat Detail"
                      >
                        <Eye className="w-4 h-4" />
                      </button>
                      <button
                        onClick={() => handleOpenEditModal(item)}
                        className="p-1.5 text-slate-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                        title="Koreksi / Edit Nilai"
                      >
                        <Edit3 className="w-4 h-4" />
                      </button>
                      <button
                        onClick={() => handleDelete(item.id, item.siswa?.nama)}
                        className="p-1.5 text-slate-500 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors"
                        title="Hapus"
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

        {/* Pagination */}
        {!loading && dataPengumpulan.length > 0 && (
          <div className="p-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500">
            <div>
              Halaman <span className="font-semibold text-slate-700">{pagination.current_page}</span> dari{' '}
              <span className="font-semibold text-slate-700">{pagination.last_page}</span> (Total {pagination.total} data)
            </div>
            <div className="flex items-center gap-2">
              <button
                disabled={pagination.current_page <= 1}
                onClick={() => setPage((prev) => Math.max(prev - 1, 1))}
                className="p-2 border border-slate-200 rounded-lg hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed"
              >
                <ChevronLeft className="w-4 h-4" />
              </button>
              <button
                disabled={pagination.current_page >= pagination.last_page}
                onClick={() => setPage((prev) => prev + 1)}
                className="p-2 border border-slate-200 rounded-lg hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed"
              >
                <ChevronRight className="w-4 h-4" />
              </button>
            </div>
          </div>
        )}
      </div>

      {/* Modal Form for Create / Edit / Grade */}
      {isModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm animate-fadeIn">
          <div className="bg-white rounded-[20px] shadow-2xl border border-slate-100 w-full max-w-lg overflow-hidden transform transition-all">
            <div className="p-5 border-b border-slate-100 flex items-center justify-between bg-gradient-to-r from-[#0E5C44] to-[#1E8E5A] text-white">
              <h3 className="text-lg font-bold flex items-center gap-2">
                <UploadCloud className="w-5 h-5" />
                {editId ? 'Koreksi & Form Penilaian' : 'Input Submission Pengumpulan Tugas'}
              </h3>
              <button
                onClick={() => setIsModalOpen(false)}
                className="text-white/80 hover:text-white p-1 rounded-lg"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleSubmitForm} className="p-6 space-y-4 text-sm max-h-[80vh] overflow-y-auto">
              <div>
                <label className="block font-semibold text-slate-700 mb-1">
                  Penugasan <span className="text-rose-500">*</span>
                </label>
                <select
                  required
                  value={formData.penugasan_id}
                  onChange={(e) => setFormData({ ...formData, penugasan_id: e.target.value })}
                  className="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-[#0E5C44]/20 focus:border-[#0E5C44]"
                >
                  <option value="">-- Pilih Penugasan --</option>
                  {options.penugasan.map((p) => (
                    <option key={p.id} value={p.id}>
                      {p.label || p.judul_tugas || p.judul} {p.subject ? `(${p.subject})` : ''}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block font-semibold text-slate-700 mb-1">
                  Siswa <span className="text-rose-500">*</span>
                </label>
                <select
                  required
                  value={formData.siswa_id}
                  onChange={(e) => setFormData({ ...formData, siswa_id: e.target.value })}
                  className="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-[#0E5C44]/20 focus:border-[#0E5C44]"
                >
                  <option value="">-- Pilih Siswa --</option>
                  {options.siswa.map((s) => (
                    <option key={s.id} value={s.id}>
                      {s.label || s.full_name || s.name || s.nama_lengkap} {s.nisn ? `(${s.nisn})` : ''}
                    </option>
                  ))}
                </select>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block font-semibold text-slate-700 mb-1">
                    Status Submission
                  </label>
                  <select
                    value={formData.status}
                    onChange={(e) => setFormData({ ...formData, status: e.target.value })}
                    className="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-[#0E5C44]/20 focus:border-[#0E5C44]"
                  >
                    {options.status.map((st) => (
                      <option key={st.id} value={st.id}>
                        {st.label}
                      </option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="block font-semibold text-slate-700 mb-1">
                    Nilai (0 - 100)
                  </label>
                  <input
                    type="number"
                    min="0"
                    max="100"
                    step="0.1"
                    placeholder="Contoh: 90"
                    value={formData.nilai}
                    onChange={(e) => setFormData({ ...formData, nilai: e.target.value })}
                    className="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-[#0E5C44]/20 focus:border-[#0E5C44]"
                  />
                </div>
              </div>

              <div>
                <label className="block font-semibold text-slate-700 mb-1">
                  URL / Path File Lampiran (file)
                </label>
                <input
                  type="text"
                  placeholder="https://drive.google.com/... atau /storage/tugas/file.pdf"
                  value={formData.file}
                  onChange={(e) => setFormData({ ...formData, file: e.target.value })}
                  className="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-[#0E5C44]/20 focus:border-[#0E5C44]"
                />
              </div>

              <div>
                <label className="block font-semibold text-slate-700 mb-1">
                  Link Tautan Eksternal (link)
                </label>
                <input
                  type="url"
                  placeholder="https://github.com/... atau https://docs.google.com/..."
                  value={formData.link}
                  onChange={(e) => setFormData({ ...formData, link: e.target.value })}
                  className="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-[#0E5C44]/20 focus:border-[#0E5C44]"
                />
              </div>

              <div>
                <label className="block font-semibold text-slate-700 mb-1">
                  Catatan / Ulasan Guru & Teks Jawaban (catatan)
                </label>
                <textarea
                  rows="3"
                  placeholder="Tuliskan catatan apresiasi, saran perbaikan, atau rangkuman jawaban siswa..."
                  value={formData.catatan}
                  onChange={(e) => setFormData({ ...formData, catatan: e.target.value })}
                  className="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-[#0E5C44]/20 focus:border-[#0E5C44]"
                ></textarea>
              </div>

              <div className="pt-3 border-t border-slate-100 flex items-center justify-end gap-3">
                <button
                  type="button"
                  onClick={() => setIsModalOpen(false)}
                  className="px-4 py-2.5 border border-slate-200 text-slate-600 rounded-xl font-medium hover:bg-slate-50 transition-colors"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={formLoading}
                  className="px-5 py-2.5 bg-[#0E5C44] text-white rounded-xl font-semibold shadow-lg hover:bg-[#1E8E5A] transition-all flex items-center gap-2 disabled:opacity-50"
                >
                  {formLoading && <RefreshCw className="w-4 h-4 animate-spin" />}
                  {editId ? 'Simpan Koreksi' : 'Kumpulkan Tugas'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Detail Drawer */}
      {isDetailOpen && selectedDetail && (
        <div className="fixed inset-0 z-50 flex justify-end bg-slate-900/40 backdrop-blur-xs animate-fadeIn">
          <div className="bg-white w-full max-w-md h-full shadow-2xl overflow-y-auto flex flex-col">
            <div className="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50">
              <h3 className="font-bold text-slate-800 text-base flex items-center gap-2">
                <FileText className="w-5 h-5 text-[#0E5C44]" />
                Detail Pengumpulan Tugas
              </h3>
              <button
                onClick={() => setIsDetailOpen(false)}
                className="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <div className="p-6 space-y-6 flex-1 text-sm">
              <div className="bg-slate-50 p-4 rounded-xl space-y-2 border border-slate-100">
                <p className="text-xs font-semibold text-slate-400 uppercase tracking-wider">Identitas Siswa</p>
                <p className="font-bold text-slate-800 text-base">{selectedDetail.siswa?.nama || 'Siswa'}</p>
                <p className="text-xs text-slate-500">NISN: {selectedDetail.siswa?.nisn || '-'}</p>
              </div>

              <div className="space-y-2">
                <p className="text-xs font-semibold text-slate-400 uppercase tracking-wider">Penugasan</p>
                <p className="font-semibold text-slate-800">{selectedDetail.penugasan?.judul || 'Judul Penugasan'}</p>
                <p className="text-xs text-emerald-700 font-medium">
                  {selectedDetail.penugasan?.subject} • Kelas {selectedDetail.penugasan?.kelas}
                </p>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div className="bg-emerald-50/50 p-3 rounded-xl border border-emerald-100">
                  <p className="text-xs text-slate-500 font-medium">Nilai Akhir</p>
                  <p className="text-xl font-bold text-[#0E5C44] mt-0.5">
                    {selectedDetail.nilai !== null && selectedDetail.nilai !== undefined ? `${selectedDetail.nilai} / 100` : 'Belum Dinilai'}
                  </p>
                </div>
                <div className="bg-blue-50/50 p-3 rounded-xl border border-blue-100">
                  <p className="text-xs text-slate-500 font-medium">Status</p>
                  <div className="mt-1">{getStatusBadge(selectedDetail.status)}</div>
                </div>
              </div>

              {selectedDetail.jawaban_teks && (
                <div className="space-y-1">
                  <p className="text-xs font-semibold text-slate-400 uppercase tracking-wider">Teks Jawaban Siswa</p>
                  <div className="p-3 bg-slate-50 rounded-xl border border-slate-100 text-slate-700 whitespace-pre-line">
                    {selectedDetail.jawaban_teks}
                  </div>
                </div>
              )}

              {selectedDetail.file || selectedDetail.file_path ? (
                <div className="space-y-1">
                  <p className="text-xs font-semibold text-slate-400 uppercase tracking-wider">Berkas File</p>
                  <a
                    href={selectedDetail.file || selectedDetail.file_path}
                    target="_blank"
                    rel="noreferrer"
                    className="flex items-center gap-2 p-3 bg-emerald-50 rounded-xl border border-emerald-200 text-emerald-800 font-medium hover:bg-emerald-100 transition-colors"
                  >
                    <File className="w-5 h-5 text-emerald-600" />
                    <span className="flex-1 truncate">{selectedDetail.file || selectedDetail.file_path}</span>
                    <ExternalLink className="w-4 h-4" />
                  </a>
                </div>
              ) : null}

              {selectedDetail.link || selectedDetail.url_link ? (
                <div className="space-y-1">
                  <p className="text-xs font-semibold text-slate-400 uppercase tracking-wider">Tautan External</p>
                  <a
                    href={selectedDetail.link || selectedDetail.url_link}
                    target="_blank"
                    rel="noreferrer"
                    className="flex items-center gap-2 p-3 bg-blue-50 rounded-xl border border-blue-200 text-blue-800 font-medium hover:bg-blue-100 transition-colors"
                  >
                    <ExternalLink className="w-5 h-5 text-blue-600" />
                    <span className="flex-1 truncate">{selectedDetail.link || selectedDetail.url_link}</span>
                  </a>
                </div>
              ) : null}

              {selectedDetail.catatan && (
                <div className="space-y-1">
                  <p className="text-xs font-semibold text-slate-400 uppercase tracking-wider">Catatan Guru / Penilai</p>
                  <div className="p-3 bg-amber-50 rounded-xl border border-amber-200 text-amber-900 italic">
                    "{selectedDetail.catatan}"
                  </div>
                </div>
              )}

              <div className="pt-4 border-t border-slate-100 space-y-2 text-xs text-slate-500">
                <div className="flex justify-between">
                  <span>Waktu Kumpul:</span>
                  <span className="font-medium text-slate-700">{selectedDetail.waktu_kumpul || '-'}</span>
                </div>
                {selectedDetail.penilai && (
                  <div className="flex justify-between">
                    <span>Penilai (Guru):</span>
                    <span className="font-medium text-slate-700">{selectedDetail.penilai.nama}</span>
                  </div>
                )}
              </div>
            </div>

            <div className="p-4 border-t border-slate-100 bg-slate-50">
              <button
                onClick={() => {
                  setIsDetailOpen(false)
                  handleOpenEditModal(selectedDetail)
                }}
                className="w-full py-2.5 bg-[#0E5C44] text-white rounded-xl font-semibold hover:bg-[#1E8E5A] transition-colors flex items-center justify-center gap-2"
              >
                <Edit3 className="w-4 h-4" />
                Koreksi / Beri Nilai
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
