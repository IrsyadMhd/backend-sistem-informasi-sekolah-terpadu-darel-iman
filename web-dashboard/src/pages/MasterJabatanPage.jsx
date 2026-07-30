import React, { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import Swal from 'sweetalert2'
import {
  FaBriefcase,
  FaPlus,
  FaSearch,
  FaFileExcel,
  FaFileImport,
  FaRedo,
  FaCheckCircle,
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
import { MasterDataPage } from '../components/master-data'

export default function MasterJabatanPage() {
  const queryClient = useQueryClient()

  // Filter & Pagination States
  const [search, setSearch] = useState('')
  const [selectedUnitFilter, setSelectedUnitFilter] = useState('')
  const [selectedSatuanKerjaFilter, setSelectedSatuanKerjaFilter] = useState('')
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
    isError,
    refetch,
  } = useQuery({
    queryKey: [
      'jabatan-list',
      page,
      perPage,
      search,
      selectedUnitFilter,
      selectedSatuanKerjaFilter,
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
        satuan_kerja: selectedSatuanKerjaFilter,
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
        satuan_kerja: selectedSatuanKerjaFilter,
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
        'Satuan Kerja',
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
            `"${row.satuan_kerja || ''}"`,
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

  return (
    <MasterDataPage>
      <section className="ui-enter rounded-2xl border border-slate-200/80 bg-white p-6 shadow-xs">
        <div className="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
          <div>
            <h1 className="text-2xl font-black tracking-tight text-slate-800">Master Jabatan</h1>
            <p className="mt-1 text-xs text-slate-500">
              Kelola jabatan, satuan kerja, cakupan akses, struktur organisasi, dan role sistem pegawai.
            </p>
          </div>

          <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:flex-wrap">
            <button
              onClick={handleExportExcel}
              className="ui-button inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-100"
            >
              <FaFileExcel className="h-4 w-4" /> Ekspor CSV
            </button>

            <button
              onClick={() => setIsImportModalOpen(true)}
              className="ui-button inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-100"
            >
              <FaFileImport className="h-4 w-4" /> Impor Data
            </button>

            <button
              onClick={handleOpenCreate}
              className="ui-button inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-800 px-5 py-2.5 text-xs font-semibold text-white shadow-md shadow-emerald-800/20 hover:bg-emerald-900"
            >
              <FaPlus className="h-4 w-4" /> Tambah Jabatan
            </button>
          </div>
        </div>
      </section>

      <section className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4" aria-label="Ringkasan jabatan">
        <div className="ui-card flex min-h-28 items-center gap-5 rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm">
          <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl border border-emerald-100 bg-emerald-50 text-emerald-800">
            <FaBriefcase className="h-6 w-6" />
          </div>
          <div>
            <p className="text-[11px] font-black uppercase tracking-[0.12em] text-slate-500">Total Jabatan</p>
            <p className="mt-0.5 text-2xl font-black text-slate-800">{statistik.total_jabatan ?? 0}</p>
            <p className="mt-0.5 text-xs font-medium text-slate-500">Terdaftar di sistem</p>
          </div>
        </div>

        <div className="ui-card flex min-h-28 items-center gap-5 rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm">
          <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl border border-emerald-100 bg-emerald-50 text-emerald-800">
            <FaCheckCircle className="h-6 w-6" />
          </div>
          <div>
            <p className="text-[11px] font-black uppercase tracking-[0.12em] text-slate-500">Jabatan Aktif</p>
            <p className="mt-0.5 text-2xl font-black text-slate-800">{statistik.aktif ?? 0}</p>
            <p className="mt-0.5 text-xs font-medium text-slate-500">Beroperasi saat ini</p>
          </div>
        </div>

        <div className="ui-card flex min-h-28 items-center gap-5 rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm">
          <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl border border-emerald-100 bg-emerald-50 text-emerald-800">
            <FaSitemap className="h-6 w-6" />
          </div>
          <div>
            <p className="text-[11px] font-black uppercase tracking-[0.12em] text-slate-500">Bagan Struktur</p>
            <p className="mt-0.5 text-2xl font-black text-slate-800">{statistik.tampil_struktur ?? 0}</p>
            <p className="mt-0.5 text-xs font-medium text-slate-500">Tampil di organisasi</p>
          </div>
        </div>

        <div className="ui-card flex min-h-28 items-center gap-5 rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm">
          <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl border border-emerald-100 bg-emerald-50 text-emerald-800">
            <FaLockOpen className="h-6 w-6" />
          </div>
          <div>
            <p className="text-[11px] font-black uppercase tracking-[0.12em] text-slate-500">Akses Login</p>
            <p className="mt-0.5 text-2xl font-black text-slate-800">{statistik.boleh_login ?? 0}</p>
            <p className="mt-0.5 text-xs font-medium text-slate-500">Dapat memakai sistem</p>
          </div>
        </div>
      </section>

      <section className="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm" aria-label="Pencarian dan filter">
        <div className="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-5">
        <div className="relative lg:col-span-2">
          <FaSearch className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm" />
          <label htmlFor="cari-jabatan" className="sr-only">Cari jabatan</label>
          <input
            id="cari-jabatan"
            type="text"
            placeholder="Cari nama atau kode jabatan..."
            value={search}
            onChange={(e) => {
              setSearch(e.target.value)
              setPage(1)
            }}
            className="w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-10 pr-3.5 text-sm text-slate-800 shadow-sm placeholder:text-slate-400 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-emerald-600"
          />
        </div>

	          <select
	            aria-label="Filter satuan kerja"
	            value={selectedSatuanKerjaFilter}
	            onChange={(e) => {
	              setSelectedSatuanKerjaFilter(e.target.value)
	              setPage(1)
	            }}
	            className="rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-800 shadow-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-emerald-600"
	          >
	            <option value="">Semua Satuan Kerja</option>
	            {(options.satuan_kerja || []).map((item) => (
	              <option key={item.value} value={item.value}>{item.label}</option>
	            ))}
	          </select>

	          <select
            aria-label="Filter level jabatan"
            value={selectedLevelFilter}
            onChange={(e) => {
              setSelectedLevelFilter(e.target.value)
              setPage(1)
            }}
            className="rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-800 shadow-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-emerald-600"
          >
            <option value="">Semua Level (1-14)</option>
            {(options.level_jabatan || []).map((lvl) => (
              <option key={lvl.value} value={lvl.value}>
                {lvl.label}
              </option>
            ))}
          </select>

          <select
            aria-label="Filter unit sekolah"
            value={selectedUnitFilter}
            onChange={(e) => {
              setSelectedUnitFilter(e.target.value)
              setPage(1)
            }}
            className="rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-800 shadow-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-emerald-600"
          >
            <option value="">Semua Unit Sekolah</option>
            {(options.unit_sekolah || []).map((unit) => (
              <option key={unit.id} value={unit.id}>
                {unit.nama}
              </option>
            ))}
          </select>

          <select
            aria-label="Filter status jabatan"
            value={selectedStatusFilter}
            onChange={(e) => {
              setSelectedStatusFilter(e.target.value)
              setPage(1)
            }}
            className="rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-800 shadow-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-emerald-600"
          >
            <option value="">Semua Status</option>
            <option value="Aktif">Status Aktif</option>
            <option value="Nonaktif">Status Nonaktif</option>
          </select>
	        <div className="flex items-center justify-between gap-3 border-t border-slate-100 pt-4 text-xs text-slate-500 md:col-span-2 lg:col-span-5">
          <div className="flex items-center space-x-4">
            <label className="flex items-center space-x-1.5 cursor-pointer">
              <input
                type="checkbox"
                checked={denganSampahFilter === 'ya'}
                onChange={(e) => {
                  setDenganSampahFilter(e.target.checked ? 'ya' : '')
                  setPage(1)
                }}
                className="rounded border-slate-300 text-emerald-800 focus:ring-emerald-600"
              />
              <span>Tampilkan Data Terhapus (Soft Deleted)</span>
            </label>
          </div>

	          {(search || selectedUnitFilter || selectedSatuanKerjaFilter || selectedLevelFilter || selectedStatusFilter || denganSampahFilter) && (
            <button
              onClick={() => {
                setSearch('')
	                setSelectedUnitFilter('')
	                setSelectedSatuanKerjaFilter('')
                setSelectedLevelFilter('')
                setSelectedStatusFilter('')
                setDenganSampahFilter('')
                setPage(1)
              }}
              className="ui-button flex items-center space-x-1 rounded-lg px-2 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-100 hover:text-slate-800"
            >
              <FaRedo className="w-3 h-3" />
              <span>Reset Filter</span>
            </button>
          )}
        </div>
        </div>
      </section>

      {isError ? (
        <section className="rounded-2xl border border-rose-200 bg-white p-10 text-center shadow-sm">
          <p className="text-sm font-bold text-slate-800">Data jabatan gagal dimuat.</p>
          <p className="mt-1 text-xs text-slate-500">Periksa koneksi, lalu coba muat ulang.</p>
          <button onClick={() => refetch()} className="ui-button mt-4 rounded-xl bg-emerald-800 px-5 py-2.5 text-xs font-semibold text-white hover:bg-emerald-900">
            Coba Lagi
          </button>
        </section>
      ) : (
        <JabatanTable
          data={daftarJabatan}
          isLoading={isLoading || isFetching}
          onDetail={handleOpenDetail}
          onEdit={handleOpenEdit}
          onDelete={handleDelete}
          onRestore={handleRestore}
        />
      )}

      {/* Pagination Controls */}
      {meta.total > 0 && (
        <div className="flex flex-col items-center justify-between gap-4 rounded-2xl border border-slate-200/80 bg-white p-4 text-xs text-slate-600 shadow-sm sm:flex-row">
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
                aria-label="Jumlah data per halaman"
                className="rounded-lg border border-slate-200 bg-white px-2 py-1 font-semibold focus:outline-none focus:ring-2 focus:ring-emerald-600"
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
                className="ui-button rounded-lg border border-slate-200 p-2 hover:bg-slate-100 disabled:opacity-40"
                title="Halaman sebelumnya"
                aria-label="Halaman sebelumnya"
              >
                <FaChevronLeft className="w-3.5 h-3.5" />
              </button>
              <span className="px-3 py-1 font-bold">
                {meta.current_page || 1} / {meta.last_page || 1}
              </span>
              <button
                disabled={page >= (meta.last_page || 1)}
                onClick={() => setPage((prev) => prev + 1)}
                className="ui-button rounded-lg border border-slate-200 p-2 hover:bg-slate-100 disabled:opacity-40"
                title="Halaman berikutnya"
                aria-label="Halaman berikutnya"
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
    </MasterDataPage>
  )
}
