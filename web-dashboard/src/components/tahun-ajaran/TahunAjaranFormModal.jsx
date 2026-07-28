import React, { useState, useEffect } from 'react'
import { FaTimes, FaCheckCircle, FaCalendarAlt, FaArrowRight, FaArrowLeft } from 'react-icons/fa'

export default function TahunAjaranFormModal({
  isOpen,
  onClose,
  onSubmit,
  initialData = null,
  isSubmitting = false,
}) {
  const isEdit = Boolean(initialData?.id || initialData?.name)
  const [currentStep, setCurrentStep] = useState(1)

  const [formData, setFormData] = useState({
    name: '',
    start_date: '',
    end_date: '',
    is_active: false,
    keterangan: '',
  })

  const [errors, setErrors] = useState({})

  useEffect(() => {
    if (isOpen) {
      setCurrentStep(1)
      if (initialData) {
        setFormData({
          name: initialData.name || '',
          start_date: initialData.start_date || '',
          end_date: initialData.end_date || '',
          is_active: !!initialData.is_active,
          keterangan: initialData.keterangan || initialData.metadata?.keterangan || '',
        })
      } else {
        const currentYear = new Date().getFullYear()
        setFormData({
          name: `${currentYear}/${currentYear + 1}`,
          start_date: `${currentYear}-07-01`,
          end_date: `${currentYear + 1}-06-30`,
          is_active: false,
          keterangan: '',
        })
      }
      setErrors({})
    }
  }, [initialData, isOpen])

  if (!isOpen) return null

  // Validate step navigation
  const validateStep = (step) => {
    const errs = {}
    if (step === 1) {
      if (!formData.name || !formData.name.trim()) {
        errs.name = 'Nama tahun ajaran wajib diisi.'
      }
    } else if (step === 2) {
      if (!formData.start_date) {
        errs.start_date = 'Tanggal mulai wajib diisi.'
      }
      if (!formData.end_date) {
        errs.end_date = 'Tanggal selesai wajib diisi.'
      }
      if (formData.start_date && formData.end_date && formData.start_date >= formData.end_date) {
        errs.end_date = 'Tanggal selesai harus setelah tanggal mulai.'
      }
    }
    setErrors(errs)
    return Object.keys(errs).length === 0
  }

  const handleNext = () => {
    if (validateStep(currentStep)) {
      setCurrentStep((prev) => Math.min(prev + 1, 4))
    }
  }

  const handlePrev = () => {
    setCurrentStep((prev) => Math.max(prev - 1, 1))
  }

  const handleStepClick = (targetStep) => {
    if (targetStep < currentStep) {
      setCurrentStep(targetStep)
    } else {
      if (validateStep(currentStep)) {
        setCurrentStep(targetStep)
      }
    }
  }

  const handleSubmit = (e) => {
    e.preventDefault()
    if (!validateStep(1) || !validateStep(2)) {
      if (!validateStep(1)) setCurrentStep(1)
      else if (!validateStep(2)) setCurrentStep(2)
      return
    }
    onSubmit(formData)
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm overflow-y-auto animate-fade-in">
      <div className="bg-white rounded-[24px] shadow-2xl border border-slate-100 w-full max-w-4xl overflow-hidden my-6 transition-all">
        {/* Modal Header Bar (Persis Referensi UI/UX) */}
        <div className="flex items-center justify-between border-b border-slate-100 bg-white px-7 py-5">
          <h2 className="text-xl font-black text-[#0f172a]">
            {isEdit ? 'Edit Master Data Tahun Ajaran' : 'Tambah Master Data Tahun Ajaran'}
          </h2>
          <button
            type="button"
            onClick={onClose}
            className="rounded-full p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors"
          >
            <FaTimes className="w-4 h-4" />
          </button>
        </div>

        {/* Modal Main Body Grid */}
        <form onSubmit={handleSubmit}>
          <div className="grid grid-cols-1 lg:grid-cols-4 min-h-[440px]">
            {/* Left Column: Wizard Stepper Vertikal */}
            <div className="border-r border-slate-100 bg-[#f8fafc] p-7 space-y-7">
              {[
                { step: 1, label: 'Identitas & Deskripsi' },
                { step: 2, label: 'Jadwal & Periode' },
                { step: 3, label: 'Status & Fitur' },
                { step: 4, label: 'Konfirmasi' },
              ].map((s) => (
                <div
                  key={s.step}
                  onClick={() => handleStepClick(s.step)}
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
              {/* STEP 1: Identitas & Deskripsi */}
              {currentStep === 1 && (
                <div className="space-y-5">
                  <h3 className="text-base font-extrabold text-[#0f172a] border-b border-slate-100 pb-2.5">
                    Identitas Tahun Ajaran
                  </h3>

                  <div>
                    <label className="block text-xs font-bold text-[#0f172a] mb-1.5">
                      Nama Tahun Ajaran <span className="text-rose-500">*</span>
                    </label>
                    <input
                      type="text"
                      value={formData.name}
                      onChange={(e) => {
                        setFormData({ ...formData, name: e.target.value })
                        if (errors.name) setErrors({ ...errors, name: null })
                      }}
                      placeholder="Contoh: 2025/2026"
                      className={`w-full rounded-xl border px-4 py-2.5 text-sm text-[#0f172a] placeholder:text-slate-400 focus:border-[#054e3b] focus:ring-2 focus:ring-[#054e3b]/10 focus:outline-none transition-all bg-white ${
                        errors.name ? 'border-rose-400 bg-rose-50/20' : 'border-slate-200/90'
                      }`}
                    />
                    {errors.name && (
                      <p className="mt-1 text-xs text-rose-500 font-semibold">{errors.name}</p>
                    )}
                  </div>

                  <div>
                    <label className="block text-xs font-bold text-[#0f172a] mb-1.5">
                      Deskripsi / Tugas Pokok Jabatan & Catatan Periode
                    </label>
                    <textarea
                      rows="4"
                      value={formData.keterangan}
                      onChange={(e) => setFormData({ ...formData, keterangan: e.target.value })}
                      placeholder="Penjelasan ringkas peran, wewenang, dan deskripsi pekerjaan..."
                      className="w-full rounded-xl border border-slate-200/90 px-4 py-2.5 text-sm text-[#0f172a] placeholder:text-slate-400 focus:border-[#054e3b] focus:ring-2 focus:ring-[#054e3b]/10 focus:outline-none transition-all bg-white"
                    />
                  </div>
                </div>
              )}

              {/* STEP 2: Jadwal & Periode */}
              {currentStep === 2 && (
                <div className="space-y-5">
                  <h3 className="text-base font-extrabold text-[#0f172a] border-b border-slate-100 pb-2.5">
                    Jadwal & Periode Kalender
                  </h3>

                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-xs font-bold text-[#0f172a] mb-1.5">
                        Tanggal Mulai <span className="text-rose-500">*</span>
                      </label>
                      <input
                        type="date"
                        value={formData.start_date}
                        onChange={(e) => {
                          setFormData({ ...formData, start_date: e.target.value })
                          if (errors.start_date) setErrors({ ...errors, start_date: null })
                        }}
                        className={`w-full rounded-xl border px-4 py-2.5 text-sm text-[#0f172a] focus:border-[#054e3b] focus:ring-2 focus:ring-[#054e3b]/10 focus:outline-none transition-all bg-white ${
                          errors.start_date ? 'border-rose-400 bg-rose-50/20' : 'border-slate-200/90'
                        }`}
                      />
                      {errors.start_date && (
                        <p className="mt-1 text-xs text-rose-500 font-semibold">{errors.start_date}</p>
                      )}
                    </div>

                    <div>
                      <label className="block text-xs font-bold text-[#0f172a] mb-1.5">
                        Tanggal Selesai <span className="text-rose-500">*</span>
                      </label>
                      <input
                        type="date"
                        value={formData.end_date}
                        onChange={(e) => {
                          setFormData({ ...formData, end_date: e.target.value })
                          if (errors.end_date) setErrors({ ...errors, end_date: null })
                        }}
                        className={`w-full rounded-xl border px-4 py-2.5 text-sm text-[#0f172a] focus:border-[#054e3b] focus:ring-2 focus:ring-[#054e3b]/10 focus:outline-none transition-all bg-white ${
                          errors.end_date ? 'border-rose-400 bg-rose-50/20' : 'border-slate-200/90'
                        }`}
                      />
                      {errors.end_date && (
                        <p className="mt-1 text-xs text-rose-500 font-semibold">{errors.end_date}</p>
                      )}
                    </div>
                  </div>

                  <div className="p-4 rounded-2xl bg-emerald-50/60 border border-emerald-100 flex items-start gap-3">
                    <FaCalendarAlt className="w-5 h-5 text-[#054e3b] mt-0.5" />
                    <div>
                      <h4 className="text-xs font-extrabold text-[#054e3b]">Informasi Durasi Periode</h4>
                      <p className="text-xs text-slate-600 mt-0.5">
                        Periode standar tahun ajaran sekolah berlangsung selama 1 (satu) tahun (12 bulan) dimulai dari bulan Juli hingga bulan Juni tahun berikutnya.
                      </p>
                    </div>
                  </div>
                </div>
              )}

              {/* STEP 3: Status & Fitur */}
              {currentStep === 3 && (
                <div className="space-y-5">
                  <h3 className="text-base font-extrabold text-[#0f172a] border-b border-slate-100 pb-2.5">
                    Status Operasional Periode
                  </h3>

                  <div className="p-5 rounded-2xl bg-white border border-slate-200/90 flex items-center justify-between shadow-xs">
                    <div>
                      <h4 className="text-sm font-extrabold text-[#0f172a]">Jadikan Periode Aktif Utama</h4>
                      <p className="text-xs text-slate-500 mt-1 max-w-md leading-relaxed">
                        Mengaktifkan tahun ajaran ini akan secara otomatis menonaktifkan tahun ajaran aktif sebelumnya di seluruh sistem sekolah.
                      </p>
                    </div>
                    <label className="relative inline-flex items-center cursor-pointer">
                      <input
                        type="checkbox"
                        checked={formData.is_active}
                        onChange={(e) => setFormData({ ...formData, is_active: e.target.checked })}
                        className="sr-only peer"
                      />
                      <div className="w-12 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#054e3b]"></div>
                    </label>
                  </div>
                </div>
              )}

              {/* STEP 4: Konfirmasi */}
              {currentStep === 4 && (
                <div className="space-y-4">
                  <h3 className="text-base font-extrabold text-[#0f172a] border-b border-slate-100 pb-2.5">
                    Konfirmasi Data Tahun Ajaran
                  </h3>

                  <div className="rounded-2xl border border-slate-200/90 bg-[#f8fafc] p-6 space-y-3.5 text-xs">
                    <div className="flex justify-between border-b border-slate-200/80 pb-2.5">
                      <span className="text-slate-500 font-medium">Nama Tahun Ajaran:</span>
                      <span className="font-black text-slate-900 text-sm">{formData.name || '-'}</span>
                    </div>
                    <div className="flex justify-between border-b border-slate-200/80 pb-2.5">
                      <span className="text-slate-500 font-medium">Tanggal Mulai:</span>
                      <span className="font-bold text-slate-800">{formData.start_date || '-'}</span>
                    </div>
                    <div className="flex justify-between border-b border-slate-200/80 pb-2.5">
                      <span className="text-slate-500 font-medium">Tanggal Selesai:</span>
                      <span className="font-bold text-slate-800">{formData.end_date || '-'}</span>
                    </div>
                    <div className="flex justify-between border-b border-slate-200/80 pb-2.5">
                      <span className="text-slate-500 font-medium">Status Aktif Utama:</span>
                      <span
                        className={`font-bold px-2.5 py-0.5 rounded-full text-[11px] ${
                          formData.is_active
                            ? 'bg-emerald-100 text-[#054e3b]'
                            : 'bg-slate-200 text-slate-700'
                        }`}
                      >
                        {formData.is_active ? 'Aktif Utama' : 'Tidak Aktif'}
                      </span>
                    </div>
                    <div className="flex justify-between pt-1">
                      <span className="text-slate-500 font-medium">Deskripsi / Keterangan:</span>
                      <span className="font-medium text-slate-700 max-w-xs text-right">
                        {formData.keterangan || '-'}
                      </span>
                    </div>
                  </div>
                </div>
              )}
            </div>
          </div>

          {/* Modal Bottom Action Footer */}
          <div className="flex items-center justify-between border-t border-slate-100 bg-white px-7 py-4">
            <button
              type="button"
              onClick={onClose}
              className="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition-colors"
            >
              Batal
            </button>

            <div className="flex items-center gap-2.5">
              {currentStep > 1 && (
                <button
                  type="button"
                  onClick={handlePrev}
                  className="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50 transition-colors flex items-center gap-1.5"
                >
                  <FaArrowLeft className="w-3 h-3" />
                  <span>Sebelumnya</span>
                </button>
              )}

              {currentStep < 4 ? (
                <button
                  type="button"
                  onClick={handleNext}
                  className="rounded-xl bg-[#046c4e] hover:bg-[#03543d] px-6 py-2.5 text-xs font-bold text-white shadow-md transition-colors flex items-center gap-1.5"
                >
                  <span>Selanjutnya</span>
                  <FaArrowRight className="w-3 h-3" />
                </button>
              ) : (
                <button
                  type="submit"
                  disabled={isSubmitting}
                  className="rounded-xl bg-[#046c4e] hover:bg-[#03543d] px-6 py-2.5 text-xs font-bold text-white shadow-md disabled:opacity-50 transition-colors flex items-center gap-1.5"
                >
                  <FaCheckCircle className="w-3.5 h-3.5" />
                  <span>{isSubmitting ? 'Menyimpan...' : isEdit ? 'Simpan Perubahan' : 'Simpan Tahun Ajaran'}</span>
                </button>
              )}
            </div>
          </div>
        </form>
      </div>
    </div>
  )
}
