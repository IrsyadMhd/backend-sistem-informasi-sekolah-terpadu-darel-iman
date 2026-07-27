import React, { useState } from 'react'
import { FaTimes, FaFileImport, FaDownload, FaUpload, FaCheckCircle, FaExclamationTriangle } from 'react-icons/fa'

export default function JenisUnitImportModal({ isOpen, onClose, onImport, isSubmitting = false }) {
  const [jsonContent, setJsonContent] = useState('')
  const [parsedData, setParsedData] = useState([])
  const [parseError, setParseError] = useState('')

  if (!isOpen) return null

  const handleFileUpload = (e) => {
    const file = e.target.files[0]
    if (!file) return

    const reader = new FileReader()
    reader.onload = (event) => {
      try {
        const text = event.target.result
        // Check if CSV or JSON
        if (file.name.endsWith('.json')) {
          const parsed = JSON.parse(text)
          if (Array.isArray(parsed)) {
            setParsedData(parsed)
            setParseError('')
          } else {
            setParseError('Format file JSON harus berupa Array of Object.')
          }
        } else if (file.name.endsWith('.csv') || file.name.endsWith('.txt')) {
          const lines = text.split('\n').filter((l) => l.trim() !== '')
          if (lines.length <= 1) {
            setParseError('File CSV kosong atau hanya berisi baris header.')
            return
          }
          const headers = lines[0].split(',').map((h) => h.trim().replace(/^"|"$/g, ''))
          const rows = []

          for (let i = 1; i < lines.length; i++) {
            const cols = lines[i].split(',').map((c) => c.trim().replace(/^"|"$/g, ''))
            if (cols.length >= 2) {
              const item = {}
              headers.forEach((h, idx) => {
                item[h] = cols[idx] || ''
              })
              rows.push(item)
            }
          }
          setParsedData(rows)
          setParseError('')
        } else {
          setParseError('Format file tidak didukung. Harap gunakan CSV atau JSON.')
        }
      } catch (err) {
        setParseError('Gagal membaca file: ' + err.message)
      }
    }
    reader.readAsText(file)
  }

  const handleDownloadTemplate = () => {
    const csvContent =
      'kode_jenis,nama_jenis,singkatan,jenjang,urutan,warna_badge,icon,status,keterangan\n' +
      'SDIT,Sekolah Dasar Islam Terpadu,SDIT,SD,1,#10B981,School,true,Unit SDIT Terpadu\n' +
      'SMPIT,Sekolah Menengah Pertama Islam Terpadu,SMPIT,SMP,2,#6366F1,Graduation,true,Unit SMPIT Terpadu\n'

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.setAttribute('href', url)
    link.setAttribute('download', 'template_import_jenis_unit.csv')
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
  }

  const handleSubmit = (e) => {
    e.preventDefault()
    if (parsedData.length === 0) {
      setParseError('Belum ada data valid yang diunggah.')
      return
    }
    onImport(parsedData)
  }

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
      <div className="bg-white rounded-2xl shadow-2xl border border-emerald-100 w-full max-w-xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
        {/* Header Modal */}
        <div className="bg-gradient-to-r from-emerald-800 to-emerald-700 p-5 text-white flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="p-2 bg-white/10 rounded-lg">
              <FaFileImport className="w-5 h-5 text-emerald-200" />
            </div>
            <div>
              <h2 className="text-lg font-bold">Import Data Jenis Unit Pendidikan</h2>
              <p className="text-xs text-emerald-100">Impor data sekaligus melalui file CSV atau JSON.</p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="p-2 rounded-lg text-emerald-100 hover:bg-white/10 hover:text-white transition-colors"
          >
            <FaTimes className="w-5 h-5" />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="p-6 space-y-5 text-sm text-gray-700">
          {/* Download Template Step */}
          <div className="p-4 bg-emerald-50 rounded-xl border border-emerald-100 flex items-center justify-between">
            <div>
              <h4 className="font-bold text-emerald-900 text-sm">Unduh Template Contoh</h4>
              <p className="text-xs text-emerald-700 mt-0.5">
                Gunakan format CSV standar agar proses impor berjalan lancar.
              </p>
            </div>
            <button
              type="button"
              onClick={handleDownloadTemplate}
              className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-700 text-white font-semibold text-xs hover:bg-emerald-800 transition-colors shadow-xs"
            >
              <FaDownload className="w-3.5 h-3.5" />
              Template CSV
            </button>
          </div>

          {/* Upload Area */}
          <div>
            <label className="block font-semibold text-gray-800 mb-2">Unggah File (CSV / JSON)</label>
            <div className="border-2 border-dashed border-emerald-300 hover:border-emerald-500 bg-emerald-50/40 rounded-xl p-6 text-center transition-colors">
              <FaUpload className="w-8 h-8 mx-auto text-emerald-600 mb-2" />
              <p className="text-sm font-semibold text-gray-700">Klik atau seret file CSV / JSON ke sini</p>
              <p className="text-xs text-gray-500 mt-1">File maks. 5 MB (CSV/JSON)</p>
              <input
                type="file"
                accept=".csv, .json, .txt"
                onChange={handleFileUpload}
                className="mt-3 text-xs text-gray-500 file:mr-4 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-emerald-600 file:text-white hover:file:bg-emerald-700 cursor-pointer"
              />
            </div>
          </div>

          {parseError && (
            <div className="p-3.5 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl text-xs flex items-center gap-2">
              <FaExclamationTriangle className="w-4 h-4 flex-shrink-0" />
              <span>{parseError}</span>
            </div>
          )}

          {parsedData.length > 0 && (
            <div className="p-3.5 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-xs flex items-center justify-between">
              <div className="flex items-center gap-2">
                <FaCheckCircle className="w-4 h-4 text-emerald-600" />
                <span>
                  Berhasil membaca <strong>{parsedData.length}</strong> data siap diimpor.
                </span>
              </div>
            </div>
          )}

          {/* Footer Buttons */}
          <div className="pt-4 border-t border-gray-100 flex items-center justify-end gap-3">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 font-medium hover:bg-gray-50 transition-colors"
            >
              Batal
            </button>
            <button
              type="submit"
              disabled={isSubmitting || parsedData.length === 0}
              className="inline-flex items-center gap-2 px-5 py-2 rounded-lg bg-emerald-700 text-white font-semibold hover:bg-emerald-800 transition-colors shadow-sm disabled:opacity-50"
            >
              <FaFileImport className="w-4 h-4" />
              {isSubmitting ? 'Memproses...' : 'Mulai Import'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}
