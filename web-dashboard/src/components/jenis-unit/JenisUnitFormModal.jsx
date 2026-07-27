import React, { useState, useEffect } from 'react'
import { FaTimes, FaSave, FaRedo, FaSchool } from 'react-icons/fa'
import { renderJenisUnitIcon } from './JenisUnitTable'

const JENJANG_OPTIONS = [
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

const ICON_OPTIONS = [
  { value: 'Building', label: 'Building (Gedung)' },
  { value: 'School', label: 'School (Sekolah)' },
  { value: 'Book', label: 'Book (Buku / Kitab)' },
  { value: 'Mosque', label: 'Mosque (Masjid)' },
  { value: 'Graduation', label: 'Graduation (Kelulusan)' },
  { value: 'University', label: 'University (Kampus / Mahad)' },
  { value: 'Children', label: 'Children (Anak Usia Dini)' },
  { value: 'Home', label: 'Home (Rumah)' },
]

const COLOR_PRESETS = [
  '#10B981', // Hijau (Default)
  '#06B6D4', // Cyan
  '#3B82F6', // Biru
  '#F59E0B', // Amber
  '#6366F1', // Indigo
  '#8B5CF6', // Purple
  '#059669', // Emerald
  '#D97706', // Warm Amber
]

export default function JenisUnitFormModal({
  isOpen,
  onClose,
  onSubmit,
  initialData = null,
  isSubmitting = false,
}) {
  const [formData, setFormData] = useState({
    kode_jenis: '',
    nama_jenis: '',
    singkatan: '',
    jenjang: 'SD',
    urutan: 1,
    warna_badge: '#10B981',
    icon: 'School',
    status: true,
    keterangan: '',
  })

  const [errors, setErrors] = useState({})

  useEffect(() => {
    if (initialData) {
      setFormData({
        kode_jenis: initialData.kode_jenis || '',
        nama_jenis: initialData.nama_jenis || '',
        singkatan: initialData.singkatan || '',
        jenjang: initialData.jenjang || 'SD',
        urutan: initialData.urutan || 1,
        warna_badge: initialData.warna_badge || '#10B981',
        icon: initialData.icon || 'School',
        status: initialData.status !== undefined ? Boolean(initialData.status) : true,
        keterangan: initialData.keterangan || '',
      })
    } else {
      handleReset()
    }
    setErrors({})
  }, [initialData, isOpen])

  const handleReset = () => {
    setFormData({
      kode_jenis: initialData?.kode_jenis || '',
      nama_jenis: initialData?.nama_jenis || '',
      singkatan: initialData?.singkatan || '',
      jenjang: initialData?.jenjang || 'SD',
      urutan: initialData?.urutan || 1,
      warna_badge: initialData?.warna_badge || '#10B981',
      icon: initialData?.icon || 'School',
      status: initialData?.status !== undefined ? Boolean(initialData.status) : true,
      keterangan: initialData?.keterangan || '',
    })
    setErrors({})
  }

  const validate = () => {
    const errs = {}
    if (!formData.kode_jenis.trim()) {
      errs.kode_jenis = 'Kode wajib diisi'
    }
    if (!formData.nama_jenis.trim()) {
      errs.nama_jenis = 'Nama wajib diisi'
    }
    if (!formData.jenjang) {
      errs.jenjang = 'Jenjang pendidikan wajib dipilih'
    }
    if (formData.urutan === '' || formData.urutan === null || isNaN(formData.urutan)) {
      errs.urutan = 'Urutan harus angka'
    }

    setErrors(errs)
    return Object.keys(errs).length === 0
  }

  const handleSubmit = (e) => {
    e.preventDefault()
    if (!validate()) return

    onSubmit({
      ...formData,
      urutan: parseInt(formData.urutan, 10),
    })
  }

  if (!isOpen) return null

  const isEdit = Boolean(initialData)

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
      <div className="bg-white rounded-2xl shadow-2xl border border-emerald-100 w-full max-w-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
        {/* Header Modal */}
        <div className="bg-gradient-to-r from-emerald-800 to-emerald-700 p-5 text-white flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="p-2 bg-white/10 rounded-lg">
              <FaSchool className="w-5 h-5 text-emerald-200" />
            </div>
            <div>
              <h2 className="text-lg font-bold">
                {isEdit ? 'Edit Jenis Unit Pendidikan' : 'Tambah Jenis Unit Pendidikan'}
              </h2>
              <p className="text-xs text-emerald-100">
                Kelola informasi data jenis unit pendidikan terpadu.
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="p-2 rounded-lg text-emerald-100 hover:bg-white/10 hover:text-white transition-colors"
          >
            <FaTimes className="w-5 h-5" />
          </button>
        </div>

        {/* Form Body */}
        <form onSubmit={handleSubmit} className="p-6 space-y-5 text-sm text-gray-700">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {/* Kode Jenis */}
            <div>
              <label className="block font-semibold text-gray-800 mb-1">
                Kode Jenis <span className="text-rose-500">*</span>
              </label>
              <input
                type="text"
                value={formData.kode_jenis}
                onChange={(e) =>
                  setFormData({ ...formData, kode_jenis: e.target.value.toUpperCase() })
                }
                placeholder="Masukkan Kode Jenis (Contoh: SDIT)"
                className={`w-full px-3.5 py-2 rounded-lg border ${
                  errors.kode_jenis ? 'border-rose-500 bg-rose-50' : 'border-gray-300'
                } focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 font-medium`}
              />
              {errors.kode_jenis && (
                <p className="mt-1 text-xs text-rose-500">{errors.kode_jenis}</p>
              )}
            </div>

            {/* Nama Jenis Unit */}
            <div>
              <label className="block font-semibold text-gray-800 mb-1">
                Nama Jenis Unit <span className="text-rose-500">*</span>
              </label>
              <input
                type="text"
                value={formData.nama_jenis}
                onChange={(e) => setFormData({ ...formData, nama_jenis: e.target.value })}
                placeholder="Contoh: Sekolah Dasar Islam Terpadu"
                className={`w-full px-3.5 py-2 rounded-lg border ${
                  errors.nama_jenis ? 'border-rose-500 bg-rose-50' : 'border-gray-300'
                } focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 font-medium`}
              />
              {errors.nama_jenis && (
                <p className="mt-1 text-xs text-rose-500">{errors.nama_jenis}</p>
              )}
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {/* Singkatan */}
            <div>
              <label className="block font-semibold text-gray-800 mb-1">Singkatan</label>
              <input
                type="text"
                value={formData.singkatan}
                onChange={(e) => setFormData({ ...formData, singkatan: e.target.value })}
                placeholder="Contoh: SDIT"
                className="w-full px-3.5 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
              />
            </div>

            {/* Jenjang Pendidikan */}
            <div>
              <label className="block font-semibold text-gray-800 mb-1">
                Jenjang Pendidikan <span className="text-rose-500">*</span>
              </label>
              <select
                value={formData.jenjang}
                onChange={(e) => setFormData({ ...formData, jenjang: e.target.value })}
                className="w-full px-3.5 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 font-medium bg-white"
              >
                {JENJANG_OPTIONS.map((opt) => (
                  <option key={opt} value={opt}>
                    {opt}
                  </option>
                ))}
              </select>
              {errors.jenjang && <p className="mt-1 text-xs text-rose-500">{errors.jenjang}</p>}
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            {/* Urutan */}
            <div>
              <label className="block font-semibold text-gray-800 mb-1">
                Urutan <span className="text-rose-500">*</span>
              </label>
              <input
                type="number"
                min="1"
                value={formData.urutan}
                onChange={(e) => setFormData({ ...formData, urutan: e.target.value })}
                placeholder="Contoh: 1"
                className={`w-full px-3.5 py-2 rounded-lg border ${
                  errors.urutan ? 'border-rose-500 bg-rose-50' : 'border-gray-300'
                } focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 font-medium`}
              />
              {errors.urutan && <p className="mt-1 text-xs text-rose-500">{errors.urutan}</p>}
            </div>

            {/* Icon */}
            <div>
              <label className="block font-semibold text-gray-800 mb-1">Icon</label>
              <div className="flex items-center gap-2">
                <select
                  value={formData.icon}
                  onChange={(e) => setFormData({ ...formData, icon: e.target.value })}
                  className="w-full px-3.5 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 bg-white"
                >
                  {ICON_OPTIONS.map((ico) => (
                    <option key={ico.value} value={ico.value}>
                      {ico.label}
                    </option>
                  ))}
                </select>
                <div className="p-2 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-lg">
                  {renderJenisUnitIcon(formData.icon, 'w-5 h-5')}
                </div>
              </div>
            </div>

            {/* Status */}
            <div>
              <label className="block font-semibold text-gray-800 mb-1">Status</label>
              <select
                value={formData.status ? 'true' : 'false'}
                onChange={(e) =>
                  setFormData({ ...formData, status: e.target.value === 'true' })
                }
                className="w-full px-3.5 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 font-medium bg-white"
              >
                <option value="true">Aktif</option>
                <option value="false">Tidak Aktif</option>
              </select>
            </div>
          </div>

          {/* Warna Badge */}
          <div>
            <label className="block font-semibold text-gray-800 mb-1">Warna Badge</label>
            <div className="flex flex-wrap items-center gap-3">
              <input
                type="color"
                value={formData.warna_badge}
                onChange={(e) => setFormData({ ...formData, warna_badge: e.target.value })}
                className="w-10 h-10 rounded-lg border border-gray-300 cursor-pointer p-0.5"
              />
              <span className="font-mono text-xs uppercase px-2.5 py-1 bg-gray-100 border border-gray-200 rounded-md">
                {formData.warna_badge}
              </span>
              <div className="flex items-center gap-1.5 ml-2">
                <span className="text-xs text-gray-500 mr-1">Preset:</span>
                {COLOR_PRESETS.map((color) => (
                  <button
                    type="button"
                    key={color}
                    onClick={() => setFormData({ ...formData, warna_badge: color })}
                    className={`w-6 h-6 rounded-full border-2 transition-transform ${
                      formData.warna_badge === color
                        ? 'border-gray-800 scale-110 shadow-xs'
                        : 'border-transparent hover:scale-105'
                    }`}
                    style={{ backgroundColor: color }}
                  />
                ))}
              </div>
            </div>
          </div>

          {/* Keterangan */}
          <div>
            <label className="block font-semibold text-gray-800 mb-1">Keterangan</label>
            <textarea
              rows="3"
              value={formData.keterangan}
              onChange={(e) => setFormData({ ...formData, keterangan: e.target.value })}
              placeholder="Masukkan keterangan atau deskripsi jenis unit..."
              className="w-full px-3.5 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 resize-none"
            ></textarea>
          </div>

          {/* Buttons Footer */}
          <div className="pt-4 border-t border-gray-100 flex items-center justify-between">
            <button
              type="button"
              onClick={handleReset}
              className="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg border border-gray-300 text-gray-600 font-medium hover:bg-gray-50 transition-colors"
            >
              <FaRedo className="w-3.5 h-3.5" />
              Reset
            </button>

            <div className="flex items-center gap-3">
              <button
                type="button"
                onClick={onClose}
                className="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 font-medium hover:bg-gray-50 transition-colors"
              >
                Batal
              </button>
              <button
                type="submit"
                disabled={isSubmitting}
                className="inline-flex items-center gap-2 px-5 py-2 rounded-lg bg-emerald-600 text-white font-semibold hover:bg-emerald-700 focus:ring-2 focus:ring-emerald-500 focus:ring-offset-1 transition-colors shadow-sm disabled:opacity-50"
              >
                <FaSave className="w-4 h-4" />
                {isSubmitting ? 'Menyimpan...' : 'Simpan'}
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  )
}
