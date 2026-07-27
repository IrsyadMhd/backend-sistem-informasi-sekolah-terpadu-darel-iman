import React, { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import Swal from 'sweetalert2'
import {
  FaBriefcase,
  FaPlus,
  FaSearch,
  FaFileExcel,
  FaFilePdf,
  FaFileImport,
  FaRedo,
  FaCheckCircle,
  FaTimesCircle,
  FaSitemap,
  FaLockOpen,
  FaChevronLeft,
  FaChevronRight,
} from 'react-icons/fa'
import { jabatanService } from '../services/jabatanService'
import JabatanTable from '../components/jabatan/JabatanTable'
import JabatanFormModal from '../components/jabatan/JabatanFormModal'
import JabatanDetailModal from '../components/jabatan/JabatanDetailModal'
import JabatanImportModal from '../components/jabatan/JabatanImportModal'

export default function MasterJabatanPage() {
  const queryClient = useQueryClient()

  // Filter & Pagination States
  const [search, setSearch] = useState('')
  const [selectedUnitFilter, setSelectedUnitFilter] = useState('')
  const [selectedLevelFilter, setSelectedLevelFilter] = useState('')
  const [selectedStatusFilter, setSelectedStatusFilter] = useState('')
  const [denganSampahFilter, setDenganSampahFilter] = useState('')
  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState(15)

  // Modals States
  const [isFormModalOpen, setIsFormModalOpen] = useState(false)
  const [selectedJabatanForEdit, setSelectedJabatanForEdit] = useState(null)

  const [isDetailModalOpen, setIsDetailModalOpen] = useState(false)
  const [selectedJabatanForDetail, setSelectedJabatanForDetail] = useState(null)

  const [isImportModalOpen, setIsImportModalOpen] = useState(false)

  // Query Options Dropdown
  const { data: options = {} } = useQuery({
    queryKey: ['jabatan-options'],
    queryFn: () => jabatanService.getOptions(),
  })

  // Query Daftar Jabatan
  const {
    data: jabatanData = {},
    isLoading,
    isFetching,
  } = useQuery({
    queryKey: [
      'jabatan-list',
      page,
      perPage,
      search,
      selectedUnitFilter,
      selectedLevelFilter,
      selectedStatusFilter,
      denganSampahFilter,
    ],
    queryFn: () =>
      jabatanService.getDaftar({
        page,
        per_page: perPage,
        search,
        unit_sekolah_id: selectedUnitFilter,
        level_jabatan: selectedLevelFilter,
        status: selectedStatusFilter,
        dengan_sampah: denganSampahFilter,
        order_by: 'urutan',
        order_dir: 'asc',
      }),
  })

  const daftarJabatan = jabatanData?.data || []
  const meta = jabatanData?.meta || {}
  const statistik = jabatanData?.statistik || {}

  // Mutations
  const simpanMutation = useMutation({
    mutationFn: (payload) => jabatanService.tambah(payload),
    onSuccess: (res) => {
      queryClient.invalidateQueries(['jabatan-list'])
      queryClient.invalidateQueries(['jabatan-options'])
      setIsFormModalOpen(false)
      Swal.fire({
        icon: 'success',
        title: 'Berhasil!',
        text: res?.message || 'Data jabatan baru berhasil ditambahkan.',
        timer: 2000,
        showConfirmButton: false,
      })
    },
    onError: (err) => {
      const msg = err.response?.data?.message || 'Gagal menyimpan data jabatan.'
      Swal.fire('Error', msg, 'error')
    },
  })

  const ubahMutation = useMutation({
    mutationFn: ({ id, payload }) => jabatanService.ubah({ id, payload }),
    onSuccess: (res) => {
      queryClient.invalidateQueries(['jabatan-list'])
      queryClient.invalidateQueries(['jabatan-options'])
      setIsFormModalOpen(false)
      setSelectedJabatanForEdit(null)
      Swal.fire({
        icon: 'success',
        title: 'Berhasil!',
        text: res?.message || 'Perubahan data jabatan berhasil disimpan.',
        timer: 2000,
        showConfirmButton: false,
      })
    },
    onError: (err) => {
      const msg = err.response?.data?.message || 'Gagal memperbarui data jabatan.'
      Swal.fire('Error', msg, 'error')
    },
  })

  const hapusMutation = useMutation({
    mutationFn: (id) => jabatanService.hapus(id),
    onSuccess: (res) => {
      queryClient.invalidateQueries(['jabatan-list'])
      Swal.fire('Terhapus!', res?.message || 'Data jabatan berhasil dihapus.', 'success')
    },
    onError: (err) => {
      Swal.fire('Gagal!', err.response?.data?.message || 'Terjadi kesalahan saat menghapus.', 'error')
    },
  })

  const pulihkanMutation = useMutation({
    mutationFn: (id) => jabatanService.pulihkan(id),
    onSuccess: (res) => {
      queryClient.invalidateQueries(['jabatan-list'])
      Swal.fire('Dipulihkan!', res?.message || 'Data jabatan berhasil dipulihkan.', 'success')
    },
    onError: (err) => {
      Swal.fire('Gagal!', err.response?.data?.message || 'Terjadi kesalahan saat memulihkan.', 'error')
    },
  })

  const importMutation = useMutation({
    mutationFn: (rows) => jabatanService.prosesImport(rows),
    onSuccess: (res) => {
      queryClient.invalidateQueries(['jabatan-list'])
      queryClient.invalidateQueries(['jabatan-options'])
      setIsImportModalOpen(false)
      Swal.fire({
        icon: 'success',
        title: 'Impor Selesai',
        text: res?.message || `Berhasil diimpor.`,
      })
    },
    onError: (err) => {
      Swal.fire('Gagal Impor!', err.response?.data?.message || 'Format data impor bermasalah.', 'error')
    },
  })

  // Handlers
  const handleOpenCreate = () => {
    setSelectedJabatanForEdit(null)
    setIsFormModalOpen(true)
  }

  const handleOpenEdit = (item) => {
    setSelectedJabatanForEdit(item)
    setIsFormModalOpen(true)
  }

  const handleOpenDetail = (item) => {
    setSelectedJabatanForDetail(item)
    setIsDetailModalOpen(true)
  }

  const handleDelete = (item) => {
    Swal.fire({
      title: 'Hapus Data Jabatan?',
      html: `Apakah Anda yakin ingin menghapus jabatan <strong>${item.nama_jabatan || item.name}</strong> (${item.kode_jabatan || item.code})?`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#ef4444',
      cancelButtonColor: '#64748b',
      confirmButtonText: 'Ya, Hapus (Soft Delete)',
      cancelButtonText: 'Batal',
    }).then((result) => {
      if (result.isConfirmed) {
        hapusMutation.mutate(item.id)
      }
    })
  }

  const handleRestore = (item) => {
    Swal.fire({
      title: 'Pulihkan Data Jabatan?',
      html: `Apakah Anda yakin ingin memulihkan jabatan <strong>${item.nama_jabatan || item.name}</strong>?`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#10b981',
      cancelButtonColor: '#64748b',
      confirmButtonText: 'Ya, Pulihkan',
      cancelButtonText: 'Batal',
    }).then((result) => {
      if (result.isConfirmed) {
        pulihkanMutation.mutate(item.id)
      }
    })
  }

  const handleFormSubmit = (data) => {
    if (selectedJabatanForEdit) {
      ubahMutation.mutate({ id: selectedJabatanForEdit.id, payload: data })
    } else {
      simpanMutation.mutate(data)
    }
  }

  // Export Excel CSV
  const handleExportExcel = async () => {
    try {
      const dataEkspor = await jabatanService.ekspor({
        search,
        unit_sekolah_id: selectedUnitFilter,
        level_jabatan: selectedLevelFilter,
        status: selectedStatusFilter,
      })

      if (!dataEkspor || dataEkspor.length === 0) {
        Swal.fire('Info', 'Tidak ada data untuk diekspor.', 'info')
        return
      }

      const headers = [
        'Kode Jabatan',
        'Nama Jabatan',
        'Level',
        'Level Label',
        'Unit Sekolah',
        'Atasan Langsung',
        'Role Sistem',
        'Urutan',
        'Status',
        'Tampil Struktur',
        'Boleh Login',
        'Jumlah Pegawai',
        'Deskripsi',
      ]

      const csvRows = [
        headers.join(','),
        ...dataEkspor.map((row) =>
          [
            `"${row.kode_jabatan || ''}"`,
            `"${row.nama_jabatan || ''}"`,
            row.level_jabatan || '',
            `"${row.level_label || ''}"`,
            `"${row.unit_sekolah || ''}"`,
            `"${row.atasan_langsung || ''}"`,
            `"${row.role_sistem || ''}"`,
            row.urutan || 0,
            `"${row.status || ''}"`,
            `"${row.tampil_struktur || ''}"`,
            `"${row.boleh_login || ''}"`,
            row.jumlah_pegawai || 0,
            `"${(row.deskripsi || '').replace(/"/g, '""')}"`,
          ].join(',')
        ),
      ]

      const blob = new Blob([csvRows.join('\n')], { type: 'text/csv;charset=utf-8;' })
      const url = URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = `Master_Jabatan_Sekolah_${new Date().toISOString().slice(0, 10)}.csv`
      document.body.appendChild(a)
      a.click()
      a.remove()
      URL.revokeObjectURL(url)

      Swal.fire({
        icon: 'success',
        title: 'Ekspor Berhasil',
        text: 'File CSV Master Jabatan berhasil diunduh.',
        timer: 2000,
        showConfirmButton: false,
      })
    } catch (err) {
      Swal.fire('Error', 'Gagal mengekspor data: ' + err.message, 'error')
    }
  }

  // Export PDF (Cetak Halaman)
  const handleExportPDF = () => {
    window.print()
  }

  return (
    <div className="space-y-6 pb-12">
      {/* Header Banner (Persis Gambar UI/UX) */}
      <div className="bg-[#054e3b] rounded-[24px] p-7 text-white shadow-lg border border-emerald-800/40">
        <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
          <div>
            <span className="bg-[#086a52] text-emerald-200 text-[11px] font-extrabold px-3.5 py-1 rounded-full uppercase tracking-wider">
              MANAGEMENT JABATAN SEKOLAH
            </span>
            <h1 className="text-3xl md:text-4xl font-black mt-2 tracking-tight">
              Data Master Jabatan
            </h1>
            <p className="text-emerald-100/90 text-sm mt-1">
              Kelola 14 level hirarki jabatan, visibilitas bagan struktur organisasi, dan hak akses login pegawai.
            </p>
          </div>

          {/* Action Buttons Header */}
          <div className="flex flex-wrap items-center gap-2.5">
            <button
              onClick={handleExportExcel}
              className="bg-[#086a52]/80 hover:bg-[#086a52] text-white font-bold px-4 py-2.5 rounded-xl border border-emerald-500/30 transition flex items-center gap-2 text-xs shadow-sm"
            >
              <FaFileExcel className="text-sm" /> Export Excel
            </button>

            <button
              onClick={() => setIsImportModalOpen(true)}
              className="bg-[#086a52]/80 hover:bg-[#086a52] text-white font-bold px-4 py-2.5 rounded-xl border border-emerald-500/30 transition flex items-center gap-2 text-xs shadow-sm"
            >
              <FaFileImport className="text-sm" /> Import Excel
            </button>

            {/* <button
              onClick={handleExportPDF}
              className="bg-[#086a52]/80 hover:bg-[#086a52] text-white font-bold px-4 py-2.5 rounded-xl border border-emerald-500/30 transition flex items-center gap-2 text-xs shadow-sm"
            >
              <FaFilePdf className="text-sm" /> Cetak PDF
            </button> */}

            <button
              onClick={handleOpenCreate}
              className="bg-[#00b981] hover:bg-[#05a373] text-white font-black px-5 py-2.5 rounded-xl transition flex items-center gap-2 text-xs shadow-lg"
            >
              <FaPlus className="text-sm" /> Tambah Jabatan
            </button>
          </div>
        </div>
      </div>

      {/* Ringkasan 4 Kartu Statistik (Persis Gambar UI/UX) */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="bg-white p-5 rounded-[20px] border border-slate-200/90 shadow-sm flex items-center gap-4">
          <div className="w-12 h-12 rounded-2xl bg-[#dcfce7] text-[#15803d] flex items-center justify-center text-xl font-bold shrink-0">
            <FaBriefcase />
          </div>
          <div>
            <p className="text-xs text-slate-500 font-bold mb-1">Total Master Jabatan</p>
            <h3 className="text-3xl font-black text-slate-900 leading-none mb-1">
              {statistik.total_jabatan ?? 0}
            </h3>
            <span className="text-xs text-[#16a34a] font-bold">Terdaftar di sistem</span>
          </div>
        </div>

        <div className="bg-white p-5 rounded-[20px] border border-slate-200/90 shadow-sm flex items-center gap-4">
          <div className="w-12 h-12 rounded-2xl bg-[#dbeafe] text-[#1d4ed8] flex items-center justify-center text-xl font-bold shrink-0">
            <FaCheckCircle />
          </div>
          <div>
            <p className="text-xs text-slate-500 font-bold mb-1">Status Aktif</p>
            <h3 className="text-3xl font-black text-slate-900 leading-none mb-1">
              {statistik.aktif ?? 0}
            </h3>
            <span className="text-xs text-[#2563eb] font-bold">Beroperasi secara penuh</span>
          </div>
        </div>

        <div className="bg-white p-5 rounded-[20px] border border-slate-200/90 shadow-sm flex items-center gap-4">
          <div className="w-12 h-12 rounded-2xl bg-[#f3e8ff] text-[#7e22ce] flex items-center justify-center text-xl font-bold shrink-0">
            <FaSitemap />
          </div>
          <div>
            <p className="text-xs text-slate-500 font-bold mb-1">Bagan Struktur</p>
            <h3 className="text-3xl font-black text-slate-900 leading-none mb-1">
              {statistik.tampil_struktur ?? 0}
            </h3>
            <span className="text-xs text-[#9333ea] font-bold">Tampil di organisasi</span>
          </div>
        </div>

        <div className="bg-white p-5 rounded-[20px] border border-slate-200/90 shadow-sm flex items-center gap-4">
          <div className="w-12 h-12 rounded-2xl bg-[#fef9c3] text-[#ca8a04] flex items-center justify-center text-xl font-bold shrink-0">
            <FaLockOpen />
          </div>
          <div>
            <p className="text-xs text-slate-500 font-bold mb-1">Hak Akses Login</p>
            <h3 className="text-3xl font-black text-slate-900 leading-none mb-1">
              {statistik.boleh_login ?? 0}
            </h3>
            <span className="text-xs text-[#d97706] font-bold">Pengguna Sistem</span>
          </div>
        </div>
      </div>

      {/* Filter Bar (Persis Gambar UI/UX Pill Input & Pill Selects) */}
      <div className="flex flex-col sm:flex-row items-center justify-between bg-white p-3 rounded-[24px] border border-slate-200/90 shadow-sm gap-3">
        {/* Search Input Pill */}
        <div className="relative w-full sm:w-1/2 md:w-[40%]">
          <FaSearch className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm" />
          <input
            type="text"
            placeholder="Cari nama unit, kode, atau jabatan..."
            value={search}
            onChange={(e) => {
              setSearch(e.target.value)
              setPage(1)
            }}
            className="w-full rounded-full border border-slate-200/90 bg-[#f8fafc] pl-10 pr-4 py-2.5 text-xs font-semibold text-slate-700 placeholder:text-slate-400 focus:border-emerald-500 focus:bg-white focus:outline-none transition-all"
          />
        </div>

        {/* Filter Dropdowns Pill */}
        <div className="flex items-center gap-2 w-full sm:w-auto overflow-x-auto pb-1 sm:pb-0 scrollbar-hide">
          <div className="flex items-center gap-1.5 text-slate-500 shrink-0 mr-1">
            <span className="text-xs font-extrabold text-slate-600">Filter:</span>
          </div>

          <select
            value={selectedLevelFilter}
            onChange={(e) => {
              setSelectedLevelFilter(e.target.value)
              setPage(1)
            }}
            className="rounded-full border border-slate-200/90 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 focus:border-emerald-500 focus:outline-none shrink-0"
          >
            <option value="">Semua Level (1-14)</option>
            {(options.level_jabatan || []).map((lvl) => (
              <option key={lvl.value} value={lvl.value}>
                {lvl.label}
              </option>
            ))}
          </select>

          <select
            value={selectedUnitFilter}
            onChange={(e) => {
              setSelectedUnitFilter(e.target.value)
              setPage(1)
            }}
            className="rounded-full border border-slate-200/90 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 focus:border-emerald-500 focus:outline-none shrink-0"
          >
            <option value="">Semua Unit Sekolah</option>
            {(options.unit_sekolah || []).map((unit) => (
              <option key={unit.id} value={unit.id}>
                {unit.nama}
              </option>
            ))}
          </select>

          <select
            value={selectedStatusFilter}
            onChange={(e) => {
              setSelectedStatusFilter(e.target.value)
              setPage(1)
            }}
            className="rounded-full border border-slate-200/90 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 focus:border-emerald-500 focus:outline-none shrink-0"
          >
            <option value="">Semua Status</option>
            <option value="Aktif">Status Aktif</option>
            <option value="Nonaktif">Status Nonaktif</option>
          </select>
        </div>

        {/* Filter Trash Toggle */}
        <div className="flex items-center justify-between pt-2 border-t border-slate-100 text-xs text-slate-500 w-full col-span-full">
          <div className="flex items-center space-x-4">
            <label className="flex items-center space-x-1.5 cursor-pointer">
              <input
                type="checkbox"
                checked={denganSampahFilter === 'ya'}
                onChange={(e) => {
                  setDenganSampahFilter(e.target.checked ? 'ya' : '')
                  setPage(1)
                }}
                className="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
              />
              <span>Tampilkan Data Terhapus (Soft Deleted)</span>
            </label>
          </div>

          {(search || selectedUnitFilter || selectedLevelFilter || selectedStatusFilter || denganSampahFilter) && (
            <button
              onClick={() => {
                setSearch('')
                setSelectedUnitFilter('')
                setSelectedLevelFilter('')
                setSelectedStatusFilter('')
                setDenganSampahFilter('')
                setPage(1)
              }}
              className="text-xs font-semibold text-rose-600 hover:text-rose-700 flex items-center space-x-1"
            >
              <FaRedo className="w-3 h-3" />
              <span>Reset Filter</span>
            </button>
          )}
        </div>
      </div>

      {/* Main Table */}
      <JabatanTable
        data={daftarJabatan}
        isLoading={isLoading || isFetching}
        onDetail={handleOpenDetail}
        onEdit={handleOpenEdit}
        onDelete={handleDelete}
        onRestore={handleRestore}
      />

      {/* Pagination Controls */}
      {meta.total > 0 && (
        <div className="flex flex-col sm:flex-row items-center justify-between gap-4 bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 text-xs text-slate-600 dark:text-slate-400">
          <div>
            Menampilkan <strong>{meta.from || 0}</strong> - <strong>{meta.to || 0}</strong> dari total{' '}
            <strong>{meta.total || 0}</strong> data jabatan
          </div>

          <div className="flex items-center space-x-4">
            <div className="flex items-center space-x-2">
              <span>Tampilkan:</span>
              <select
                value={perPage}
                onChange={(e) => {
                  setPerPage(Number(e.target.value))
                  setPage(1)
                }}
                className="px-2 py-1 rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800 font-semibold"
              >
                <option value={10}>10</option>
                <option value={15}>15</option>
                <option value={25}>25</option>
                <option value={50}>50</option>
              </select>
            </div>

            <div className="flex items-center space-x-1">
              <button
                disabled={page <= 1}
                onClick={() => setPage((prev) => Math.max(prev - 1, 1))}
                className="p-2 rounded-lg border border-slate-200 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800 disabled:opacity-40 transition-colors"
              >
                <FaChevronLeft className="w-3.5 h-3.5" />
              </button>
              <span className="px-3 py-1 font-bold">
                {meta.current_page || 1} / {meta.last_page || 1}
              </span>
              <button
                disabled={page >= (meta.last_page || 1)}
                onClick={() => setPage((prev) => prev + 1)}
                className="p-2 rounded-lg border border-slate-200 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800 disabled:opacity-40 transition-colors"
              >
                <FaChevronRight className="w-3.5 h-3.5" />
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Modals */}
      <JabatanFormModal
        isOpen={isFormModalOpen}
        onClose={() => {
          setIsFormModalOpen(false)
          setSelectedJabatanForEdit(null)
        }}
        onSubmit={handleFormSubmit}
        initialData={selectedJabatanForEdit}
        options={options}
        isSubmitting={simpanMutation.isPending || ubahMutation.isPending}
      />

      <JabatanDetailModal
        isOpen={isDetailModalOpen}
        onClose={() => {
          setIsDetailModalOpen(false)
          setSelectedJabatanForDetail(null)
        }}
        jabatan={selectedJabatanForDetail}
      />

      <JabatanImportModal
        isOpen={isImportModalOpen}
        onClose={() => setIsImportModalOpen(false)}
        onImport={(rows) => importMutation.mutate(rows)}
        isSubmitting={importMutation.isPending}
      />
    </div>
  )
}
