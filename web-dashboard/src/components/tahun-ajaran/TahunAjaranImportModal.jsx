import React, { useState } from 'react'
import { FaTimes, FaFileImport, FaUpload, FaDownload, FaCheck, FaExclamationTriangle } from 'react-icons/fa'

export default function TahunAjaranImportModal({
  isOpen,
  onClose,
  onImport,
  isSubmitting = false,
}) {
  const [fileContent, setFileContent] = useState(null)
  const [previewRows, setPreviewRows] = useState([])
  const [fileName, setFileName] = useState('')
  const [errorMsg, setErrorMsg] = useState('')

  if (!isOpen) return null

  const handleFileUpload = (e) => {
    const file = e.target.files[0]
    if (!file) return

    setFileName(file.name)
    setErrorMsg('')

    const reader = new FileReader()
    reader.onload = (evt) => {
      try {
        const text = evt.target.result
        const lines = text.split(/\r\n|\n/).filter((l) => l.trim().length > 0)

        if (lines.length <= 1) {
          setErrorMsg('File CSV kosong atau tidak memiliki baris data.')
          return
        }

        const headers = lines[0].split(',').map((h) => h.trim().replace(/^"|"$/g, '').toLowerCase())
        const rows = []

        for (let i = 1; i < lines.length; i++) {
          const cols = lines[i].split(',').map((c) => c.trim().replace(/^"|"$/g, ''))
          if (cols.length >= 3) {
            rows.push({
              name: cols[0] || '',
              start_date: cols[1] || '',
              end_date: cols[2] || '',
              is_active: cols[3] || 'false',
              keterangan: cols[4] || '',
            })
          }
        }

        if (rows.length === 0) {
          setErrorMsg('Format kolom file CSV tidak valid.')
        } else {
          setPreviewRows(rows)
        }
      } catch (err) {
        setErrorMsg('Gagal membaca isi file CSV.')
      }
    }
    reader.readAsText(file)
  }

  const handleDownloadTemplate = () => {
    const csvContent =
      'name,start_date,end_date,is_active,keterangan\n2025/2026,2025-07-01,2026-06-30,true,Tahun ajaran baru\n2026/2027,2026-07-01,2027-06-30,false,Tahun ajaran mendatang'
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.setAttribute('href', url)
    link.setAttribute('download', 'template_import_tahun_ajaran.csv')
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
  }

  const handleSubmitImport = () => {
    if (previewRows.length === 0) return
    onImport(previewRows)
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-xs animate-fade-in">
      <div className="bg-white rounded-3xl shadow-2xl border border-emerald-100 w-full max-w-2xl overflow-hidden flex flex-col max-h-[90vh]">
        {/* HEADER */}
        <div className="bg-gradient-to-r from-emerald-900 via-emerald-800 to-emerald-700 px-6 py-5 text-white flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="p-2.5 rounded-xl bg-emerald-700/80 border border-emerald-500/40 text-emerald-100">
              <FaFileImport className="w-5 h-5" />
            </div>
            <div>
              <h3 className="text-lg font-extrabold tracking-tight">Impor Data Tahun Ajaran</h3>
              <p className="text-xs text-emerald-100/80 mt-0.5">
                Unggah file CSV/Excel untuk impor masal data tahun ajaran
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="p-2 rounded-xl bg-white/10 hover:bg-white/20 text-white transition-all active:scale-95"
          >
            <FaTimes className="w-4 h-4" />
          </button>
        </div>

        {/* BODY */}
        <div className="p-6 space-y-5 overflow-y-auto flex-1 text-sm">
          {/* Download Template Box */}
          <div className="p-4 rounded-2xl bg-emerald-50/70 border border-emerald-100 flex items-center justify-between">
            <div>
              <h4 className="font-bold text-emerald-900">Belum punya format file?</h4>
              <p className="text-xs text-emerald-700 mt-0.5">
                Unduh contoh template CSV untuk mencocokkan kolom data.
              </p>
            </div>
            <button
              onClick={handleDownloadTemplate}
              className="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-white hover:bg-emerald-100/50 text-emerald-800 font-bold text-xs border border-emerald-200 shadow-xs transition-all"
            >
              <FaDownload className="w-3.5 h-3.5 text-emerald-600" /> Unduh Template
            </button>
          </div>

          {/* Upload Box */}
          <div className="border-2 border-dashed border-emerald-200 rounded-2xl p-6 text-center hover:border-emerald-400 transition-colors bg-emerald-50/20">
            <input
              type="file"
              accept=".csv, text/csv"
              id="file-import-input"
              onChange={handleFileUpload}
              className="hidden"
            />
            <label htmlFor="file-import-input" className="cursor-pointer space-y-2 block">
              <div className="w-12 h-12 bg-emerald-100 text-emerald-700 rounded-2xl flex items-center justify-center mx-auto shadow-xs">
                <FaUpload className="w-5 h-5" />
              </div>
              <p className="font-bold text-gray-800">
                {fileName ? fileName : 'Klik di sini untuk memilih file CSV'}
              </p>
              <p className="text-xs text-gray-500">Format yang didukung: .CSV (Comma Separated Values)</p>
            </label>
          </div>

          {errorMsg && (
            <div className="p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-semibold flex items-center gap-2">
              <FaExclamationTriangle className="w-4 h-4 text-rose-500 flex-shrink-0" />
              <span>{errorMsg}</span>
            </div>
          )}

          {/* Preview Table */}
          {previewRows.length > 0 && (
            <div>
              <h4 className="font-bold text-gray-800 mb-2 flex items-center justify-between">
                <span>Pratinjau Data Impor</span>
                <span className="text-xs font-bold text-emerald-600 px-2.5 py-0.5 rounded-full bg-emerald-50 border border-emerald-200">
                  {previewRows.length} Baris Siap Diimpor
                </span>
              </h4>
              <div className="max-h-48 overflow-y-auto border border-gray-200 rounded-xl">
                <table className="w-full text-left text-xs">
                  <tr className="bg-gray-100 font-bold text-gray-700 sticky top-0">
                    <th className="p-2 border-b">Nama</th>
                    <th className="p-2 border-b">Mulai</th>
                    <th className="p-2 border-b">Selesai</th>
                    <th className="p-2 border-b">Status Aktif</th>
                  </tr>
                  {previewRows.map((r, i) => (
                    <tr key={i} className="border-b hover:bg-gray-50">
                      <td className="p-2 font-bold">{r.name}</td>
                      <td className="p-2">{r.start_date}</td>
                      <td className="p-2">{r.end_date}</td>
                      <td className="p-2">{r.is_active}</td>
                    </tr>
                  ))}
                </table>
              </div>
            </div>
          )}
        </div>

        {/* FOOTER */}
        <div className="p-4 bg-gray-50 border-t border-gray-100 flex justify-end gap-3">
          <button
            onClick={onClose}
            className="px-5 py-2.5 rounded-xl border border-gray-200 text-gray-600 font-bold text-sm hover:bg-gray-100 transition-all"
          >
            Batal
          </button>
          <button
            onClick={handleSubmitImport}
            disabled={previewRows.length === 0 || isSubmitting}
            className="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-sm shadow-md transition-all disabled:opacity-40"
          >
            {isSubmitting ? 'Memproses...' : 'Proses Impor Data'}
          </button>
        </div>
      </div>
    </div>
  )
}
