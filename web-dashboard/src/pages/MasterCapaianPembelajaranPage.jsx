import { useState, useEffect } from 'react'
import {
  BookOpen,
  Layers,
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
  FileText,
  Building2,
  Calendar,
  GraduationCap,
} from 'lucide-react'
import { capaianPembelajaranService } from '../services/capaianPembelajaranService'
import { educationUnitService } from '../services/educationUnitService'
import { tahunAjaranService } from '../services/tahunAjaranService'
import { masterKurikulumService } from '../services/masterKurikulumService'
import { subjectService } from '../services/subjectService'

export default function MasterCapaianPembelajaranPage() {
  const [dataCp, setDataCp] = useState([])
  const [units, setUnits] = useState([])
  const [tahunAjarans, setTahunAjarans] = useState([])
  const [kurikulums, setKurikulums] = useState([])
  const [subjects, setSubjects] = useState([])
  const [stats, setStats] = useState({
    total_cp: 0,
    total_cp_aktif: 0,
    total_cp_nonaktif: 0,
  })

  const [loading, setLoading] = useState(true)
  const [errorMsg, setErrorMsg] = useState('')
  const [successMsg, setSuccessMsg] = useState('')

  // Filters & Pagination
  const [search, setSearch] = useState('')
  const [selectedUnit, setSelectedUnit] = useState('')
  const [selectedTahun, setSelectedTahun] = useState('')
  const [selectedKurikulum, setSelectedKurikulum] = useState('')
  const [selectedSubject, setSelectedSubject] = useState('')
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
  const [editingItem, setEditingItem] = useState(null)
  const [formSubmitting, setFormSubmitting] = useState(false)
  const [formData, setFormData] = useState({
    unit_pendidikan_id: '',
    tahun_ajaran_id: '',
    kurikulum_id: '',
    mata_pelajaran_id: '',
    kode_cp: '',
    nama_cp: '',
    deskripsi: '',
    fase: 'Fase A',
    kelas_target: 'Kelas 1',
    urutan: 1,
    status: true,
  })

  const loadDropdownMasterData = async () => {
    try {
      const [uRes, tRes, kRes, sRes, sStats] = await Promise.all([
        educationUnitService.getDaftar().catch(() => ({ data: [] })),
        tahunAjaranService.getDropdown().catch(() => []),
        masterKurikulumService.getDropdown().catch(() => []),
        subjectService.getDropdown().catch(() => ({ data: [] })),
        capaianPembelajaranService.getStats().catch(() => null),
      ])

      const extractList = (res) => (Array.isArray(res) ? res : Array.isArray(res?.data) ? res.data : [])

      setUnits(extractList(uRes))
      setTahunAjarans(extractList(tRes))
      setKurikulums(extractList(kRes))
      setSubjects(extractList(sRes))
      if (sStats) setStats(sStats)
    } catch (err) {
      console.error('Error loading dropdown masters:', err)
    }
  }

  const fetchDaftarCp = async () => {
    setLoading(true)
    setErrorMsg('')
    try {
      const response = await capaianPembelajaranService.getDaftar({
        page,
        search,
        unit_pendidikan_id: selectedUnit,
        tahun_ajaran_id: selectedTahun,
        kurikulum_id: selectedKurikulum,
        mata_pelajaran_id: selectedSubject,
        status: selectedStatus,
        per_page: 15,
      })
      if (response?.data) {
        setDataCp(response.data)
        if (response.meta) {
          setPagination({
            current_page: response.meta.current_page || 1,
            last_page: response.meta.last_page || 1,
            total: response.meta.total || 0,
            per_page: response.meta.per_page || 15,
          })
        }
      }
    } catch (err) {
      console.error('Error fetching CP data:', err)
      setErrorMsg('Gagal memuat data Capaian Pembelajaran. Silakan coba lagi.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    loadDropdownMasterData()
  }, [])

  useEffect(() => {
    fetchDaftarCp()
  }, [page, search, selectedUnit, selectedTahun, selectedKurikulum, selectedSubject, selectedStatus])

  const handleOpenModal = (item = null) => {
    if (item) {
      setEditingItem(item)
      setFormData({
        unit_pendidikan_id: item.unit_pendidikan_id || '',
        tahun_ajaran_id: item.tahun_ajaran_id || '',
        kurikulum_id: item.kurikulum_id || '',
        mata_pelajaran_id: item.mata_pelajaran_id || '',
        kode_cp: item.kode_cp || '',
        nama_cp: item.nama_cp || '',
        deskripsi: item.deskripsi || '',
        fase: item.fase || 'Fase A',
        kelas_target: item.kelas_target || 'Kelas 1',
        urutan: item.urutan || 1,
        status: item.status !== undefined ? item.status : true,
      })
    } else {
      setEditingItem(null)
      setFormData({
        unit_pendidikan_id: units.length > 0 ? units[0].id : '',
        tahun_ajaran_id: tahunAjarans.length > 0 ? tahunAjarans[0].id : '',
        kurikulum_id: kurikulums.length > 0 ? kurikulums[0].id : '',
        mata_pelajaran_id: subjects.length > 0 ? subjects[0].id : '',
        kode_cp: `CP-MAPEL-${dataCp.length + 1}`,
        nama_cp: '',
        deskripsi: '',
        fase: 'Fase A',
        kelas_target: 'Kelas 1',
        urutan: dataCp.length + 1,
        status: true,
      })
    }
    setModalOpen(true)
  }

  const handleCloseModal = () => {
    setModalOpen(false)
    setEditingItem(null)
  }

  const handleSubmitForm = async (e) => {
    e.preventDefault()
    if (!formData.kurikulum_id) {
      setErrorMsg('Kurikulum harus dipilih.')
      return
    }
    if (!formData.mata_pelajaran_id) {
      setErrorMsg('Mata Pelajaran harus dipilih.')
      return
    }
    if (!formData.kode_cp.trim() || !formData.nama_cp.trim()) {
      setErrorMsg('Kode dan Nama Capaian Pembelajaran wajib diisi.')
      return
    }

    setFormSubmitting(true)
    setErrorMsg('')
    try {
      if (editingItem) {
        await capaianPembelajaranService.ubah({
          id: editingItem.id,
          payload: formData,
        })
        setSuccessMsg('Capaian Pembelajaran berhasil diperbarui!')
      } else {
        await capaianPembelajaranService.tambah(formData)
        setSuccessMsg('Capaian Pembelajaran berhasil ditambahkan!')
      }
      handleCloseModal()
      fetchDaftarCp()
      loadDropdownMasterData()
      setTimeout(() => setSuccessMsg(''), 4000)
    } catch (err) {
      console.error('Error submitting CP form:', err)
      const msg = err.response?.data?.message || 'Gagal menyimpan data. Pastikan kolom diisi dengan benar.'
      setErrorMsg(msg)
    } finally {
      setFormSubmitting(false)
    }
  }

  const handleHapus = async (id, kode) => {
    if (!window.confirm(`Apakah Anda yakin ingin menghapus Capaian Pembelajaran [${kode}]?`)) {
      return
    }
    try {
      await capaianPembelajaranService.hapus(id)
      setSuccessMsg(`Capaian Pembelajaran [${kode}] berhasil dihapus.`)
      fetchDaftarCp()
      loadDropdownMasterData()
      setTimeout(() => setSuccessMsg(''), 4000)
    } catch (err) {
      console.error('Error deleting CP:', err)
      setErrorMsg('Gagal menghapus data Capaian Pembelajaran.')
    }
  }

  return (
    <div className="min-h-screen bg-[#F7F9FC] dark:bg-[#0F172A] p-4 sm:p-6 lg:p-8 transition-colors duration-200">
      {/* Hero Banner */}
      <div className="relative overflow-hidden rounded-2xl bg-gradient-to-r from-[#0E5C44] via-[#1E8E5A] to-[#3FBF75] p-6 sm:p-8 text-white shadow-xl mb-8">
        <div className="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-6">
          <div>
            <div className="flex items-center gap-2 text-emerald-100 text-xs font-semibold uppercase tracking-wider mb-2">
              <span>Kurikulum & LMS</span>
              <span>/</span>
              <span className="text-white">Capaian Pembelajaran (CP)</span>
            </div>
            <h1 className="text-2xl sm:text-3xl font-extrabold tracking-tight flex items-center gap-3">
              <BookOpen className="w-8 h-8 text-emerald-200" />
              Master Capaian Pembelajaran (CP)
            </h1>
            <p className="mt-2 text-emerald-100 text-sm max-w-2xl">
              Kelola Master Capaian Pembelajaran (CP) berbasis Kurikulum, Unit Pendidikan, dan Mata Pelajaran sebagai fondasi utama penyusunan Tujuan Pembelajaran (TP) & Modul Ajar.
            </p>
          </div>
          <button
            onClick={() => handleOpenModal()}
            className="self-start md:self-center inline-flex items-center gap-2 bg-white text-[#0E5C44] hover:bg-emerald-50 px-5 py-3 rounded-xl font-bold text-sm shadow-md transition-all duration-200 transform hover:scale-[1.02] active:scale-[0.98]"
          >
            <Plus className="w-5 h-5 text-[#0E5C44]" />
            Tambah CP Baru
          </button>
        </div>
        <div className="absolute -right-10 -bottom-10 w-48 h-48 rounded-full bg-white/10 blur-2xl pointer-events-none" />
      </div>

      {/* Stats Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-8">
        <div className="bg-white dark:bg-[#1B2433] rounded-2xl p-5 border border-slate-100 dark:border-slate-800 shadow-sm flex items-center gap-4">
          <div className="w-12 h-12 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 flex items-center justify-center text-[#0E5C44] dark:text-[#3FBF75]">
            <BookOpen className="w-6 h-6" />
          </div>
          <div>
            <p className="text-xs text-slate-500 dark:text-slate-400 font-medium">Total Capaian Pembelajaran</p>
            <p className="text-2xl font-bold text-slate-800 dark:text-slate-100">{stats.total_cp ?? 0}</p>
          </div>
        </div>

        <div className="bg-white dark:bg-[#1B2433] rounded-2xl p-5 border border-slate-100 dark:border-slate-800 shadow-sm flex items-center gap-4">
          <div className="w-12 h-12 rounded-xl bg-green-50 dark:bg-green-950/50 flex items-center justify-center text-green-600 dark:text-green-400">
            <CheckCircle className="w-6 h-6" />
          </div>
          <div>
            <p className="text-xs text-slate-500 dark:text-slate-400 font-medium">CP Status Aktif</p>
            <p className="text-2xl font-bold text-slate-800 dark:text-slate-100">{stats.total_cp_aktif ?? 0}</p>
          </div>
        </div>

        <div className="bg-white dark:bg-[#1B2433] rounded-2xl p-5 border border-slate-100 dark:border-slate-800 shadow-sm flex items-center gap-4">
          <div className="w-12 h-12 rounded-xl bg-rose-50 dark:bg-rose-950/50 flex items-center justify-center text-rose-600 dark:text-rose-400">
            <AlertCircle className="w-6 h-6" />
          </div>
          <div>
            <p className="text-xs text-slate-500 dark:text-slate-400 font-medium">CP Status Nonaktif</p>
            <p className="text-2xl font-bold text-slate-800 dark:text-slate-100">{stats.total_cp_nonaktif ?? 0}</p>
          </div>
        </div>
      </div>

      {/* Notifications */}
      {successMsg && (
        <div className="mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 dark:bg-emerald-950/40 dark:border-emerald-800 dark:text-emerald-300 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <CheckCircle className="w-5 h-5 text-emerald-600" />
            <span className="text-sm font-semibold">{successMsg}</span>
          </div>
          <button onClick={() => setSuccessMsg('')} className="text-emerald-500 hover:text-emerald-700">
            <X className="w-4 h-4" />
          </button>
        </div>
      )}

      {errorMsg && (
        <div className="mb-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 dark:bg-rose-950/40 dark:border-rose-800 dark:text-rose-300 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <AlertCircle className="w-5 h-5 text-rose-600" />
            <span className="text-sm font-semibold">{errorMsg}</span>
          </div>
          <button onClick={() => setErrorMsg('')} className="text-rose-500 hover:text-rose-700">
            <X className="w-4 h-4" />
          </button>
        </div>
      )}

      {/* Filters Bar */}
      <div className="bg-white dark:bg-[#1B2433] rounded-2xl p-5 border border-slate-100 dark:border-slate-800 shadow-sm mb-6">
        <div className="flex flex-col lg:flex-row gap-4 justify-between items-center">
          <div className="relative w-full lg:w-72">
            <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
            <input
              type="text"
              placeholder="Cari kode / nama CP..."
              value={search}
              onChange={(e) => {
                setSearch(e.target.value)
                setPage(1)
              }}
              className="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44] dark:text-slate-100"
            />
          </div>

          <div className="flex flex-wrap items-center gap-3 w-full lg:w-auto">
            <select
              value={selectedUnit}
              onChange={(e) => {
                setSelectedUnit(e.target.value)
                setPage(1)
              }}
              className="px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44] dark:text-slate-100"
            >
              <option value="">-- Semua Unit --</option>
              {units.map((u) => (
                <option key={u.id} value={u.id}>
                  {u.name || u.code}
                </option>
              ))}
            </select>

            <select
              value={selectedKurikulum}
              onChange={(e) => {
                setSelectedKurikulum(e.target.value)
                setPage(1)
              }}
              className="px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44] dark:text-slate-100"
            >
              <option value="">-- Semua Kurikulum --</option>
              {kurikulums.map((k) => (
                <option key={k.id} value={k.id}>
                  {k.nama_kurikulum || k.kode_kurikulum}
                </option>
              ))}
            </select>

            <select
              value={selectedSubject}
              onChange={(e) => {
                setSelectedSubject(e.target.value)
                setPage(1)
              }}
              className="px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44] dark:text-slate-100"
            >
              <option value="">-- Semua Mata Pelajaran --</option>
              {subjects.map((s) => (
                <option key={s.id} value={s.id}>
                  {s.nama_mapel || s.name}
                </option>
              ))}
            </select>

            <button
              onClick={() => {
                setSearch('')
                setSelectedUnit('')
                setSelectedTahun('')
                setSelectedKurikulum('')
                setSelectedSubject('')
                setSelectedStatus('')
                setPage(1)
              }}
              className="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 text-sm hover:bg-slate-100 dark:hover:bg-slate-800 transition"
            >
              <RefreshCw className="w-4 h-4" />
              Reset
            </button>
          </div>
        </div>
      </div>

      {/* Main Table */}
      <div className="bg-white dark:bg-[#1B2433] rounded-2xl border border-slate-100 dark:border-slate-800 shadow-sm overflow-hidden mb-8">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm border-collapse">
            <thead>
              <tr className="bg-slate-50 dark:bg-slate-900/60 border-b border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 uppercase text-xs tracking-wider font-semibold">
                <th className="py-4 px-5 text-center w-16">Urutan</th>
                <th className="py-4 px-5 w-40">Kode CP</th>
                <th className="py-4 px-5">Nama & Deskripsi CP</th>
                <th className="py-4 px-5">Kurikulum & Mapel</th>
                <th className="py-4 px-5 text-center w-28">Fase / Kelas</th>
                <th className="py-4 px-5 text-center w-28">Status</th>
                <th className="py-4 px-5 text-center w-32">Aksi</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
              {loading ? (
                <tr>
                  <td colSpan={7} className="py-12 text-center text-slate-400">
                    <RefreshCw className="w-6 h-6 animate-spin mx-auto mb-2 text-[#0E5C44]" />
                    Memuat data Capaian Pembelajaran...
                  </td>
                </tr>
              ) : dataCp.length === 0 ? (
                <tr>
                  <td colSpan={7} className="py-12 text-center text-slate-400">
                    <FileText className="w-10 h-10 mx-auto mb-3 text-slate-300 dark:text-slate-600" />
                    Belum ada data Capaian Pembelajaran yang ditemukan.
                  </td>
                </tr>
              ) : (
                dataCp.map((item) => (
                  <tr key={item.id} className="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition">
                    <td className="py-4 px-5 text-center font-bold text-slate-500 dark:text-slate-400">
                      #{item.urutan}
                    </td>

                    <td className="py-4 px-5 font-mono font-bold text-xs text-[#0E5C44] dark:text-[#3FBF75]">
                      <span className="px-2.5 py-1 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800">
                        {item.kode_cp}
                      </span>
                    </td>

                    <td className="py-4 px-5 max-w-md">
                      <div className="font-bold text-slate-800 dark:text-slate-100">{item.nama_cp}</div>
                      {item.deskripsi && (
                        <p className="text-xs text-slate-500 dark:text-slate-400 line-clamp-2 mt-1">
                          {item.deskripsi}
                        </p>
                      )}
                    </td>

                    <td className="py-4 px-5">
                      <div className="font-semibold text-slate-800 dark:text-slate-200">
                        {item.subject?.nama_mapel || item.subject?.name || '-'}
                      </div>
                      <div className="text-xs text-slate-500 dark:text-slate-400">
                        {item.kurikulum?.nama_kurikulum || 'Tanpa Kurikulum'}
                      </div>
                    </td>

                    <td className="py-4 px-5 text-center">
                      <span className="inline-block px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-semibold">
                        {item.fase || '-'} ({item.kelas_target || 'Semua'})
                      </span>
                    </td>

                    <td className="py-4 px-5 text-center">
                      <span
                        className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold ${
                          item.status
                            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
                            : 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300'
                        }`}
                      >
                        <span className={`w-2 h-2 rounded-full ${item.status ? 'bg-emerald-500' : 'bg-rose-500'}`} />
                        {item.status ? 'Aktif' : 'Nonaktif'}
                      </span>
                    </td>

                    <td className="py-4 px-5 text-center">
                      <div className="flex items-center justify-center gap-2">
                        <button
                          onClick={() => handleOpenModal(item)}
                          className="p-2 rounded-lg text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-950/40 transition"
                          title="Edit CP"
                        >
                          <Edit3 className="w-4 h-4" />
                        </button>
                        <button
                          onClick={() => handleHapus(item.id, item.kode_cp)}
                          className="p-2 rounded-lg text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition"
                          title="Hapus CP"
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

        {pagination.last_page > 1 && (
          <div className="p-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-900/40 text-xs text-slate-500">
            <div>
              Menampilkan Halaman <span className="font-bold">{pagination.current_page}</span> dari{' '}
              <span className="font-bold">{pagination.last_page}</span> ({pagination.total} data total)
            </div>
            <div className="flex items-center gap-2">
              <button
                disabled={page <= 1}
                onClick={() => setPage((p) => Math.max(p - 1, 1))}
                className="p-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 disabled:opacity-40 hover:bg-slate-100 dark:hover:bg-slate-700"
              >
                <ChevronLeft className="w-4 h-4" />
              </button>
              <button
                disabled={page >= pagination.last_page}
                onClick={() => setPage((p) => Math.min(p + 1, pagination.last_page))}
                className="p-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 disabled:opacity-40 hover:bg-slate-100 dark:hover:bg-slate-700"
              >
                <ChevronRight className="w-4 h-4" />
              </button>
            </div>
          </div>
        )}
      </div>

      {/* Modal Form */}
      {modalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-fadeIn">
          <div className="bg-white dark:bg-[#1B2433] w-full max-w-xl rounded-2xl shadow-2xl border border-slate-100 dark:border-slate-800 overflow-hidden">
            <div className="bg-gradient-to-r from-[#0E5C44] to-[#1E8E5A] p-5 text-white flex items-center justify-between">
              <div className="flex items-center gap-3">
                <BookOpen className="w-6 h-6 text-emerald-200" />
                <h3 className="text-lg font-bold">
                  {editingItem ? 'Edit Capaian Pembelajaran' : 'Tambah Capaian Pembelajaran Baru'}
                </h3>
              </div>
              <button onClick={handleCloseModal} className="text-emerald-100 hover:text-white p-1 rounded-lg">
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleSubmitForm} className="p-6 space-y-4">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
                    Unit Pendidikan
                  </label>
                  <select
                    value={formData.unit_pendidikan_id}
                    onChange={(e) => setFormData({ ...formData, unit_pendidikan_id: e.target.value })}
                    className="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44] dark:text-slate-100"
                  >
                    <option value="">-- Pilih Unit --</option>
                    {units.map((u) => (
                      <option key={u.id} value={u.id}>
                        {u.name || u.code}
                      </option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
                    Tahun Ajaran
                  </label>
                  <select
                    value={formData.tahun_ajaran_id}
                    onChange={(e) => setFormData({ ...formData, tahun_ajaran_id: e.target.value })}
                    className="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44] dark:text-slate-100"
                  >
                    <option value="">-- Pilih Tahun Ajaran --</option>
                    {tahunAjarans.map((t) => (
                      <option key={t.id} value={t.id}>
                        {t.tahun || t.name}
                      </option>
                    ))}
                  </select>
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
                    Kurikulum <span className="text-rose-500">*</span>
                  </label>
                  <select
                    value={formData.kurikulum_id}
                    onChange={(e) => setFormData({ ...formData, kurikulum_id: e.target.value })}
                    className="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44] dark:text-slate-100"
                    required
                  >
                    <option value="">-- Pilih Kurikulum --</option>
                    {kurikulums.map((k) => (
                      <option key={k.id} value={k.id}>
                        {k.nama_kurikulum || k.kode_kurikulum}
                      </option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
                    Mata Pelajaran <span className="text-rose-500">*</span>
                  </label>
                  <select
                    value={formData.mata_pelajaran_id}
                    onChange={(e) => setFormData({ ...formData, mata_pelajaran_id: e.target.value })}
                    className="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44] dark:text-slate-100"
                    required
                  >
                    <option value="">-- Pilih Mata Pelajaran --</option>
                    {subjects.map((s) => (
                      <option key={s.id} value={s.id}>
                        {s.nama_mapel || s.name}
                      </option>
                    ))}
                  </select>
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
                    Kode CP <span className="text-rose-500">*</span>
                  </label>
                  <input
                    type="text"
                    placeholder="Contoh: CP-MTK-SD-01"
                    value={formData.kode_cp}
                    onChange={(e) => setFormData({ ...formData, kode_cp: e.target.value })}
                    className="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44] dark:text-slate-100"
                    required
                  />
                </div>

                <div>
                  <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
                    Fase <span className="text-slate-400 font-normal">(Kurikulum Merdeka)</span>
                  </label>
                  <select
                    value={formData.fase}
                    onChange={(e) => setFormData({ ...formData, fase: e.target.value })}
                    className="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44] dark:text-slate-100"
                  >
                    <option value="Fase A">Fase A (Kelas 1-2)</option>
                    <option value="Fase B">Fase B (Kelas 3-4)</option>
                    <option value="Fase C">Fase C (Kelas 5-6)</option>
                    <option value="Fase D">Fase D (Kelas 7-9)</option>
                    <option value="Fase E">Fase E (Kelas 10)</option>
                    <option value="Fase F">Fase F (Kelas 11-12)</option>
                  </select>
                </div>
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
                  Nama Capaian Pembelajaran (CP) <span className="text-rose-500">*</span>
                </label>
                <input
                  type="text"
                  placeholder="Masukkan nama / ringkasan Capaian Pembelajaran..."
                  value={formData.nama_cp}
                  onChange={(e) => setFormData({ ...formData, nama_cp: e.target.value })}
                  className="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44] dark:text-slate-100"
                  required
                />
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
                  Deskripsi Lengkap CP
                </label>
                <textarea
                  rows={3}
                  placeholder="Deskripsi uraian kompetensi elemen CP..."
                  value={formData.deskripsi}
                  onChange={(e) => setFormData({ ...formData, deskripsi: e.target.value })}
                  className="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44] dark:text-slate-100"
                />
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
                    Urutan
                  </label>
                  <input
                    type="number"
                    min={1}
                    value={formData.urutan}
                    onChange={(e) => setFormData({ ...formData, urutan: parseInt(e.target.value) || 1 })}
                    className="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44] dark:text-slate-100"
                  />
                </div>

                <div>
                  <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
                    Status Aktivasi
                  </label>
                  <select
                    value={formData.status ? 'true' : 'false'}
                    onChange={(e) => setFormData({ ...formData, status: e.target.value === 'true' })}
                    className="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44] dark:text-slate-100"
                  >
                    <option value="true">Aktif</option>
                    <option value="false">Nonaktif</option>
                  </select>
                </div>
              </div>

              <div className="pt-4 flex items-center justify-end gap-3 border-t border-slate-100 dark:border-slate-800">
                <button
                  type="button"
                  onClick={handleCloseModal}
                  className="px-5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 text-sm font-semibold hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={formSubmitting}
                  className="inline-flex items-center gap-2 bg-[#0E5C44] hover:bg-[#1E8E5A] text-white px-6 py-2.5 rounded-xl text-sm font-bold shadow-md transition disabled:opacity-50"
                >
                  {formSubmitting ? (
                    <>
                      <RefreshCw className="w-4 h-4 animate-spin" />
                      Menyimpan...
                    </>
                  ) : (
                    <>
                      <Sparkles className="w-4 h-4" />
                      Simpan CP
                    </>
                  )}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  )
}
