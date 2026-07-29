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
import { MasterDataPage } from '../components/master-data'

export default function MasterTahunAjaranPage() {
  const queryClient = useQueryClient()

  // Filter & Pagination States
  const [search, setSearch] = useState('')
  const [selectedStatusFilter, setSelectedStatusFilter] = useState('')
  const [denganSampahFilter, setDenganSampahFilter] = useState('')
  const [page, setPage] = useState(1)
  const perPage = 15

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
    } catch {
      Swal.fire('Error', 'Gagal mengunduh data ekspor.', 'error')
    }
  }

  return (
    <MasterDataPage>
      {/* Header dan urutan aksi mengikuti UI_UX_GUIDELINES.md */}
      <section className="ui-enter rounded-2xl border border-slate-200/80 bg-white p-6 shadow-xs">
        <div className="flex flex-col justify-between gap-5 lg:flex-row lg:items-center">
          {/* Header Left Text */}
          <div>
            <h1 className="text-2xl font-black tracking-tight text-slate-800">
              Data Master Tahun Ajaran
            </h1>
            <p className="mt-1 max-w-xl text-xs text-slate-500">
              Kelola seluruh periode tahun ajaran dan tetapkan periode aktif sekolah.
            </p>
          </div>

          {/* Header Right Action Buttons */}
          <div className="grid w-full grid-cols-2 gap-2 sm:flex sm:w-auto sm:flex-wrap sm:items-center">
            <button
              onClick={handleExportExcel}
              className="ui-button inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 shadow-sm transition-all hover:bg-slate-100"
            >
              <FaFileExcel className="h-4 w-4 text-emerald-700" />
              Ekspor
            </button>

            <button
              onClick={() => setIsImportModalOpen(true)}
              className="ui-button inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 shadow-sm transition-all hover:bg-slate-100"
            >
              <FaFileImport className="h-4 w-4 text-slate-500" />
              Impor
            </button>

            <button
              onClick={handleOpenFormTambah}
              className="ui-button col-span-2 inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-800 px-5 py-2.5 text-xs font-semibold text-white shadow-md shadow-emerald-800/20 transition-all hover:bg-emerald-900 hover:shadow-lg sm:ml-1"
            >
              <FaPlus className="w-4 h-4" />
              Tambah Tahun Ajaran
            </button>
          </div>
        </div>
      </section>

      {/* DASHBOARD CARDS */}
      <section className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4" aria-label="Ringkasan tahun ajaran">
        {/* Card 1 */}
        <div className="ui-card ui-enter flex min-h-28 items-center gap-5 rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm" style={{ animationDelay: '60ms' }}>
          <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl border border-emerald-100 bg-emerald-50 text-emerald-800">
            <FaCalendarAlt className="h-6 w-6" />
          </div>
          <div>
            <p className="text-[11px] font-black uppercase tracking-[0.12em] text-slate-500">Total Tahun Ajaran</p>
            <h3 className="mt-0.5 text-2xl font-black text-slate-800">{stats.total ?? 0}</h3>
            <p className="mt-0.5 text-xs font-medium text-slate-500">Terdaftar di sistem</p>
          </div>
        </div>

        {/* Card 2 */}
        <div className="ui-card ui-enter flex min-h-28 items-center gap-5 rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm" style={{ animationDelay: '110ms' }}>
          <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl border border-emerald-100 bg-emerald-50 text-emerald-800">
            <FaStar className="h-6 w-6 text-amber-500" />
          </div>
          <div>
            <p className="text-[11px] font-black uppercase tracking-[0.12em] text-slate-500">Periode Aktif Utama</p>
            <h3 className="mt-0.5 text-2xl font-black text-slate-800">{stats.aktif ?? 0}</h3>
            <p className="mt-0.5 text-xs font-medium text-emerald-700">Berjalan saat ini</p>
          </div>
        </div>

        {/* Card 3 */}
        <div className="ui-card ui-enter flex min-h-28 items-center gap-5 rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm" style={{ animationDelay: '160ms' }}>
          <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 text-slate-500">
            <FaTimesCircle className="h-6 w-6" />
          </div>
          <div>
            <p className="text-[11px] font-black uppercase tracking-[0.12em] text-slate-500">Arsip / Nonaktif</p>
            <h3 className="mt-0.5 text-2xl font-black text-slate-800">{stats.tidak_aktif ?? 0}</h3>
            <p className="mt-0.5 text-xs font-medium text-slate-500">Periode lampau atau mendatang</p>
          </div>
        </div>

        <div className="ui-card ui-enter flex min-h-28 items-center gap-5 rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm" style={{ animationDelay: '210ms' }}>
          <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl border border-blue-100 bg-blue-50 text-blue-600">
            <FaCalendarAlt className="h-6 w-6" />
          </div>
          <div>
            <p className="text-[11px] font-black uppercase tracking-[0.12em] text-slate-500">Periode Tersimpan</p>
            <h3 className="mt-0.5 text-2xl font-black text-slate-800">{stats.total ?? 0}</h3>
            <p className="mt-0.5 text-xs font-medium text-blue-600">Riwayat akademik</p>
          </div>
        </div>
      </section>

      {/* SEARCH & FILTER BAR */}
      <section className="ui-enter rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm" style={{ animationDelay: '210ms' }}>
        <div className="mb-4 flex items-center gap-2 text-xs font-bold text-slate-700">
          <FaFilter className="h-3.5 w-3.5 text-emerald-800" />
          Pencarian & Filter Data
        </div>
        <div className="grid grid-cols-1 gap-3 md:grid-cols-[minmax(0,1fr)_200px_200px]">
        {/* Search Box */}
        <label>
          <span className="mb-1.5 block text-xs font-semibold text-slate-700">Cari tahun ajaran</span>
          <span className="relative block">
          <FaSearch className="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 w-4 h-4" />
          <input
            type="text"
            value={search}
            onChange={(e) => {
              setSearch(e.target.value)
              setPage(1)
            }}
            placeholder="Cari Nama Tahun Ajaran (misal: 2025/2026)..."
            className="w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-10 pr-4 text-sm text-slate-800 shadow-sm transition-all placeholder:text-slate-400 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-emerald-600"
          />
          </span>
        </label>

        {/* Filters */}
          {/* Filter Status */}
          <label>
            <span className="mb-1.5 block text-xs font-semibold text-slate-700">Status periode</span>
          <select
            value={selectedStatusFilter}
            onChange={(e) => {
              setSelectedStatusFilter(e.target.value)
              setPage(1)
            }}
            className="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-800 shadow-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-emerald-600"
          >
            <option value="">Semua Status</option>
            <option value="true">Aktif Utama</option>
            <option value="false">Tidak Aktif</option>
          </select>
          </label>

          {/* Filter Trash */}
          <label>
            <span className="mb-1.5 block text-xs font-semibold text-slate-700">Cakupan data</span>
          <select
            value={denganSampahFilter}
            onChange={(e) => {
              setDenganSampahFilter(e.target.value)
              setPage(1)
            }}
            className="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-800 shadow-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-emerald-600"
          >
            <option value="">Data Aktif</option>
            <option value="true">Termasuk Terhapus</option>
          </select>
          </label>
        </div>
      </section>

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
        <div className="flex flex-col justify-between gap-4 rounded-2xl border border-slate-200/80 bg-white p-4 text-xs font-medium text-slate-600 shadow-sm sm:flex-row sm:items-center">
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
              <FaChevronLeft className="w-3 h-3" /> Sebelumnya
            </button>
            <span className="px-3 py-1.5 rounded-lg bg-emerald-50 text-emerald-800 font-bold border border-emerald-200">
              {meta.current_page || 1} / {meta.last_page || 1}
            </span>
            <button
              onClick={() => setPage((p) => Math.min(p + 1, meta.last_page || 1))}
              disabled={page >= (meta.last_page || 1)}
              className="px-3 py-1.5 rounded-lg border border-gray-200 hover:bg-emerald-50 disabled:opacity-40 disabled:hover:bg-transparent transition-colors inline-flex items-center gap-1"
            >
              Berikutnya <FaChevronRight className="w-3 h-3" />
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
    </MasterDataPage>
  )
}
