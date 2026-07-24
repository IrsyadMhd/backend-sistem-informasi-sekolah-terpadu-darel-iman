import { useMemo, useState } from 'react'
import Swal from 'sweetalert2'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  FaArrowLeft,
  FaBuilding,
  FaCheckCircle,
  FaDownload,
  FaEdit,
  FaExclamationTriangle,
  FaEye,
  FaFilePdf,
  FaPlus,
  FaSearch,
  FaTrash,
  FaUpload,
  FaUserGraduate,
  FaChalkboardTeacher,
  FaSchool,
  FaCalendarAlt,
  FaPhoneAlt,
  FaEnvelope,
  FaMapMarkerAlt,
} from 'react-icons/fa'
import { educationUnitService } from '../services/educationUnitService'

const UNIT_TYPES = ['TKIT', 'TAUD', 'SDIT', 'MIT', 'SMPIT', 'SMAIT', 'PONPES', 'Mahad']

const UNIT_COLORS = {
  TKIT: { bg: 'bg-emerald-800', text: 'text-white', border: 'border-emerald-700' },
  TAUD: { bg: 'bg-emerald-700', text: 'text-white', border: 'border-emerald-600' },
  SDIT: { bg: 'bg-blue-600', text: 'text-white', border: 'border-blue-500' },
  MIT: { bg: 'bg-amber-500', text: 'text-white', border: 'border-amber-400' },
  SMPIT: { bg: 'bg-cyan-600', text: 'text-white', border: 'border-cyan-500' },
  SMAIT: { bg: 'bg-purple-600', text: 'text-white', border: 'border-purple-500' },
  PONPES: { bg: 'bg-emerald-900', text: 'text-white', border: 'border-emerald-800' },
  Mahad: { bg: 'bg-amber-800', text: 'text-white', border: 'border-amber-700' },
}

function getUnitBadgeStyle(type) {
  return (
    UNIT_COLORS[type] || {
      bg: 'bg-slate-700',
      text: 'text-white',
      border: 'border-slate-600',
    }
  )
}

function initialFormState() {
  return {
    id: null,
    code: '',
    name: '',
    unit_type: '',
    npsn: '',
    email: '',
    phone: '',
    address: '',
    city: 'Padang',
    province: 'Sumatera Barat',
    postal_code: '',
    principal_name: '',
    principal_nip: '',
    established_year: new Date().getFullYear(),
    accreditation: 'A',
    sk_pendirian: '',
    tgl_sk: '',
    logo_url: '',
    is_active: true,
    description: '',
  }
}

function parseFromApi(item) {
  const meta = item?.metadata || {}
  return {
    id: item?.id || null,
    code: item?.code || '',
    name: item?.name || '',
    unit_type: item?.level || '',
    npsn: meta.npsn || '',
    email: meta.email || '',
    phone: meta.phone || '',
    address: meta.address || '',
    city: meta.city || 'Padang',
    province: meta.province || 'Sumatera Barat',
    postal_code: meta.postal_code || '',
    principal_name: meta.principal_name || meta.kepala_unit || '',
    principal_nip: meta.principal_nip || '',
    established_year: meta.established_year || 2011,
    accreditation: meta.accreditation || 'A',
    sk_pendirian: meta.sk_pendirian || '',
    tgl_sk: meta.tgl_sk || '',
    logo_url: meta.logo_url || '',
    is_active: item?.is_active ?? true,
    description: item?.description || '',
    total_siswa: meta.total_siswa || 1250,
    total_guru: meta.total_guru || 95,
    total_kelas: meta.total_kelas || 42,
    total_rombel: meta.total_rombel || 48,
  }
}

function makePayload(form) {
  return {
    code: form.code,
    name: form.name,
    level: form.unit_type,
    description: form.description,
    is_active: form.is_active,
    metadata: {
      npsn: form.npsn,
      email: form.email,
      phone: form.phone,
      address: form.address,
      city: form.city,
      province: form.province,
      postal_code: form.postal_code,
      principal_name: form.principal_name,
      principal_nip: form.principal_nip,
      established_year: form.established_year,
      accreditation: form.accreditation,
      sk_pendirian: form.sk_pendirian,
      tgl_sk: form.tgl_sk,
      logo_url: form.logo_url,
    },
  }
}

