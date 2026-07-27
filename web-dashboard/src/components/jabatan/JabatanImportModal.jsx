import React, { useState } from 'react'
import { FaTimes, FaFileImport, FaDownload, FaUpload, FaCheckCircle, FaExclamationCircle } from 'react-icons/fa'

export default function JabatanImportModal({ isOpen, onClose, onImport, isSubmitting = false }) {
  const [file, setFile] = useState(null)
  const [parsedData, setParsedData] = useState([])
  const [parseError, setParseError] = useState('')

  if (!isOpen) return null

  const handleFileChange = (e) => {
    const selected = e.target.files[0]
    setFile(selected)
    setParseError('')
    setParsedData([])

    if (!selected) return

    const reader = new FileReader()
    reader.onload = (event) => {
      try {
        const text = event.target.result
        if (selected.name.endsWith('.json')) {
          const json = JSON.parse(text)
          if (Array.isArray(json)) {
            setParsedData(json)
          } else if (json.data && Array.isArray(json.data)) {
            setParsedData(json.data)
          } else {
            setParseError('Format file JSON harus berupa array objek data jabatan.')
          }
        } else if (selected.name.endsWith('.csv') || selected.name.endsWith('.txt')) {
          const lines = text.split('\n').filter((line) => line.trim() !== '')
          if (lines.length < 2) {
            setParseError('File CSV minimal harus memiliki header dan 1 baris data.')
            return
          }
          const headers = lines[0].split(',').map((h) => h.trim().replace(/^"|"$/g, ''))
          const rows = lines.slice(1).map((line) => {
            const values = line.split(',').map((v) => v.trim().replace(/^"|"$/g, ''))
            const obj = {}
            headers.forEach((h, idx) => {
              obj[h] = values[idx] || ''
            })
            return obj
          })
          setParsedData(rows)
        } else {
          setParseError('Format file tidak didukung. Harap gunakan CSV atau JSON.')
        }
      } catch (err) {
        setParseError('Gagal membaca file: ' + err.message)
      }
    }
    reader.readAsText(selected)
  }

  const handleDownloadTemplate = () => {
    const sampleData = [
      {
        kode_jabatan: 'JBT-101',
        nama_jabatan: 'Koordinator Ekstrakurikuler',
        level_jabatan: 5,
        urutan: 15,
        warna: '#3B82F6',
        ikon: 'UserCheck',
        deskripsi: 'Mengkoordinasi seluruh kegiatan ekstrakurikuler siswa',
        status: 'Aktif',
        tampil_struktur: true,
        boleh_login: true,
      },
    ]

    const dataStr = 'data:text/json;charset=utf-8,' + encodeURIComponent(JSON.stringify(sampleData, null, 2))
    const downloadAnchor = document.createElement('a')
    downloadAnchor.setAttribute('href', dataStr)
    downloadAnchor.setAttribute('download', 'template_import_master_jabatan.json')
    document.body.appendChild(downloadAnchor)
    downloadAnchor.click()
    downloadAnchor.remove()
  }

  const handleSubmit = (e) => {
    e.preventDefault()
    if (parsedData.length === 0) {
      setParseError('Pilih file yang berisi data valid terlebih dahulu.')
      return
    }
    onImport(parsedData)
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm overflow-y-auto">
      <div className="bg-white rounded-[24px] shadow-2xl border border-slate-100 w-full max-w-xl overflow-hidden my-8 transform transition-all">
        {/* Header Bar */}
        <div className="flex items-center justify-between px-7 py-5 border-b border-slate-100 bg-white">
          <div className="flex items-center space-x-3">
            <div className="p-2.5 rounded-xl bg-emerald-50 text-[#054e3b] font-bold">
              <FaFileImport className="w-5 h-5" />
            </div>
            <div>
              <h3 className="text-xl font-black text-[#0f172a]">
                Impor Master Data Jabatan
              </h3>
              <p className="text-xs text-slate-500 mt-0.5">
                Unggah file JSON atau CSV berisi daftar jabatan batch.
              </p>
            </div>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="rounded-full p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors"
          >
            <FaTimes className="w-4 h-4" />
          </button>
        </div>

        {/* Content */}
        <form onSubmit={handleSubmit} className="p-7 space-y-5">
          {/* Template Download Option */}
          <div className="flex items-center justify-between p-4 bg-[#f8fafc] rounded-2xl border border-slate-200/90 text-xs">
            <div>
              <p className="font-bold text-slate-900">Belum punya format template?</p>
              <p className="text-slate-500 mt-0.5">Unduh contoh berkas struktur data yang sesuai.</p>
            </div>
            <button
              type="button"
              onClick={handleDownloadTemplate}
              className="inline-flex items-center space-x-1.5 px-3.5 py-2 text-xs font-bold text-[#054e3b] bg-white border border-[#054e3b]/30 rounded-xl hover:bg-emerald-50 transition-colors shadow-sm"
            >
              <FaDownload className="w-3.5 h-3.5" />
              <span>Unduh Template</span>
            </button>
          </div>

          {/* Upload Area (Persis UI UX Referensi) */}
          <div className="border-2 border-dashed border-[#10b981] rounded-2xl p-6 text-center hover:bg-emerald-50/20 transition-colors bg-emerald-50/10">
            <input
              type="file"
              accept=".json,.csv,.txt"
              onChange={handleFileChange}
              className="hidden"
              id="file-upload-jabatan"
            />
            <label htmlFor="file-upload-jabatan" className="cursor-pointer space-y-2 block">
              <FaUpload className="w-8 h-8 mx-auto text-[#047857]" />
              <p className="text-sm font-bold text-[#0f172a]">
                {file ? file.name : 'Upload File CSV / JSON'}
              </p>
              <p className="text-xs text-slate-400">Ukuran maksimal file 5MB</p>
            </label>
          </div>

          {/* Validation Feedback */}
          {parseError && (
            <div className="p-3.5 bg-rose-50 text-rose-700 rounded-2xl text-xs flex items-center space-x-2 border border-rose-200">
              <FaExclamationCircle className="w-4 h-4 flex-shrink-0" />
              <span>{parseError}</span>
            </div>
          )}

          {parsedData.length > 0 && (
            <div className="p-3.5 bg-emerald-50 text-emerald-800 rounded-2xl text-xs flex items-center space-x-2 border border-emerald-200">
              <FaCheckCircle className="w-4 h-4 flex-shrink-0 text-emerald-600" />
              <span>Berhasil membaca <strong>{parsedData.length} baris</strong> data siap diimpor.</span>
            </div>
          )}

          {/* Footer Bar (Persis UI UX Referensi) */}
          <div className="flex items-center justify-between pt-4 border-t border-slate-100">
            <button
              type="button"
              onClick={onClose}
              className="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition-colors"
            >
              Batal
            </button>
            <button
              type="submit"
              disabled={isSubmitting || parsedData.length === 0}
              className="inline-flex items-center space-x-2 rounded-xl bg-[#046c4e] hover:bg-[#03543d] px-6 py-2.5 text-xs font-bold text-white shadow-md disabled:opacity-50 transition-colors"
            >
              <FaFileImport className="w-3.5 h-3.5" />
              <span>{isSubmitting ? 'Memproses Impor...' : 'Proses Impor Data'}</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}
