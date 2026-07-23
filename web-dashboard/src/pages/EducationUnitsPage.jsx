import { useMemo, useState } from 'react'
import Swal from 'sweetalert2'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  FaEdit,
  FaEye,
  FaFileExport,
  FaPlus,
  FaSearch,
  FaTrash,
  FaUpload,
} from 'react-icons/fa'
import { educationUnitService } from '../services/educationUnitService'

const UNIT_TYPES = ['TKIT', 'TAUD', 'SDIT', 'MIT', 'SMPIT', 'SMAIT', 'PONPES', 'Mahad']
const STEP_TITLES = ['Informasi Unit', 'Alamat', 'Kepala Sekolah', 'Konfirmasi']

const UNIT_COLORS = {
  TKIT: {
    chip: 'bg-lime-100 text-lime-700 border-lime-200',
    dot: 'bg-lime-500',
  },
  TAUD: {
    chip: 'bg-violet-100 text-violet-700 border-violet-200',
    dot: 'bg-violet-500',
  },
  SDIT: {
    chip: 'bg-blue-100 text-blue-700 border-blue-200',
    dot: 'bg-blue-500',
  },
  MIT: {
    chip: 'bg-amber-100 text-amber-700 border-amber-200',
    dot: 'bg-amber-500',
  },
  SMPIT: {
    chip: 'bg-cyan-100 text-cyan-700 border-cyan-200',
    dot: 'bg-cyan-500',
  },
  SMAIT: {
    chip: 'bg-purple-100 text-purple-700 border-purple-200',
    dot: 'bg-purple-500',
  },
  PONPES: {
    chip: 'bg-emerald-100 text-emerald-700 border-emerald-200',
    dot: 'bg-emerald-700',
  },
  Mahad: {
    chip: 'bg-amber-100 text-amber-900 border-amber-300',
    dot: 'bg-amber-800',
  },
}

function getUnitColor(type) {
  return UNIT_COLORS[type] || {
    chip: 'bg-slate-100 text-slate-700 border-slate-200',
    dot: 'bg-slate-500',
  }
}

function initialFormState() {
  return {
    id: null,
    code: '',
    name: '',
    unit_type: '',
    address: '',
    city: '',
    province: '',
    principal_name: '',
    phone: '',
    email: '',
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
    address: meta.address || '',
    city: meta.city || '',
    province: meta.province || '',
    principal_name: meta.principal_name || '',
    phone: meta.phone || '',
    email: meta.email || '',
    logo_url: meta.logo_url || '',
    is_active: !!item?.is_active,
    description: item?.description || '',
  }
}

function makePayload(form) {
  return {
    code: form.code,
    name: form.name,
    level: form.unit_type || null,
    description: form.description || null,
    is_active: !!form.is_active,
    metadata: {
      address: form.address || null,
      city: form.city || null,
      province: form.province || null,
      principal_name: form.principal_name || null,
      phone: form.phone || null,
      email: form.email || null,
      logo_url: form.logo_url || null,
    },
  }
}

function StatusBadge({ active }) {
  return (
    <span className={`inline-flex items-center rounded-full px-3 py-1 text-xs font-bold ${active ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'}`}>
      {active ? 'Aktif' : 'Nonaktif'}
    </span>
  )
}

