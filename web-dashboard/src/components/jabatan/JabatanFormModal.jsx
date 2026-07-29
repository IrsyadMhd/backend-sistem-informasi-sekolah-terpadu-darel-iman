import React, { useState, useEffect } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import * as z from 'zod'
import { FaTimes, FaCheckCircle } from 'react-icons/fa'

// Validation Schema using Zod
const jabatanSchema = z.object({
  kode_jabatan: z.string().optional().or(z.literal('')),
  nama_jabatan: z.string().min(2, { message: 'Nama jabatan minimal 2 karakter' }),
  unit_sekolah_id: z.string().optional().nullable(),
  level_jabatan: z.coerce.number().min(1, { message: 'Pilih level jabatan (1-14)' }).max(14),
  atasan_langsung_id: z.string().optional().nullable(),
  atasan_pegawai_id: z.string().optional().nullable(),
  role_sistem_id: z.string().optional().nullable(),
  urutan: z.coerce.number().min(0, { message: 'Urutan minimal 0' }),
  warna: z.string().min(1, { message: 'Warna wajib dipilih' }),
  ikon: z.string().min(1, { message: 'Ikon wajib dipilih' }),
  deskripsi: z.string().optional().nullable(),
  status: z.enum(['Aktif', 'Nonaktif']),
  tampil_struktur: z.boolean(),
  boleh_login: z.boolean(),
})

const PRESET_WARNA = [
  { hex: '#8B5CF6', label: 'Ungu (Yayasan)' },
  { hex: '#3B82F6', label: 'Biru (Kepala Sekolah)' },
  { hex: '#0284C7', label: 'Biru Muda (Wakil)' },
  { hex: '#0D9488', label: 'Teal (Divisi)' },
  { hex: '#059669', label: 'Hijau Emerald (TU)' },
  { hex: '#10B981', label: 'Hijau (Operator)' },
  { hex: '#D97706', label: 'Amber (Bendahara)' },
  { hex: '#2563EB', label: 'Royal Blue (Guru)' },
  { hex: '#7C3AED', label: 'Violet (Wali Kelas)' },
  { hex: '#DC2626', label: 'Merah (Satpam)' },
  { hex: '#6B7280', label: 'Abu-abu (CS/Umum)' },
]

const PRESET_IKON = [
  { val: 'Crown', label: 'Crown (Mahkota)' },
  { val: 'ShieldCheck', label: 'Shield (Pengurus)' },
  { val: 'UserTie', label: 'UserTie (Kepala/Pimpinan)' },
  { val: 'UserCheck', label: 'UserCheck (Wakil/Wali)' },
  { val: 'Briefcase', label: 'Briefcase (Divisi)' },
  { val: 'Building', label: 'Building (Tata Usaha)' },
  { val: 'Laptop', label: 'Laptop (Operator)' },
  { val: 'Wallet', label: 'Wallet (Bendahara)' },
  { val: 'GraduationCap', label: 'GraduationCap (Guru)' },
  { val: 'Users', label: 'Users (Tim/Wali)' },
  { val: 'BookOpen', label: 'BookOpen (Tahfizh)' },
  { val: 'FileText', label: 'FileText (Staf)' },
  { val: 'Shield', label: 'Shield (Satpam)' },
  { val: 'Broom', label: 'Broom (CS)' },
]

// Fallback statis Level Jabatan — dipakai jika API options belum terpenuhi
const LEVEL_JABATAN_OPTIONS = [
  { value: 1,  label: 'Level 1 - Ketua Yayasan' },
  { value: 2,  label: 'Level 2 - Pengurus Yayasan' },
  { value: 3,  label: 'Level 3 - Kepala Sekolah' },
  { value: 4,  label: 'Level 4 - Wakil Kepala Sekolah' },
  { value: 5,  label: 'Level 5 - Kepala Divisi' },
  { value: 6,  label: 'Level 6 - Kepala Tata Usaha' },
  { value: 7,  label: 'Level 7 - Operator Sekolah' },
  { value: 8,  label: 'Level 8 - Bendahara' },
  { value: 9,  label: 'Level 9 - Guru' },
  { value: 10, label: 'Level 10 - Wali Kelas' },
  { value: 11, label: 'Level 11 - Pembimbing Tahfizh' },
  { value: 12, label: 'Level 12 - Staf Administrasi' },
  { value: 13, label: 'Level 13 - Satpam' },
  { value: 14, label: 'Level 14 - Cleaning Service' },
]

