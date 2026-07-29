import React, { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import Swal from 'sweetalert2'
import {
  FaSchool,
  FaPlus,
  FaSearch,
  FaFileExcel,
  FaFileImport,
  FaFilter,
  FaCheckCircle,
  FaTimesCircle,
  FaChevronLeft,
  FaChevronRight,
} from 'react-icons/fa'
import { jenisUnitService } from '../services/jenisUnitService'
import JenisUnitTable from '../components/jenis-unit/JenisUnitTable'
import JenisUnitFormModal from '../components/jenis-unit/JenisUnitFormModal'
import JenisUnitDetailModal from '../components/jenis-unit/JenisUnitDetailModal'
import JenisUnitImportModal from '../components/jenis-unit/JenisUnitImportModal'
import { MasterDataPage } from '../components/master-data'

const JENJANG_LIST = [
  'PAUD',
  'TK',
  'SD',
  'MI',
  'SMP',
  'MTs',
  'SMA',
  'MA',
  'Pondok Pesantren',
  'Mahad',
]

export default function MasterJenisUnitPendidikanPage() {
  const queryClient = useQueryClient()

  // Filter & Pagination States
  const [search, setSearch] = useState('')
  const [selectedStatusFilter, setSelectedStatusFilter] = useState('')
  const [selectedJenjangFilter, setSelectedJenjangFilter] = useState('')
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
      'jenis-unit-list',
      page,
      perPage,
      search,
      selectedStatusFilter,
      selectedJenjangFilter,
      denganSampahFilter,
    ],
    queryFn: () =>
      jenisUnitService.getDaftar({
        page,
        per_page: perPage,
        search,
        status: selectedStatusFilter,
        jenjang: selectedJenjangFilter,
        dengan_sampah: denganSampahFilter,
        order_by: 'urutan',
        order_dir: 'asc',
      }),
  })

  const listData = responseData?.data || []
  const meta = responseData?.meta || {}
  const stats = responseData?.statistik || {}

  // Mutations
  const simpanMutation = useMutation({
    mutationFn: (payload) => jenisUnitService.tambah(payload),
    onSuccess: (res) => {
      queryClient.invalidateQueries(['jenis-unit-list'])
      setIsFormModalOpen(false)
      Swal.fire({
        icon: 'success',
        title: 'Berhasil!',
        text: res?.message || 'Data jenis unit pendidikan baru berhasil ditambahkan.',
        timer: 2000,
        showConfirmButton: false,
      })
    },
    onError: (err) => {
      const msg = err.response?.data?.message || 'Gagal menyimpan data jenis unit pendidikan.'
      Swal.fire('Error', msg, 'error')
    },
  })

  const ubahMutation = useMutation({
    mutationFn: ({ id, payload }) => jenisUnitService.ubah({ id, payload }),
    onSuccess: (res) => {
      queryClient.invalidateQueries(['jenis-unit-list'])
      setIsFormModalOpen(false)
      setSelectedForEdit(null)
      Swal.fire({
        icon: 'success',
        title: 'Berhasil!',
        text: res?.message || 'Perubahan data jenis unit pendidikan berhasil disimpan.',
        timer: 2000,
        showConfirmButton: false,
      })
    },
    onError: (err) => {
      const msg = err.response?.data?.message || 'Gagal memperbarui data jenis unit pendidikan.'
      Swal.fire('Error', msg, 'error')
    },
  })

  const hapusMutation = useMutation({
    mutationFn: (id) => jenisUnitService.hapus(id),
    onSuccess: (res) => {
      queryClient.invalidateQueries(['jenis-unit-list'])
      Swal.fire({
        icon: 'success',
        title: 'Berhasil!',
        text: res?.message || 'Data jenis unit berhasil dihapus.',
        timer: 2000,
        showConfirmButton: false,
      })
    },
    onError: (err) => {
      const msg =
        err.response?.data?.message ||
        'Jika data sudah digunakan oleh Unit Pendidikan maka data tidak dapat dihapus.'
      Swal.fire('Gagal Menghapus', msg, 'error')
    },
  })

  const pulihkanMutation = useMutation({
    mutationFn: (id) => jenisUnitService.pulihkan(id),
    onSuccess: (res) => {
      queryClient.invalidateQueries(['jenis-unit-list'])
      Swal.fire({
        icon: 'success',
        title: 'Berhasil!',
        text: res?.message || 'Data jenis unit berhasil dipulihkan.',
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
    mutationFn: (rows) => jenisUnitService.prosesImport(rows),
    onSuccess: (res) => {
      queryClient.invalidateQueries(['jenis-unit-list'])
      setIsImportModalOpen(false)
      Swal.fire({
        icon: 'success',
        title: 'Impor Selesai!',
        text: res?.message || 'Data jenis unit berhasil diimpor.',
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
      title: 'Apakah Anda yakin ingin menghapus data ini?',
      text: 'Jika data sudah digunakan oleh Unit Pendidikan maka data tidak dapat dihapus.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#e11d48',
      cancelButtonColor: '#64748b',
      confirmButtonText: 'Ya, Hapus Data',
      cancelButtonText: 'Batal',
    }).then((result) => {
      if (result.isConfirmed) {
        hapusMutation.mutate(item.id || item.uuid)
      }
    })
  }

  const handleConfirmRestore = (item) => {
    pulihkanMutation.mutate(item.id || item.uuid)
  }

  const handleFormSubmit = (payload) => {
    if (selectedForEdit) {
      ubahMutation.mutate({ id: selectedForEdit.id || selectedForEdit.uuid, payload })
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

      const dataEkspor = await jenisUnitService.ekspor({
        search,
        status: selectedStatusFilter,
        jenjang: selectedJenjangFilter,
      })

      if (!dataEkspor || dataEkspor.length === 0) {
        Swal.fire('Info', 'Tidak ada data untuk diekspor.', 'info')
        return
      }

      // Convert data to CSV for Excel download
      const headers = [
        'NO',
        'KODE JENIS',
        'NAMA JENIS UNIT',
        'SINGKATAN',
        'JENJANG',
        'WARNA BADGE',
        'ICON',
        'URUTAN',
        'STATUS',
        'KETERANGAN',
        'TANGGAL DIBUAT',
      ]
      let csvStr = headers.join(',') + '\n'

      dataEkspor.forEach((row) => {
        const line = [
          row.no,
          `"${row.kode_jenis}"`,
          `"${row.nama_jenis}"`,
          `"${row.singkatan}"`,
          `"${row.jenjang}"`,
          `"${row.warna_badge}"`,
          `"${row.icon}"`,
          row.urutan,
          `"${row.status}"`,
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
        `export_jenis_unit_pendidikan_${new Date().toISOString().slice(0, 10)}.csv`
      )
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)

      Swal.fire({
        icon: 'success',
        title: 'Berhasil Ekspor!',
        text: `${dataEkspor.length} data berhasil diunduh.`,
        timer: 2000,
        showConfirmButton: false,
      })
    } catch {
      Swal.fire('Error', 'Gagal mengunduh data ekspor.', 'error')
    }
  }

  return (
    <MasterDataPage>
      {/* Header — standar modul Profil */}
      <div className="ui-enter rounded-2xl border border-slate-200/80 bg-white p-6 shadow-xs">
        <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
          {/* Header Left Text */}
          <div>
            <h1 className="text-2xl font-black tracking-tight text-slate-800">
              Data Jenis Unit Pendidikan
            </h1>
            <p className="mt-1 max-w-xl text-xs text-slate-500">
              Kelola seluruh data jenis unit pendidikan yang digunakan pada sistem.
            </p>
          </div>

          {/* Header Right Action Buttons */}
          <div className="flex flex-wrap items-center gap-2.5">
            <button
              onClick={handleExportExcel}
              className="ui-button flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 shadow-sm transition-all hover:bg-slate-100"
            >
              <FaFileExcel className="text-sm text-emerald-700" />
              Export Excel
            </button>

            <button
              onClick={() => setIsImportModalOpen(true)}
              className="ui-button flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 shadow-sm transition-all hover:bg-slate-100"
            >
              <FaFileImport className="text-sm text-slate-500" />
              Import Excel
            </button>

            <button
              onClick={handleOpenFormTambah}
              className="ui-button flex items-center gap-2 rounded-xl bg-emerald-800 px-5 py-2.5 text-xs font-semibold text-white shadow-md shadow-emerald-800/20 transition-all hover:bg-emerald-900 hover:shadow-lg"
            >
              <FaPlus className="text-sm" />
              Tambah Jenis Unit
            </button>
          </div>
        </div>
      </div>

      {/* DASHBOARD CARDS */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {/* Card 1 */}
        <div className="ui-card ui-enter flex items-center gap-4 rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm" style={{ animationDelay: '60ms' }}>
          <div className="p-3.5 rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-100">
            <FaSchool className="w-6 h-6" />
          </div>
          <div>
            <p className="text-xs font-bold text-gray-400 uppercase tracking-wider">Total Jenis Unit</p>
            <h3 className="text-2xl font-black text-gray-900 mt-0.5">{stats.total ?? 8}</h3>
            <p className="text-xs font-medium text-emerald-600 mt-0.5">Terdaftar di sistem</p>
          </div>
        </div>

        {/* Card 2 */}
        <div className="ui-card ui-enter flex items-center gap-4 rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm" style={{ animationDelay: '110ms' }}>
          <div className="p-3.5 rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-100">
            <FaCheckCircle className="w-6 h-6 text-emerald-500" />
          </div>
          <div>
            <p className="text-xs font-bold text-gray-400 uppercase tracking-wider">Jenis Aktif</p>
            <h3 className="text-2xl font-black text-gray-900 mt-0.5">{stats.aktif ?? 8}</h3>
            <p className="text-xs font-medium text-emerald-600 mt-0.5">Beroperasi secara penuh</p>
          </div>
        </div>

        {/* Card 3 */}
        <div className="ui-card ui-enter flex items-center gap-4 rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm" style={{ animationDelay: '160ms' }}>
          <div className="p-3.5 rounded-xl bg-amber-50 text-amber-600 border border-amber-100">
            <FaTimesCircle className="w-6 h-6 text-amber-500" />
          </div>
          <div>
            <p className="text-xs font-bold text-gray-400 uppercase tracking-wider">Jenis Tidak Aktif</p>
            <h3 className="text-2xl font-black text-gray-900 mt-0.5">{stats.tidak_aktif ?? 0}</h3>
            <p className="text-xs font-medium text-amber-600 mt-0.5">Non-aktif / Dinonaktifkan</p>
          </div>
        </div>

        <div className="ui-card ui-enter flex items-center gap-4 rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm" style={{ animationDelay: '210ms' }}>
          <div className="rounded-xl border border-blue-100 bg-blue-50 p-3.5 text-blue-600">
            <FaSchool className="h-6 w-6" />
          </div>
          <div>
            <p className="text-xs font-bold uppercase tracking-wider text-gray-400">Cakupan Jenjang</p>
            <h3 className="mt-0.5 text-2xl font-black text-gray-900">{JENJANG_LIST.length}</h3>
            <p className="mt-0.5 text-xs font-medium text-blue-600">Jenjang pendidikan</p>
          </div>
        </div>
      </div>

      {/* SEARCH & FILTER BAR */}
      <div className="ui-enter flex flex-col justify-between gap-4 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm md:flex-row md:items-center" style={{ animationDelay: '210ms' }}>
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
            placeholder="Cari Nama Jenis Unit... Cari Kode..."
            className="w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-700 shadow-sm transition-all placeholder:text-slate-400 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-emerald-600"
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
            className="rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-semibold text-slate-700 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-emerald-600"
          >
            <option value="">Semua Status</option>
            <option value="true">Aktif</option>
            <option value="false">Tidak Aktif</option>
          </select>

          {/* Filter Jenjang */}
          <select
            value={selectedJenjangFilter}
            onChange={(e) => {
              setSelectedJenjangFilter(e.target.value)
              setPage(1)
            }}
            className="rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-semibold text-slate-700 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-emerald-600"
          >
            <option value="">Semua Jenjang</option>
            {JENJANG_LIST.map((j) => (
              <option key={j} value={j}>
                {j}
              </option>
            ))}
          </select>

          {/* Filter Trash */}
          <select
            value={denganSampahFilter}
            onChange={(e) => {
              setDenganSampahFilter(e.target.value)
              setPage(1)
            }}
            className="rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-semibold text-slate-700 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-emerald-600"
          >
            <option value="">Data Aktif</option>
            <option value="true">Termasuk Terhapus</option>
          </select>
        </div>
      </div>

      {/* TABLE DATA */}
      <JenisUnitTable
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
        <div className="flex flex-col justify-between gap-4 rounded-2xl border border-slate-200/80 bg-white p-4 text-xs font-medium text-slate-600 shadow-sm sm:flex-row sm:items-center">
          <div>
            Menampilkan <strong>{meta.from || 0}</strong> - <strong>{meta.to || 0}</strong> dari{' '}
            <strong>{meta.total || 0}</strong> data Jenis Unit
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
      <JenisUnitFormModal
        isOpen={isFormModalOpen}
        onClose={() => setIsFormModalOpen(false)}
        onSubmit={handleFormSubmit}
        initialData={selectedForEdit}
        isSubmitting={simpanMutation.isPending || ubahMutation.isPending}
      />

      <JenisUnitDetailModal
        isOpen={isDetailModalOpen}
        onClose={() => setIsDetailModalOpen(false)}
        data={selectedForDetail}
      />

      <JenisUnitImportModal
        isOpen={isImportModalOpen}
        onClose={() => setIsImportModalOpen(false)}
        onImport={(rows) => importMutation.mutate(rows)}
        isSubmitting={importMutation.isPending}
      />
    </MasterDataPage>
  )
}