export default function EducationUnitsPage() {
  const queryClient = useQueryClient()

  const [searchInput, setSearchInput] = useState('')
  const [searchApplied, setSearchApplied] = useState('')

  const [filterType, setFilterType] = useState('')
  const [filterCity, setFilterCity] = useState('')
  const [filterProvince, setFilterProvince] = useState('')
  const [filterStatus, setFilterStatus] = useState('aktif')

  const [form, setForm] = useState(initialFormState())
  const [step, setStep] = useState(0)
  const [isEdit, setIsEdit] = useState(false)
  const [selectedDetail, setSelectedDetail] = useState(null)

  const { data, isLoading } = useQuery({
    queryKey: ['education-units', { search: searchApplied }],
    queryFn: () => educationUnitService.getDaftar({ search: searchApplied, per_page: 200 }),
  })

  const tambah = useMutation({
    mutationFn: educationUnitService.tambah,
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['education-units'] })
      await Swal.fire('Berhasil', 'Unit pendidikan berhasil ditambahkan.', 'success')
    },
  })

  const ubah = useMutation({
    mutationFn: educationUnitService.ubah,
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['education-units'] })
      await Swal.fire('Berhasil', 'Unit pendidikan berhasil diperbarui.', 'success')
    },
  })

  const hapus = useMutation({
    mutationFn: educationUnitService.hapus,
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['education-units'] })
      await Swal.fire('Berhasil', 'Unit pendidikan berhasil dihapus.', 'success')
    },
  })

  const rows = useMemo(() => {
    const raw = data?.data || []
    return raw.map((item, idx) => {
      const parsed = parseFromApi(item)
      return {
        no: idx + 1,
        id: item.id,
        code: item.code || '-',
        name: item.name || '-',
        unit_type: parsed.unit_type || '-',
        address: parsed.address || '-',
        city: parsed.city || '-',
        province: parsed.province || '-',
        principal_name: parsed.principal_name || '-',
        phone: parsed.phone || '-',
        email: parsed.email || '-',
        logo_url: parsed.logo_url || '',
        is_active: !!item.is_active,
        raw: item,
      }
    })
  }, [data])

  const cities = useMemo(() => Array.from(new Set(rows.map((r) => r.city).filter(Boolean).filter((v) => v !== '-'))), [rows])
  const provinces = useMemo(() => Array.from(new Set(rows.map((r) => r.province).filter(Boolean).filter((v) => v !== '-'))), [rows])

  const filteredRows = useMemo(() => {
    return rows.filter((row) => {
      if (filterType && row.unit_type !== filterType) return false
      if (filterCity && row.city !== filterCity) return false
      if (filterProvince && row.province !== filterProvince) return false
      if (filterStatus === 'aktif' && !row.is_active) return false
      if (filterStatus === 'nonaktif' && row.is_active) return false
      return true
    })
  }, [rows, filterType, filterCity, filterProvince, filterStatus])

  const applySearch = () => setSearchApplied(searchInput.trim())

  const resetFilters = () => {
    setSearchInput('')
    setSearchApplied('')
    setFilterType('')
    setFilterCity('')
    setFilterProvince('')
    setFilterStatus('aktif')
  }

  const openCreate = () => {
    setIsEdit(false)
    setForm(initialFormState())
    setStep(0)
    setSelectedDetail(null)
  }

  const openEdit = (row) => {
    setIsEdit(true)
    setForm(parseFromApi(row.raw))
    setStep(0)
    setSelectedDetail(null)
  }

  const openDetail = (row) => {
    setSelectedDetail(row)
    setIsEdit(false)
  }

  const onLogoChange = (file) => {
    if (!file) return
    const reader = new FileReader()
    reader.onload = () => {
      setForm((prev) => ({ ...prev, logo_url: String(reader.result || '') }))
    }
    reader.readAsDataURL(file)
  }

  const submitForm = async (e) => {
    e.preventDefault()
    const payload = makePayload(form)

    if (isEdit && form.id) {
      await ubah.mutateAsync({ id: form.id, payload })
    } else {
      await tambah.mutateAsync(payload)
    }

    setForm(initialFormState())
    setStep(0)
    setIsEdit(false)
  }

  const removeData = async (row) => {
    const confirm = await Swal.fire({
      title: 'Hapus Unit Pendidikan?',
      text: `${row.name} akan dihapus permanen.`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Hapus',
      cancelButtonText: 'Batal',
    })
    if (!confirm.isConfirmed) return
    await hapus.mutateAsync(row.id)
  }

  return (
    <section className="space-y-4">
      <header className="rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
        <p className="text-xs font-semibold uppercase tracking-wide text-emerald-700">Master Data</p>
        <h1 className="text-3xl font-extrabold text-slate-900">Unit Pendidikan</h1>
        <p className="text-sm text-slate-500">Kelola seluruh unit pendidikan di lingkungan Dar el-Iman.</p>
      </header>

      <div className="grid gap-4 xl:grid-cols-[1.95fr_1fr]">
        <article className="space-y-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
          <div className="grid gap-2 lg:grid-cols-6">
            <div className="relative lg:col-span-2">
              <FaSearch className="absolute left-3 top-3 text-slate-400" />
              <input
                value={searchInput}
                onChange={(e) => setSearchInput(e.target.value)}
                placeholder="Cari unit pendidikan..."
                className="w-full rounded-lg border border-slate-300 py-2.5 pl-9 pr-3 text-sm outline-none focus:border-emerald-500"
              />
            </div>
            <select value={filterType} onChange={(e) => setFilterType(e.target.value)} className="rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
              <option value="">Semua Jenis Unit</option>
              {UNIT_TYPES.map((t) => <option key={t} value={t}>{t}</option>)}
            </select>
            <select value={filterCity} onChange={(e) => setFilterCity(e.target.value)} className="rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
              <option value="">Semua Kota</option>
              {cities.map((c) => <option key={c} value={c}>{c}</option>)}
            </select>
            <select value={filterProvince} onChange={(e) => setFilterProvince(e.target.value)} className="rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
              <option value="">Semua Provinsi</option>
              {provinces.map((p) => <option key={p} value={p}>{p}</option>)}
            </select>
            <select value={filterStatus} onChange={(e) => setFilterStatus(e.target.value)} className="rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
              <option value="">Semua Status</option>
              <option value="aktif">Status Aktif</option>
              <option value="nonaktif">Status Nonaktif</option>
            </select>
          </div>

          <div className="flex flex-wrap items-center justify-between gap-2">
            <div className="flex gap-2">
              <button onClick={applySearch} className="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700">
                <FaSearch className="mr-2 inline" /> Cari
              </button>
              <button onClick={resetFilters} className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700">
                Reset
              </button>
            </div>
            <div className="flex gap-2">
              <button className="rounded-lg border border-emerald-200 bg-white px-3 py-2 text-sm font-semibold text-emerald-700">
                <FaFileExport className="mr-2 inline" /> Export Excel
              </button>
              <button onClick={openCreate} className="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-bold text-white">
                <FaPlus className="mr-2 inline" /> Tambah Unit Pendidikan
              </button>
            </div>
          </div>

          <div className="overflow-hidden rounded-xl border border-slate-200">
            <div className="overflow-x-auto">
              <table className="min-w-[1200px] text-sm">
                <thead className="bg-slate-50 text-xs font-bold uppercase text-slate-600">
                  <tr>
                    <th className="px-3 py-3 text-left">No</th>
                    <th className="px-3 py-3 text-left">Logo</th>
                    <th className="px-3 py-3 text-left">Nama Unit Pendidikan</th>
                    <th className="px-3 py-3 text-left">Jenis Unit</th>
                    <th className="px-3 py-3 text-left">Alamat</th>
                    <th className="px-3 py-3 text-left">Kota / Provinsi</th>
                    <th className="px-3 py-3 text-left">Kepala Sekolah / Pimpinan</th>
                    <th className="px-3 py-3 text-left">Status</th>
                    <th className="px-3 py-3 text-left">Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  {!isLoading && filteredRows.map((row) => {
                    const color = getUnitColor(row.unit_type)
                    return (
                      <tr key={row.id} className="border-t border-slate-100 hover:bg-slate-50/80">
                        <td className="px-3 py-3">{row.no}</td>
                        <td className="px-3 py-3">
                          {row.logo_url ? (
                            <img src={row.logo_url} alt={row.name} className="h-9 w-9 rounded-full border border-slate-200 object-cover" />
                          ) : (
                            <div className={`grid h-9 w-9 place-content-center rounded-full border text-[10px] font-extrabold ${color.chip}`}>
                              {String(row.unit_type || 'UP').slice(0, 4)}
                            </div>
                          )}
                        </td>
                        <td className="px-3 py-3 font-semibold text-slate-800">{row.name}</td>
                        <td className="px-3 py-3">
                          <span className={`inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-bold ${color.chip}`}>
                            <span className={`h-2 w-2 rounded-full ${color.dot}`} />
                            {row.unit_type}
                          </span>
                        </td>
                        <td className="px-3 py-3 text-slate-600">{row.address}</td>
                        <td className="px-3 py-3 text-slate-600">{row.city}, {row.province}</td>
                        <td className="px-3 py-3 text-slate-700">{row.principal_name}</td>
                        <td className="px-3 py-3"><StatusBadge active={row.is_active} /></td>
                        <td className="px-3 py-3">
                          <div className="flex gap-1">
                            <button onClick={() => openDetail(row)} className="rounded-md border border-sky-200 bg-sky-50 px-2 py-1 text-sky-700"><FaEye /></button>
                            <button onClick={() => openEdit(row)} className="rounded-md border border-amber-200 bg-amber-50 px-2 py-1 text-amber-700"><FaEdit /></button>
                            <button onClick={() => removeData(row)} className="rounded-md border border-rose-200 bg-rose-50 px-2 py-1 text-rose-700"><FaTrash /></button>
                          </div>
                        </td>
                      </tr>
                    )
                  })}
                  {isLoading && (
                    <tr>
                      <td className="px-3 py-8 text-center text-slate-500" colSpan={9}>Memuat data unit pendidikan...</td>
                    </tr>
                  )}
                  {!isLoading && filteredRows.length === 0 && (
                    <tr>
                      <td className="px-3 py-8 text-center text-slate-500" colSpan={9}>Tidak ada data unit pendidikan.</td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </article>

        <article className="rounded-2xl border border-emerald-200 bg-white shadow-sm">
          <header className="rounded-t-2xl bg-emerald-700 px-4 py-3 text-center text-sm font-bold uppercase tracking-wide text-white">
            {isEdit ? 'Edit Unit Pendidikan' : selectedDetail ? 'Detail Unit Pendidikan' : 'Tambah Unit Pendidikan'}
          </header>

          {selectedDetail ? (
            <div className="space-y-4 p-4">
              <div className="rounded-xl border border-slate-200 p-4">
                <div className="mb-3 flex items-center gap-3">
                  {selectedDetail.logo_url ? (
                    <img src={selectedDetail.logo_url} alt={selectedDetail.name} className="h-14 w-14 rounded-xl object-cover" />
                  ) : (
                    <div className={`grid h-14 w-14 place-content-center rounded-xl border text-sm font-extrabold ${getUnitColor(selectedDetail.unit_type).chip}`}>
                      {selectedDetail.unit_type}
                    </div>
                  )}
                  <div>
                    <h3 className="text-lg font-bold text-slate-900">{selectedDetail.name}</h3>
                    <p className="text-xs text-slate-500">{selectedDetail.unit_type}</p>
                  </div>
                </div>
                <div className="grid gap-2 text-sm text-slate-700">
                  <p><span className="font-semibold">Alamat:</span> {selectedDetail.address}</p>
                  <p><span className="font-semibold">Kota/Provinsi:</span> {selectedDetail.city}, {selectedDetail.province}</p>
                  <p><span className="font-semibold">Pimpinan:</span> {selectedDetail.principal_name}</p>
                  <p><span className="font-semibold">Telepon:</span> {selectedDetail.phone}</p>
                  <p><span className="font-semibold">Email:</span> {selectedDetail.email}</p>
                </div>
              </div>
              <button onClick={() => setSelectedDetail(null)} className="w-full rounded-lg border border-slate-300 bg-white py-2 font-semibold text-slate-700">
                Tutup Detail
              </button>
            </div>
          ) : (
            <form onSubmit={submitForm} className="space-y-4 p-4">
              <div className="flex items-start gap-3">
                {STEP_TITLES.map((title, idx) => (
                  <button
                    key={title}
                    type="button"
                    onClick={() => setStep(idx)}
                    className={`rounded-full border px-3 py-1 text-xs font-bold ${step === idx ? 'border-emerald-500 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-white text-slate-500'}`}
                  >
                    {idx + 1}. {title}
                  </button>
                ))}
              </div>

              {step === 0 && (
                <div className="space-y-3 rounded-xl border border-slate-200 p-3">
                  <label className="block text-sm font-semibold text-slate-700">Upload logo sekolah</label>
                  <label className="flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-dashed border-slate-300 bg-slate-50 px-3 py-4 text-sm text-slate-600">
                    <FaUpload /> Pilih Logo
                    <input type="file" className="hidden" accept="image/*" onChange={(e) => onLogoChange(e.target.files?.[0])} />
                  </label>
                  {form.logo_url ? <img src={form.logo_url} alt="Preview logo" className="h-16 w-16 rounded-lg border object-cover" /> : null}

                  <div className="grid gap-3">
                    <div>
                      <label className="mb-1 block text-sm font-semibold text-slate-700">Nama unit pendidikan</label>
                      <input value={form.name} onChange={(e) => setForm((p) => ({ ...p, name: e.target.value }))} required className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                      <label className="mb-1 block text-sm font-semibold text-slate-700">Kode Unit</label>
                      <input value={form.code} onChange={(e) => setForm((p) => ({ ...p, code: e.target.value }))} required className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                      <label className="mb-1 block text-sm font-semibold text-slate-700">Jenis unit</label>
                      <select value={form.unit_type} onChange={(e) => setForm((p) => ({ ...p, unit_type: e.target.value }))} required className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Pilih Jenis Unit</option>
                        {UNIT_TYPES.map((item) => <option key={item} value={item}>{item}</option>)}
                      </select>
                    </div>
                  </div>
                </div>
              )}

              {step === 1 && (
                <div className="space-y-3 rounded-xl border border-slate-200 p-3">
                  <div>
                    <label className="mb-1 block text-sm font-semibold text-slate-700">Alamat lengkap</label>
                    <textarea value={form.address} onChange={(e) => setForm((p) => ({ ...p, address: e.target.value }))} rows={3} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                  </div>
                  <div>
                    <label className="mb-1 block text-sm font-semibold text-slate-700">Kota / Kabupaten</label>
                    <input value={form.city} onChange={(e) => setForm((p) => ({ ...p, city: e.target.value }))} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                  </div>
                  <div>
                    <label className="mb-1 block text-sm font-semibold text-slate-700">Provinsi</label>
                    <input value={form.province} onChange={(e) => setForm((p) => ({ ...p, province: e.target.value }))} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                  </div>
                </div>
              )}

              {step === 2 && (
                <div className="space-y-3 rounded-xl border border-slate-200 p-3">
                  <div>
                    <label className="mb-1 block text-sm font-semibold text-slate-700">Kepala sekolah / pimpinan</label>
                    <input value={form.principal_name} onChange={(e) => setForm((p) => ({ ...p, principal_name: e.target.value }))} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                  </div>
                  <div>
                    <label className="mb-1 block text-sm font-semibold text-slate-700">Nomor telepon</label>
                    <input value={form.phone} onChange={(e) => setForm((p) => ({ ...p, phone: e.target.value }))} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                  </div>
                  <div>
                    <label className="mb-1 block text-sm font-semibold text-slate-700">Email</label>
                    <input type="email" value={form.email} onChange={(e) => setForm((p) => ({ ...p, email: e.target.value }))} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                  </div>
                </div>
              )}

              {step === 3 && (
                <div className="space-y-3 rounded-xl border border-slate-200 p-3 text-sm text-slate-700">
                  <p><span className="font-semibold">Nama Unit:</span> {form.name || '-'}</p>
                  <p><span className="font-semibold">Jenis:</span> {form.unit_type || '-'}</p>
                  <p><span className="font-semibold">Alamat:</span> {form.address || '-'}</p>
                  <p><span className="font-semibold">Kota/Provinsi:</span> {form.city || '-'} / {form.province || '-'}</p>
                  <p><span className="font-semibold">Pimpinan:</span> {form.principal_name || '-'}</p>
                </div>
              )}

              <div className="flex gap-2">
                <button type="button" onClick={() => setStep((prev) => Math.max(prev - 1, 0))} className="w-full rounded-lg border border-slate-300 bg-white py-2 font-semibold text-slate-700">
                  Batal
                </button>
                {step < 3 ? (
                  <button type="button" onClick={() => setStep((prev) => Math.min(prev + 1, 3))} className="w-full rounded-lg bg-emerald-700 py-2 font-bold text-white">
                    Selanjutnya
                  </button>
                ) : (
                  <button type="submit" className="w-full rounded-lg bg-emerald-700 py-2 font-bold text-white">
                    {isEdit ? 'Simpan Perubahan' : 'Simpan Unit'}
                  </button>
                )}
              </div>
            </form>
          )}
        </article>
      </div>
    </section>
  )
}
