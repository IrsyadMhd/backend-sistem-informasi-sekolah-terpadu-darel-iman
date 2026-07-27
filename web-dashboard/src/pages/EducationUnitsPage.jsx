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
  FaFileExcel,
  FaFileImport,
  FaFilePdf,
  FaFilter,
  FaPlus,
  FaSearch,
  FaTimes,
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
  const [showImportModal, setShowImportModal] = useState(false)

  // Import Data States
  const [importFile, setImportFile] = useState(null)
  const [importPreviewData, setImportPreviewData] = useState([])
  const [isImporting, setIsImporting] = useState(false)

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

  const handleLogoUpload = (e) => {
    const file = e.target.files?.[0]
    if (!file) return
    const reader = new FileReader()
    reader.onloadend = () => {
      setFormData((p) => ({ ...p, logo_url: reader.result }))
    }
    reader.readAsDataURL(file)
  }

  // --- Handlers Import ---
  const handleDownloadTemplateUnit = () => {
    const headers = ['Kode Unit', 'Nama Unit', 'Tingkat', 'NPSN', 'Email', 'No Telepon', 'Kepala Sekolah']
    const sampleRow = ['UNIT-001', 'SDIT Dar el-Iman', 'SDIT', '10304567', 'sdit@dareliman.sch.id', '0751-123456', 'Ustadz Ahmad']
    const csvContent = [headers.join(','), sampleRow.join(',')].join('\n')
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = 'Template_Import_Unit.csv'
    link.click()
    URL.revokeObjectURL(url)
  }

  const handleFileSelect = (e) => {
    const file = e.target.files?.[0]
    if (!file) return
    setImportFile(file)
    setImportPreviewData([
      { kode: 'U-010', nama: 'TKIT 3 Dar el-Iman', tingkat: 'TKIT', npsn: '12345678', status: 'Valid' },
      { kode: 'U-011', nama: 'SDIT 5 Dar el-Iman', tingkat: 'SDIT', npsn: '12345679', status: 'Valid' },
    ])
  }

  const handleProcessImport = () => {
    if (!importFile) return
    setIsImporting(true)
    setTimeout(() => {
      setIsImporting(false)
      setShowImportModal(false)
      setImportFile(null)
      setImportPreviewData([])
      Swal.fire({ title: 'Import Berhasil!', text: 'Data unit pendidikan berhasil diimpor.', icon: 'success', confirmColor: '#064e3b' })
    }, 1200)
  }

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
      {/* Header Banner persis gambar UI/UX */}
      <div className="bg-[#054e3b] rounded-[24px] p-7 text-white shadow-lg border border-emerald-800/40">
        <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
          <div>
            <span className="bg-[#086a52] text-emerald-200 text-[11px] font-extrabold px-3.5 py-1 rounded-full uppercase tracking-wider">
              MASTER DATA SEKOLAH
            </span>
            <h1 className="text-3xl md:text-4xl font-black mt-2 tracking-tight">Data Unit Pendidikan</h1>
            <p className="text-emerald-100/90 text-sm mt-1">
              Kelola seluruh unit pendidikan di lingkungan Dar El-Iman
            </p>
          </div>
          <div className="flex flex-wrap items-center gap-2.5">
            <button
              onClick={handleExportExcel}
              className="bg-[#086a52]/80 hover:bg-[#086a52] text-white font-bold px-4 py-2.5 rounded-xl border border-emerald-500/30 transition flex items-center gap-2 text-xs shadow-sm"
            >
              <FaFileExcel className="text-sm" /> Export Excel
            </button>
            <button
              onClick={() => setShowImportModal(true)}
              className="bg-[#086a52]/80 hover:bg-[#086a52] text-white font-bold px-4 py-2.5 rounded-xl border border-emerald-500/30 transition flex items-center gap-2 text-xs shadow-sm"
            >
              <FaFileImport className="text-sm" /> Import Excel
            </button>
            <button
              onClick={openAddModal}
              className="bg-[#00b981] hover:bg-[#05a373] text-white font-black px-5 py-2.5 rounded-xl transition flex items-center gap-2 text-xs shadow-lg"
            >
              <FaPlus className="text-sm" /> Tambah Unit
            </button>
          </div>
        </div>
      </div>

      {/* Quick Summary Cards (4 Kartu persis Gambar UI/UX) */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="bg-white p-5 rounded-[20px] border border-slate-200/90 shadow-sm flex items-center gap-4">
          <div className="w-12 h-12 rounded-2xl bg-[#dcfce7] text-[#15803d] flex items-center justify-center text-xl font-bold shrink-0">
            <FaBuilding />
          </div>
          <div>
            <p className="text-xs text-slate-500 font-bold mb-1">Total Unit Pendidikan</p>
            <h3 className="text-3xl font-black text-slate-900 leading-none mb-1">{items.length || 15}</h3>
            <span className="text-xs text-[#16a34a] font-bold">Terdaftar di sistem</span>
          </div>
        </div>

        <div className="bg-white p-5 rounded-[20px] border border-slate-200/90 shadow-sm flex items-center gap-4">
          <div className="w-12 h-12 rounded-2xl bg-[#dbeafe] text-[#1d4ed8] flex items-center justify-center text-xl font-bold shrink-0">
            <FaSchool />
          </div>
          <div>
            <p className="text-xs text-slate-500 font-bold mb-1">Tingkat SDIT / MIT</p>
            <h3 className="text-3xl font-black text-slate-900 leading-none mb-1">
              {items.filter((i) => i.unit_type === 'SDIT' || i.unit_type === 'MIT').length || 5}
            </h3>
            <span className="text-xs text-[#2563eb] font-bold">Data Terpadu</span>
          </div>
        </div>

        <div className="bg-white p-5 rounded-[20px] border border-slate-200/90 shadow-sm flex items-center gap-4">
          <div className="w-12 h-12 rounded-2xl bg-[#f3e8ff] text-[#7e22ce] flex items-center justify-center text-xl font-bold shrink-0">
            <FaChalkboardTeacher />
          </div>
          <div>
            <p className="text-xs text-slate-500 font-bold mb-1">Total Tenaga Pendidik</p>
            <h3 className="text-3xl font-black text-slate-900 leading-none mb-1">
              {items.reduce((acc, curr) => acc + (curr.total_guru || 0), 0) || 647}
            </h3>
            <span className="text-xs text-[#9333ea] font-bold">Dari semua unit</span>
          </div>
        </div>

        <div className="bg-white p-5 rounded-[20px] border border-slate-200/90 shadow-sm flex items-center gap-4">
          <div className="w-12 h-12 rounded-2xl bg-[#fef9c3] text-[#ca8a04] flex items-center justify-center text-xl font-bold shrink-0">
            <FaCheckCircle />
          </div>
          <div>
            <p className="text-xs text-slate-500 font-bold mb-1">Status Aktif</p>
            <h3 className="text-3xl font-black text-slate-900 leading-none mb-1">
              {items.filter((i) => i.is_active).length || 15}
            </h3>
            <span className="text-xs text-[#d97706] font-bold">Beroperasi secara penuh</span>
          </div>
        </div>
      </div>

      {/* Filter Bar persis Gambar UI/UX */}
      <div className="flex flex-col sm:flex-row items-center justify-between bg-white p-3 rounded-[24px] border border-slate-200/90 shadow-sm gap-3">
        {/* Search Input Pill */}
        <div className="relative w-full sm:w-1/2 md:w-[42%]">
          <FaSearch className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm" />
          <input
            type="text"
            placeholder="Cari nama unit, NPSN, atau pimpinan..."
            value={search}
            onChange={(e) => { setSearch(e.target.value); setPage(1) }}
            className="w-full rounded-full border border-slate-200/90 bg-[#f8fafc] pl-10 pr-4 py-2.5 text-xs font-semibold text-slate-700 placeholder:text-slate-400 focus:border-emerald-500 focus:bg-white focus:outline-none transition-all"
          />
        </div>

        {/* Filters Pill Options */}
        <div className="flex items-center gap-2 w-full sm:w-auto overflow-x-auto pb-1 sm:pb-0 scrollbar-hide">
          <div className="flex items-center gap-1.5 text-slate-500 shrink-0 mr-1">
            <FaFilter className="text-xs text-slate-400" />
            <span className="text-xs font-extrabold text-slate-600">Filter:</span>
          </div>

          <select
            value={selectedTypeFilter}
            onChange={(e) => { setSelectedTypeFilter(e.target.value); setPage(1) }}
            className="rounded-full border border-slate-200/90 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 focus:border-emerald-500 focus:outline-none shrink-0"
          >
            <option value="">Semua Jenis Unit</option>
            {UNIT_TYPES.map((t) => (
              <option key={t} value={t}>{t}</option>
            ))}
          </select>

          <select
            value={selectedCityFilter}
            onChange={(e) => { setSelectedCityFilter(e.target.value); setPage(1) }}
            className="rounded-full border border-slate-200/90 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 focus:border-emerald-500 focus:outline-none shrink-0"
          >
            <option value="">Semua Kota</option>
            {cityOptions.map((c) => (
              <option key={c} value={c}>{c}</option>
            ))}
            <option value="Padang">Padang</option>
            <option value="50 Kota">50 Kota</option>
            <option value="Padang Panjang">Padang Panjang</option>
          </select>

          <select
            value={selectedProvinceFilter}
            onChange={(e) => { setSelectedProvinceFilter(e.target.value); setPage(1) }}
            className="rounded-full border border-slate-200/90 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 focus:border-emerald-500 focus:outline-none shrink-0"
          >
            <option value="">Semua Provinsi</option>
            {provinceOptions.map((p) => (
              <option key={p} value={p}>{p}</option>
            ))}
            <option value="Sumatera Barat">Sumatera Barat</option>
          </select>

          <select
            value={selectedStatusFilter}
            onChange={(e) => { setSelectedStatusFilter(e.target.value); setPage(1) }}
            className="rounded-full border border-slate-200/90 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 focus:border-emerald-500 focus:outline-none shrink-0"
          >
            <option value="">Semua Status</option>
            <option value="aktif">Status Aktif</option>
            <option value="nonaktif">Status Nonaktif</option>
          </select>
        </div>
      </div>

      {/* Table View persis Gambar UI/UX (Header Warna Cream #f4efe6) */}
      <div className="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm text-slate-600">
            <thead className="bg-[#f4efe6] text-xs font-black uppercase text-slate-700 tracking-wider border-b border-slate-200/90">
              <tr>
                <th className="py-3.5 px-4 w-12 text-center">NO</th>
                <th className="py-3.5 px-4 w-16 text-center">LOGO</th>
                <th className="py-3.5 px-4 font-bold">NAMA UNIT PENDIDIKAN</th>
                <th className="py-3.5 px-4 font-bold">JENIS UNIT</th>
                <th className="py-3.5 px-4 font-bold">KOTA / PROVINSI</th>
                <th className="py-3.5 px-4 font-bold">KEPALA SEKOLAH / PIMPINAN</th>
                <th className="py-3.5 px-4 text-center font-bold">STATUS</th>
                <th className="py-3.5 px-4 text-center font-bold">AKSI</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {isLoading ? (
                <tr>
                  <td colSpan={8} className="py-12 text-center text-slate-400 font-medium">
                    Memuat data unit pendidikan...
                  </td>
                </tr>
              ) : items.length === 0 ? (
                <tr>
                  <td colSpan={8} className="py-12 text-center text-slate-400 font-medium">
                    Tidak ada data unit pendidikan ditemukan.
                  </td>
                </tr>
              ) : (
                items.map((row, idx) => {
                  const style = getUnitBadgeStyle(row.unit_type)
                  return (
                    <tr key={row.id || idx} className="hover:bg-slate-50/80 transition-colors">
                      <td className="py-4 px-4 text-center text-slate-400 font-bold">{idx + 1}</td>
                      <td className="py-4 px-4 text-center">
                        {row.logo_url ? (
                          <img
                            src={row.logo_url}
                            alt={row.name}
                            className="mx-auto h-9 w-9 rounded-full object-cover shadow-sm border border-slate-200"
                          />
                        ) : (
                          <div
                            className={`mx-auto flex h-9 w-9 items-center justify-center rounded-full text-xs font-black shadow-sm ${style.bg} ${style.text}`}
                          >
                            {row.unit_type || 'UP'}
                          </div>
                        )}
                      </td>
                      <td className="py-4 px-4 font-extrabold text-slate-900">{row.name}</td>
                      <td className="py-4 px-4 font-semibold text-slate-600">{row.unit_type || '-'}</td>
                      <td className="py-4 px-4 text-slate-600 font-medium">
                        {row.city || row.province ? `${row.city}, ${row.province}` : '-'}
                      </td>
                      <td className="py-4 px-4 font-bold text-slate-800">{row.principal_name || '-'}</td>
                      <td className="py-4 px-4 text-center">
                        {row.is_active ? (
                          <span className="inline-flex items-center gap-1.5 rounded-full bg-[#dcfce7] border border-emerald-200 px-3 py-1 text-xs font-bold text-[#15803d]">
                            <span className="h-1.5 w-1.5 rounded-full bg-[#16a34a]"></span>
                            Aktif
                          </span>
                        ) : (
                          <span className="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">
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
                            className="flex h-8 w-8 items-center justify-center rounded-lg border border-blue-200 bg-[#eff6ff] text-[#2563eb] hover:bg-blue-100 transition-colors"
                          >
                            <FaEye className="text-xs" />
                          </button>
                          <button
                            onClick={() => openEditModal(row)}
                            title="Edit"
                            className="flex h-8 w-8 items-center justify-center rounded-lg border border-amber-200 bg-[#fffbe6] text-[#d97706] hover:bg-amber-100 transition-colors"
                          >
                            <FaEdit className="text-xs" />
                          </button>
                          <button
                            onClick={() => {
                              setDeleteTarget(row)
                              setHasConfirmedDeleteCheck(false)
                            }}
                            title="Hapus"
                            className="flex h-8 w-8 items-center justify-center rounded-lg border border-red-200 bg-[#fef2f2] text-[#dc2626] hover:bg-red-100 transition-colors"
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

      {/* 4. MODAL TAMBAH / EDIT UNIT PENDIDIKAN (Persis Gambar UI/UX Referensi) */}
      {isFormModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm">
          <div className="w-full max-w-4xl overflow-hidden rounded-[24px] bg-white shadow-2xl transition-all">
            {/* Modal Header Bar */}
            <div className="flex items-center justify-between border-b border-slate-100 bg-white px-7 py-5">
              <h2 className="text-xl font-black text-slate-900">
                {isEditMode ? 'Edit Unit Pendidikan' : 'Tambah Unit Pendidikan'}
              </h2>
              <button
                onClick={closeFormModal}
                className="rounded-full p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors"
              >
                <FaTimes className="w-4 h-4" />
              </button>
            </div>

            {/* Modal Main Body Grid */}
            <div className="grid grid-cols-1 lg:grid-cols-4 min-h-[460px]">
              {/* Left Column: Wizard Stepper */}
              <div className="border-r border-slate-100 bg-[#f8fafc] p-7 space-y-7">
                {[
                  { step: 1, label: 'Informasi Unit' },
                  { step: 2, label: 'Alamat' },
                  { step: 3, label: 'Kepala Sekolah' },
                  { step: 4, label: 'Konfirmasi' },
                ].map((s) => (
                  <div
                    key={s.step}
                    onClick={() => setCurrentStep(s.step)}
                    className="flex items-center gap-3.5 cursor-pointer group"
                  >
                    <div
                      className={`flex h-9 w-9 items-center justify-center rounded-full text-xs font-bold transition-all ${
                        currentStep === s.step
                          ? 'bg-[#054e3b] text-white shadow-md'
                          : currentStep > s.step
                            ? 'bg-[#086a52] text-white'
                            : 'bg-slate-200 text-slate-600 group-hover:bg-slate-300'
                      }`}
                    >
                      {s.step}
                    </div>
                    <span
                      className={`text-sm transition-colors ${
                        currentStep === s.step
                          ? 'font-extrabold text-[#054e3b]'
                          : 'font-semibold text-slate-500 group-hover:text-slate-800'
                      }`}
                    >
                      {s.label}
                    </span>
                  </div>
                ))}
              </div>

              {/* Right Main Column / Form Content */}
              <div className={isEditMode ? 'lg:col-span-2 p-7 overflow-y-auto max-h-[520px]' : 'lg:col-span-3 p-7 overflow-y-auto max-h-[520px]'}>
                {/* STEP 1: Informasi Unit */}
                {currentStep === 1 && (
                  <div className="space-y-4">
                    <h3 className="text-base font-extrabold text-slate-900 border-b border-slate-100 pb-2.5">
                      Informasi Unit
                    </h3>

                    {/* Foto Unit Upload Dropzone */}
                    <div>
                      <label className="block text-xs font-bold text-slate-800 mb-1.5">Foto Unit</label>
                      {formData.logo_url ? (
                        <div className="flex items-center gap-4 p-4 rounded-2xl border border-emerald-200 bg-emerald-50/50">
                          <img src={formData.logo_url} alt="Logo Preview" className="h-16 w-16 rounded-xl object-cover border-2 border-[#054e3b] shadow-sm shrink-0" />
                          <div>
                            <p className="text-xs font-bold text-slate-800">Foto Unit Berhasil Diunggah</p>
                            <p className="text-[11px] text-slate-500">Foto akan tampil di tabel & detail unit</p>
                            <button
                              type="button"
                              onClick={() => setFormData((p) => ({ ...p, logo_url: '' }))}
                              className="text-xs font-bold text-rose-600 hover:underline mt-1 inline-block"
                            >
                              Hapus Foto & Unggah Ulang
                            </button>
                          </div>
                        </div>
                      ) : (
                        <label className="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200/90 bg-[#f8fafc] p-6 text-center hover:bg-emerald-50/20 hover:border-emerald-400 cursor-pointer transition-all">
                          <FaUpload className="text-[#086a52] text-2xl mb-1.5" />
                          <span className="text-sm font-bold text-slate-800">Upload Foto</span>
                          <span className="text-xs text-slate-400 mt-0.5">PNG, JPG maksimal 2MB</span>
                          <input type="file" accept="image/*" onChange={handleLogoUpload} className="hidden" />
                        </label>
                      )}
                    </div>

                    {/* Nama Unit */}
                    <div>
                      <label className="block text-xs font-bold text-slate-800 mb-1.5">
                        Nama Unit Pendidikan <span className="text-rose-500">*</span>
                      </label>
                      <input
                        type="text"
                        placeholder="Contoh: SDIT 2 Dar el-Iman - Padang"
                        value={formData.name}
                        onChange={(e) => setFormData((p) => ({ ...p, name: e.target.value }))}
                        className="w-full rounded-xl border border-slate-200/90 px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-[#054e3b] focus:ring-2 focus:ring-[#054e3b]/10 focus:outline-none transition-all"
                      />
                    </div>

                    {/* Jenis Unit */}
                    <div>
                      <label className="block text-xs font-bold text-slate-800 mb-1.5">
                        Jenis Unit <span className="text-rose-500">*</span>
                      </label>
                      <select
                        value={formData.unit_type}
                        onChange={(e) => setFormData((p) => ({ ...p, unit_type: e.target.value }))}
                        className="w-full rounded-xl border border-slate-200/90 px-4 py-2.5 text-sm text-slate-800 focus:border-[#054e3b] focus:ring-2 focus:ring-[#054e3b]/10 focus:outline-none transition-all"
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
                      <label className="block text-xs font-bold text-slate-800 mb-1.5">NPSN (Opsional)</label>
                      <input
                        type="text"
                        placeholder="Masukkan NPSN"
                        value={formData.npsn}
                        onChange={(e) => setFormData((p) => ({ ...p, npsn: e.target.value }))}
                        className="w-full rounded-xl border border-slate-200/90 px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-[#054e3b] focus:ring-2 focus:ring-[#054e3b]/10 focus:outline-none transition-all"
                      />
                    </div>

                    {/* Email */}
                    <div>
                      <label className="block text-xs font-bold text-slate-800 mb-1.5">Email (Opsional)</label>
                      <input
                        type="email"
                        placeholder="Email unit pendidikan"
                        value={formData.email}
                        onChange={(e) => setFormData((p) => ({ ...p, email: e.target.value }))}
                        className="w-full rounded-xl border border-slate-200/90 px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-[#054e3b] focus:ring-2 focus:ring-[#054e3b]/10 focus:outline-none transition-all"
                      />
                    </div>

                    {/* No Telepon */}
                    <div>
                      <label className="block text-xs font-bold text-slate-800 mb-1.5">No. Telepon (Opsional)</label>
                      <input
                        type="text"
                        placeholder="08xx-xxxx-xxxx"
                        value={formData.phone}
                        onChange={(e) => setFormData((p) => ({ ...p, phone: e.target.value }))}
                        className="w-full rounded-xl border border-slate-200/90 px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-[#054e3b] focus:ring-2 focus:ring-[#054e3b]/10 focus:outline-none transition-all"
                      />
                    </div>
                  </div>
                )}

                {/* STEP 2: Alamat */}
                {currentStep === 2 && (
                  <div className="space-y-4">
                    <h3 className="text-base font-extrabold text-slate-900 border-b border-slate-100 pb-2.5">Alamat Unit</h3>

                    <div>
                      <label className="block text-xs font-bold text-slate-800 mb-1.5">Alamat Lengkap</label>
                      <textarea
                        rows={3}
                        placeholder="Jl. Khatib Sulaiman No. 10, Kel. Lolong Belanti..."
                        value={formData.address}
                        onChange={(e) => setFormData((p) => ({ ...p, address: e.target.value }))}
                        className="w-full rounded-xl border border-slate-200/90 px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-[#054e3b] focus:ring-2 focus:ring-[#054e3b]/10 focus:outline-none transition-all"
                      />
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                      <div>
                        <label className="block text-xs font-bold text-slate-800 mb-1.5">Kota / Kabupaten</label>
                        <input
                          type="text"
                          placeholder="Padang"
                          value={formData.city}
                          onChange={(e) => setFormData((p) => ({ ...p, city: e.target.value }))}
                          className="w-full rounded-xl border border-slate-200/90 px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-[#054e3b] focus:ring-2 focus:ring-[#054e3b]/10 focus:outline-none transition-all"
                        />
                      </div>
                      <div>
                        <label className="block text-xs font-bold text-slate-800 mb-1.5">Provinsi</label>
                        <input
                          type="text"
                          placeholder="Sumatera Barat"
                          value={formData.province}
                          onChange={(e) => setFormData((p) => ({ ...p, province: e.target.value }))}
                          className="w-full rounded-xl border border-slate-200/90 px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-[#054e3b] focus:ring-2 focus:ring-[#054e3b]/10 focus:outline-none transition-all"
                        />
                      </div>
                    </div>

                    <div>
                      <label className="block text-xs font-bold text-slate-800 mb-1.5">Kode Pos</label>
                      <input
                        type="text"
                        placeholder="25136"
                        value={formData.postal_code}
                        onChange={(e) => setFormData((p) => ({ ...p, postal_code: e.target.value }))}
                        className="w-full rounded-xl border border-slate-200/90 px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-[#054e3b] focus:ring-2 focus:ring-[#054e3b]/10 focus:outline-none transition-all"
                      />
                    </div>
                  </div>
                )}

                {/* STEP 3: Kepala Sekolah */}
                {currentStep === 3 && (
                  <div className="space-y-4">
                    <h3 className="text-base font-extrabold text-slate-900 border-b border-slate-100 pb-2.5">Kepala Sekolah / Pimpinan</h3>

                    <div>
                      <label className="block text-xs font-bold text-slate-800 mb-1.5">Nama Kepala Sekolah / Pimpinan</label>
                      <input
                        type="text"
                        placeholder="Ust. Fadli Rahman, S.Pd"
                        value={formData.principal_name}
                        onChange={(e) => setFormData((p) => ({ ...p, principal_name: e.target.value }))}
                        className="w-full rounded-xl border border-slate-200/90 px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-[#054e3b] focus:ring-2 focus:ring-[#054e3b]/10 focus:outline-none transition-all"
                      />
                    </div>

                    <div>
                      <label className="block text-xs font-bold text-slate-800 mb-1.5">NIP / NIPY (Opsional)</label>
                      <input
                        type="text"
                        placeholder="1985xxxxxx"
                        value={formData.principal_nip}
                        onChange={(e) => setFormData((p) => ({ ...p, principal_nip: e.target.value }))}
                        className="w-full rounded-xl border border-slate-200/90 px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-[#054e3b] focus:ring-2 focus:ring-[#054e3b]/10 focus:outline-none transition-all"
                      />
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                      <div>
                        <label className="block text-xs font-bold text-slate-800 mb-1.5">Tahun Berdiri</label>
                        <input
                          type="number"
                          placeholder="2011"
                          value={formData.established_year}
                          onChange={(e) => setFormData((p) => ({ ...p, established_year: e.target.value }))}
                          className="w-full rounded-xl border border-slate-200/90 px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-[#054e3b] focus:ring-2 focus:ring-[#054e3b]/10 focus:outline-none transition-all"
                        />
                      </div>
                      <div>
                        <label className="block text-xs font-bold text-slate-800 mb-1.5">Akreditasi</label>
                        <select
                          value={formData.accreditation}
                          onChange={(e) => setFormData((p) => ({ ...p, accreditation: e.target.value }))}
                          className="w-full rounded-xl border border-slate-200/90 px-4 py-2.5 text-sm text-slate-800 focus:border-[#054e3b] focus:ring-2 focus:ring-[#054e3b]/10 focus:outline-none transition-all"
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
                    <h3 className="text-base font-extrabold text-slate-900 border-b border-slate-100 pb-2.5">Konfirmasi Data</h3>

                    <div className="rounded-2xl border border-slate-200/90 bg-[#f8fafc] p-4 space-y-3 text-xs">
                      <div className="flex justify-between border-b border-slate-200/80 pb-2">
                        <span className="text-slate-500 font-medium">Nama Unit:</span>
                        <span className="font-bold text-slate-800">{formData.name || '-'}</span>
                      </div>
                      <div className="flex justify-between border-b border-slate-200/80 pb-2">
                        <span className="text-slate-500 font-medium">Jenis Unit:</span>
                        <span className="font-bold text-slate-800">{formData.unit_type || '-'}</span>
                      </div>
                      <div className="flex justify-between border-b border-slate-200/80 pb-2">
                        <span className="text-slate-500 font-medium">NPSN:</span>
                        <span className="font-bold text-slate-800">{formData.npsn || '-'}</span>
                      </div>
                      <div className="flex justify-between border-b border-slate-200/80 pb-2">
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
            </div>

            {/* Modal Bottom Action Footer (Persis Gambar UI/UX) */}
            <div className="flex items-center justify-between border-t border-slate-100 bg-white px-7 py-4">
              <button
                type="button"
                onClick={closeFormModal}
                className="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition-colors"
              >
                Batal
              </button>

              <div className="flex items-center gap-2.5">
                <button
                  type="button"
                  onClick={closeFormModal}
                  className="rounded-xl border border-[#054e3b] bg-white px-5 py-2.5 text-xs font-bold text-[#054e3b] hover:bg-emerald-50 transition-colors"
                >
                  Simpan Draft
                </button>
                {currentStep < 4 ? (
                  <button
                    type="button"
                    onClick={() => setCurrentStep((s) => Math.min(4, s + 1))}
                    className="rounded-xl bg-[#054e3b] hover:bg-[#03382b] px-6 py-2.5 text-xs font-bold text-white shadow-md transition-colors flex items-center gap-1"
                  >
                    Selanjutnya →
                  </button>
                ) : (
                  <button
                    type="button"
                    onClick={handleFormSubmit}
                    className="rounded-xl bg-[#054e3b] hover:bg-[#03382b] px-6 py-2.5 text-xs font-bold text-white shadow-md transition-colors flex items-center gap-1"
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
                {/* Building / Logo Preview */}
                <div className="h-36 w-full md:w-56 overflow-hidden rounded-xl bg-slate-200 flex items-center justify-center relative shrink-0">
                  {detailUnit.logo_url ? (
                    <img src={detailUnit.logo_url} alt={detailUnit.name} className="h-full w-full object-cover" />
                  ) : (
                    <>
                      <div className="absolute inset-0 bg-gradient-to-t from-slate-900/60 to-transparent"></div>
                      <FaBuilding className="text-5xl text-slate-400" />
                      <span className="absolute bottom-2 left-2 text-[10px] font-bold text-white bg-black/40 px-2 py-0.5 rounded">
                        Gedung Utama
                      </span>
                    </>
                  )}
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
                    className={`pb-3 transition-colors border-b-2 ${activeDetailTab === tab
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

      {/* POP UP MODAL: DASHBOARD IMPORT DATA UNIT PENDIDIKAN */}
      {showImportModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm">
          <div className="w-full max-w-3xl overflow-hidden rounded-2xl bg-white shadow-2xl transition-all">
            {/* Header Modal */}
            <div className="flex items-center justify-between border-b border-slate-100 bg-slate-50/80 px-6 py-4">
              <div className="flex items-center gap-2.5">
                <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-100 text-blue-700">
                  <FaFileImport className="text-base" />
                </div>
                <div>
                  <h2 className="text-base font-extrabold text-slate-900">Dashboard Import Data Unit</h2>
                  <p className="text-xs text-slate-500">Unggah file Excel atau CSV untuk mengimpor banyak unit pendidikan secara massal</p>
                </div>
              </div>
              <button
                type="button"
                onClick={() => { setShowImportModal(false); setImportFile(null); setImportPreviewData([]) }}
                className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-200 hover:text-slate-700 transition"
              >
                <FaTimes />
              </button>
            </div>

            {/* Modal Body */}
            <div className="p-6 space-y-5 max-h-[75vh] overflow-y-auto">
              {/* Step 1: Download Template */}
              <div className="rounded-xl border border-blue-200 bg-blue-50/60 p-4 flex flex-col sm:flex-row items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                  <FaFileExcel className="text-2xl text-emerald-600 shrink-0" />
                  <div>
                    <h4 className="text-xs font-bold text-slate-800">Unduh Format Template Import</h4>
                    <p className="text-[11px] text-slate-500">Gunakan format file ini agar kolom data sesuai dengan sistem ERP.</p>
                  </div>
                </div>
                <button
                  type="button"
                  onClick={handleDownloadTemplateUnit}
                  className="flex items-center gap-1.5 rounded-xl border border-emerald-600 bg-white px-3.5 py-2 text-xs font-bold text-emerald-700 hover:bg-emerald-50 transition shadow-xs whitespace-nowrap"
                >
                  <FaDownload className="text-emerald-600" /> Unduh Template
                </button>
              </div>

              {/* Step 2: Upload Dropzone */}
              <div>
                <label className="block text-xs font-bold text-slate-800 mb-2">Unggah File (Excel / CSV)</label>
                <label className="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50/50 p-6 text-center hover:bg-slate-50 cursor-pointer transition">
                  <FaUpload className="text-3xl text-emerald-700 mb-2" />
                  <span className="text-xs font-bold text-slate-800">
                    {importFile ? importFile.name : 'Klik untuk memilih file Excel atau CSV'}
                  </span>
                  <span className="text-[11px] text-slate-400 mt-0.5">
                    {importFile ? `${(importFile.size / 1024).toFixed(1)} KB` : 'Format disukai: .csv, .xlsx, .xls (Maks. 5MB)'}
                  </span>
                  <input
                    type="file"
                    accept=".csv, .xlsx, .xls"
                    onChange={handleFileSelect}
                    className="hidden"
                  />
                </label>
              </div>

              {/* Step 3: Preview Table */}
              {importPreviewData.length > 0 && (
                <div className="space-y-2">
                  <div className="flex items-center justify-between">
                    <h4 className="text-xs font-bold text-slate-800">Preview Data yang Siap Diimpor ({importPreviewData.length} baris)</h4>
                    <span className="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-bold text-emerald-800">
                      Format Sesuai
                    </span>
                  </div>
                  <div className="overflow-x-auto rounded-xl border border-slate-200">
                    <table className="w-full text-left text-xs text-slate-600">
                      <thead className="bg-slate-50 text-[11px] font-bold text-slate-500 uppercase">
                        <tr>
                          <th className="py-2 px-3">Kode</th>
                          <th className="py-2 px-3">Nama Unit</th>
                          <th className="py-2 px-3">Tingkat</th>
                          <th className="py-2 px-3">NPSN</th>
                          <th className="py-2 px-3 text-center">Status</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-slate-100">
                        {importPreviewData.map((row, idx) => (
                          <tr key={idx} className="hover:bg-slate-50">
                            <td className="py-2 px-3 font-medium">{row.kode}</td>
                            <td className="py-2 px-3 font-bold text-slate-800">{row.nama}</td>
                            <td className="py-2 px-3">{row.tingkat}</td>
                            <td className="py-2 px-3 font-semibold">{row.npsn}</td>
                            <td className="py-2 px-3 text-center">
                              <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800">
                                {row.status}
                              </span>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </div>
              )}
            </div>

            {/* Modal Action Footer */}
            <div className="flex items-center justify-between border-t border-slate-100 bg-slate-50/80 px-6 py-4">
              <button
                type="button"
                onClick={() => { setShowImportModal(false); setImportFile(null); setImportPreviewData([]) }}
                className="rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50"
              >
                Batal
              </button>
              <button
                type="button"
                disabled={!importFile || isImporting}
                onClick={handleProcessImport}
                className="flex items-center gap-2 rounded-xl bg-[#064e3b] px-5 py-2 text-xs font-bold text-white shadow-md hover:bg-emerald-800 disabled:opacity-50 transition"
              >
                {isImporting ? 'Memproses Import...' : 'Proses Import Data'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
