import { useState, useEffect } from 'react'
import {
  Target,
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
  Clock,
  Building2,
  Calendar,
  GraduationCap,
} from 'lucide-react'
import { tujuanPembelajaranService } from '../services/tujuanPembelajaranService'
import { capaianPembelajaranService } from '../services/capaianPembelajaranService'
import { educationUnitService } from '../services/educationUnitService'
import { tahunAjaranService } from '../services/tahunAjaranService'
import { masterKurikulumService } from '../services/masterKurikulumService'
import { subjectService } from '../services/subjectService'

export default function MasterTujuanPembelajaranPage() {
  const [dataTp, setDataTp] = useState([])
  const [stats, setStats] = useState({
    total_tp: 0,
    total_tp_aktif: 0,
    total_cp: 0,
    cp_ber_tp: 0,
  })

  // Master options for dependent dropdown
  const [units, setUnits] = useState([])
  const [tahunAjarans, setTahunAjarans] = useState([])
  const [kurikulums, setKurikulums] = useState([])
  const [subjects, setSubjects] = useState([])
  const [cpOptions, setCpOptions] = useState([])
  const [loadingCp, setLoadingCp] = useState(false)

  const [loading, setLoading] = useState(true)
  const [errorMsg, setErrorMsg] = useState('')
  const [successMsg, setSuccessMsg] = useState('')

  // Filters & Pagination
  const [search, setSearch] = useState('')
  const [selectedCpFilter, setSelectedCpFilter] = useState('')
  const [selectedStatusFilter, setSelectedStatusFilter] = useState('')
  const [page, setPage] = useState(1)
  const [pagination, setPagination] = useState({
    current_page: 1,
    last_page: 1,
    total: 0,
    per_page: 15,
  })

  // Modal State & Form Data mengikuti urutan persis yang diminta:
  // 1. Unit Pendidikan -> 2. Tahun Ajaran -> 3. Kurikulum -> 4. Mata Pelajaran ->
  // 5. Dropdown CP (DB) -> 6. Kode TP (Auto) -> 7. Alokasi JP -> 8. Deskripsi TP -> 9. Urutan -> 10. Status
  const [modalOpen, setModalOpen] = useState(false)
  const [editingItem, setEditingItem] = useState(null)
  const [formSubmitting, setFormSubmitting] = useState(false)
  const [formData, setFormData] = useState({
    unit_pendidikan_id: '',
    tahun_ajaran_id: '',
    kurikulum_id: '',
    mata_pelajaran_id: '',
    cp_id: '',
    kode_tp: '',
    alokasi_waktu_jp: 2,
    deskripsi_tp: '',
    urutan: 1,
    status: true,
  })

  const loadInitialMasters = async () => {
    try {
      const [uRes, tRes, kRes, sRes, sStats] = await Promise.all([
        educationUnitService.getDaftar().catch(() => ({ data: [] })),
        tahunAjaranService.getDropdown().catch(() => []),
        masterKurikulumService.getDropdown().catch(() => []),
        subjectService.getDropdown().catch(() => ({ data: [] })),
        tujuanPembelajaranService.getStats().catch(() => null),
      ])

      const extractList = (res) => (Array.isArray(res) ? res : Array.isArray(res?.data) ? res.data : [])

      setUnits(extractList(uRes))
      setTahunAjarans(extractList(tRes))
      setKurikulums(extractList(kRes))
      setSubjects(extractList(sRes))
      if (sStats) setStats(sStats)
    } catch (err) {
      console.error('Error loading master data:', err)
    }
  }

  const fetchDaftarTp = async () => {
    setLoading(true)
    setErrorMsg('')
    try {
      const response = await tujuanPembelajaranService.getDaftar({
        page,
        search,
        cp_id: selectedCpFilter,
        status: selectedStatusFilter,
        per_page: 15,
      })
      if (response?.data) {
        setDataTp(response.data)
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
      console.error('Error fetching TP data:', err)
      setErrorMsg('Gagal memuat data Tujuan Pembelajaran. Silakan coba lagi.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    loadInitialMasters()
  }, [])

  useEffect(() => {
    fetchDaftarTp()
  }, [page, search, selectedCpFilter, selectedStatusFilter])

  // Dependent Dropdown Trigger: ketika Unit, Tahun, Kurikulum, atau Mata Pelajaran berubah
  useEffect(() => {
    if (!modalOpen) return

    const fetchFilteredCpOptions = async () => {
      setLoadingCp(true)
      try {
        const result = await capaianPembelajaranService.getDropdown({
          unit_pendidikan_id: formData.unit_pendidikan_id,
          tahun_ajaran_id: formData.tahun_ajaran_id,
          kurikulum_id: formData.kurikulum_id,
          mata_pelajaran_id: formData.mata_pelajaran_id,
        })
        setCpOptions(result)

        // Reset CP selection if current selected CP is not in new list
        if (result.length > 0) {
          const exists = result.some((cp) => cp.id === formData.cp_id)
          if (!exists) {
            setFormData((prev) => ({ ...prev, cp_id: result[0].id }))
          }
        } else {
          setFormData((prev) => ({ ...prev, cp_id: '' }))
        }
      } catch (err) {
        console.error('Error fetching dependent CP dropdown:', err)
        setCpOptions([])
      } finally {
        setLoadingCp(false)
      }
    }

    fetchFilteredCpOptions()
  }, [
    modalOpen,
    formData.unit_pendidikan_id,
    formData.tahun_ajaran_id,
    formData.kurikulum_id,
    formData.mata_pelajaran_id,
  ])

  // Dynamic Auto Generate Kode TP Preview
  useEffect(() => {
    if (editingItem) return

    const mapelObj = subjects.find((s) => s.id === formData.mata_pelajaran_id)
    const unitObj = units.find((u) => u.id === formData.unit_pendidikan_id)

    const kodeMapel = mapelObj?.kode_mapel || mapelObj?.code || 'MTK'
    const kodeUnit = unitObj?.code || unitObj?.level || 'SD'
    const seq = String(dataTp.length + 1).padStart(3, '0')

    const autoKode = `TP-${kodeMapel.toUpperCase()}-${kodeUnit.toUpperCase()}-${seq}`
    setFormData((prev) => ({ ...prev, kode_tp: autoKode }))
  }, [formData.mata_pelajaran_id, formData.unit_pendidikan_id, editingItem, dataTp.length])

  const handleOpenModal = (item = null) => {
    if (item) {
      setEditingItem(item)
      const cp = item.capaian_pembelajaran
      setFormData({
        unit_pendidikan_id: cp?.unit_pendidikan_id || (units[0]?.id ?? ''),
        tahun_ajaran_id: cp?.tahun_ajaran_id || (tahunAjarans[0]?.id ?? ''),
        kurikulum_id: cp?.kurikulum_id || (kurikulums[0]?.id ?? ''),
        mata_pelajaran_id: cp?.mata_pelajaran_id || (subjects[0]?.id ?? ''),
        cp_id: item.cp_id || '',
        kode_tp: item.kode_tp || '',
        alokasi_waktu_jp: item.alokasi_waktu_jp || 2,
        deskripsi_tp: item.deskripsi_tp || item.deskripsi || item.nama_tp || '',
        urutan: item.urutan || 1,
        status: item.status !== undefined ? item.status : true,
      })
    } else {
      setEditingItem(null)
      const defaultUnit = units[0]?.id ?? ''
      const defaultTahun = tahunAjarans[0]?.id ?? ''
      const defaultKur = kurikulums[0]?.id ?? ''
      const defaultMapel = subjects[0]?.id ?? ''

      setFormData({
        unit_pendidikan_id: defaultUnit,
        tahun_ajaran_id: defaultTahun,
        kurikulum_id: defaultKur,
        mata_pelajaran_id: defaultMapel,
        cp_id: '',
        kode_tp: '',
        alokasi_waktu_jp: 2,
        deskripsi_tp: '',
        urutan: dataTp.length + 1,
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
    if (!formData.cp_id) {
      setErrorMsg('Capaian Pembelajaran (CP) harus dipilih dari database.')
      return
    }
    if (!formData.deskripsi_tp.trim()) {
      setErrorMsg('Deskripsi Tujuan Pembelajaran (TP) tidak boleh kosong.')
      return
    }

    setFormSubmitting(true)
    setErrorMsg('')
    try {
      if (editingItem) {
        await tujuanPembelajaranService.ubah({
          id: editingItem.id,
          payload: formData,
        })
        setSuccessMsg('Tujuan Pembelajaran berhasil diperbarui!')
      } else {
        await tujuanPembelajaranService.tambah(formData)
        setSuccessMsg('Tujuan Pembelajaran berhasil ditambahkan dengan Kode TP otomatis!')
      }
      handleCloseModal()
      fetchDaftarTp()
      loadInitialMasters()
      setTimeout(() => setSuccessMsg(''), 4000)
    } catch (err) {
      console.error('Error submitting form:', err)
      const msg = err.response?.data?.message || 'Gagal menyimpan TP. Pastikan CP terpilih dan aktif.'
      setErrorMsg(msg)
    } finally {
      setFormSubmitting(false)
    }
  }

  const handleHapus = async (id, kode) => {
    if (!window.confirm(`Apakah Anda yakin ingin menghapus Tujuan Pembelajaran [${kode}]?`)) {
      return
    }
    try {
      await tujuanPembelajaranService.hapus(id)
      setSuccessMsg(`Tujuan Pembelajaran [${kode}] berhasil dihapus.`)
      fetchDaftarTp()
      loadInitialMasters()
      setTimeout(() => setSuccessMsg(''), 4000)
    } catch (err) {
      console.error('Error deleting TP:', err)
      setErrorMsg('Gagal menghapus data.')
    }
  }

  return (
    <div className="min-h-screen bg-[#F7F9FC] dark:bg-[#0F172A] p-4 sm:p-6 lg:p-8 transition-colors duration-200">
      {/* Hero Section */}
      <div className="relative overflow-hidden rounded-2xl bg-gradient-to-r from-[#0E5C44] via-[#1E8E5A] to-[#3FBF75] p-6 sm:p-8 text-white shadow-xl mb-8">
        <div className="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-6">
          <div>
            <div className="flex items-center gap-2 text-emerald-100 text-xs font-semibold uppercase tracking-wider mb-2">
              <span>Kurikulum & LMS</span>
              <span>/</span>
              <span className="text-white">Tujuan Pembelajaran (TP)</span>
            </div>
            <h1 className="text-2xl sm:text-3xl font-extrabold tracking-tight flex items-center gap-3">
              <Target className="w-8 h-8 text-emerald-200" />
              Master Tujuan Pembelajaran (TP)
            </h1>
            <p className="mt-2 text-emerald-100 text-sm max-w-2xl">
              Kelola Tujuan Pembelajaran (TP) berbasis relasi bertingkat: Unit Pendidikan → Tahun Ajaran → Kurikulum → Mata Pelajaran → CP Database → TP → Modul Ajar.
            </p>
          </div>
          <button
            onClick={() => handleOpenModal()}
            className="self-start md:self-center inline-flex items-center gap-2 bg-white text-[#0E5C44] hover:bg-emerald-50 px-5 py-3 rounded-xl font-bold text-sm shadow-md transition-all duration-200 transform hover:scale-[1.02] active:scale-[0.98]"
          >
            <Plus className="w-5 h-5 text-[#0E5C44]" />
            Tambah TP Baru
          </button>
        </div>
        <div className="absolute -right-10 -bottom-10 w-48 h-48 rounded-full bg-white/10 blur-2xl pointer-events-none" />
      </div>

      {/* KPI Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
        <div className="bg-white dark:bg-[#1B2433] rounded-2xl p-5 border border-slate-100 dark:border-slate-800 shadow-sm flex items-center gap-4">
          <div className="w-12 h-12 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 flex items-center justify-center text-[#0E5C44] dark:text-[#3FBF75]">
            <Target className="w-6 h-6" />
          </div>
          <div>
            <p className="text-xs text-slate-500 dark:text-slate-400 font-medium">Total Tujuan Pembelajaran</p>
            <p className="text-2xl font-bold text-slate-800 dark:text-slate-100">{stats.total_tp ?? 0}</p>
          </div>
        </div>

        <div className="bg-white dark:bg-[#1B2433] rounded-2xl p-5 border border-slate-100 dark:border-slate-800 shadow-sm flex items-center gap-4">
          <div className="w-12 h-12 rounded-xl bg-green-50 dark:bg-green-950/50 flex items-center justify-center text-green-600 dark:text-green-400">
            <CheckCircle className="w-6 h-6" />
          </div>
          <div>
            <p className="text-xs text-slate-500 dark:text-slate-400 font-medium">TP Status Aktif</p>
            <p className="text-2xl font-bold text-slate-800 dark:text-slate-100">{stats.total_tp_aktif ?? 0}</p>
          </div>
        </div>

        <div className="bg-white dark:bg-[#1B2433] rounded-2xl p-5 border border-slate-100 dark:border-slate-800 shadow-sm flex items-center gap-4">
          <div className="w-12 h-12 rounded-xl bg-teal-50 dark:bg-teal-950/50 flex items-center justify-center text-teal-600 dark:text-teal-400">
            <BookOpen className="w-6 h-6" />
          </div>
          <div>
            <p className="text-xs text-slate-500 dark:text-slate-400 font-medium">Total Capaian Pembelajaran</p>
            <p className="text-2xl font-bold text-slate-800 dark:text-slate-100">{stats.total_cp ?? 0}</p>
          </div>
        </div>

        <div className="bg-white dark:bg-[#1B2433] rounded-2xl p-5 border border-slate-100 dark:border-slate-800 shadow-sm flex items-center gap-4">
          <div className="w-12 h-12 rounded-xl bg-blue-50 dark:bg-blue-950/50 flex items-center justify-center text-blue-600 dark:text-blue-400">
            <Layers className="w-6 h-6" />
          </div>
          <div>
            <p className="text-xs text-slate-500 dark:text-slate-400 font-medium">CP Memiliki TP</p>
            <p className="text-2xl font-bold text-slate-800 dark:text-slate-100">{stats.cp_ber_tp ?? 0}</p>
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

      {/* Search & Filter Bar */}
      <div className="bg-white dark:bg-[#1B2433] rounded-2xl p-5 border border-slate-100 dark:border-slate-800 shadow-sm mb-6">
        <div className="flex flex-col lg:flex-row gap-4 justify-between items-center">
          <div className="relative w-full lg:w-80">
            <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
            <input
              type="text"
              placeholder="Cari kode TP / deskripsi..."
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
              value={selectedStatusFilter}
              onChange={(e) => {
                setSelectedStatusFilter(e.target.value)
                setPage(1)
              }}
              className="px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44] dark:text-slate-100"
            >
              <option value="">-- Semua Status --</option>
              <option value="true">Aktif</option>
              <option value="false">Nonaktif</option>
            </select>

            <button
              onClick={() => {
                setSearch('')
                setSelectedCpFilter('')
                setSelectedStatusFilter('')
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
                <th className="py-4 px-5 w-44">Kode TP</th>
                <th className="py-4 px-5">Capaian Pembelajaran (CP)</th>
                <th className="py-4 px-5">Deskripsi Tujuan Pembelajaran (TP)</th>
                <th className="py-4 px-5 text-center w-28">Alokasi (JP)</th>
                <th className="py-4 px-5 text-center w-28">Status</th>
                <th className="py-4 px-5 text-center w-32">Aksi</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
              {loading ? (
                <tr>
                  <td colSpan={7} className="py-12 text-center text-slate-400">
                    <RefreshCw className="w-6 h-6 animate-spin mx-auto mb-2 text-[#0E5C44]" />
                    Memuat data Tujuan Pembelajaran...
                  </td>
                </tr>
              ) : dataTp.length === 0 ? (
                <tr>
                  <td colSpan={7} className="py-12 text-center text-slate-400">
                    <FileText className="w-10 h-10 mx-auto mb-3 text-slate-300 dark:text-slate-600" />
                    Belum ada data Tujuan Pembelajaran yang ditemukan.
                  </td>
                </tr>
              ) : (
                dataTp.map((item) => (
                  <tr key={item.id} className="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition">
                    <td className="py-4 px-5 text-center font-bold text-slate-500 dark:text-slate-400">
                      #{item.urutan}
                    </td>

                    <td className="py-4 px-5 font-mono font-bold text-xs text-[#0E5C44] dark:text-[#3FBF75]">
                      <span className="px-2.5 py-1 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800">
                        {item.kode_tp}
                      </span>
                    </td>

                    <td className="py-4 px-5">
                      <div className="font-semibold text-slate-800 dark:text-slate-100">
                        {item.capaian_pembelajaran?.kode_cp || '-'}
                      </div>
                      <div className="text-xs text-slate-500 dark:text-slate-400 line-clamp-1">
                        {item.capaian_pembelajaran?.nama_cp || 'Tanpa CP'}
                      </div>
                    </td>

                    <td className="py-4 px-5 max-w-md">
                      <p className="text-slate-800 dark:text-slate-200 font-medium leading-relaxed">
                        {item.deskripsi_tp || item.deskripsi || item.nama_tp}
                      </p>
                    </td>

                    <td className="py-4 px-5 text-center font-semibold">
                      <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs">
                        <Clock className="w-3.5 h-3.5 text-slate-400" />
                        {item.alokasi_waktu_jp ?? 2} JP
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
                          title="Edit TP"
                        >
                          <Edit3 className="w-4 h-4" />
                        </button>
                        <button
                          onClick={() => handleHapus(item.id, item.kode_tp)}
                          className="p-2 rounded-lg text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition"
                          title="Hapus TP"
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

      {/* Modal Form TP - Mengikuti urutan persis yang dipersyaratkan */}
      {modalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-fadeIn">
          <div className="bg-white dark:bg-[#1B2433] w-full max-w-xl rounded-2xl shadow-2xl border border-slate-100 dark:border-slate-800 overflow-hidden">
            <div className="bg-gradient-to-r from-[#0E5C44] to-[#1E8E5A] p-5 text-white flex items-center justify-between">
              <div className="flex items-center gap-3">
                <Target className="w-6 h-6 text-emerald-200" />
                <h3 className="text-lg font-bold">
                  {editingItem ? 'Edit Tujuan Pembelajaran' : 'Tambah Tujuan Pembelajaran Baru'}
                </h3>
              </div>
              <button onClick={handleCloseModal} className="text-emerald-100 hover:text-white p-1 rounded-lg">
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleSubmitForm} className="p-6 space-y-4 max-h-[85vh] overflow-y-auto">
              {/* Field 1: Unit Pendidikan */}
              <div>
                <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
                  1. Unit Pendidikan <span className="text-rose-500">*</span>
                </label>
                <select
                  value={formData.unit_pendidikan_id}
                  onChange={(e) => setFormData({ ...formData, unit_pendidikan_id: e.target.value })}
                  className="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44] dark:text-slate-100"
                  required
                >
                  <option value="">-- Pilih Unit Pendidikan --</option>
                  {units.map((u) => (
                    <option key={u.id} value={u.id}>
                      {u.name || u.code}
                    </option>
                  ))}
                </select>
              </div>

              {/* Field 2: Tahun Ajaran */}
              <div>
                <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
                  2. Tahun Ajaran <span className="text-rose-500">*</span>
                </label>
                <select
                  value={formData.tahun_ajaran_id}
                  onChange={(e) => setFormData({ ...formData, tahun_ajaran_id: e.target.value })}
                  className="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44] dark:text-slate-100"
                  required
                >
                  <option value="">-- Pilih Tahun Ajaran --</option>
                  {tahunAjarans.map((t) => (
                    <option key={t.id} value={t.id}>
                      {t.tahun || t.name}
                    </option>
                  ))}
                </select>
              </div>

              {/* Field 3: Kurikulum */}
              <div>
                <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
                  3. Kurikulum <span className="text-rose-500">*</span>
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

              {/* Field 4: Mata Pelajaran */}
              <div>
                <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
                  4. Mata Pelajaran <span className="text-rose-500">*</span>
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

              {/* Field 5: Dropdown CP (Database, Dependent Dropdown) */}
              <div>
                <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2 flex items-center justify-between">
                  <span>
                    5. Capaian Pembelajaran (CP) <span className="text-rose-500">*</span>
                  </span>
                  {loadingCp && <span className="text-emerald-600 text-[11px] font-normal animate-pulse">Memuat CP DB...</span>}
                </label>
                <select
                  value={formData.cp_id}
                  onChange={(e) => setFormData({ ...formData, cp_id: e.target.value })}
                  className="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44] dark:text-slate-100"
                  required
                >
                  <option value="">-- Pilih CP (Ambil Data DB Status Aktif) --</option>
                  {cpOptions.map((cp) => (
                    <option key={cp.id} value={cp.id}>
                      [{cp.kode_cp}] {cp.nama_cp}
                    </option>
                  ))}
                </select>
                {cpOptions.length === 0 && !loadingCp && (
                  <p className="text-xs text-amber-600 dark:text-amber-400 mt-1">
                    * Belum ada CP Aktif di database untuk kriteria Unit/Tahun/Kurikulum/Mapel ini.
                  </p>
                )}
              </div>

              {/* Field 6: Kode TP (Auto Generate) */}
              <div>
                <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
                  6. Kode TP <span className="text-emerald-600 font-semibold">(Auto Generate)</span>
                </label>
                <input
                  type="text"
                  readOnly
                  value={formData.kode_tp}
                  className="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-100 dark:bg-slate-800/80 text-sm font-mono font-bold text-[#0E5C44] dark:text-[#3FBF75] cursor-not-allowed"
                />
              </div>

              {/* Field 7: Alokasi JP */}
              <div>
                <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
                  7. Alokasi Waktu (JP)
                </label>
                <input
                  type="number"
                  min={1}
                  value={formData.alokasi_waktu_jp}
                  onChange={(e) => setFormData({ ...formData, alokasi_waktu_jp: parseInt(e.target.value) || 1 })}
                  className="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44] dark:text-slate-100"
                />
              </div>

              {/* Field 8: Deskripsi TP */}
              <div>
                <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
                  8. Deskripsi TP <span className="text-rose-500">*</span>
                </label>
                <textarea
                  rows={3}
                  placeholder="Masukkan uraian spesifik indikator Tujuan Pembelajaran..."
                  value={formData.deskripsi_tp}
                  onChange={(e) => setFormData({ ...formData, deskripsi_tp: e.target.value })}
                  className="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-sm focus:outline-none focus:ring-2 focus:ring-[#0E5C44] dark:text-slate-100"
                  required
                />
              </div>

              {/* Field 9 & 10: Urutan & Status */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
                    9. Urutan
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
                    10. Status
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
                      Simpan TP
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
