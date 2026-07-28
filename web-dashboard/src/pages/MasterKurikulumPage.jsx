import React, { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import Swal from 'sweetalert2'
import {
  BookOpen,
  Plus,
  Search,
  FileSpreadsheet,
  Upload,
  Filter,
  CheckCircle,
  XCircle,
  ChevronLeft,
  ChevronRight,
  Layers,
  Sparkles,
} from 'lucide-react'
import { masterKurikulumService } from '../services/masterKurikulumService'
import KurikulumTable from '../components/kurikulum/KurikulumTable'
import KurikulumFormModal from '../components/kurikulum/KurikulumFormModal'
import KurikulumDetailModal from '../components/kurikulum/KurikulumDetailModal'
import KurikulumImportModal from '../components/kurikulum/KurikulumImportModal'

const JENIS_LIST = ['SIT', 'Merdeka', 'Nasional', 'Pesantren', 'Lokal', 'Lainnya']
const JENJANG_LIST = ['TK', 'PAUD', 'SD', 'MI', 'SMP', 'MTs', 'SMA', 'MA', 'Pesantren']

export default function MasterKurikulumPage() {
  const queryClient = useQueryClient()

  // Filter & Pagination States
  const [search, setSearch] = useState('')
  const [selectedStatusFilter, setSelectedStatusFilter] = useState('')
  const [selectedJenisFilter, setSelectedJenisFilter] = useState('')
  const [selectedJenjangFilter, setSelectedJenjangFilter] = useState('')
  const [denganSampahFilter, setDenganSampahFilter] = useState('')
  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState(15)

  // Modal States
  const [isFormModalOpen, setIsFormModalOpen] = useState(false)
  const [selectedForEdit, setSelectedForEdit] = useState(null)

  const [isDetailModalOpen, setIsDetailModalOpen] = useState(false)
  const [selectedForDetail, setSelectedForDetail] = useState(null)

  const [isImportModalOpen, setIsImportModalOpen] = useState(false)

  // Query Data List
  const {
    data: responseData = {},
    isLoading,
    isFetching,
  } = useQuery({
    queryKey: [
      'master-kurikulum-list',
      page,
      perPage,
      search,
      selectedStatusFilter,
      selectedJenisFilter,
      selectedJenjangFilter,
      denganSampahFilter,
    ],
    queryFn: () =>
      masterKurikulumService.getDaftar({
        page,
        per_page: perPage,
        search,
        status: selectedStatusFilter,
        jenis_kurikulum: selectedJenisFilter,
        jenjang: selectedJenjangFilter,
        dengan_sampah: denganSampahFilter,
        order_by: 'created_at',
        order_dir: 'desc',
      }),
  })

  const listData = responseData?.data || []
  const meta = responseData?.meta || {}
  const stats = responseData?.statistik || {}

  // Mutations
  const simpanMutation = useMutation({
    mutationFn: (payload) => masterKurikulumService.tambah(payload),
    onSuccess: (res) => {
      queryClient.invalidateQueries(['master-kurikulum-list'])
      setIsFormModalOpen(false)
      Swal.fire({
        icon: 'success',
        title: 'Berhasil!',
        text: res?.message || 'Data master kurikulum baru berhasil ditambahkan.',
        timer: 2000,
        showConfirmButton: false,
      })
    },
    onError: (err) => {
      const msg = err.response?.data?.message || 'Gagal menyimpan data master kurikulum.'
      Swal.fire('Error', msg, 'error')
    },
  })

  const ubahMutation = useMutation({
    mutationFn: ({ id, payload }) => masterKurikulumService.ubah({ id, payload }),
    onSuccess: (res) => {
      queryClient.invalidateQueries(['master-kurikulum-list'])
      setIsFormModalOpen(false)
      setSelectedForEdit(null)
      Swal.fire({
        icon: 'success',
        title: 'Berhasil!',
        text: res?.message || 'Perubahan data master kurikulum berhasil disimpan.',
        timer: 2000,
        showConfirmButton: false,
      })
    },
    onError: (err) => {
      const msg = err.response?.data?.message || 'Gagal memperbarui data master kurikulum.'
      Swal.fire('Error', msg, 'error')
    },
  })

  const hapusMutation = useMutation({
    mutationFn: (id) => masterKurikulumService.hapus(id),
    onSuccess: (res) => {
      queryClient.invalidateQueries(['master-kurikulum-list'])
      Swal.fire({
        icon: 'success',
        title: 'Berhasil!',
        text: res?.message || 'Data kurikulum berhasil dihapus.',
        timer: 2000,
        showConfirmButton: false,
      })
    },
    onError: (err) => {
      const msg = err.response?.data?.message || 'Gagal menghapus data kurikulum.'
      Swal.fire('Gagal Menghapus', msg, 'error')
    },
  })

  const pulihkanMutation = useMutation({
    mutationFn: (id) => masterKurikulumService.pulihkan(id),
    onSuccess: (res) => {
      queryClient.invalidateQueries(['master-kurikulum-list'])
      Swal.fire({
        icon: 'success',
        title: 'Berhasil!',
        text: res?.message || 'Data kurikulum berhasil dipulihkan.',
        timer: 2000,
        showConfirmButton: false,
      })
    },
    onError: (err) => {
      const msg = err.response?.data?.message || 'Gagal memulihkan data.'
      Swal.fire('Error', msg, 'error')
    },
  })

  const importMutation = useMutation({
    mutationFn: (rows) => masterKurikulumService.prosesImport(rows),
    onSuccess: (res) => {
      queryClient.invalidateQueries(['master-kurikulum-list'])
      setIsImportModalOpen(false)
      Swal.fire({
        icon: 'success',
        title: 'Impor Selesai!',
        text: res?.message || 'Data kurikulum berhasil diimpor.',
      })
    },
    onError: (err) => {
      const msg = err.response?.data?.message || 'Gagal memproses impor data.'
      Swal.fire('Error Impor', msg, 'error')
    },
  })

  // Handlers
  const handleOpenFormTambah = () => {
    setSelectedForEdit(null)
    setIsFormModalOpen(true)
  }

  const handleOpenFormEdit = (item) => {
    setSelectedForEdit(item)
    setIsFormModalOpen(true)
  }

  const handleOpenDetail = (item) => {
    setSelectedForDetail(item)
    setIsDetailModalOpen(true)
  }

  const handleConfirmDelete = (item) => {
    Swal.fire({
      title: 'Apakah Anda yakin ingin menghapus data kurikulum ini?',
      text: `Kurikulum "${item.nama_kurikulum}" akan dipindahkan ke tempat sampah (Soft Delete).`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#e11d48',
      cancelButtonColor: '#64748b',
      confirmButtonText: 'Ya, Hapus Data',
      cancelButtonText: 'Batal',
    }).then((result) => {
      if (result.isConfirmed) {
        hapusMutation.mutate(item.id)
      }
    })
  }

  const handleConfirmRestore = (item) => {
    pulihkanMutation.mutate(item.id)
  }

  const handleFormSubmit = (payload) => {
    if (selectedForEdit) {
      ubahMutation.mutate({ id: selectedForEdit.id, payload })
    } else {
      simpanMutation.mutate(payload)
    }
  }

  const handleExportExcel = async () => {
    try {
      Swal.fire({
        title: 'Mempersiapkan Ekspor...',
        text: 'Mohon tunggu sebentar',
        allowOutsideClick: false,
        didOpen: () => {
          Swal.showLoading()
        },
      })

      const dataEkspor = await masterKurikulumService.ekspor({
        search,
        status: selectedStatusFilter,
        jenis_kurikulum: selectedJenisFilter,
        jenjang: selectedJenjangFilter,
      })

      if (!dataEkspor || dataEkspor.length === 0) {
        Swal.fire('Info', 'Tidak ada data untuk diekspor.', 'info')
        return
      }

      const headers = [
        'NO',
        'KODE KURIKULUM',
        'NAMA KURIKULUM',
        'JENIS KURIKULUM',
        'JENJANG',
        'UNIT PENDIDIKAN',
        'TAHUN AJARAN',
        'SEMESTER',
        'TANGGAL MULAI',
        'TANGGAL SELESAI',
        'STATUS',
        'DESKRIPSI',
        'TANGGAL DIBUAT',
      ]
      let csvStr = headers.join(',') + '\n'

      dataEkspor.forEach((row) => {
        const line = [
          row.no,
          `"${row.kode_kurikulum}"`,
          `"${row.nama_kurikulum}"`,
          `"${row.jenis_kurikulum}"`,
          `"${row.jenjang}"`,
          `"${row.unit_pendidikan}"`,
          `"${row.tahun_ajaran}"`,
          `"${row.semester}"`,
          `"${row.tanggal_mulai}"`,
          `"${row.tanggal_selesai}"`,
          `"${row.status}"`,
          `"${row.deskripsi ? row.deskripsi.replace(/"/g, '""') : ''}"`,
          `"${row.created_at}"`,
        ].join(',')
        csvStr += line + '\n'
      })

      const blob = new Blob([csvStr], { type: 'text/csv;charset=utf-8;' })
      const url = URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.setAttribute('href', url)
      link.setAttribute(
        'download',
        `export_master_kurikulum_${new Date().toISOString().slice(0, 10)}.csv`
      )
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)

      Swal.fire({
        icon: 'success',
        title: 'Berhasil Ekspor!',
        text: `${dataEkspor.length} data kurikulum berhasil diunduh.`,
        timer: 2000,
        showConfirmButton: false,
      })
    } catch (err) {
      Swal.fire('Error', 'Gagal mengunduh data ekspor.', 'error')
    }
  }

  return (
    <div className="space-y-6 pb-12">
      {/* HEADER BANNER - ENTERPRISE GREEN STYLING */}
      <div className="relative overflow-hidden rounded-3xl bg-gradient-to-r from-emerald-900 via-emerald-800 to-emerald-700 p-6 md:p-8 text-white shadow-xl border border-emerald-600/30">
        <div className="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
          {/* Header Left Text */}
          <div>
            <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-700/80 text-emerald-100 border border-emerald-500/30 uppercase tracking-widest mb-3">
              <Sparkles className="w-3.5 h-3.5 text-emerald-300" />
              AKADEMIK & MANAJEMEN SEKOLAH
            </span>
            <h1 className="text-2xl md:text-3xl font-black tracking-tight">
              Master Data Kurikulum
            </h1>
            <p className="text-emerald-100/90 text-sm mt-1.5 max-w-xl leading-relaxed font-medium">
              Kelola seluruh kurikulum pendidikan yang digunakan oleh setiap Unit Pendidikan Sekolah Islam Terpadu.
            </p>
          </div>

          {/* Header Right Action Buttons */}
          <div className="flex flex-wrap items-center gap-3">
            <button
              onClick={handleExportExcel}
              className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-white font-semibold text-sm backdrop-blur-xs border border-white/20 transition-all shadow-xs"
            >
              <FileSpreadsheet className="w-4 h-4 text-emerald-300" />
              Export CSV/Excel
            </button>

            <button
              onClick={() => setIsImportModalOpen(true)}
              className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-white font-semibold text-sm backdrop-blur-xs border border-white/20 transition-all shadow-xs"
            >
              <Upload className="w-4 h-4 text-emerald-300" />
              Import Data
            </button>

            <button
              onClick={handleOpenFormTambah}
              className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-white font-bold text-sm transition-all shadow-md hover:shadow-emerald-500/20 active:scale-95"
            >
              <Plus className="w-4 h-4" />
              Tambah Kurikulum
            </button>
          </div>
        </div>

        {/* Decorative Background Blob */}
        <div className="absolute -right-12 -bottom-12 w-64 h-64 bg-emerald-500/10 rounded-full blur-2xl pointer-events-none"></div>
      </div>

      {/* KPI DASHBOARD CARDS */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-5">
        {/* Card 1 */}
        <div className="bg-white p-5 rounded-2xl shadow-sm border border-emerald-100 flex items-center gap-4 hover:shadow-md transition-all">
          <div className="p-3.5 rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-100">
            <BookOpen className="w-6 h-6" />
          </div>
          <div>
            <p className="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Kurikulum</p>
            <h3 className="text-2xl font-black text-slate-900 mt-0.5">{stats.total ?? 0}</h3>
            <p className="text-xs font-medium text-emerald-600 mt-0.5">Terdaftar di sistem</p>
          </div>
        </div>

        {/* Card 2 */}
        <div className="bg-white p-5 rounded-2xl shadow-sm border border-emerald-100 flex items-center gap-4 hover:shadow-md transition-all">
          <div className="p-3.5 rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-100">
            <CheckCircle className="w-6 h-6 text-emerald-500" />
          </div>
          <div>
            <p className="text-xs font-bold text-slate-400 uppercase tracking-wider">Kurikulum Aktif</p>
            <h3 className="text-2xl font-black text-slate-900 mt-0.5">{stats.aktif ?? 0}</h3>
            <p className="text-xs font-medium text-emerald-600 mt-0.5">Sedang diberlakukan</p>
          </div>
        </div>

        {/* Card 3 */}
        <div className="bg-white p-5 rounded-2xl shadow-sm border border-emerald-100 flex items-center gap-4 hover:shadow-md transition-all">
          <div className="p-3.5 rounded-xl bg-amber-50 text-amber-600 border border-amber-100">
            <XCircle className="w-6 h-6 text-amber-500" />
          </div>
          <div>
            <p className="text-xs font-bold text-slate-400 uppercase tracking-wider">Kurikulum Nonaktif</p>
            <h3 className="text-2xl font-black text-slate-900 mt-0.5">{stats.tidak_aktif ?? 0}</h3>
            <p className="text-xs font-medium text-amber-600 mt-0.5">Arsip / Tidak aktif</p>
          </div>
        </div>
      </div>

      {/* SEARCH & FILTER BAR */}
      <div className="bg-white p-4 rounded-2xl shadow-sm border border-emerald-100 flex flex-col md:flex-row md:items-center justify-between gap-4">
        {/* Search Box */}
        <div className="relative flex-1">
          <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 w-4 h-4" />
          <input
            type="text"
            value={search}
            onChange={(e) => {
              setSearch(e.target.value)
              setPage(1)
            }}
            placeholder="Cari Kode Kurikulum, Nama Kurikulum, atau Deskripsi..."
            className="w-full pl-10 pr-4 py-2 rounded-xl bg-slate-50/80 border border-slate-200 text-xs font-medium focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 focus:bg-white transition-colors"
          />
        </div>

        {/* Filters */}
        <div className="flex flex-wrap items-center gap-3 text-xs">
          <span className="flex items-center gap-1.5 font-bold text-slate-600">
            <Filter className="w-3.5 h-3.5 text-emerald-600" />
            Filter:
          </span>

          {/* Filter Jenis */}
          <select
            value={selectedJenisFilter}
            onChange={(e) => {
              setSelectedJenisFilter(e.target.value)
              setPage(1)
            }}
            className="px-3 py-2 rounded-xl bg-slate-50/80 border border-slate-200 font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white"
          >
            <option value="">Semua Jenis</option>
            {JENIS_LIST.map((j) => (
              <option key={j} value={j}>
                {j}
              </option>
            ))}
          </select>

          {/* Filter Jenjang */}
          <select
            value={selectedJenjangFilter}
            onChange={(e) => {
              setSelectedJenjangFilter(e.target.value)
              setPage(1)
            }}
            className="px-3 py-2 rounded-xl bg-slate-50/80 border border-slate-200 font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white"
          >
            <option value="">Semua Jenjang</option>
            {JENJANG_LIST.map((j) => (
              <option key={j} value={j}>
                {j}
              </option>
            ))}
          </select>

          {/* Filter Status */}
          <select
            value={selectedStatusFilter}
            onChange={(e) => {
              setSelectedStatusFilter(e.target.value)
              setPage(1)
            }}
            className="px-3 py-2 rounded-xl bg-slate-50/80 border border-slate-200 font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white"
          >
            <option value="">Semua Status</option>
            <option value="aktif">Aktif</option>
            <option value="tidak_aktif">Nonaktif</option>
          </select>

          {/* Filter Trash */}
          <select
            value={denganSampahFilter}
            onChange={(e) => {
              setDenganSampahFilter(e.target.value)
              setPage(1)
            }}
            className="px-3 py-2 rounded-xl bg-slate-50/80 border border-slate-200 font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white"
          >
            <option value="">Data Aktif</option>
            <option value="true">Termasuk Terhapus</option>
          </select>
        </div>
      </div>

      {/* TABLE DATA */}
      <KurikulumTable
        data={listData}
        isLoading={isLoading || isFetching}
        page={page}
        perPage={perPage}
        onDetail={handleOpenDetail}
        onEdit={handleOpenFormEdit}
        onDelete={handleConfirmDelete}
        onRestore={handleConfirmRestore}
      />

      {/* PAGINATION FOOTER */}
      {meta.total > 0 && (
        <div className="bg-white p-4 rounded-2xl shadow-sm border border-emerald-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4 text-xs text-slate-600 font-medium">
          <div>
            Menampilkan <strong>{meta.from || 0}</strong> - <strong>{meta.to || 0}</strong> dari{' '}
            <strong>{meta.total || 0}</strong> data Master Kurikulum
          </div>

          <div className="flex items-center gap-2">
            <button
              onClick={() => setPage((p) => Math.max(p - 1, 1))}
              disabled={page <= 1}
              className="px-3 py-1.5 rounded-lg border border-slate-200 hover:bg-emerald-50 disabled:opacity-40 disabled:hover:bg-transparent transition-colors inline-flex items-center gap-1 font-bold"
            >
              <ChevronLeft className="w-3.5 h-3.5" /> Prev
            </button>
            <span className="px-3 py-1.5 rounded-lg bg-emerald-50 text-emerald-800 font-bold border border-emerald-200">
              {meta.current_page || 1} / {meta.last_page || 1}
            </span>
            <button
              onClick={() => setPage((p) => Math.min(p + 1, meta.last_page || 1))}
              disabled={page >= (meta.last_page || 1)}
              className="px-3 py-1.5 rounded-lg border border-slate-200 hover:bg-emerald-50 disabled:opacity-40 disabled:hover:bg-transparent transition-colors inline-flex items-center gap-1 font-bold"
            >
              Next <ChevronRight className="w-3.5 h-3.5" />
            </button>
          </div>
        </div>
      )}

      {/* MODALS */}
      <KurikulumFormModal
        isOpen={isFormModalOpen}
        onClose={() => setIsFormModalOpen(false)}
        onSubmit={handleFormSubmit}
        initialData={selectedForEdit}
        isSubmitting={simpanMutation.isPending || ubahMutation.isPending}
      />

      <KurikulumDetailModal
        isOpen={isDetailModalOpen}
        onClose={() => setIsDetailModalOpen(false)}
        data={selectedForDetail}
      />

      <KurikulumImportModal
        isOpen={isImportModalOpen}
        onClose={() => setIsImportModalOpen(false)}
        onImport={(rows) => importMutation.mutate(rows)}
        isSubmitting={importMutation.isPending}
      />
    </div>
  )
}
