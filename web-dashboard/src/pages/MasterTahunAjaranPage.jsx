import React, { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import Swal from 'sweetalert2'
import {
  FaCalendarAlt,
  FaPlus,
  FaSearch,
  FaFileExcel,
  FaFileImport,
  FaFilter,
  FaCheckCircle,
  FaTimesCircle,
  FaStar,
  FaChevronLeft,
  FaChevronRight,
} from 'react-icons/fa'
import { tahunAjaranService } from '../services/tahunAjaranService'
import TahunAjaranTable from '../components/tahun-ajaran/TahunAjaranTable'
import TahunAjaranFormModal from '../components/tahun-ajaran/TahunAjaranFormModal'
import TahunAjaranDetailModal from '../components/tahun-ajaran/TahunAjaranDetailModal'
import TahunAjaranImportModal from '../components/tahun-ajaran/TahunAjaranImportModal'

export default function MasterTahunAjaranPage() {
  const queryClient = useQueryClient()

  // Filter & Pagination States
  const [search, setSearch] = useState('')
  const [selectedStatusFilter, setSelectedStatusFilter] = useState('')
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
      'tahun-ajaran-list',
      page,
      perPage,
      search,
      selectedStatusFilter,
      denganSampahFilter,
    ],
    queryFn: () =>
      tahunAjaranService.getDaftar({
        page,
        per_page: perPage,
        search,
        status: selectedStatusFilter,
        dengan_sampah: denganSampahFilter,
        order_by: 'start_date',
        order_dir: 'desc',
      }),
  })

  const listData = responseData?.data || []
  const meta = responseData?.meta || {}
  const stats = responseData?.statistik || {}

  // Mutations
  const simpanMutation = useMutation({
    mutationFn: (payload) => tahunAjaranService.tambah(payload),
    onSuccess: (res) => {
      queryClient.invalidateQueries(['tahun-ajaran-list'])
      setIsFormModalOpen(false)
      Swal.fire({
        icon: 'success',
        title: 'Berhasil!',
        text: res?.message || 'Data tahun ajaran baru berhasil ditambahkan.',
        timer: 2000,
        showConfirmButton: false,
      })
    },
    onError: (err) => {
      const msg = err.response?.data?.message || 'Gagal menyimpan data tahun ajaran.'
      Swal.fire('Error', msg, 'error')
    },
  })

  const ubahMutation = useMutation({
    mutationFn: ({ id, payload }) => tahunAjaranService.ubah({ id, payload }),
    onSuccess: (res) => {
      queryClient.invalidateQueries(['tahun-ajaran-list'])
      setIsFormModalOpen(false)
      setSelectedForEdit(null)
      Swal.fire({
        icon: 'success',
        title: 'Berhasil!',
        text: res?.message || 'Perubahan data tahun ajaran berhasil disimpan.',
        timer: 2000,
        showConfirmButton: false,
      })
    },
    onError: (err) => {
      const msg = err.response?.data?.message || 'Gagal memperbarui data tahun ajaran.'
      Swal.fire('Error', msg, 'error')
    },
  })

  const setAktifMutation = useMutation({
    mutationFn: (id) => tahunAjaranService.setAktif(id),
    onSuccess: (res) => {
      queryClient.invalidateQueries(['tahun-ajaran-list'])
      Swal.fire({
        icon: 'success',
        title: 'Berhasil Aktif!',
        text: res?.message || 'Tahun ajaran berhasil dijadikan sebagai periode aktif utama.',
        timer: 2000,
        showConfirmButton: false,
      })
    },
    onError: (err) => {
      const msg = err.response?.data?.message || 'Gagal mengaktifkan tahun ajaran.'
      Swal.fire('Error', msg, 'error')
    },
  })

  const hapusMutation = useMutation({
    mutationFn: (id) => tahunAjaranService.hapus(id),
    onSuccess: (res) => {
      queryClient.invalidateQueries(['tahun-ajaran-list'])
      Swal.fire({
        icon: 'success',
        title: 'Berhasil!',
        text: res?.message || 'Data tahun ajaran berhasil dihapus.',
        timer: 2000,
        showConfirmButton: false,
      })
    },
    onError: (err) => {
      const msg = err.response?.data?.message || 'Gagal menghapus data tahun ajaran.'
      Swal.fire('Gagal Menghapus', msg, 'error')
    },
  })

  const pulihkanMutation = useMutation({
    mutationFn: (id) => tahunAjaranService.pulihkan(id),
    onSuccess: (res) => {
      queryClient.invalidateQueries(['tahun-ajaran-list'])
      Swal.fire({
        icon: 'success',
        title: 'Berhasil!',
        text: res?.message || 'Data tahun ajaran berhasil dipulihkan.',
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
    mutationFn: (rows) => tahunAjaranService.prosesImport(rows),
    onSuccess: (res) => {
      queryClient.invalidateQueries(['tahun-ajaran-list'])
      setIsImportModalOpen(false)
      Swal.fire({
        icon: 'success',
        title: 'Impor Selesai!',
        text: res?.message || 'Data tahun ajaran berhasil diimpor.',
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

  const handleSetAktif = (item) => {
    Swal.fire({
      title: `Aktifkan Tahun Ajaran ${item.name}?`,
      text: 'Mengaktifkan tahun ajaran ini akan menonaktifkan tahun ajaran aktif sebelumnya.',
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#0E5C44',
      cancelButtonColor: '#64748b',
      confirmButtonText: 'Ya, Aktifkan Periode Ini',
      cancelButtonText: 'Batal',
    }).then((result) => {
      if (result.isConfirmed) {
        setAktifMutation.mutate(item.id)
      }
    })
  }

  const handleConfirmDelete = (item) => {
    Swal.fire({
      title: `Hapus Tahun Ajaran ${item.name}?`,
      text: 'Data tahun ajaran yang terhapus dapat dipulihkan kembali jika diperlukan.',
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

      const dataEkspor = await tahunAjaranService.ekspor({
        search,
        status: selectedStatusFilter,
      })

      if (!dataEkspor || dataEkspor.length === 0) {
        Swal.fire('Info', 'Tidak ada data untuk diekspor.', 'info')
        return
      }

      const headers = ['NO', 'NAMA TAHUN AJARAN', 'TANGGAL MULAI', 'TANGGAL SELESAI', 'STATUS AKTIF', 'KETERANGAN', 'TANGGAL DIBUAT']
      let csvStr = headers.join(',') + '\n'

      dataEkspor.forEach((row) => {
        const line = [
          row.no,
          `"${row.nama}"`,
          `"${row.start_date}"`,
          `"${row.end_date}"`,
          `"${row.is_active}"`,
          `"${row.keterangan ? row.keterangan.replace(/"/g, '""') : ''}"`,
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
        `export_tahun_ajaran_${new Date().toISOString().slice(0, 10)}.csv`
      )
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)

      Swal.fire({
        icon: 'success',
        title: 'Berhasil Ekspor!',
        text: `${dataEkspor.length} data tahun ajaran berhasil diunduh.`,
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
      <div className="relative overflow-hidden rounded-2xl bg-gradient-to-r from-emerald-900 via-emerald-800 to-emerald-700 p-6 md:p-8 text-white shadow-xl">
        <div className="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
          {/* Header Left Text */}
          <div>
            <span className="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-emerald-700/80 text-emerald-100 border border-emerald-500/30 uppercase tracking-widest mb-3">
              MASTER DATA AKADEMIK
            </span>
            <h1 className="text-2xl md:text-3xl font-extrabold tracking-tight">
              Data Master Tahun Ajaran
            </h1>
            <p className="text-emerald-100/90 text-sm mt-1.5 max-w-xl leading-relaxed">
              Kelola seluruh periode tahun ajaran dan tetapkan periode aktif sekolah.
            </p>
          </div>

          {/* Header Right Action Buttons */}
          <div className="flex flex-wrap items-center gap-3">
            <button
              onClick={handleExportExcel}
              className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-white font-semibold text-sm backdrop-blur-xs border border-white/20 transition-all shadow-xs"
            >
              <FaFileExcel className="w-4 h-4 text-emerald-300" />
              📥 Export Excel
            </button>

            <button
              onClick={() => setIsImportModalOpen(true)}
              className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-white font-semibold text-sm backdrop-blur-xs border border-white/20 transition-all shadow-xs"
            >
              <FaFileImport className="w-4 h-4 text-emerald-300" />
              📤 Import Excel
            </button>

            <button
              onClick={handleOpenFormTambah}
              className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-white font-bold text-sm transition-all shadow-md hover:shadow-emerald-500/20 active:scale-95"
            >
              <FaPlus className="w-4 h-4" />
              ➕ Tambah Tahun Ajaran
            </button>
          </div>
        </div>

        {/* Decorative background elements */}
        <div className="absolute -right-12 -bottom-12 w-64 h-64 bg-emerald-500/10 rounded-full blur-2xl pointer-events-none"></div>
      </div>

      {/* DASHBOARD CARDS */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-5">
        {/* Card 1 */}
        <div className="bg-white p-5 rounded-2xl shadow-sm border border-emerald-100 flex items-center gap-4 hover:shadow-md transition-all">
          <div className="p-3.5 rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-100">
            <FaCalendarAlt className="w-6 h-6" />
          </div>
          <div>
            <p className="text-xs font-bold text-gray-400 uppercase tracking-wider">Total Tahun Ajaran</p>
            <h3 className="text-2xl font-black text-gray-900 mt-0.5">{stats.total ?? 0}</h3>
            <p className="text-xs font-medium text-emerald-600 mt-0.5">Terdaftar di sistem</p>
          </div>
        </div>

        {/* Card 2 */}
        <div className="bg-white p-5 rounded-2xl shadow-sm border border-emerald-100 flex items-center gap-4 hover:shadow-md transition-all">
          <div className="p-3.5 rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-100">
            <FaStar className="w-6 h-6 text-amber-500" />
          </div>
          <div>
            <p className="text-xs font-bold text-gray-400 uppercase tracking-wider">Periode Aktif Utama</p>
            <h3 className="text-2xl font-black text-gray-900 mt-0.5">{stats.aktif ?? 0}</h3>
            <p className="text-xs font-medium text-emerald-600 mt-0.5">Berjalan saat ini</p>
          </div>
        </div>

        {/* Card 3 */}
        <div className="bg-white p-5 rounded-2xl shadow-sm border border-emerald-100 flex items-center gap-4 hover:shadow-md transition-all">
          <div className="p-3.5 rounded-xl bg-slate-50 text-slate-600 border border-slate-200">
            <FaTimesCircle className="w-6 h-6 text-slate-400" />
          </div>
          <div>
            <p className="text-xs font-bold text-gray-400 uppercase tracking-wider">Arsip / Non-Aktif</p>
            <h3 className="text-2xl font-black text-gray-900 mt-0.5">{stats.tidak_aktif ?? 0}</h3>
            <p className="text-xs font-medium text-slate-500 mt-0.5">Periode lampau / mendatang</p>
          </div>
        </div>
      </div>

      {/* SEARCH & FILTER BAR */}
      <div className="bg-white p-4 rounded-2xl shadow-sm border border-emerald-100 flex flex-col md:flex-row md:items-center justify-between gap-4">
        {/* Search Box */}
        <div className="relative flex-1">
          <FaSearch className="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 w-4 h-4" />
          <input
            type="text"
            value={search}
            onChange={(e) => {
              setSearch(e.target.value)
              setPage(1)
            }}
            placeholder="Cari Nama Tahun Ajaran (misal: 2025/2026)..."
            className="w-full pl-10 pr-4 py-2 rounded-xl bg-gray-50/80 border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 focus:bg-white transition-colors"
          />
        </div>

        {/* Filters */}
        <div className="flex flex-wrap items-center gap-3 text-sm">
          <span className="flex items-center gap-1.5 font-bold text-gray-600">
            <FaFilter className="w-3.5 h-3.5 text-emerald-600" />
            Filter:
          </span>

          {/* Filter Status */}
          <select
            value={selectedStatusFilter}
            onChange={(e) => {
              setSelectedStatusFilter(e.target.value)
              setPage(1)
            }}
            className="px-3.5 py-2 rounded-xl bg-gray-50/80 border border-gray-200 font-medium text-gray-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white"
          >
            <option value="">Semua Status</option>
            <option value="true">Aktif Utama</option>
            <option value="false">Tidak Aktif</option>
          </select>

          {/* Filter Trash */}
          <select
            value={denganSampahFilter}
            onChange={(e) => {
              setDenganSampahFilter(e.target.value)
              setPage(1)
            }}
            className="px-3.5 py-2 rounded-xl bg-gray-50/80 border border-gray-200 font-medium text-gray-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white"
          >
            <option value="">Data Aktif</option>
            <option value="true">Termasuk Terhapus</option>
          </select>
        </div>
      </div>

      {/* TABLE DATA */}
      <TahunAjaranTable
        data={listData}
        isLoading={isLoading || isFetching}
        page={page}
        perPage={perPage}
        onDetail={handleOpenDetail}
        onEdit={handleOpenFormEdit}
        onSetAktif={handleSetAktif}
        onDelete={handleConfirmDelete}
        onRestore={handleConfirmRestore}
      />

      {/* PAGINATION FOOTER */}
      {meta.total > 0 && (
        <div className="bg-white p-4 rounded-2xl shadow-sm border border-emerald-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4 text-xs text-gray-600 font-medium">
          <div>
            Menampilkan <strong>{meta.from || 0}</strong> - <strong>{meta.to || 0}</strong> dari{' '}
            <strong>{meta.total || 0}</strong> data Tahun Ajaran
          </div>

          <div className="flex items-center gap-2">
            <button
              onClick={() => setPage((p) => Math.max(p - 1, 1))}
              disabled={page <= 1}
              className="px-3 py-1.5 rounded-lg border border-gray-200 hover:bg-emerald-50 disabled:opacity-40 disabled:hover:bg-transparent transition-colors inline-flex items-center gap-1"
            >
              <FaChevronLeft className="w-3 h-3" /> Prev
            </button>
            <span className="px-3 py-1.5 rounded-lg bg-emerald-50 text-emerald-800 font-bold border border-emerald-200">
              {meta.current_page || 1} / {meta.last_page || 1}
            </span>
            <button
              onClick={() => setPage((p) => Math.min(p + 1, meta.last_page || 1))}
              disabled={page >= (meta.last_page || 1)}
              className="px-3 py-1.5 rounded-lg border border-gray-200 hover:bg-emerald-50 disabled:opacity-40 disabled:hover:bg-transparent transition-colors inline-flex items-center gap-1"
            >
              Next <FaChevronRight className="w-3 h-3" />
            </button>
          </div>
        </div>
      )}

      {/* MODALS */}
      <TahunAjaranFormModal
        isOpen={isFormModalOpen}
        onClose={() => setIsFormModalOpen(false)}
        onSubmit={handleFormSubmit}
        initialData={selectedForEdit}
        isSubmitting={simpanMutation.isPending || ubahMutation.isPending}
      />

      <TahunAjaranDetailModal
        isOpen={isDetailModalOpen}
        onClose={() => setIsDetailModalOpen(false)}
        data={selectedForDetail}
      />

      <TahunAjaranImportModal
        isOpen={isImportModalOpen}
        onClose={() => setIsImportModalOpen(false)}
        onImport={(rows) => importMutation.mutate(rows)}
        isSubmitting={importMutation.isPending}
      />
    </div>
  )
}