export default function EducationUnitsPage() {
  const queryClient = useQueryClient()

  // Filter States
  const [search, setSearch] = useState('')
  const [selectedTypeFilter, setSelectedTypeFilter] = useState('')
  const [selectedCityFilter, setSelectedCityFilter] = useState('')
  const [selectedProvinceFilter, setSelectedProvinceFilter] = useState('')
  const [selectedStatusFilter, setSelectedStatusFilter] = useState('')

  // Pagination State
  const [page, setPage] = useState(1)

  // Modal Controls
  const [isFormModalOpen, setIsFormModalOpen] = useState(false)
  const [isEditMode, setIsEditMode] = useState(false)
  const [currentStep, setCurrentStep] = useState(1)
  const [formData, setFormData] = useState(initialFormState())

  // Detail Modal State
  const [detailUnit, setDetailUnit] = useState(null)
  const [activeDetailTab, setActiveDetailTab] = useState('Informasi')

  // Delete Modal State
  const [deleteTarget, setDeleteTarget] = useState(null)
  const [hasConfirmedDeleteCheck, setHasConfirmedDeleteCheck] = useState(false)

  // Query Fetching
  const { data, isLoading } = useQuery({
    queryKey: [
      'education-units',
      page,
      search,
      selectedTypeFilter,
      selectedCityFilter,
      selectedProvinceFilter,
      selectedStatusFilter,
    ],
    queryFn: () =>
      educationUnitService.getDaftar({
        page,
        per_page: 15,
        search: search || undefined,
        level: selectedTypeFilter || undefined,
        city: selectedCityFilter || undefined,
        province: selectedProvinceFilter || undefined,
        status: selectedStatusFilter || undefined,
      }),
  })

  const rawList = data?.data || []
  const paginationInfo = {
    total: data?.total || rawList.length,
    from: data?.from || (rawList.length > 0 ? 1 : 0),
    to: data?.to || rawList.length,
    last_page: data?.last_page || 1,
  }

  const items = useMemo(() => rawList.map(parseFromApi), [rawList])

  // Extract unique cities & provinces for filters
  const cityOptions = useMemo(() => {
    const set = new Set(items.map((i) => i.city).filter(Boolean))
    return Array.from(set)
  }, [items])

  const provinceOptions = useMemo(() => {
    const set = new Set(items.map((i) => i.province).filter(Boolean))
    return Array.from(set)
  }, [items])

  // Mutations
  const createMutation = useMutation({
    mutationFn: (payload) => educationUnitService.tambah(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['education-units'] })
      Swal.fire({
        title: 'Berhasil!',
        text: 'Unit pendidikan berhasil ditambahkan.',
        icon: 'success',
        confirmColor: '#065F46',
      })
      closeFormModal()
    },
    onError: (err) => {
      Swal.fire('Gagal!', err?.response?.data?.message || 'Terjadi kesalahan saat menyimpan.', 'error')
    },
  })

  const updateMutation = useMutation({
    mutationFn: ({ id, payload }) => educationUnitService.ubah({ id, payload }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['education-units'] })
      Swal.fire({
        title: 'Berhasil!',
        text: 'Unit pendidikan berhasil diperbarui.',
        icon: 'success',
        confirmColor: '#065F46',
      })
      closeFormModal()
    },
    onError: (err) => {
      Swal.fire('Gagal!', err?.response?.data?.message || 'Terjadi kesalahan saat memperbarui.', 'error')
    },
  })

  const deleteMutation = useMutation({
    mutationFn: (id) => educationUnitService.hapus(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['education-units'] })
      Swal.fire({
        title: 'Berhasil!',
        text: 'Unit pendidikan berhasil dihapus secara permanen.',
        icon: 'success',
        confirmColor: '#065F46',
      })
      setDeleteTarget(null)
      setHasConfirmedDeleteCheck(false)
    },
    onError: (err) => {
      Swal.fire('Gagal!', err?.response?.data?.message || 'Terjadi kesalahan saat menghapus.', 'error')
    },
  })

  // Modal Handlers
  const openAddModal = () => {
    setIsEditMode(false)
    setFormData(initialFormState())
    setCurrentStep(1)
    setIsFormModalOpen(true)
  }

  const openEditModal = (unit) => {
    setIsEditMode(true)
    setFormData(unit)
    setCurrentStep(1)
    setIsFormModalOpen(true)
  }

  const closeFormModal = () => {
    setIsFormModalOpen(false)
    setIsEditMode(false)
    setCurrentStep(1)
    setFormData(initialFormState())
  }

  const handleFormSubmit = (e) => {
    e?.preventDefault()
    if (!formData.name.trim()) {
      Swal.fire('Peringatan', 'Nama Unit Pendidikan wajib diisi!', 'warning')
      return
    }
    if (!formData.unit_type) {
      Swal.fire('Peringatan', 'Jenis Unit wajib dipilih!', 'warning')
      return
    }

    const payload = makePayload(formData)
    if (isEditMode && formData.id) {
      updateMutation.mutate({ id: formData.id, payload })
    } else {
      createMutation.mutate(payload)
    }
  }

  const toggleUnitStatus = (unit) => {
    const updatedForm = { ...unit, is_active: !unit.is_active }
    const payload = makePayload(updatedForm)
    updateMutation.mutate({ id: unit.id, payload })
  }

  // Export Excel Dummy Handler
  const handleExportExcel = () => {
    Swal.fire({
      title: 'Export Data',
      text: 'Mengeksport data unit pendidikan ke format Excel...',
      icon: 'info',
      timer: 1500,
      showConfirmButton: false,
    })
  }

  return (
    <div className="space-y-6 pb-12">
      {/* 1. Header Section */}
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-800">Unit Pendidikan</h1>
          <p className="text-sm text-slate-500">Kelola seluruh unit pendidikan di lingkungan Dar el-Iman</p>
        </div>
        <div className="flex items-center gap-3">
          <button
            onClick={handleExportExcel}
            className="flex items-center gap-2 rounded-lg border border-emerald-600 bg-white px-4 py-2 text-sm font-semibold text-emerald-700 shadow-sm hover:bg-emerald-50 transition-colors"
          >
            <FaDownload className="text-emerald-600" />
            Export Excel
          </button>
          <button
            onClick={openAddModal}
            className="flex items-center gap-2 rounded-lg bg-emerald-800 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-emerald-900 transition-colors"
          >
            <FaPlus />
            + Tambah Unit
          </button>
        </div>
      </div>

      {/* 2. Filter Bar */}
      <div className="grid grid-cols-1 gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 md:grid-cols-5">
        {/* Search */}
        <div className="relative md:col-span-1">
          <FaSearch className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm" />
          <input
            type="text"
            placeholder="Cari unit pendidikan..."
            value={search}
            onChange={(e) => {
              setSearch(e.target.value)
              setPage(1)
            }}
            className="w-full rounded-lg border border-slate-300 py-2 pl-9 pr-3 text-sm focus:border-emerald-600 focus:outline-none"
          />
        </div>

        {/* Filter Jenis Unit */}
        <div>
          <select
            value={selectedTypeFilter}
            onChange={(e) => {
              setSelectedTypeFilter(e.target.value)
              setPage(1)
            }}
            className="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm text-slate-700 focus:border-emerald-600 focus:outline-none"
          >
            <option value="">Semua Jenis Unit</option>
            {UNIT_TYPES.map((t) => (
              <option key={t} value={t}>
                {t}
              </option>
            ))}
          </select>
        </div>

        {/* Filter Kota */}
        <div>
          <select
            value={selectedCityFilter}
            onChange={(e) => {
              setSelectedCityFilter(e.target.value)
              setPage(1)
            }}
            className="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm text-slate-700 focus:border-emerald-600 focus:outline-none"
          >
            <option value="">Semua Kota</option>
            {cityOptions.map((c) => (
              <option key={c} value={c}>
                {c}
              </option>
            ))}
            <option value="Padang">Padang</option>
            <option value="50 Kota">50 Kota</option>
            <option value="Padang Panjang">Padang Panjang</option>
          </select>
        </div>

        {/* Filter Provinsi */}
        <div>
          <select
            value={selectedProvinceFilter}
            onChange={(e) => {
              setSelectedProvinceFilter(e.target.value)
              setPage(1)
            }}
            className="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm text-slate-700 focus:border-emerald-600 focus:outline-none"
          >
            <option value="">Semua Provinsi</option>
            {provinceOptions.map((p) => (
              <option key={p} value={p}>
                {p}
              </option>
            ))}
            <option value="Sumatera Barat">Sumatera Barat</option>
          </select>
        </div>

        {/* Filter Status */}
        <div>
          <select
            value={selectedStatusFilter}
            onChange={(e) => {
              setSelectedStatusFilter(e.target.value)
              setPage(1)
            }}
            className="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm text-slate-700 focus:border-emerald-600 focus:outline-none"
          >
            <option value="">Status (Semua)</option>
            <option value="aktif">Status Aktif</option>
            <option value="nonaktif">Status Nonaktif</option>
          </select>
        </div>
      </div>

      {/* 3. Table View */}
      <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm text-slate-600">
            <thead className="bg-slate-50 text-xs font-semibold uppercase text-slate-500 border-b border-slate-200">
              <tr>
                <th className="py-3.5 px-4 w-12 text-center">No</th>
                <th className="py-3.5 px-4 w-16 text-center">Logo</th>
                <th className="py-3.5 px-4 font-bold">Nama Unit Pendidikan</th>
                <th className="py-3.5 px-4 font-bold">Jenis Unit</th>
                <th className="py-3.5 px-4 font-bold">Kota / Provinsi</th>
                <th className="py-3.5 px-4 font-bold">Kepala Sekolah / Pimpinan</th>
                <th className="py-3.5 px-4 text-center font-bold">Status</th>
                <th className="py-3.5 px-4 text-center font-bold">Aksi</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {isLoading ? (
                <tr>
                  <td colSpan={8} className="py-12 text-center text-slate-400">
                    Memuat data unit pendidikan...
                  </td>
                </tr>
              ) : items.length === 0 ? (
                <tr>
                  <td colSpan={8} className="py-12 text-center text-slate-400">
                    Tidak ada data unit pendidikan ditemukan.
                  </td>
                </tr>
              ) : (
                items.map((row, idx) => {
                  const style = getUnitBadgeStyle(row.unit_type)
                  return (
                    <tr key={row.id || idx} className="hover:bg-slate-50/80 transition-colors">
                      <td className="py-4 px-4 text-center text-slate-400 font-medium">{idx + 1}</td>
                      <td className="py-4 px-4 text-center">
                        <div
                          className={`mx-auto flex h-9 w-9 items-center justify-center rounded-full text-xs font-black shadow-sm ${style.bg} ${style.text}`}
                        >
                          {row.unit_type || 'UP'}
                        </div>
                      </td>
                      <td className="py-4 px-4 font-bold text-slate-900">{row.name}</td>
                      <td className="py-4 px-4 font-medium text-slate-600">{row.unit_type || '-'}</td>
                      <td className="py-4 px-4 text-slate-600">
                        {row.city || row.province ? `${row.city}, ${row.province}` : '-'}
                      </td>
                      <td className="py-4 px-4 font-medium text-slate-700">{row.principal_name || '-'}</td>
                      <td className="py-4 px-4 text-center">
                        {row.is_active ? (
                          <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">
                            <span className="h-1.5 w-1.5 rounded-full bg-emerald-600"></span>
                            Aktif
                          </span>
                        ) : (
                          <span className="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                            <span className="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                            Nonaktif
                          </span>
                        )}
                      </td>
                      <td className="py-4 px-4 text-center">
                        <div className="flex items-center justify-center gap-1.5">
                          <button
                            onClick={() => setDetailUnit(row)}
                            title="Detail"
                            className="flex h-8 w-8 items-center justify-center rounded-lg border border-blue-200 bg-blue-50 text-blue-600 hover:bg-blue-100 transition-colors"
                          >
                            <FaEye className="text-xs" />
                          </button>
                          <button
                            onClick={() => openEditModal(row)}
                            title="Edit"
                            className="flex h-8 w-8 items-center justify-center rounded-lg border border-amber-200 bg-amber-50 text-amber-600 hover:bg-amber-100 transition-colors"
                          >
                            <FaEdit className="text-xs" />
                          </button>
                          <button
                            onClick={() => {
                              setDeleteTarget(row)
                              setHasConfirmedDeleteCheck(false)
                            }}
                            title="Hapus"
                            className="flex h-8 w-8 items-center justify-center rounded-lg border border-red-200 bg-red-50 text-red-600 hover:bg-red-100 transition-colors"
                          >
                            <FaTrash className="text-xs" />
                          </button>
                        </div>
                      </td>
                    </tr>
                  )
                })
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination Footer */}
        <div className="flex flex-col gap-3 border-t border-slate-200 bg-white px-4 py-3 sm:flex-row sm:items-center sm:justify-between text-xs text-slate-500">
          <div>
            Menampilkan <span className="font-semibold">{paginationInfo.from}</span> sampai{' '}
            <span className="font-semibold">{paginationInfo.to}</span> dari{' '}
            <span className="font-semibold">{paginationInfo.total}</span> data
          </div>
          <div className="flex items-center gap-2">
            <button
              disabled={page <= 1}
              onClick={() => setPage((p) => Math.max(1, p - 1))}
              className="rounded-lg border border-slate-300 px-3 py-1.5 font-medium hover:bg-slate-50 disabled:opacity-50"
            >
              Sebelumnya
            </button>
            <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-800 font-bold text-white">
              {page}
            </span>
            <button
              disabled={page >= paginationInfo.last_page}
              onClick={() => setPage((p) => p + 1)}
              className="rounded-lg border border-slate-300 px-3 py-1.5 font-medium hover:bg-slate-50 disabled:opacity-50"
            >
              Selanjutnya
            </button>
          </div>
        </div>
      </div>

      {/* 4. MODAL TAMBAH / EDIT UNIT PENDIDIKAN */}
      {isFormModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm">
          <div className="w-full max-w-4xl overflow-hidden rounded-2xl bg-white shadow-2xl transition-all">
            {/* Modal Header Bar */}
            <div className="flex items-center justify-between border-b border-slate-100 bg-slate-50/80 px-6 py-4">
              <h2 className="text-lg font-bold text-slate-900">
                {isEditMode ? 'Edit Unit Pendidikan' : 'Tambah Unit Pendidikan'}
              </h2>
              <button
                onClick={closeFormModal}
                className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-200 hover:text-slate-700"
              >
                ✕
              </button>
            </div>

            {/* Modal Main Body Grid */}
            <div className="grid grid-cols-1 lg:grid-cols-4 min-h-[460px]">
              {/* Left Column: Wizard Stepper */}
              <div className="border-r border-slate-100 bg-slate-50/50 p-6 space-y-6">
                {[
                  { step: 1, label: 'Informasi Unit' },
                  { step: 2, label: 'Alamat' },
                  { step: 3, label: 'Kepala Sekolah' },
                  { step: 4, label: 'Konfirmasi' },
                ].map((s) => (
                  <div
                    key={s.step}
                    onClick={() => setCurrentStep(s.step)}
                    className="flex items-center gap-3 cursor-pointer group"
                  >
                    <div
                      className={`flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold transition-all ${
                        currentStep === s.step
                          ? 'bg-emerald-800 text-white ring-4 ring-emerald-100'
                          : currentStep > s.step
                          ? 'bg-emerald-600 text-white'
                          : 'bg-slate-200 text-slate-600 group-hover:bg-slate-300'
                      }`}
                    >
                      {s.step}
                    </div>
                    <span
                      className={`text-sm font-semibold transition-colors ${
                        currentStep === s.step ? 'text-emerald-900' : 'text-slate-500 group-hover:text-slate-800'
                      }`}
                    >
                      {s.label}
                    </span>
                  </div>
                ))}
              </div>

              {/* Right Main Column / Form Content */}
              <div className={isEditMode ? 'lg:col-span-2 p-6 overflow-y-auto max-h-[520px]' : 'lg:col-span-3 p-6 overflow-y-auto max-h-[520px]'}>
                {/* STEP 1: Informasi Unit */}
                {currentStep === 1 && (
                  <div className="space-y-4">
                    <h3 className="text-base font-bold text-slate-800 border-b border-slate-100 pb-2">Informasi Unit</h3>
                    
                    {/* Logo Unit Upload Dropzone */}
                    <div>
                      <label className="block text-xs font-semibold text-slate-700 mb-1">Logo Unit</label>
                      <div className="flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-slate-200 bg-slate-50/50 p-4 text-center hover:bg-slate-50 transition-colors">
                        <FaUpload className="text-slate-400 text-xl mb-1" />
                        <span className="text-xs font-bold text-slate-700">Upload Logo</span>
                        <span className="text-[10px] text-slate-400">PNG, JPG maksimal 2MB</span>
                      </div>
                    </div>

                    {/* Nama Unit */}
                    <div>
                      <label className="block text-xs font-semibold text-slate-700 mb-1">
                        Nama Unit Pendidikan <span className="text-red-500">*</span>
                      </label>
                      <input
                        type="text"
                        placeholder="Contoh: SDIT 2 Dar el-Iman - Padang"
                        value={formData.name}
                        onChange={(e) => setFormData((p) => ({ ...p, name: e.target.value }))}
                        className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none"
                      />
                    </div>

                    {/* Jenis Unit */}
                    <div>
                      <label className="block text-xs font-semibold text-slate-700 mb-1">
                        Jenis Unit <span className="text-red-500">*</span>
                      </label>
                      <select
                        value={formData.unit_type}
                        onChange={(e) => setFormData((p) => ({ ...p, unit_type: e.target.value }))}
                        className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none"
                      >
                        <option value="">Pilih Jenis Unit</option>
                        {UNIT_TYPES.map((t) => (
                          <option key={t} value={t}>
                            {t}
                          </option>
                        ))}
                      </select>
                    </div>

                    {/* NPSN */}
                    <div>
                      <label className="block text-xs font-semibold text-slate-700 mb-1">NPSN (Opsional)</label>
                      <input
                        type="text"
                        placeholder="Masukkan NPSN"
                        value={formData.npsn}
                        onChange={(e) => setFormData((p) => ({ ...p, npsn: e.target.value }))}
                        className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none"
                      />
                    </div>

                    {/* Email */}
                    <div>
                      <label className="block text-xs font-semibold text-slate-700 mb-1">Email (Opsional)</label>
                      <input
                        type="email"
                        placeholder="Email unit pendidikan"
                        value={formData.email}
                        onChange={(e) => setFormData((p) => ({ ...p, email: e.target.value }))}
                        className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none"
                      />
                    </div>

                    {/* No Telepon */}
                    <div>
                      <label className="block text-xs font-semibold text-slate-700 mb-1">No. Telepon (Opsional)</label>
                      <input
                        type="text"
                        placeholder="08xx-xxxx-xxxx"
                        value={formData.phone}
                        onChange={(e) => setFormData((p) => ({ ...p, phone: e.target.value }))}
                        className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none"
                      />
                    </div>
                  </div>
                )}

                {/* STEP 2: Alamat */}
                {currentStep === 2 && (
                  <div className="space-y-4">
                    <h3 className="text-base font-bold text-slate-800 border-b border-slate-100 pb-2">Alamat Unit</h3>
                    
                    <div>
                      <label className="block text-xs font-semibold text-slate-700 mb-1">Alamat Lengkap</label>
                      <textarea
                        rows={3}
                        placeholder="Jl. Khatib Sulaiman No. 10, Kel. Lolong Belanti..."
                        value={formData.address}
                        onChange={(e) => setFormData((p) => ({ ...p, address: e.target.value }))}
                        className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none"
                      />
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                      <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1">Kota / Kabupaten</label>
                        <input
                          type="text"
                          placeholder="Padang"
                          value={formData.city}
                          onChange={(e) => setFormData((p) => ({ ...p, city: e.target.value }))}
                          className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none"
                        />
                      </div>
                      <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1">Provinsi</label>
                        <input
                          type="text"
                          placeholder="Sumatera Barat"
                          value={formData.province}
                          onChange={(e) => setFormData((p) => ({ ...p, province: e.target.value }))}
                          className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none"
                        />
                      </div>
                    </div>

                    <div>
                      <label className="block text-xs font-semibold text-slate-700 mb-1">Kode Pos</label>
                      <input
                        type="text"
                        placeholder="25136"
                        value={formData.postal_code}
                        onChange={(e) => setFormData((p) => ({ ...p, postal_code: e.target.value }))}
                        className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none"
                      />
                    </div>
                  </div>
                )}

                {/* STEP 3: Kepala Sekolah */}
                {currentStep === 3 && (
                  <div className="space-y-4">
                    <h3 className="text-base font-bold text-slate-800 border-b border-slate-100 pb-2">Kepala Sekolah / Pimpinan</h3>
                    
                    <div>
                      <label className="block text-xs font-semibold text-slate-700 mb-1">Nama Kepala Sekolah / Pimpinan</label>
                      <input
                        type="text"
                        placeholder="Ust. Fadli Rahman, S.Pd"
                        value={formData.principal_name}
                        onChange={(e) => setFormData((p) => ({ ...p, principal_name: e.target.value }))}
                        className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none"
                      />
                    </div>

                    <div>
                      <label className="block text-xs font-semibold text-slate-700 mb-1">NIP / NIPY (Opsional)</label>
                      <input
                        type="text"
                        placeholder="1985xxxxxx"
                        value={formData.principal_nip}
                        onChange={(e) => setFormData((p) => ({ ...p, principal_nip: e.target.value }))}
                        className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none"
                      />
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                      <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1">Tahun Berdiri</label>
                        <input
                          type="number"
                          placeholder="2011"
                          value={formData.established_year}
                          onChange={(e) => setFormData((p) => ({ ...p, established_year: e.target.value }))}
                          className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none"
                        />
                      </div>
                      <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1">Akreditasi</label>
                        <select
                          value={formData.accreditation}
                          onChange={(e) => setFormData((p) => ({ ...p, accreditation: e.target.value }))}
                          className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none"
                        >
                          <option value="A">A (Unggul)</option>
                          <option value="B">B (Baik)</option>
                          <option value="C">C</option>
                        </select>
                      </div>
                    </div>
                  </div>
                )}

                {/* STEP 4: Konfirmasi */}
                {currentStep === 4 && (
                  <div className="space-y-4">
                    <h3 className="text-base font-bold text-slate-800 border-b border-slate-100 pb-2">Konfirmasi Data</h3>
                    
                    <div className="rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-3 text-xs">
                      <div className="flex justify-between border-b border-slate-200 pb-2">
                        <span className="text-slate-500 font-medium">Nama Unit:</span>
                        <span className="font-bold text-slate-800">{formData.name || '-'}</span>
                      </div>
                      <div className="flex justify-between border-b border-slate-200 pb-2">
                        <span className="text-slate-500 font-medium">Jenis Unit:</span>
                        <span className="font-bold text-slate-800">{formData.unit_type || '-'}</span>
                      </div>
                      <div className="flex justify-between border-b border-slate-200 pb-2">
                        <span className="text-slate-500 font-medium">NPSN:</span>
                        <span className="font-bold text-slate-800">{formData.npsn || '-'}</span>
                      </div>
                      <div className="flex justify-between border-b border-slate-200 pb-2">
                        <span className="text-slate-500 font-medium">Kota / Provinsi:</span>
                        <span className="font-bold text-slate-800">{formData.city}, {formData.province}</span>
                      </div>
                      <div className="flex justify-between">
                        <span className="text-slate-500 font-medium">Kepala Sekolah:</span>
                        <span className="font-bold text-slate-800">{formData.principal_name || '-'}</span>
                      </div>
                    </div>
                  </div>
                )}
              </div>

              {/* Edit Mode Side Card (Ringkasan Unit) */}
              {isEditMode && (
                <div className="border-l border-slate-100 bg-slate-50/30 p-6 space-y-4">
                  <div className="rounded-xl border border-blue-200 bg-gradient-to-br from-blue-600 to-blue-800 p-4 text-white shadow-sm">
                    <div className="flex items-center justify-between mb-1">
                      <span className="text-xs font-bold uppercase tracking-wider">{formData.unit_type || 'UNIT'}</span>
                      <span className="rounded-full bg-white/20 px-2 py-0.5 text-[10px] font-bold">Aktif</span>
                    </div>
                    <h4 className="font-extrabold text-sm leading-tight">{formData.name}</h4>
                  </div>

                  <div className="rounded-xl border border-slate-200 bg-white p-4 space-y-3 text-xs">
                    <h5 className="font-bold text-slate-800 border-b border-slate-100 pb-2">Ringkasan Unit</h5>
                    <div className="flex items-center justify-between text-slate-600">
                      <span className="flex items-center gap-1.5"><FaUserGraduate className="text-blue-500" /> Total Siswa</span>
                      <span className="font-bold text-slate-900">{formData.total_siswa?.toLocaleString() || '1,250'}</span>
                    </div>
                    <div className="flex items-center justify-between text-slate-600">
                      <span className="flex items-center gap-1.5"><FaChalkboardTeacher className="text-emerald-500" /> Total Guru</span>
                      <span className="font-bold text-slate-900">{formData.total_guru || '95'}</span>
                    </div>
                    <div className="flex items-center justify-between text-slate-600">
                      <span className="flex items-center gap-1.5"><FaSchool className="text-amber-500" /> Total Kelas</span>
                      <span className="font-bold text-slate-900">{formData.total_kelas || '42'}</span>
                    </div>
                    <div className="flex items-center justify-between text-slate-600">
                      <span className="flex items-center gap-1.5"><FaCalendarAlt className="text-purple-500" /> Tahun Berdiri</span>
                      <span className="font-bold text-slate-900">{formData.established_year || '2011'}</span>
                    </div>
                  </div>

                  <div className="space-y-2 pt-2">
                    <button
                      type="button"
                      onClick={() => toggleUnitStatus(formData)}
                      className="w-full rounded-lg border border-amber-300 bg-amber-50 py-2 text-xs font-bold text-amber-700 hover:bg-amber-100 transition-colors"
                    >
                      {formData.is_active ? 'Nonaktifkan Unit' : 'Aktifkan Unit'}
                    </button>
                    <button
                      type="button"
                      onClick={() => {
                        setDeleteTarget(formData)
                        closeFormModal()
                      }}
                      className="w-full rounded-lg border border-red-200 bg-red-50 py-2 text-xs font-bold text-red-600 hover:bg-red-100 transition-colors"
                    >
                      Hapus Unit
                    </button>
                  </div>
                </div>
              )}
            </div>

            {/* Modal Bottom Action Footer */}
            <div className="flex items-center justify-between border-t border-slate-100 bg-slate-50/80 px-6 py-4">
              <button
                type="button"
                onClick={closeFormModal}
                className="rounded-lg border border-slate-300 bg-white px-5 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50 transition-colors"
              >
                Batal
              </button>
              
              <div className="flex items-center gap-2">
                <button
                  type="button"
                  onClick={closeFormModal}
                  className="rounded-lg border border-emerald-600 bg-white px-5 py-2 text-xs font-bold text-emerald-700 hover:bg-emerald-50 transition-colors"
                >
                  Simpan Draft
                </button>
                {currentStep < 4 ? (
                  <button
                    type="button"
                    onClick={() => setCurrentStep((s) => Math.min(4, s + 1))}
                    className="rounded-lg bg-emerald-800 px-6 py-2 text-xs font-bold text-white shadow hover:bg-emerald-900 transition-colors"
                  >
                    Selanjutnya →
                  </button>
                ) : (
                  <button
                    type="button"
                    onClick={handleFormSubmit}
                    className="rounded-lg bg-emerald-800 px-6 py-2 text-xs font-bold text-white shadow hover:bg-emerald-900 transition-colors"
                  >
                    {isEditMode ? 'Simpan Perubahan' : 'Simpan Unit'}
                  </button>
                )}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* 5. MODAL DETAIL UNIT PENDIDIKAN */}
      {detailUnit && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm">
          <div className="w-full max-w-4xl overflow-hidden rounded-2xl bg-white shadow-2xl">
            {/* Top Action Header */}
            <div className="flex items-center justify-between border-b border-slate-100 px-6 py-3.5 bg-slate-50">
              <button
                onClick={() => setDetailUnit(null)}
                className="flex items-center gap-2 text-xs font-bold text-slate-600 hover:text-slate-900"
              >
                <FaArrowLeft /> Kembali
              </button>
              <div className="flex items-center gap-2">
                <button
                  onClick={() => {
                    const target = detailUnit
                    setDetailUnit(null)
                    openEditModal(target)
                  }}
                  className="flex items-center gap-1.5 rounded-lg border border-emerald-600 bg-white px-3 py-1.5 text-xs font-bold text-emerald-700 hover:bg-emerald-50"
                >
                  <FaEdit /> Edit
                </button>
                <button
                  onClick={() => Swal.fire('Export PDF', 'Mencetak dokumen PDF detail unit...', 'info')}
                  className="flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-50"
                >
                  <FaFilePdf className="text-red-500" /> Export PDF
                </button>
              </div>
            </div>

            {/* Main Content Body */}
            <div className="p-6 space-y-6 max-h-[80vh] overflow-y-auto">
              {/* Hero Header Card */}
              <div className="flex flex-col md:flex-row gap-6 items-center rounded-2xl border border-slate-200 bg-slate-50 p-4">
                {/* Building Photo Preview */}
                <div className="h-36 w-full md:w-56 overflow-hidden rounded-xl bg-slate-200 flex items-center justify-center relative">
                  <div className="absolute inset-0 bg-gradient-to-t from-slate-900/60 to-transparent"></div>
                  <FaBuilding className="text-5xl text-slate-400" />
                  <span className="absolute bottom-2 left-2 text-[10px] font-bold text-white bg-black/40 px-2 py-0.5 rounded">
                    Gedung Utama
                  </span>
                </div>

                {/* Right Details */}
                <div className="flex-1 space-y-2">
                  <div className="flex items-center gap-2">
                    <h2 className="text-xl font-black text-slate-900">{detailUnit.name}</h2>
                    <span className="rounded-md bg-blue-600 px-2 py-0.5 text-xs font-bold text-white">
                      {detailUnit.unit_type || 'SDIT'}
                    </span>
                  </div>
                  <div className="flex items-center gap-2 text-xs font-medium text-emerald-700">
                    <span className="h-2 w-2 rounded-full bg-emerald-500"></span>
                    Status : <span className="font-bold">Aktif</span>
                  </div>
                  <p className="flex items-start gap-1.5 text-xs text-slate-500">
                    <FaMapMarkerAlt className="mt-0.5 shrink-0 text-slate-400" />
                    {detailUnit.address || 'Jl. Khatib Sulaiman No. 10, Kel. Lolong Belanti, Kec. Padang Utara, Kota Padang'}
                  </p>
                  <p className="flex items-center gap-1.5 text-xs text-slate-600 font-semibold">
                    👤 Kepala Sekolah : {detailUnit.principal_name || 'Ust. Fadli Rahman, S.Pd'}
                  </p>
                </div>
              </div>

              {/* Tab Navigation */}
              <div className="flex border-b border-slate-200 gap-6 text-xs font-bold text-slate-500">
                {['Informasi', 'Statistik', 'Guru', 'Siswa', 'Kelas', 'Dokumen', 'Riwayat'].map((tab) => (
                  <button
                    key={tab}
                    onClick={() => setActiveDetailTab(tab)}
                    className={`pb-3 transition-colors border-b-2 ${
                      activeDetailTab === tab
                        ? 'border-emerald-800 text-emerald-900'
                        : 'border-transparent hover:text-slate-800'
                    }`}
                  >
                    {tab}
                  </button>
                ))}
              </div>

              {/* Tab Content: Informasi */}
              {activeDetailTab === 'Informasi' && (
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                  {/* Left 2 Cols: Detail Fields Grid */}
                  <div className="md:col-span-2 rounded-xl border border-slate-200 bg-white p-5 space-y-4">
                    <h3 className="font-bold text-slate-800 border-b border-slate-100 pb-2 text-sm">Informasi Unit</h3>
                    <div className="grid grid-cols-2 gap-4 text-xs">
                      <div>
                        <span className="text-slate-400 block mb-0.5">Jenis Unit</span>
                        <span className="font-bold text-slate-800">{detailUnit.unit_type}</span>
                      </div>
                      <div>
                        <span className="text-slate-400 block mb-0.5">Tahun Berdiri</span>
                        <span className="font-bold text-slate-800">{detailUnit.established_year}</span>
                      </div>
                      <div>
                        <span className="text-slate-400 block mb-0.5">NPSN</span>
                        <span className="font-bold text-slate-800">{detailUnit.npsn || '70012345'}</span>
                      </div>
                      <div>
                        <span className="text-slate-400 block mb-0.5">Status Akreditasi</span>
                        <span className="font-bold text-slate-800">{detailUnit.accreditation || 'A'}</span>
                      </div>
                      <div>
                        <span className="text-slate-400 block mb-0.5">Email</span>
                        <span className="font-bold text-slate-800">{detailUnit.email || 'sdit2@dareliman.sch.id'}</span>
                      </div>
                      <div>
                        <span className="text-slate-400 block mb-0.5">SK Pendirian</span>
                        <span className="font-bold text-slate-800">{detailUnit.sk_pendirian || '421.3/123/DI/2011'}</span>
                      </div>
                      <div>
                        <span className="text-slate-400 block mb-0.5">No. Telepon</span>
                        <span className="font-bold text-slate-800">{detailUnit.phone || '0812-3456-7890'}</span>
                      </div>
                      <div>
                        <span className="text-slate-400 block mb-0.5">Tgl SK</span>
                        <span className="font-bold text-slate-800">{detailUnit.tgl_sk || '12 Januari 2011'}</span>
                      </div>
                    </div>
                  </div>

                  {/* Right 1 Col: Quick Stats Card */}
                  <div className="rounded-xl border border-slate-200 bg-white p-5 space-y-4">
                    <h3 className="font-bold text-slate-800 border-b border-slate-100 pb-2 text-sm">Statistik Singkat</h3>
                    <div className="space-y-3 text-xs">
                      <div className="flex items-center justify-between p-2.5 rounded-lg bg-blue-50/60 border border-blue-100">
                        <span className="flex items-center gap-2 text-slate-700"><FaUserGraduate className="text-blue-600" /> Siswa</span>
                        <span className="font-extrabold text-blue-900">{detailUnit.total_siswa?.toLocaleString()}</span>
                      </div>
                      <div className="flex items-center justify-between p-2.5 rounded-lg bg-emerald-50/60 border border-emerald-100">
                        <span className="flex items-center gap-2 text-slate-700"><FaChalkboardTeacher className="text-emerald-600" /> Guru</span>
                        <span className="font-extrabold text-emerald-900">{detailUnit.total_guru}</span>
                      </div>
                      <div className="flex items-center justify-between p-2.5 rounded-lg bg-amber-50/60 border border-amber-100">
                        <span className="flex items-center gap-2 text-slate-700"><FaSchool className="text-amber-600" /> Kelas</span>
                        <span className="font-extrabold text-amber-900">{detailUnit.total_kelas}</span>
                      </div>
                      <div className="flex items-center justify-between p-2.5 rounded-lg bg-purple-50/60 border border-purple-100">
                        <span className="flex items-center gap-2 text-slate-700"><FaBuilding className="text-purple-600" /> Rombel</span>
                        <span className="font-extrabold text-purple-900">{detailUnit.total_rombel}</span>
                      </div>
                    </div>
                  </div>
                </div>
              )}

              {activeDetailTab !== 'Informasi' && (
                <div className="py-12 text-center text-slate-400 text-xs">
                  Modul data {activeDetailTab} untuk unit ini siap digunakan.
                </div>
              )}
            </div>
          </div>
        </div>
      )}

      {/* 6. MODAL KONFIRMASI HAPUS UNIT */}
      {deleteTarget && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm">
          <div className="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl">
            {/* Header / Warning Icon */}
            <div className="p-6 text-center space-y-3 border-b border-slate-100">
              <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-amber-100 text-amber-600 text-2xl">
                <FaExclamationTriangle />
              </div>
              <h3 className="text-lg font-extrabold text-slate-900">Hapus Unit Pendidikan</h3>
              <p className="text-xs text-slate-500">Apakah Anda yakin ingin menghapus unit pendidikan berikut?</p>
            </div>

            {/* Target Unit Card Preview */}
            <div className="p-6 space-y-4">
              <div className="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3.5">
                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-600 font-black text-white text-xs">
                  {deleteTarget.unit_type}
                </div>
                <div className="text-xs space-y-0.5">
                  <h4 className="font-extrabold text-slate-900">{deleteTarget.name}</h4>
                  <p className="text-slate-500">Jenis Unit: <span className="font-medium text-slate-700">{deleteTarget.unit_type}</span></p>
                  <p className="text-slate-500">Kota / Provinsi: <span className="font-medium text-slate-700">{deleteTarget.city}, {deleteTarget.province}</span></p>
                  <p className="text-slate-500">Kepala Sekolah: <span className="font-medium text-slate-700">{deleteTarget.principal_name}</span></p>
                </div>
              </div>

              {/* Danger Warning Alert Box */}
              <div className="rounded-xl border border-amber-200 bg-amber-50/80 p-4 text-xs text-amber-900 space-y-2">
                <p className="font-bold">Semua data yang terkait dengan unit ini akan terhapus permanen, termasuk:</p>
                <div className="grid grid-cols-2 gap-2 text-amber-800 font-medium">
                  <div className="flex items-center gap-1.5">📄 Data Siswa</div>
                  <div className="flex items-center gap-1.5">👨‍🏫 Data Guru</div>
                  <div className="flex items-center gap-1.5">🏫 Data Kelas</div>
                  <div className="flex items-center gap-1.5">📊 Laporan & Dokumen</div>
                  <div className="flex items-center gap-1.5">📅 Absensi</div>
                  <div className="flex items-center gap-1.5">💰 Data Keuangan</div>
                </div>
              </div>

              {/* Confirmation Checkbox */}
              <label className="flex items-center gap-2 cursor-pointer text-xs font-semibold text-slate-700">
                <input
                  type="checkbox"
                  checked={hasConfirmedDeleteCheck}
                  onChange={(e) => setHasConfirmedDeleteCheck(e.target.checked)}
                  className="h-4 w-4 rounded border-slate-300 text-emerald-800 focus:ring-emerald-600"
                />
                Saya memahami bahwa data tidak dapat dikembalikan.
              </label>
            </div>

            {/* Bottom Buttons */}
            <div className="flex items-center justify-end gap-3 border-t border-slate-100 bg-slate-50 px-6 py-4">
              <button
                onClick={() => setDeleteTarget(null)}
                className="rounded-lg border border-slate-300 bg-white px-5 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50"
              >
                Batal
              </button>
              <button
                disabled={!hasConfirmedDeleteCheck || deleteMutation.isPending}
                onClick={() => deleteMutation.mutate(deleteTarget.id)}
                className="rounded-lg bg-red-600 px-5 py-2 text-xs font-bold text-white shadow hover:bg-red-700 disabled:opacity-50 transition-colors"
              >
                {deleteMutation.isPending ? 'Menghapus...' : 'Hapus Permanen'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
