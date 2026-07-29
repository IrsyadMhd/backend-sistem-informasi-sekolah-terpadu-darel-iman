import React, { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import Swal from 'sweetalert2'
import {
  BookOpen,
  Plus,
  Search,
  Filter,
  CheckCircle,
  XCircle,
  ChevronLeft,
  ChevronRight,
  Sparkles,
  Edit2,
  Trash2,
  RefreshCw,
  Eye,
  Layers,
  Award,
  Download,
  Upload,
  FileSpreadsheet,
  FileText,
  SlidersHorizontal,
  CheckSquare,
  Square,
  ShieldCheck,
  UserCheck,
  Building,
  GraduationCap,
} from 'lucide-react'
import { subjectService } from '../services/subjectService'
import { masterKurikulumService } from '../services/masterKurikulumService'
import { educationUnitService } from '../services/educationUnitService'

const KELOMPOK_LIST = ['Kelompok A', 'Kelompok B', 'Kekhasan SIT', 'Muatan Lokal', 'Al-Qur\'an/Tahfizh']
const KATEGORI_LIST = ['Wajib', 'Pilihan', 'Tahfizh/Diniyah', 'Ekstrakurikuler', 'Vokasi']
const JENJANG_LIST = ['TK', 'PAUD', 'SD', 'MI', 'SMP', 'MTs', 'SMA', 'MA', 'Pesantren']

export default function MasterSubjectPage() {
  const queryClient = useQueryClient()

  // Filter States
  const [search, setSearch] = useState('')
  const [selectedUnitFilter, setSelectedUnitFilter] = useState('')
  const [selectedKurikulumFilter, setSelectedKurikulumFilter] = useState('')
  const [selectedKelompokFilter, setSelectedKelompokFilter] = useState('')
  const [selectedKategoriFilter, setSelectedKategoriFilter] = useState('')
  const [selectedJenjangFilter, setSelectedJenjangFilter] = useState('')
  const [selectedStatusFilter, setSelectedStatusFilter] = useState('')
  const [denganSampahFilter, setDenganSampahFilter] = useState('')
  const [page, setPage] = useState(1)
  const [perPage] = useState(15)

  // Selection & Bulk States
  const [selectedIds, setSelectedIds] = useState([])

  // Modal States
  const [isFormModalOpen, setIsFormModalOpen] = useState(false)
  const [selectedForEdit, setSelectedForEdit] = useState(null)
  const [isDetailModalOpen, setIsDetailModalOpen] = useState(false)
  const [selectedForDetail, setSelectedForDetail] = useState(null)
  const [isImportModalOpen, setIsImportModalOpen] = useState(false)
  const [importFile, setImportFile] = useState(null)

  // Form State
  const [formData, setFormData] = useState({
    unit_pendidikan_id: '',
    kurikulum_id: '',
    kode_mapel: '',
    nama_mapel: '',
    nama_singkat: '',
    kelompok_mapel: 'Kelompok A',
    kategori: 'Wajib',
    jenjang: 'SD',
    tingkat_kelas: 'All',
    jam_pelajaran: 2,
    kkm: 75,
    bobot_pengetahuan: 40,
    bobot_keterampilan: 40,
    bobot_sikap: 20,
    warna: '#0E5C44',
    ikon: 'BookOpen',
    urutan_tampil: 1,
    status: true,
    deskripsi: '',
  })

  // Queries
  const { data: responseData = {}, isLoading, isFetching } = useQuery({
    queryKey: [
      'master-subjects-list',
      page,
      perPage,
      search,
      selectedUnitFilter,
      selectedKurikulumFilter,
      selectedKelompokFilter,
      selectedKategoriFilter,
      selectedJenjangFilter,
      selectedStatusFilter,
      denganSampahFilter,
    ],
    queryFn: () =>
      subjectService.getDaftar({
        page,
        per_page: perPage,
        search,
        unit_pendidikan_id: selectedUnitFilter,
        kurikulum_id: selectedKurikulumFilter,
        kelompok_mapel: selectedKelompokFilter,
        kategori: selectedKategoriFilter,
        jenjang: selectedJenjangFilter,
        status: selectedStatusFilter,
        dengan_sampah: denganSampahFilter,
        order_by: 'created_at',
        order_dir: 'desc',
      }),
  })

  const { data: kurikulumDropdown = [] } = useQuery({
    queryKey: ['kurikulum-dropdown-options'],
    queryFn: async () => {
      const res = await masterKurikulumService.getDropdown()
      return Array.isArray(res) ? res : (res?.data || [])
    },
  })

  const { data: unitDropdown = [] } = useQuery({
    queryKey: ['education-units-dropdown-options'],
    queryFn: async () => {
      const res = await educationUnitService.getDaftar()
      return res.data || []
    },
  })

  // Mutations
  const simpanMutation = useMutation({
    mutationFn: (payload) => {
      if (selectedForEdit) {
        return subjectService.ubah({ id: selectedForEdit.id, payload })
      }
      return subjectService.tambah(payload)
    },
    onSuccess: (res) => {
      Swal.fire('Berhasil', res.message || 'Data mata pelajaran berhasil disimpan.', 'success')
      setIsFormModalOpen(false)
      setSelectedForEdit(null)
      queryClient.invalidateQueries(['master-subjects-list'])
    },
    onError: (err) => {
      const msg = err.response?.data?.message || 'Gagal menyimpan data mata pelajaran.'
      Swal.fire('Error', msg, 'error')
    },
  })

  const hapusMutation = useMutation({
    mutationFn: (id) => subjectService.hapus(id),
    onSuccess: (res) => {
      Swal.fire('Terhapus', res.message || 'Mata pelajaran berhasil dihapus.', 'success')
      queryClient.invalidateQueries(['master-subjects-list'])
    },
    onError: (err) => {
      Swal.fire('Gagal', err.response?.data?.message || 'Gagal menghapus mata pelajaran.', 'error')
    },
  })

  const pulihkanMutation = useMutation({
    mutationFn: (id) => subjectService.pulihkan(id),
    onSuccess: (res) => {
      Swal.fire('Dipulihkan', res.message || 'Mata pelajaran berhasil dipulihkan.', 'success')
      queryClient.invalidateQueries(['master-subjects-list'])
    },
  })

  const bulkStatusMutation = useMutation({
    mutationFn: ({ ids, status }) => subjectService.bulkStatus(ids, status),
    onSuccess: (res) => {
      Swal.fire('Berhasil', res.message || 'Status berhasil diperbarui secara massal.', 'success')
      setSelectedIds([])
      queryClient.invalidateQueries(['master-subjects-list'])
    },
  })

  const bulkDeleteMutation = useMutation({
    mutationFn: (ids) => subjectService.bulkDelete(ids),
    onSuccess: (res) => {
      Swal.fire('Berhasil', res.message || 'Data berhasil dihapus secara massal.', 'success')
      setSelectedIds([])
      queryClient.invalidateQueries(['master-subjects-list'])
    },
  })

  const items = responseData.data || []
  const meta = responseData.meta || {}
  const stats = responseData.statistik || { total: 0, aktif: 0, tidak_aktif: 0, terhapus: 0 }

  // Multi select logic
  const isAllSelected = items.length > 0 && selectedIds.length === items.length
  const toggleSelectAll = () => {
    if (isAllSelected) {
      setSelectedIds([])
    } else {
      setSelectedIds(items.map((i) => i.id))
    }
  }

  const toggleSelectRow = (id) => {
    setSelectedIds((prev) =>
      prev.includes(id) ? prev.filter((item) => item !== id) : [...prev, id]
    )
  }

  const handleOpenFormTambah = () => {
    setSelectedForEdit(null)
    setFormData({
      unit_pendidikan_id: unitDropdown[0]?.id || '',
      kurikulum_id: kurikulumDropdown[0]?.id || '',
      kode_mapel: '',
      nama_mapel: '',
      nama_singkat: '',
      kelompok_mapel: 'Kelompok A',
      kategori: 'Wajib',
      jenjang: 'SD',
      tingkat_kelas: 'All',
      jam_pelajaran: 2,
      kkm: 75,
      bobot_pengetahuan: 40,
      bobot_keterampilan: 40,
      bobot_sikap: 20,
      warna: '#0E5C44',
      ikon: 'BookOpen',
      urutan_tampil: 1,
      status: true,
      deskripsi: '',
    })
    setIsFormModalOpen(true)
  }

  const handleOpenFormEdit = (row) => {
    setSelectedForEdit(row)
    setFormData({
      unit_pendidikan_id: row.unit_pendidikan_id || '',
      kurikulum_id: row.kurikulum_id || '',
      kode_mapel: row.kode_mapel || row.code || '',
      nama_mapel: row.nama_mapel || row.name || '',
      nama_singkat: row.nama_singkat || '',
      kelompok_mapel: row.kelompok_mapel || 'Kelompok A',
      kategori: row.kategori || 'Wajib',
      jenjang: row.jenjang || 'SD',
      tingkat_kelas: row.tingkat_kelas || 'All',
      jam_pelajaran: row.jam_pelajaran || 2,
      kkm: row.kkm || 75,
      bobot_pengetahuan: row.bobot_pengetahuan || 40,
      bobot_keterampilan: row.bobot_keterampilan || 40,
      bobot_sikap: row.bobot_sikap || 20,
      warna: row.warna || '#0E5C44',
      ikon: row.ikon || 'BookOpen',
      urutan_tampil: row.urutan_tampil || 1,
      status: row.status ?? true,
      deskripsi: row.deskripsi || row.description || '',
    })
    setIsFormModalOpen(true)
  }

  const handleOpenDetail = (row) => {
    setSelectedForDetail(row)
    setIsDetailModalOpen(true)
  }

  const handleSubmit = (e) => {
    e.preventDefault()
    if (!formData.kode_mapel || !formData.nama_mapel) {
      Swal.fire('Peringatan', 'Kode dan Nama Mata Pelajaran wajib diisi.', 'warning')
      return
    }
    simpanMutation.mutate(formData)
  }

  const handleExportExcel = async () => {
    try {
      const res = await subjectService.exportExcel({
        search,
        unit_pendidikan_id: selectedUnitFilter,
        kurikulum_id: selectedKurikulumFilter,
        kelompok_mapel: selectedKelompokFilter,
        kategori: selectedKategoriFilter,
        jenjang: selectedJenjangFilter,
        status: selectedStatusFilter,
      })

      const blob = new Blob([JSON.stringify(res.data, null, 2)], { type: 'application/json' })
      const url = window.URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = res.filename || 'master_mata_pelajaran.json'
      a.click()
      Swal.fire('Ekspor Berhasil', `${res.total_rows || res.data?.length || 0} data telah diekspor.`, 'success')
    } catch {
      Swal.fire('Gagal', 'Gagal mendownload data ekspor Excel.', 'error')
    }
  }

  const handleExportPdf = async () => {
    try {
      const res = await subjectService.exportPdf({
        search,
        unit_pendidikan_id: selectedUnitFilter,
        kurikulum_id: selectedKurikulumFilter,
        kelompok_mapel: selectedKelompokFilter,
        kategori: selectedKategoriFilter,
        jenjang: selectedJenjangFilter,
        status: selectedStatusFilter,
      })
      Swal.fire('Ekspor PDF Ready', res.message || 'Laporan PDF berhasil dibuat.', 'success')
    } catch {
      Swal.fire('Gagal', 'Gagal mencetak dokumen PDF.', 'error')
    }
  }

  const handleImportSubmit = async (e) => {
    e.preventDefault()
    if (!importFile) {
      Swal.fire('Peringatan', 'Pilih file Excel/CSV terlebih dahulu.', 'warning')
      return
    }
    const form = new FormData()
    form.append('file', importFile)

    try {
      const res = await subjectService.importFile(form)
      Swal.fire('Impor Berhasil', res.message || 'Data berhasil diimpor.', 'success')
      setIsImportModalOpen(false)
      setImportFile(null)
      queryClient.invalidateQueries(['master-subjects-list'])
    } catch (err) {
      Swal.fire('Gagal Impor', err.response?.data?.message || 'Proses impor gagal.', 'error')
    }
  }

  return (
    <div className="space-y-6 animate-fade-in pb-12">
      {/* HERO BANNER & TITLE */}
      <div className="relative overflow-hidden rounded-[18px] bg-gradient-to-r from-[#0E5C44] via-[#1E8E5A] to-[#3FBF75] p-6 text-white shadow-xl">
        <div className="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <div className="flex items-center gap-2.5">
              <div className="flex h-10 w-10 items-center justify-center rounded-2xl bg-white/20 backdrop-blur-md">
                <BookOpen className="h-6 w-6 text-emerald-100" />
              </div>
              <div>
                <h1 className="text-2xl font-black tracking-tight font-sans">Master Mata Pelajaran</h1>
                <p className="text-xs font-medium text-emerald-100/90 mt-0.5">
                  Pusat Referensi Utama Kurikulum, LMS, Jadwal, CBT, Penilaian & Rapor Terpadu
                </p>
              </div>
            </div>
          </div>

          <div className="flex items-center gap-2 flex-wrap">
            <button
              onClick={handleExportExcel}
              className="flex items-center gap-2 rounded-xl bg-white/10 px-3.5 py-2 text-xs font-bold text-white hover:bg-white/20 backdrop-blur-md transition-all shadow-xs"
            >
              <FileSpreadsheet className="h-4 w-4" />
              <span>Export Excel</span>
            </button>
            <button
              onClick={handleExportPdf}
              className="flex items-center gap-2 rounded-xl bg-white/10 px-3.5 py-2 text-xs font-bold text-white hover:bg-white/20 backdrop-blur-md transition-all shadow-xs"
            >
              <FileText className="h-4 w-4" />
              <span>Export PDF</span>
            </button>
            <button
              onClick={() => setIsImportModalOpen(true)}
              className="flex items-center gap-2 rounded-xl bg-white/10 px-3.5 py-2 text-xs font-bold text-white hover:bg-white/20 backdrop-blur-md transition-all shadow-xs"
            >
              <Upload className="h-4 w-4" />
              <span>Import</span>
            </button>
            <button
              onClick={handleOpenFormTambah}
              className="flex items-center gap-2 rounded-xl bg-white text-[#0E5C44] px-4 py-2 text-xs font-extrabold hover:bg-emerald-50 transition-all shadow-lg active:scale-95"
            >
              <Plus className="h-4 w-4 stroke-[3]" />
              <span>Tambah Mapel Baru</span>
            </button>
          </div>
        </div>
      </div>

      {/* KPI STATISTIC CARDS */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="rounded-[18px] bg-white p-5 shadow-sm border border-slate-200/80 dark:bg-[#1B2433] dark:border-slate-800">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Mata Pelajaran</p>
              <h3 className="text-2xl font-black text-slate-800 dark:text-white mt-1">{stats.total || 0}</h3>
            </div>
            <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-50 text-[#0E5C44] dark:bg-emerald-950/40 dark:text-emerald-400">
              <BookOpen className="h-6 w-6" />
            </div>
          </div>
        </div>

        <div className="rounded-[18px] bg-white p-5 shadow-sm border border-slate-200/80 dark:bg-[#1B2433] dark:border-slate-800">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-[11px] font-bold uppercase tracking-wider text-slate-400">Mapel Aktif</p>
              <h3 className="text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-1">{stats.aktif || 0}</h3>
            </div>
            <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400">
              <CheckCircle className="h-6 w-6" />
            </div>
          </div>
        </div>

        <div className="rounded-[18px] bg-white p-5 shadow-sm border border-slate-200/80 dark:bg-[#1B2433] dark:border-slate-800">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-[11px] font-bold uppercase tracking-wider text-slate-400">Mapel Non-Aktif</p>
              <h3 className="text-2xl font-black text-amber-600 dark:text-amber-400 mt-1">{stats.tidak_aktif || 0}</h3>
            </div>
            <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-amber-50 text-amber-600 dark:bg-amber-950/40 dark:text-amber-400">
              <XCircle className="h-6 w-6" />
            </div>
          </div>
        </div>

        <div className="rounded-[18px] bg-white p-5 shadow-sm border border-slate-200/80 dark:bg-[#1B2433] dark:border-slate-800">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-[11px] font-bold uppercase tracking-wider text-slate-400">Tong Sampah</p>
              <h3 className="text-2xl font-black text-rose-600 dark:text-rose-400 mt-1">{stats.terhapus || 0}</h3>
            </div>
            <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-rose-50 text-rose-600 dark:bg-rose-950/40 dark:text-rose-400">
              <Trash2 className="h-6 w-6" />
            </div>
          </div>
        </div>
      </div>

      {/* FILTER CONTROL BAR */}
      <div className="rounded-[18px] bg-white p-4 shadow-sm border border-slate-200/80 space-y-3 dark:bg-[#1B2433] dark:border-slate-800">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2 text-xs font-bold text-slate-700 dark:text-slate-200">
            <Filter className="h-4 w-4 text-[#0E5C44] dark:text-[#3FBF75]" />
            <span>Pencarian & Filter Data</span>
          </div>
          {(search || selectedUnitFilter || selectedKurikulumFilter || selectedKelompokFilter || selectedKategoriFilter || selectedJenjangFilter || selectedStatusFilter) && (
            <button
              onClick={() => {
                setSearch('')
                setSelectedUnitFilter('')
                setSelectedKurikulumFilter('')
                setSelectedKelompokFilter('')
                setSelectedKategoriFilter('')
                setSelectedJenjangFilter('')
                setSelectedStatusFilter('')
                setDenganSampahFilter('')
              }}
              className="text-xs text-rose-600 font-bold hover:underline"
            >
              Reset Filter
            </button>
          )}
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
          <div className="relative col-span-1 sm:col-span-2">
            <Search className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
            <input
              type="text"
              placeholder="Cari kode/nama mapel..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="w-full rounded-xl border border-slate-200 bg-slate-50/50 pl-9 pr-3 py-2 text-xs font-medium focus:bg-white focus:border-[#0E5C44] focus:outline-none dark:bg-slate-900 dark:border-slate-800 dark:text-white"
            />
          </div>

          <select
            value={selectedUnitFilter}
            onChange={(e) => setSelectedUnitFilter(e.target.value)}
            className="rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2 text-xs font-medium dark:bg-slate-900 dark:border-slate-800 dark:text-white"
          >
            <option value="">Semua Unit</option>
            {unitDropdown.map((u) => (
              <option key={u.id} value={u.id}>{u.name}</option>
            ))}
          </select>

          <select
            value={selectedKurikulumFilter}
            onChange={(e) => setSelectedKurikulumFilter(e.target.value)}
            className="rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2 text-xs font-medium dark:bg-slate-900 dark:border-slate-800 dark:text-white"
          >
            <option value="">Semua Kurikulum</option>
            {kurikulumDropdown.map((k) => (
              <option key={k.id} value={k.id}>{k.nama_kurikulum}</option>
            ))}
          </select>

          <select
            value={selectedKelompokFilter}
            onChange={(e) => setSelectedKelompokFilter(e.target.value)}
            className="rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2 text-xs font-medium dark:bg-slate-900 dark:border-slate-800 dark:text-white"
          >
            <option value="">Semua Kelompok</option>
            {KELOMPOK_LIST.map((k) => (
              <option key={k} value={k}>{k}</option>
            ))}
          </select>

          <select
            value={selectedStatusFilter}
            onChange={(e) => setSelectedStatusFilter(e.target.value)}
            className="rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2 text-xs font-medium dark:bg-slate-900 dark:border-slate-800 dark:text-white"
          >
            <option value="">Semua Status</option>
            <option value="aktif">Aktif</option>
            <option value="tidak_aktif">Non-Aktif</option>
          </select>
        </div>
      </div>

      {/* BULK ACTION BAR */}
      {selectedIds.length > 0 && (
        <div className="flex items-center justify-between rounded-[18px] bg-emerald-900 text-white p-3.5 shadow-lg border border-emerald-700 animate-slide-down">
          <div className="flex items-center gap-2 text-xs font-bold">
            <CheckSquare className="h-4 w-4 text-[#3FBF75]" />
            <span>Terpilih {selectedIds.length} data mata pelajaran</span>
          </div>
          <div className="flex items-center gap-2">
            <button
              onClick={() => bulkStatusMutation.mutate({ ids: selectedIds, status: true })}
              className="px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-xs font-bold text-white transition"
            >
              Set Aktif
            </button>
            <button
              onClick={() => bulkStatusMutation.mutate({ ids: selectedIds, status: false })}
              className="px-3 py-1.5 rounded-xl bg-amber-600 hover:bg-amber-500 text-xs font-bold text-white transition"
            >
              Set Non-Aktif
            </button>
            <button
              onClick={() => {
                Swal.fire({
                  title: 'Hapus Massal?',
                  text: `Apakah Anda yakin ingin menghapus ${selectedIds.length} mata pelajaran terpilih?`,
                  icon: 'warning',
                  showCancelButton: true,
                  confirmButtonColor: '#e11d48',
                  confirmButtonText: 'Ya, Hapus Semua',
                  cancelButtonText: 'Batal',
                }).then((result) => {
                  if (result.isConfirmed) {
                    bulkDeleteMutation.mutate(selectedIds)
                  }
                })
              }}
              className="px-3 py-1.5 rounded-xl bg-rose-600 hover:bg-rose-500 text-xs font-bold text-white transition"
            >
              Hapus Massal
            </button>
          </div>
        </div>
      )}

      {/* TABLE DATA */}
      <div className="rounded-[18px] bg-white shadow-sm border border-slate-200/80 overflow-hidden dark:bg-[#1B2433] dark:border-slate-800">
        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse text-xs">
            <thead>
              <tr className="bg-slate-50 border-b border-slate-200/80 font-bold uppercase tracking-wider text-slate-500 dark:bg-slate-900/60 dark:border-slate-800 dark:text-slate-400">
                <th className="p-3.5 w-10 text-center">
                  <button onClick={toggleSelectAll} className="text-slate-400 hover:text-emerald-600">
                    {isAllSelected ? <CheckSquare className="h-4 w-4 text-emerald-600" /> : <Square className="h-4 w-4" />}
                  </button>
                </th>
                <th className="p-3.5">Kode & Mapel</th>
                <th className="p-3.5">Kurikulum & Unit</th>
                <th className="p-3.5">Kelompok & Kategori</th>
                <th className="p-3.5 text-center">JP</th>
                <th className="p-3.5 text-center">KKM</th>
                <th className="p-3.5 text-center">Bobot (P / K / S)</th>
                <th className="p-3.5 text-center">Status</th>
                <th className="p-3.5 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 font-medium dark:divide-slate-800 text-slate-700 dark:text-slate-200">
              {isLoading ? (
                <tr>
                  <td colSpan="9" className="p-8 text-center text-slate-400">
                    <RefreshCw className="h-6 w-6 animate-spin mx-auto mb-2 text-[#0E5C44]" />
                    Memuat data master mata pelajaran...
                  </td>
                </tr>
              ) : items.length === 0 ? (
                <tr>
                  <td colSpan="9" className="p-8 text-center text-slate-400">
                    Tidak ada data mata pelajaran ditemukan.
                  </td>
                </tr>
              ) : (
                items.map((row) => {
                  const isSelected = selectedIds.includes(row.id)
                  return (
                    <tr
                      key={row.id}
                      className={`hover:bg-slate-50/80 transition dark:hover:bg-slate-800/50 ${isSelected ? 'bg-emerald-50/40 dark:bg-emerald-950/20' : ''}`}
                    >
                      <td className="p-3.5 text-center">
                        <button onClick={() => toggleSelectRow(row.id)}>
                          {isSelected ? <CheckSquare className="h-4 w-4 text-emerald-600" /> : <Square className="h-4 w-4 text-slate-300" />}
                        </button>
                      </td>
                      <td className="p-3.5">
                        <div className="flex items-center gap-2.5">
                          <span
                            className="h-3 w-3 rounded-full shrink-0"
                            style={{ backgroundColor: row.warna || '#0E5C44' }}
                          />
                          <div>
                            <p className="font-extrabold text-slate-900 dark:text-white">
                              {row.nama_mapel || row.name}
                            </p>
                            <p className="text-[10px] font-semibold text-slate-400 font-mono">
                              {row.kode_mapel || row.code} {row.nama_singkat ? `(${row.nama_singkat})` : ''}
                            </p>
                          </div>
                        </div>
                      </td>
                      <td className="p-3.5">
                        <p className="font-bold text-slate-800 dark:text-slate-100">
                          {row.kurikulum?.nama_kurikulum || 'Kurikulum Terpadu'}
                        </p>
                        <p className="text-[10px] text-slate-400">
                          {row.unit_pendidikan?.name || 'Semua Unit'}
                        </p>
                      </td>
                      <td className="p-3.5">
                        <span className="inline-block px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 font-bold text-[10px] dark:bg-slate-800 dark:text-slate-300 mr-1">
                          {row.kelompok_mapel || 'Kelompok A'}
                        </span>
                        <span className="inline-block px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-700 font-bold text-[10px] dark:bg-emerald-950/40 dark:text-emerald-400">
                          {row.kategori || 'Wajib'}
                        </span>
                      </td>
                      <td className="p-3.5 text-center font-bold text-slate-800 dark:text-slate-200">
                        {row.jam_pelajaran || 2} JP
                      </td>
                      <td className="p-3.5 text-center font-extrabold text-emerald-700 dark:text-emerald-400">
                        {row.kkm || 75}
                      </td>
                      <td className="p-3.5 text-center font-semibold text-slate-600 dark:text-slate-300">
                        {row.bobot_pengetahuan || 40}% / {row.bobot_keterampilan || 40}% / {row.bobot_sikap || 20}%
                      </td>
                      <td className="p-3.5 text-center">
                        {row.status ? (
                          <span className="px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-black dark:bg-emerald-950/50 dark:text-emerald-300">
                            AKTIF
                          </span>
                        ) : (
                          <span className="px-2.5 py-1 rounded-full bg-slate-100 text-slate-600 text-[10px] font-black dark:bg-slate-800 dark:text-slate-400">
                            NON-AKTIF
                          </span>
                        )}
                      </td>
                      <td className="p-3.5 text-right space-x-1">
                        <button
                          onClick={() => handleOpenDetail(row)}
                          className="p-1.5 rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300"
                          title="Detail Mapel"
                        >
                          <Eye className="w-4 h-4" />
                        </button>
                        {row.is_deleted ? (
                          <button
                            onClick={() => pulihkanMutation.mutate(row.id)}
                            className="p-1.5 rounded-lg bg-emerald-50 text-emerald-600 hover:bg-emerald-100"
                            title="Pulihkan Data"
                          >
                            <RefreshCw className="w-4 h-4" />
                          </button>
                        ) : (
                          <>
                            <button
                              onClick={() => handleOpenFormEdit(row)}
                              className="p-1.5 rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300"
                              title="Edit"
                            >
                              <Edit2 className="w-4 h-4" />
                            </button>
                            <button
                              onClick={() => {
                                Swal.fire({
                                  title: 'Hapus Mata Pelajaran?',
                                  text: `Hapus mapel ${row.nama_mapel || row.name}?`,
                                  icon: 'warning',
                                  showCancelButton: true,
                                  confirmButtonColor: '#e11d48',
                                  confirmButtonText: 'Ya, Hapus',
                                }).then((result) => {
                                  if (result.isConfirmed) {
                                    hapusMutation.mutate(row.id)
                                  }
                                })
                              }}
                              className="p-1.5 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-100"
                              title="Hapus"
                            >
                              <Trash2 className="w-4 h-4" />
                            </button>
                          </>
                        )}
                      </td>
                    </tr>
                  )
                })
              )}
            </tbody>
          </table>
        </div>

        {/* PAGINATION */}
        {meta.last_page > 1 && (
          <div className="flex items-center justify-between p-4 border-t border-slate-100 dark:border-slate-800">
            <p className="text-xs text-slate-500 font-medium">
              Menampilkan {meta.from || 0} - {meta.to || 0} dari {meta.total || 0} Mata Pelajaran
            </p>
            <div className="flex items-center gap-1">
              <button
                disabled={page === 1}
                onClick={() => setPage((p) => Math.max(p - 1, 1))}
                className="p-1.5 rounded-lg border border-slate-200 disabled:opacity-40 text-slate-600 dark:border-slate-800 dark:text-slate-300"
              >
                <ChevronLeft className="h-4 w-4" />
              </button>
              <span className="px-3 py-1 text-xs font-bold text-slate-700 dark:text-slate-300">
                Halaman {page} dari {meta.last_page}
              </span>
              <button
                disabled={page === meta.last_page}
                onClick={() => setPage((p) => p + 1)}
                className="p-1.5 rounded-lg border border-slate-200 disabled:opacity-40 text-slate-600 dark:border-slate-800 dark:text-slate-300"
              >
                <ChevronRight className="h-4 w-4" />
              </button>
            </div>
          </div>
        )}
      </div>

      {/* FORM MODAL (CREATE & EDIT) */}
      {isFormModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
          <div className="bg-white rounded-[24px] max-w-2xl w-full p-6 space-y-4 shadow-2xl border border-emerald-100 dark:bg-[#1B2433] dark:border-slate-800">
            <div className="flex items-center justify-between border-b border-slate-100 pb-3 dark:border-slate-800">
              <h2 className="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                <BookOpen className="h-5 w-5 text-[#0E5C44] dark:text-[#3FBF75]" />
                <span>{selectedForEdit ? 'Edit Mata Pelajaran' : 'Tambah Mata Pelajaran Baru'}</span>
              </h2>
              <button onClick={() => setIsFormModalOpen(false)} className="text-slate-400 hover:text-slate-600 font-bold">
                ✕
              </button>
            </div>

            <form onSubmit={handleSubmit} className="space-y-4 text-xs font-medium">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="font-bold text-slate-700 block mb-1 dark:text-slate-300">Kurikulum *</label>
                  <select
                    value={formData.kurikulum_id}
                    onChange={(e) => setFormData({ ...formData, kurikulum_id: e.target.value })}
                    required
                    className="w-full p-2.5 rounded-xl border border-slate-200 dark:bg-slate-900 dark:border-slate-800 dark:text-white"
                  >
                    <option value="">Pilih Kurikulum</option>
                    {kurikulumDropdown.map((k) => (
                      <option key={k.id} value={k.id}>
                        {k.nama_kurikulum} {k.kode_kurikulum ? `(${k.kode_kurikulum})` : ''}
                      </option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="font-bold text-slate-700 block mb-1 dark:text-slate-300">Unit Pendidikan *</label>
                  <select
                    value={formData.unit_pendidikan_id}
                    onChange={(e) => setFormData({ ...formData, unit_pendidikan_id: e.target.value })}
                    required
                    className="w-full p-2.5 rounded-xl border border-slate-200 dark:bg-slate-900 dark:border-slate-800 dark:text-white"
                  >
                    <option value="">Pilih Unit Pendidikan</option>
                    {unitDropdown.map((u) => (
                      <option key={u.id} value={u.id}>{u.name}</option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="font-bold text-slate-700 block mb-1 dark:text-slate-300">Kode Mapel *</label>
                  <input
                    type="text"
                    value={formData.kode_mapel}
                    onChange={(e) => setFormData({ ...formData, kode_mapel: e.target.value })}
                    required
                    placeholder="Contoh: MP-SD-PAI"
                    className="w-full p-2.5 rounded-xl border border-slate-200 dark:bg-slate-900 dark:border-slate-800 dark:text-white"
                  />
                </div>

                <div>
                  <label className="font-bold text-slate-700 block mb-1 dark:text-slate-300">Nama Mapel *</label>
                  <input
                    type="text"
                    value={formData.nama_mapel}
                    onChange={(e) => setFormData({ ...formData, nama_mapel: e.target.value })}
                    required
                    placeholder="Contoh: Pendidikan Agama Islam"
                    className="w-full p-2.5 rounded-xl border border-slate-200 dark:bg-slate-900 dark:border-slate-800 dark:text-white"
                  />
                </div>

                <div>
                  <label className="font-bold text-slate-700 block mb-1 dark:text-slate-300">Nama Singkat / Singkatan</label>
                  <input
                    type="text"
                    value={formData.nama_singkat}
                    onChange={(e) => setFormData({ ...formData, nama_singkat: e.target.value })}
                    placeholder="Contoh: PAI"
                    className="w-full p-2.5 rounded-xl border border-slate-200 dark:bg-slate-900 dark:border-slate-800 dark:text-white"
                  />
                </div>

                <div>
                  <label className="font-bold text-slate-700 block mb-1 dark:text-slate-300">Kelompok Mapel</label>
                  <select
                    value={formData.kelompok_mapel}
                    onChange={(e) => setFormData({ ...formData, kelompok_mapel: e.target.value })}
                    className="w-full p-2.5 rounded-xl border border-slate-200 dark:bg-slate-900 dark:border-slate-800 dark:text-white"
                  >
                    {KELOMPOK_LIST.map((k) => (
                      <option key={k} value={k}>{k}</option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="font-bold text-slate-700 block mb-1 dark:text-slate-300">Kategori</label>
                  <select
                    value={formData.kategori}
                    onChange={(e) => setFormData({ ...formData, kategori: e.target.value })}
                    className="w-full p-2.5 rounded-xl border border-slate-200 dark:bg-slate-900 dark:border-slate-800 dark:text-white"
                  >
                    {KATEGORI_LIST.map((k) => (
                      <option key={k} value={k}>{k}</option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="font-bold text-slate-700 block mb-1 dark:text-slate-300">Alokasi JP per Minggu</label>
                  <input
                    type="number"
                    value={formData.jam_pelajaran}
                    onChange={(e) => setFormData({ ...formData, jam_pelajaran: parseInt(e.target.value) || 0 })}
                    className="w-full p-2.5 rounded-xl border border-slate-200 dark:bg-slate-900 dark:border-slate-800 dark:text-white"
                  />
                </div>

                <div>
                  <label className="font-bold text-slate-700 block mb-1 dark:text-slate-300">KKM Minimum</label>
                  <input
                    type="number"
                    step="0.01"
                    value={formData.kkm}
                    onChange={(e) => setFormData({ ...formData, kkm: parseFloat(e.target.value) || 0 })}
                    className="w-full p-2.5 rounded-xl border border-slate-200 dark:bg-slate-900 dark:border-slate-800 dark:text-white"
                  />
                </div>

                <div>
                  <label className="font-bold text-slate-700 block mb-1 dark:text-slate-300">Bobot Pengetahuan (%)</label>
                  <input
                    type="number"
                    value={formData.bobot_pengetahuan}
                    onChange={(e) => setFormData({ ...formData, bobot_pengetahuan: parseInt(e.target.value) || 0 })}
                    className="w-full p-2.5 rounded-xl border border-slate-200 dark:bg-slate-900 dark:border-slate-800 dark:text-white"
                  />
                </div>

                <div>
                  <label className="font-bold text-slate-700 block mb-1 dark:text-slate-300">Bobot Keterampilan (%)</label>
                  <input
                    type="number"
                    value={formData.bobot_keterampilan}
                    onChange={(e) => setFormData({ ...formData, bobot_keterampilan: parseInt(e.target.value) || 0 })}
                    className="w-full p-2.5 rounded-xl border border-slate-200 dark:bg-slate-900 dark:border-slate-800 dark:text-white"
                  />
                </div>

                <div>
                  <label className="font-bold text-slate-700 block mb-1 dark:text-slate-300">Bobot Sikap (%)</label>
                  <input
                    type="number"
                    value={formData.bobot_sikap}
                    onChange={(e) => setFormData({ ...formData, bobot_sikap: parseInt(e.target.value) || 0 })}
                    className="w-full p-2.5 rounded-xl border border-slate-200 dark:bg-slate-900 dark:border-slate-800 dark:text-white"
                  />
                </div>
              </div>

              <div className="flex justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-800">
                <button
                  type="button"
                  onClick={() => setIsFormModalOpen(false)}
                  className="px-4 py-2 rounded-xl border border-slate-200 font-bold text-slate-600 dark:border-slate-800 dark:text-slate-300"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={simpanMutation.isPending}
                  className="px-5 py-2 rounded-xl bg-[#0E5C44] text-white font-bold hover:bg-[#1E8E5A] transition shadow-md"
                >
                  {simpanMutation.isPending ? 'Menyimpan...' : 'Simpan Data'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* DETAIL MODAL */}
      {isDetailModalOpen && selectedForDetail && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
          <div className="bg-white rounded-[24px] max-w-xl w-full p-6 space-y-4 shadow-2xl border border-emerald-100 dark:bg-[#1B2433] dark:border-slate-800 text-xs">
            <div className="flex items-center justify-between border-b border-slate-100 pb-3 dark:border-slate-800">
              <h2 className="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                <Eye className="h-5 w-5 text-[#0E5C44] dark:text-[#3FBF75]" />
                <span>Detail Mata Pelajaran</span>
              </h2>
              <button onClick={() => setIsDetailModalOpen(false)} className="text-slate-400 font-bold">
                ✕
              </button>
            </div>

            <div className="space-y-3">
              <div className="p-4 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-100 dark:border-slate-800 space-y-2">
                <div className="flex justify-between">
                  <span className="text-slate-400 font-bold">Kode Mapel:</span>
                  <span className="font-mono font-extrabold text-slate-900 dark:text-white">{selectedForDetail.kode_mapel || selectedForDetail.code}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-slate-400 font-bold">Nama Mapel:</span>
                  <span className="font-black text-slate-900 dark:text-white">{selectedForDetail.nama_mapel || selectedForDetail.name}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-slate-400 font-bold">Kurikulum:</span>
                  <span className="font-bold text-slate-700 dark:text-slate-200">{selectedForDetail.kurikulum?.nama_kurikulum || '-'}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-slate-400 font-bold">Unit Pendidikan:</span>
                  <span className="font-bold text-slate-700 dark:text-slate-200">{selectedForDetail.unit_pendidikan?.name || '-'}</span>
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div className="p-3 rounded-xl bg-emerald-50/50 dark:bg-emerald-950/30 border border-emerald-100 dark:border-emerald-900">
                  <p className="text-[10px] text-slate-400 font-bold">ALOKASI JAM PELAJARAN</p>
                  <p className="text-base font-black text-emerald-700 dark:text-emerald-300 mt-0.5">{selectedForDetail.jam_pelajaran || 2} JP / Minggu</p>
                </div>
                <div className="p-3 rounded-xl bg-emerald-50/50 dark:bg-emerald-950/30 border border-emerald-100 dark:border-emerald-900">
                  <p className="text-[10px] text-slate-400 font-bold">KKM MINIMUM</p>
                  <p className="text-base font-black text-emerald-700 dark:text-emerald-300 mt-0.5">{selectedForDetail.kkm || 75}</p>
                </div>
              </div>

              <div className="p-3 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-100 dark:border-slate-800">
                <p className="text-[10px] text-slate-400 font-bold uppercase mb-1">Bobot Penilaian Rapor</p>
                <div className="flex justify-between text-xs font-bold text-slate-700 dark:text-slate-300">
                  <span>Pengetahuan: {selectedForDetail.bobot_pengetahuan || 40}%</span>
                  <span>Keterampilan: {selectedForDetail.bobot_keterampilan || 40}%</span>
                  <span>Sikap: {selectedForDetail.bobot_sikap || 20}%</span>
                </div>
              </div>
            </div>

            <div className="flex justify-end pt-3">
              <button
                onClick={() => setIsDetailModalOpen(false)}
                className="px-4 py-2 rounded-xl bg-slate-100 font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-300"
              >
                Tutup
              </button>
            </div>
          </div>
        </div>
      )}

      {/* IMPORT MODAL */}
      {isImportModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
          <div className="bg-white rounded-[24px] max-w-md w-full p-6 space-y-4 shadow-2xl border border-emerald-100 dark:bg-[#1B2433] dark:border-slate-800 text-xs">
            <div className="flex items-center justify-between border-b border-slate-100 pb-3 dark:border-slate-800">
              <h2 className="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                <Upload className="h-5 w-5 text-[#0E5C44] dark:text-[#3FBF75]" />
                <span>Import Data Mata Pelajaran</span>
              </h2>
              <button onClick={() => setIsImportModalOpen(false)} className="text-slate-400 font-bold">
                ✕
              </button>
            </div>

            <form onSubmit={handleImportSubmit} className="space-y-4">
              <div className="p-4 rounded-xl bg-slate-50 border-2 border-dashed border-slate-200 text-center dark:bg-slate-900 dark:border-slate-800">
                <FileSpreadsheet className="h-8 w-8 text-emerald-600 mx-auto mb-2" />
                <p className="font-bold text-slate-700 dark:text-slate-300">Pilih file Excel (.xlsx) / CSV</p>
                <input
                  type="file"
                  accept=".xlsx, .xls, .csv"
                  onChange={(e) => setImportFile(e.target.files[0])}
                  className="mt-2 text-xs w-full"
                />
              </div>

              <div className="flex justify-between items-center pt-3 border-t border-slate-100 dark:border-slate-800">
                <button
                  type="button"
                  onClick={() => Swal.fire('Format Impor', 'Header kolom: kode_mapel, nama_mapel, kelompok_mapel, kategori, jam_pelajaran, kkm.', 'info')}
                  className="text-xs text-emerald-600 font-bold hover:underline"
                >
                  Download Template
                </button>
                <div className="flex gap-2">
                  <button
                    type="button"
                    onClick={() => setIsImportModalOpen(false)}
                    className="px-3 py-2 rounded-xl border border-slate-200 font-bold"
                  >
                    Batal
                  </button>
                  <button
                    type="submit"
                    className="px-4 py-2 rounded-xl bg-[#0E5C44] text-white font-bold hover:bg-[#1E8E5A]"
                  >
                    Upload & Impor
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  )
}
