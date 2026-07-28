import React, { useState } from 'react'
import { X, Upload, FileSpreadsheet, Download, RefreshCw, AlertCircle } from 'lucide-react'

const SAMPLE_JSON = [
  {
    kode_kurikulum: 'KUR-SD-SIT-2026',
    nama_kurikulum: 'Kurikulum Merdeka SIT SD 2026',
    jenis_kurikulum: 'SIT',
    unit_pendidikan_id: 'UUID_UNIT_PENDIDIKAN',
    jenjang: 'SD',
    tahun_ajaran_id: 'UUID_TAHUN_AJARAN',
    tanggal_mulai: '2026-07-15',
    status: true,
    deskripsi: 'Kurikulum hasil impor massal.',
  },
]

export default function KurikulumImportModal({ isOpen, onClose, onImport, isSubmitting = false }) {
  const [jsonText, setJsonText] = useState('')
  const [errorMsg, setErrorMsg] = useState('')

  if (!isOpen) return null

  const handleDownloadTemplate = () => {
    const jsonStr = JSON.stringify(SAMPLE_JSON, null, 2)
    const blob = new Blob([jsonStr], { type: 'application/json' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = 'template_import_master_kurikulum.json'
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
  }

  const handleSubmit = (e) => {
    e.preventDefault()
    setErrorMsg('')

    if (!jsonText.trim()) {
      setErrorMsg('Payload data JSON tidak boleh kosong.')
      return
    }

    try {
      const parsed = JSON.parse(jsonText)
      if (!Array.isArray(parsed) || parsed.length === 0) {
        setErrorMsg('Format JSON harus berupa Array object yang berisi minimal 1 data.')
        return
      }

      onImport(parsed)
    } catch (err) {
      setErrorMsg('Format JSON tidak valid! Pastikan format JSON sudah benar.')
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto">
      <div className="relative w-full max-w-xl bg-white rounded-3xl shadow-2xl border border-emerald-100 overflow-hidden my-8 animate-in fade-in zoom-in-95 duration-200">
        {/* Header Modal */}
        <div className="bg-gradient-to-r from-emerald-900 via-emerald-800 to-emerald-700 px-6 py-5 text-white flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="p-2.5 rounded-xl bg-white/10 border border-white/20">
              <FileSpreadsheet className="w-6 h-6 text-emerald-300" />
            </div>
            <div>
              <h2 className="text-lg font-bold">Impor Massal Master Kurikulum</h2>
              <p className="text-emerald-100/80 text-xs mt-0.5">
                Upload atau masukkan payload data kurikulum berformat JSON.
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

        {/* Content Body */}
        <form onSubmit={handleSubmit} className="p-6 space-y-4 text-xs text-slate-700">
          <div className="flex items-center justify-between p-3.5 rounded-2xl bg-emerald-50 border border-emerald-100">
            <div>
              <p className="font-bold text-emerald-900">Format Template Impor</p>
              <p className="text-emerald-700 text-[11px] mt-0.5">
                Gunakan format JSON yang sesuai dengan struktur atribut kurikulum.
              </p>
            </div>
            <button
              type="button"
              onClick={handleDownloadTemplate}
              className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-[11px] transition-all shadow-xs"
            >
              <Download className="w-3.5 h-3.5" /> Template
            </button>
          </div>

          <div>
            <label className="block font-bold text-slate-700 mb-1.5">Payload Data (JSON Array)</label>
            <textarea
              rows={8}
              value={jsonText}
              onChange={(e) => setJsonText(e.target.value)}
              placeholder='[\n  {\n    "kode_kurikulum": "KUR-SD-SIT-2026",\n    "nama_kurikulum": "Kurikulum Merdeka SD SIT",\n    "jenis_kurikulum": "SIT",\n    "unit_pendidikan_id": "UUID_UNIT",\n    "jenjang": "SD",\n    "tahun_ajaran_id": "UUID_TAHUN"\n  }\n]'
              className="w-full p-3 rounded-2xl border border-slate-200 bg-slate-50 font-mono text-[11px] focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white transition-all"
            ></textarea>
          </div>

          {errorMsg && (
            <div className="p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 font-semibold flex items-center gap-2">
              <AlertCircle className="w-4 h-4 shrink-0" />
              <span>{errorMsg}</span>
            </div>
          )}

          {/* Action Buttons */}
          <div className="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 font-bold hover:bg-slate-50 transition-colors"
            >
              Batal
            </button>
            <button
              type="submit"
              disabled={isSubmitting}
              className="inline-flex items-center gap-2 px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold transition-all shadow-md disabled:opacity-50"
            >
              {isSubmitting ? (
                <>
                  <RefreshCw className="w-4 h-4 animate-spin" /> Memproses...
                </>
              ) : (
                <>
                  <Upload className="w-4 h-4" /> Proses Impor Data
                </>
              )}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}
