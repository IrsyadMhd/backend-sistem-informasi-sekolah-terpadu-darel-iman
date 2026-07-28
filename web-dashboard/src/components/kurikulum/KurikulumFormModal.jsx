import React, { useState, useEffect } from 'react'
import { X, BookOpen, Save, RefreshCw, AlertCircle } from 'lucide-react'
import { api } from '../../services/api'

const JENIS_OPTIONS = ['SIT', 'Merdeka', 'Nasional', 'Pesantren', 'Lokal', 'Lainnya']
const JENJANG_OPTIONS = ['TK', 'PAUD', 'SD', 'MI', 'SMP', 'MTs', 'SMA', 'MA', 'Pesantren']

export default function KurikulumFormModal({
  isOpen,
  onClose,
  onSubmit,
  initialData = null,
  isSubmitting = false,
}) {
  const [formData, setFormData] = useState({
    kode_kurikulum: '',
    nama_kurikulum: '',
    jenis_kurikulum: 'SIT',
    unit_pendidikan_id: '',
    jenjang: 'SD',
    tahun_ajaran_id: '',
    semester_id: '',
    tanggal_mulai: '',
    tanggal_selesai: '',
    status: true,
    deskripsi: '',
  })

  const [units, setUnits] = useState([])
  const [tahunAjarans, setTahunAjarans] = useState([])
  const [semesters, setSemesters] = useState([])
  const [errors, setErrors] = useState({})

  // Fetch Dropdown options for Units & Tahun Ajaran when modal opens
  useEffect(() => {
    if (isOpen) {
      fetchOptions()
    }
  }, [isOpen])

  // Populate form data on Edit or Reset on Create
  useEffect(() => {
    if (initialData) {
      setFormData({
        kode_kurikulum: initialData.kode_kurikulum || '',
        nama_kurikulum: initialData.nama_kurikulum || '',
        jenis_kurikulum: initialData.jenis_kurikulum || 'SIT',
        unit_pendidikan_id: initialData.unit_pendidikan_id || '',
        jenjang: initialData.jenjang || 'SD',
        tahun_ajaran_id: initialData.tahun_ajaran_id || '',
        semester_id: initialData.semester_id || '',
        tanggal_mulai: initialData.tanggal_mulai || '',
        tanggal_selesai: initialData.tanggal_selesai || '',
        status: initialData.status !== undefined ? initialData.status : true,
        deskripsi: initialData.deskripsi || '',
      })
    } else {
      setFormData({
        kode_kurikulum: `KUR-${new Date().getFullYear()}-${Math.floor(100 + Math.random() * 900)}`,
        nama_kurikulum: '',
        jenis_kurikulum: 'SIT',
        unit_pendidikan_id: units[0]?.id || '',
        jenjang: 'SD',
        tahun_ajaran_id: tahunAjarans[0]?.id || '',
        semester_id: '',
        tanggal_mulai: `${new Date().getFullYear()}-07-15`,
        tanggal_selesai: `${new Date().getFullYear() + 1}-06-30`,
        status: true,
        deskripsi: '',
      })
    }
    setErrors({})
  }, [initialData, isOpen, units, tahunAjarans])

  const fetchOptions = async () => {
    try {
      // Fetch Education Units
      const resUnits = await api.get('/education-units')
      const unitList = resUnits.data?.data || resUnits.data || []
      setUnits(unitList)

      // Fetch Academic Years
      const resTahun = await api.get('/master/tahun-ajaran/dropdown')
      const tahunList = resTahun.data?.data || []
      setTahunAjarans(tahunList)

      if (!initialData && unitList.length > 0) {
        setFormData((prev) => ({ ...prev, unit_pendidikan_id: prev.unit_pendidikan_id || unitList[0].id }))
      }
      if (!initialData && tahunList.length > 0) {
        setFormData((prev) => ({ ...prev, tahun_ajaran_id: prev.tahun_ajaran_id || tahunList[0].id }))
      }
    } catch (err) {
      console.error('Error fetching dropdown options:', err)
    }
  }

  if (!isOpen) return null

  const handleChange = (e) => {
    const { name, value, type, checked } = e.target
    setFormData((prev) => ({
      ...prev,
      [name]: type === 'checkbox' ? checked : value,
    }))
    if (errors[name]) {
      setErrors((prev) => ({ ...prev, [name]: null }))
    }
  }

  const validate = () => {
    const errs = {}
    if (!formData.kode_kurikulum.trim()) errs.kode_kurikulum = 'Kode kurikulum wajib diisi.'
    if (!formData.nama_kurikulum.trim()) errs.nama_kurikulum = 'Nama kurikulum wajib diisi.'
    if (!formData.unit_pendidikan_id) errs.unit_pendidikan_id = 'Unit pendidikan wajib dipilih.'
    if (!formData.tahun_ajaran_id) errs.tahun_ajaran_id = 'Tahun ajaran wajib dipilih.'
    if (!formData.tanggal_mulai) errs.tanggal_mulai = 'Tanggal mulai wajib diisi.'

    setErrors(errs)
    return Object.keys(errs).length === 0
  }

  const handleSubmit = (e) => {
    e.preventDefault()
    if (validate()) {
      onSubmit(formData)
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto">
      <div className="relative w-full max-w-2xl bg-white rounded-3xl shadow-2xl border border-emerald-100 overflow-hidden my-8 animate-in fade-in zoom-in-95 duration-200">
        {/* Header Modal */}
        <div className="bg-gradient-to-r from-emerald-900 via-emerald-800 to-emerald-700 px-6 py-5 text-white flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="p-2.5 rounded-xl bg-white/10 border border-white/20">
              <BookOpen className="w-6 h-6 text-emerald-300" />
            </div>
            <div>
              <h2 className="text-lg font-bold">
                {initialData ? 'Edit Data Master Kurikulum' : 'Tambah Master Kurikulum Baru'}
              </h2>
              <p className="text-emerald-100/80 text-xs mt-0.5">
                {initialData
                  ? 'Perbarui informasi rincian kurikulum yang berlaku.'
                  : 'Lengkapi formulir untuk menambahkan kurikulum unit pendidikan.'}
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="p-2 rounded-xl bg-white/10 hover:bg-white/20 text-white transition-colors"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Body Form */}
        <form onSubmit={handleSubmit} className="p-6 space-y-5 text-xs text-slate-700">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {/* Kode Kurikulum */}
            <div>
              <label className="block font-bold text-slate-700 mb-1.5">
                Kode Kurikulum <span className="text-rose-500">*</span>
              </label>
              <input
                type="text"
                name="kode_kurikulum"
                value={formData.kode_kurikulum}
                onChange={handleChange}
                placeholder="Misal: KUR-SD-SIT-2026"
                className={`w-full px-3.5 py-2.5 rounded-xl border ${
                  errors.kode_kurikulum ? 'border-rose-400 bg-rose-50/30' : 'border-slate-200 bg-slate-50/50'
                } font-mono text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white transition-all`}
              />
              {errors.kode_kurikulum && (
                <p className="text-rose-500 text-[11px] font-semibold mt-1 flex items-center gap-1">
                  <AlertCircle className="w-3 h-3" /> {errors.kode_kurikulum}
                </p>
              )}
            </div>

            {/* Nama Kurikulum */}
            <div>
              <label className="block font-bold text-slate-700 mb-1.5">
                Nama Kurikulum <span className="text-rose-500">*</span>
              </label>
              <input
                type="text"
                name="nama_kurikulum"
                value={formData.nama_kurikulum}
                onChange={handleChange}
                placeholder="Misal: Kurikulum Merdeka Terpadu SD"
                className={`w-full px-3.5 py-2.5 rounded-xl border ${
                  errors.nama_kurikulum ? 'border-rose-400 bg-rose-50/30' : 'border-slate-200 bg-slate-50/50'
                } font-semibold text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white transition-all`}
              />
              {errors.nama_kurikulum && (
                <p className="text-rose-500 text-[11px] font-semibold mt-1 flex items-center gap-1">
                  <AlertCircle className="w-3 h-3" /> {errors.nama_kurikulum}
                </p>
              )}
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            {/* Jenis Kurikulum */}
            <div>
              <label className="block font-bold text-slate-700 mb-1.5">
                Jenis Kurikulum <span className="text-rose-500">*</span>
              </label>
              <select
                name="jenis_kurikulum"
                value={formData.jenis_kurikulum}
                onChange={handleChange}
                className="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50/50 font-semibold focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white transition-all"
              >
                {JENIS_OPTIONS.map((j) => (
                  <option key={j} value={j}>
                    {j}
                  </option>
                ))}
              </select>
            </div>

            {/* Jenjang */}
            <div>
              <label className="block font-bold text-slate-700 mb-1.5">
                Jenjang <span className="text-rose-500">*</span>
              </label>
              <select
                name="jenjang"
                value={formData.jenjang}
                onChange={handleChange}
                className="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50/50 font-semibold focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white transition-all"
              >
                {JENJANG_OPTIONS.map((j) => (
                  <option key={j} value={j}>
                    {j}
                  </option>
                ))}
              </select>
            </div>

            {/* Status */}
            <div>
              <label className="block font-bold text-slate-700 mb-1.5">Status Berlaku</label>
              <div className="flex items-center gap-3 pt-2">
                <label className="relative inline-flex items-center cursor-pointer">
                  <input
                    type="checkbox"
                    name="status"
                    checked={formData.status}
                    onChange={handleChange}
                    className="sr-only peer"
                  />
                  <div className="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                  <span className="ml-2 font-bold text-slate-800">
                    {formData.status ? 'Aktif' : 'Nonaktif'}
                  </span>
                </label>
              </div>
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {/* Unit Pendidikan */}
            <div>
              <label className="block font-bold text-slate-700 mb-1.5">
                Unit Pendidikan <span className="text-rose-500">*</span>
              </label>
              <select
                name="unit_pendidikan_id"
                value={formData.unit_pendidikan_id}
                onChange={handleChange}
                className={`w-full px-3.5 py-2.5 rounded-xl border ${
                  errors.unit_pendidikan_id ? 'border-rose-400 bg-rose-50/30' : 'border-slate-200 bg-slate-50/50'
                } font-semibold focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white transition-all`}
              >
                <option value="">-- Pilih Unit Pendidikan --</option>
                {units.map((u) => (
                  <option key={u.id} value={u.id}>
                    {u.name} ({u.code})
                  </option>
                ))}
              </select>
              {errors.unit_pendidikan_id && (
                <p className="text-rose-500 text-[11px] font-semibold mt-1 flex items-center gap-1">
                  <AlertCircle className="w-3 h-3" /> {errors.unit_pendidikan_id}
                </p>
              )}
            </div>

            {/* Tahun Ajaran */}
            <div>
              <label className="block font-bold text-slate-700 mb-1.5">
                Tahun Ajaran <span className="text-rose-500">*</span>
              </label>
              <select
                name="tahun_ajaran_id"
                value={formData.tahun_ajaran_id}
                onChange={handleChange}
                className={`w-full px-3.5 py-2.5 rounded-xl border ${
                  errors.tahun_ajaran_id ? 'border-rose-400 bg-rose-50/30' : 'border-slate-200 bg-slate-50/50'
                } font-semibold focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white transition-all`}
              >
                <option value="">-- Pilih Tahun Ajaran --</option>
                {tahunAjarans.map((t) => (
                  <option key={t.id} value={t.id}>
                    {t.nama || t.name} {t.is_active ? '(Aktif)' : ''}
                  </option>
                ))}
              </select>
              {errors.tahun_ajaran_id && (
                <p className="text-rose-500 text-[11px] font-semibold mt-1 flex items-center gap-1">
                  <AlertCircle className="w-3 h-3" /> {errors.tahun_ajaran_id}
                </p>
              )}
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {/* Tanggal Mulai */}
            <div>
              <label className="block font-bold text-slate-700 mb-1.5">
                Tanggal Mulai Berlaku <span className="text-rose-500">*</span>
              </label>
              <input
                type="date"
                name="tanggal_mulai"
                value={formData.tanggal_mulai}
                onChange={handleChange}
                className={`w-full px-3.5 py-2.5 rounded-xl border ${
                  errors.tanggal_mulai ? 'border-rose-400 bg-rose-50/30' : 'border-slate-200 bg-slate-50/50'
                } font-semibold focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white transition-all`}
              />
              {errors.tanggal_mulai && (
                <p className="text-rose-500 text-[11px] font-semibold mt-1 flex items-center gap-1">
                  <AlertCircle className="w-3 h-3" /> {errors.tanggal_mulai}
                </p>
              )}
            </div>

            {/* Tanggal Selesai */}
            <div>
              <label className="block font-bold text-slate-700 mb-1.5">Tanggal Selesai (Opsional)</label>
              <input
                type="date"
                name="tanggal_selesai"
                value={formData.tanggal_selesai}
                onChange={handleChange}
                className="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50/50 font-semibold focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white transition-all"
              />
            </div>
          </div>

          {/* Deskripsi */}
          <div>
            <label className="block font-bold text-slate-700 mb-1.5">Deskripsi / Catatan Kurikulum</label>
            <textarea
              name="deskripsi"
              rows={3}
              value={formData.deskripsi}
              onChange={handleChange}
              placeholder="Catatan tambahan mengenai struktur kurikulum..."
              className="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50/50 font-medium focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white transition-all"
            ></textarea>
          </div>

          {/* Action Buttons */}
          <div className="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 font-bold hover:bg-slate-50 transition-colors"
            >
              Batal
            </button>
            <button
              type="submit"
              disabled={isSubmitting}
              className="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold transition-all shadow-md disabled:opacity-50"
            >
              {isSubmitting ? (
                <>
                  <RefreshCw className="w-4 h-4 animate-spin" /> Menyimpan...
                </>
              ) : (
                <>
                  <Save className="w-4 h-4" /> Simpan Data
                </>
              )}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}