export default function JabatanFormModal({
  isOpen,
  onClose,
  onSubmit,
  initialData = null,
  options = {},
  isSubmitting = false,
}) {
  const isEdit = Boolean(initialData?.id)
  const [currentStep, setCurrentStep] = useState(1)

  const {
    register,
    handleSubmit,
    setValue,
    watch,
    reset,
    trigger,
    formState: { errors },
  } = useForm({
    resolver: zodResolver(jabatanSchema),
    defaultValues: {
      kode_jabatan: '',
      nama_jabatan: '',
      unit_sekolah_id: '',
      level_jabatan: 9,
      atasan_langsung_id: '',
      atasan_pegawai_id: '',
      role_sistem_id: '',
      urutan: 0,
      warna: '#3B82F6',
      ikon: 'UserCheck',
      deskripsi: '',
      status: 'Aktif',
      tampil_struktur: true,
      boleh_login: false,
    },
  })

  const watchWarna = watch('warna')
  const watchNama = watch('nama_jabatan')
  const watchKode = watch('kode_jabatan')
  const watchLevel = watch('level_jabatan')
  const watchDeskripsi = watch('deskripsi')

  useEffect(() => {
    if (isOpen) {
      setCurrentStep(1)
      if (initialData) {
        reset({
          kode_jabatan: initialData.kode_jabatan || initialData.code || '',
          nama_jabatan: initialData.nama_jabatan || initialData.name || '',
          unit_sekolah_id: initialData.unit_sekolah_id || '',
          level_jabatan: initialData.level_jabatan || 9,
          atasan_langsung_id: initialData.atasan_langsung_id || '',
          atasan_pegawai_id: initialData.atasan_pegawai_id || '',
          role_sistem_id: initialData.role_sistem_id ? String(initialData.role_sistem_id) : '',
          urutan: initialData.urutan || 0,
          warna: initialData.warna || '#3B82F6',
          ikon: initialData.ikon || 'UserCheck',
          deskripsi: initialData.deskripsi || initialData.description || '',
          status: initialData.status === 'Aktif' || initialData.is_active ? 'Aktif' : 'Nonaktif',
          tampil_struktur: typeof initialData.tampil_struktur === 'boolean' ? initialData.tampil_struktur : true,
          boleh_login: typeof initialData.boleh_login === 'boolean' ? initialData.boleh_login : false,
        })
      } else {
        reset({
          kode_jabatan: '',
          nama_jabatan: '',
          unit_sekolah_id: '',
          level_jabatan: 9,
          atasan_langsung_id: '',
          atasan_pegawai_id: '',
          role_sistem_id: '',
          urutan: 0,
          warna: '#3B82F6',
          ikon: 'UserCheck',
          deskripsi: '',
          status: 'Aktif',
          tampil_struktur: true,
          boleh_login: false,
        })
      }
    }
  }, [isOpen, initialData, reset])

  if (!isOpen) return null

  const handleNext = async () => {
    if (currentStep === 1) {
      const isValid = await trigger(['nama_jabatan', 'kode_jabatan'])
      if (isValid) setCurrentStep(2)
    } else if (currentStep === 2) {
      const isValid = await trigger(['level_jabatan', 'unit_sekolah_id'])
      if (isValid) setCurrentStep(3)
    } else if (currentStep === 3) {
      setCurrentStep(4)
    }
  }

  const submitHandler = (data) => {
    onSubmit(data)
  }

  return (
    <div className="ui-backdrop fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-900/60 p-4 backdrop-blur-xs" role="dialog" aria-modal="true" aria-labelledby="form-jabatan-title">
      <div className="ui-modal my-6 w-full max-w-4xl overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-2xl">
        {/* Modal Header Bar (Persis Gambar Referensi UI/UX) */}
        <div className="flex items-center justify-between border-b border-slate-100 bg-white px-7 py-5">
          <h2 id="form-jabatan-title" className="text-xl font-black text-slate-800">
            {isEdit ? 'Edit Master Data Jabatan' : 'Tambah Master Data Jabatan'}
          </h2>
          <button
            type="button"
            onClick={onClose}
            className="rounded-full p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors"
            title="Tutup formulir jabatan"
            aria-label="Tutup formulir jabatan"
          >
            <FaTimes className="w-4 h-4" />
          </button>
        </div>

        {/* Modal Main Body Grid */}
        <form onSubmit={handleSubmit(submitHandler)}>
          <div className="grid grid-cols-1 lg:grid-cols-4 min-h-[440px]">
            {/* Left Column: Wizard Stepper Vertikal */}
            <div className="border-r border-slate-100 bg-[#f8fafc] p-7 space-y-7">
              {[
                { step: 1, label: 'Identitas & Kode' },
                { step: 2, label: 'Level & Afiliasi' },
                { step: 3, label: 'Visual & Hak Akses' },
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
                          ? 'bg-[#046c4e] text-white'
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
            <div className="lg:col-span-3 p-7 overflow-y-auto max-h-[520px]">
              {/* STEP 1: Identitas & Kode */}
              {currentStep === 1 && (
                <div className="space-y-5">
                  <h3 className="text-base font-extrabold text-[#0f172a] border-b border-slate-100 pb-2.5">
                    Identitas Jabatan
                  </h3>

                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-xs font-bold text-[#0f172a] mb-1.5">
                        Kode Jabatan <span className="text-slate-400 font-normal">(Auto jika kosong)</span>
                      </label>
                      <input
                        type="text"
                        placeholder="NIY-2026xxxx / JBT-001"
                        {...register('kode_jabatan')}
                        className="w-full rounded-xl border border-slate-200/90 px-4 py-2.5 text-sm text-[#0f172a] placeholder:text-slate-400 focus:border-[#054e3b] focus:ring-2 focus:ring-[#054e3b]/10 focus:outline-none transition-all bg-white"
                      />
                      {errors.kode_jabatan && (
                        <p className="mt-1 text-xs text-rose-500">{errors.kode_jabatan.message}</p>
                      )}
                    </div>

                    <div>
                      <label className="block text-xs font-bold text-[#0f172a] mb-1.5">
                        Nama Jabatan <span className="text-rose-500">*</span>
                      </label>
                      <input
                        type="text"
                        placeholder="Contoh: Kepala Sekolah / Guru Kelas"
                        {...register('nama_jabatan')}
                        className="w-full rounded-xl border border-slate-200/90 px-4 py-2.5 text-sm text-[#0f172a] placeholder:text-slate-400 focus:border-[#054e3b] focus:ring-2 focus:ring-[#054e3b]/10 focus:outline-none transition-all bg-white"
                      />
                      {errors.nama_jabatan && (
                        <p className="mt-1 text-xs text-rose-500">{errors.nama_jabatan.message}</p>
                      )}
                    </div>
                  </div>

                  <div>
                    <label className="block text-xs font-bold text-[#0f172a] mb-1.5">
                      Deskripsi / Tugas Pokok Jabatan
                    </label>
                    <textarea
                      rows="3"
                      placeholder="Penjelasan ringkas peran, wewenang, dan deskripsi pekerjaan..."
                      {...register('deskripsi')}
                      className="w-full rounded-xl border border-slate-200/90 px-4 py-2.5 text-sm text-[#0f172a] placeholder:text-slate-400 focus:border-[#054e3b] focus:ring-2 focus:ring-[#054e3b]/10 focus:outline-none transition-all bg-white"
                    />
                  </div>
                </div>
              )}

              {/* STEP 2: Level & Afiliasi */}
              {currentStep === 2 && (
                <div className="space-y-5">
                  <h3 className="text-base font-extrabold text-[#0f172a] border-b border-slate-100 pb-2.5">
                    Level Hirarki & Afiliasi Unit
                  </h3>

                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-xs font-bold text-[#0f172a] mb-1.5">
                        Level Jabatan <span className="text-rose-500">*</span>
                      </label>
                      <select
                        {...register('level_jabatan')}
                        className="w-full rounded-xl border border-slate-200/90 px-4 py-2.5 text-sm text-[#0f172a] focus:border-[#054e3b] focus:ring-2 focus:ring-[#054e3b]/10 focus:outline-none transition-all bg-white"
                      >
                        <option value="">-- Pilih Level Jabatan --</option>
                        {(options.level_jabatan?.length > 0 ? options.level_jabatan : LEVEL_JABATAN_OPTIONS).map((lvl) => (
                          <option key={lvl.value} value={lvl.value}>
                            {lvl.label}
                          </option>
                        ))}
                      </select>
                      {errors.level_jabatan && (
                        <p className="mt-1 text-xs text-rose-500">{errors.level_jabatan.message}</p>
                      )}
                    </div>

                    <div>
                      <label className="block text-xs font-bold text-[#0f172a] mb-1.5">
                        Unit Sekolah / Yayasan
                        <span className="text-slate-400 font-normal ml-1">(dari Data Unit Pendidikan)</span>
                      </label>
                      <select
                        {...register('unit_sekolah_id')}
                        className="w-full rounded-xl border border-slate-200/90 px-4 py-2.5 text-sm text-[#0f172a] focus:border-[#054e3b] focus:ring-2 focus:ring-[#054e3b]/10 focus:outline-none transition-all bg-white"
                      >
                        <option value="">-- Semua Unit / Yayasan --</option>
                        {(options.unit_sekolah || []).map((unit) => (
                          <option key={unit.id} value={unit.id}>
                            {unit.nama} {unit.kode ? `(${unit.kode})` : ''}
                          </option>
                        ))}
                      </select>
                      {(options.unit_sekolah || []).length === 0 && (
                        <p className="mt-1 text-[11px] font-medium text-amber-700">Belum ada data unit pendidikan. Tambahkan di menu Unit Pendidikan.</p>
                      )}
                    </div>

                    <div>
                      <label className="block text-xs font-bold text-[#0f172a] mb-1.5">
                        Atasan Langsung
                        <span className="text-slate-400 font-normal ml-1">(dari Data Pegawai)</span>
                      </label>
                      <select
                        {...register('atasan_pegawai_id')}
                        className="w-full rounded-xl border border-slate-200/90 px-4 py-2.5 text-sm text-[#0f172a] focus:border-[#054e3b] focus:ring-2 focus:ring-[#054e3b]/10 focus:outline-none transition-all bg-white"
                      >
                        <option value="">-- Tidak Ada / Langsung ke Yayasan --</option>
                        {(options.atasan_langsung || []).map((p) => (
                          <option key={p.id} value={p.id}>
                            {p.nama_pegawai}{p.nama_jabatan ? ` — ${p.nama_jabatan}` : ''}{p.niy ? ` (${p.niy})` : ''}
                          </option>
                        ))}
                      </select>
                      {(options.atasan_langsung || []).length === 0 && (
                        <p className="mt-1 text-[11px] font-medium text-amber-700">Belum ada data pegawai aktif. Tambahkan di menu Kepegawaian.</p>
                      )}
                    </div>

                    <div>
                      <label className="block text-xs font-bold text-[#0f172a] mb-1.5">
                        Role Sistem
                        <span className="text-slate-400 font-normal ml-1">(dari Tabel Hak Akses)</span>
                      </label>
                      <select
                        {...register('role_sistem_id')}
                        className="w-full rounded-xl border border-slate-200/90 px-4 py-2.5 text-sm text-[#0f172a] focus:border-[#054e3b] focus:ring-2 focus:ring-[#054e3b]/10 focus:outline-none transition-all bg-white"
                      >
                        <option value="">-- Tanpa Role / Atur Manual --</option>
                        {(options.role_sistem || []).map((r) => (
                          <option key={r.id} value={r.id}>
                            {r.name}
                          </option>
                        ))}
                      </select>
                      {(options.role_sistem || []).length === 0 && (
                        <p className="mt-1 text-[11px] text-amber-500 font-medium">⚠ Belum ada role terdaftar. Tambahkan di menu Hak Akses.</p>
                      )}
                    </div>
                  </div>
                </div>
              )}

              {/* STEP 3: Visual & Hak Akses */}
              {currentStep === 3 && (
                <div className="space-y-5">
                  <h3 className="text-base font-extrabold text-[#0f172a] border-b border-slate-100 pb-2.5">
                    Visual & Konfigurasi Fitur
                  </h3>

                  <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                      <label className="block text-xs font-bold text-[#0f172a] mb-1.5">Urutan Tampilan</label>
                      <input
                        type="number"
                        min="0"
                        {...register('urutan')}
                        className="w-full rounded-xl border border-slate-200/90 px-4 py-2.5 text-sm text-[#0f172a] focus:border-[#054e3b] focus:ring-2 focus:ring-[#054e3b]/10 focus:outline-none transition-all bg-white"
                      />
                    </div>

                    <div>
                      <label className="block text-xs font-bold text-[#0f172a] mb-1.5">Warna Indikator</label>
                      <div className="flex items-center space-x-2">
                        <input
                          type="color"
                          {...register('warna')}
                          className="w-10 h-10 p-0.5 rounded-xl border border-slate-200 cursor-pointer"
                        />
                        <input
                          type="text"
                          {...register('warna')}
                          placeholder="#3B82F6"
                          className="w-full rounded-xl border border-slate-200/90 px-3 py-2.5 text-sm uppercase text-[#0f172a]"
                        />
                      </div>
                      <div className="flex flex-wrap gap-1.5 mt-2">
                        {PRESET_WARNA.map((p) => (
                          <button
                            key={p.hex}
                            type="button"
                            onClick={() => setValue('warna', p.hex)}
                            className={`w-5 h-5 rounded-full border border-white transition-transform ${
                              watchWarna === p.hex ? 'scale-125 ring-2 ring-[#054e3b]' : 'hover:scale-110'
                            }`}
                            style={{ backgroundColor: p.hex }}
                            title={p.label}
                          />
                        ))}
                      </div>
                    </div>

                    <div>
                      <label className="block text-xs font-bold text-[#0f172a] mb-1.5">Pilihan Ikon</label>
                      <select
                        {...register('ikon')}
                        className="w-full rounded-xl border border-slate-200/90 px-4 py-2.5 text-sm text-[#0f172a] focus:border-[#054e3b] focus:ring-2 focus:ring-[#054e3b]/10 focus:outline-none transition-all bg-white"
                      >
                        {PRESET_IKON.map((i) => (
                          <option key={i.val} value={i.val}>
                            {i.label}
                          </option>
                        ))}
                      </select>
                    </div>
                  </div>

                  <div className="grid grid-cols-1 md:grid-cols-3 gap-4 bg-[#f8fafc] p-4 rounded-2xl border border-slate-200/90">
                    <div>
                      <label className="block text-xs font-bold text-[#0f172a] mb-1.5">Status Operasional</label>
                      <select
                        {...register('status')}
                        className="w-full rounded-xl border border-slate-200/90 px-3 py-2 text-sm text-[#0f172a] bg-white"
                      >
                        <option value="Aktif">Aktif</option>
                        <option value="Nonaktif">Nonaktif</option>
                      </select>
                    </div>

                    <div className="flex items-center justify-between p-2.5 rounded-xl bg-white border border-slate-200/90">
                      <div>
                        <span className="text-xs font-bold text-[#0f172a] block">Tampil Struktur</span>
                        <span className="text-[10px] text-slate-500">Bagan Organisasi</span>
                      </div>
                      <input
                        type="checkbox"
                        {...register('tampil_struktur')}
                        className="w-5 h-5 rounded text-[#054e3b] focus:ring-[#054e3b]"
                      />
                    </div>

                    <div className="flex items-center justify-between p-2.5 rounded-xl bg-white border border-slate-200/90">
                      <div>
                        <span className="text-xs font-bold text-[#0f172a] block">Boleh Login</span>
                        <span className="text-[10px] text-slate-500">Akun Pengguna</span>
                      </div>
                      <input
                        type="checkbox"
                        {...register('boleh_login')}
                        className="w-5 h-5 rounded text-[#054e3b] focus:ring-[#054e3b]"
                      />
                    </div>
                  </div>
                </div>
              )}

              {/* STEP 4: Konfirmasi */}
              {currentStep === 4 && (
                <div className="space-y-4">
                  <h3 className="text-base font-extrabold text-[#0f172a] border-b border-slate-100 pb-2.5">
                    Konfirmasi Data Jabatan
                  </h3>

                  <div className="rounded-2xl border border-slate-200/90 bg-[#f8fafc] p-5 space-y-3 text-xs">
                    <div className="flex justify-between border-b border-slate-200/80 pb-2">
                      <span className="text-slate-500 font-medium">Nama Jabatan:</span>
                      <span className="font-extrabold text-slate-900">{watchNama || '-'}</span>
                    </div>
                    <div className="flex justify-between border-b border-slate-200/80 pb-2">
                      <span className="text-slate-500 font-medium">Kode Jabatan:</span>
                      <span className="font-bold text-slate-800 font-mono">{watchKode || 'Auto-generated'}</span>
                    </div>
                    <div className="flex justify-between border-b border-slate-200/80 pb-2">
                      <span className="text-slate-500 font-medium">Level Jabatan:</span>
                      <span className="font-bold text-slate-800">Level {watchLevel}</span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-slate-500 font-medium">Deskripsi:</span>
                      <span className="font-medium text-slate-700">{watchDeskripsi || '-'}</span>
                    </div>
                  </div>
                </div>
              )}
            </div>
          </div>

          {/* Modal Bottom Action Footer (Persis Gambar UI/UX Referensi) */}
          <div className="flex items-center justify-between border-t border-slate-100 bg-white px-7 py-4">
            <button
              type="button"
              onClick={onClose}
              className="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition-colors"
            >
              Batal
            </button>

            <div className="flex items-center gap-2.5">
              {currentStep < 4 ? (
                <button
                  type="button"
                  onClick={handleNext}
                  className="rounded-xl bg-[#046c4e] hover:bg-[#03543d] px-6 py-2.5 text-xs font-bold text-white shadow-md transition-colors flex items-center gap-1.5"
                >
                  <span>Selanjutnya</span>
                  <span>→</span>
                </button>
              ) : (
                <button
                  type="submit"
                  disabled={isSubmitting}
                  className="rounded-xl bg-[#046c4e] hover:bg-[#03543d] px-6 py-2.5 text-xs font-bold text-white shadow-md disabled:opacity-50 transition-colors flex items-center gap-1.5"
                >
                  <FaCheckCircle className="w-3.5 h-3.5" />
                  <span>{isSubmitting ? 'Menyimpan...' : isEdit ? 'Simpan Perubahan' : 'Simpan Jabatan'}</span>
                </button>
              )}
            </div>
          </div>
        </form>
      </div>
    </div>
  )
}
