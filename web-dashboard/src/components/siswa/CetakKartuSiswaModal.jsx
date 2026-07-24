import { useState } from 'react'
import { FaPrint, FaQrcode, FaTimes } from 'react-icons/fa'

export default function CetakKartuSiswaModal({ student, onClose }) {
  const [template, setTemplate] = useState('standard')
  const [ukuran, setUkuran] = useState('CR80 (86 x 54 mm)')

  if (!student) return null

  const handlePrint = () => {
    window.print()
  }

  const fotoUrl =
    student.foto ||
    student.raw?.metadata?.foto_url ||
    `https://ui-avatars.com/api/?name=${encodeURIComponent(student.nama || 'Siswa')}&background=0D8ABC&color=fff`

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm overflow-y-auto">
      <div className="relative w-full max-w-2xl rounded-2xl bg-white p-6 shadow-2xl transition-all border border-slate-200 my-8">
        {/* Modal Header */}
        <div className="flex items-center justify-between border-b border-slate-200 pb-4 mb-4">
          <h3 className="text-base font-bold tracking-wide text-slate-800 uppercase flex items-center gap-2">
            <FaPrint className="text-emerald-700" /> Cetak Kartu Siswa
          </h3>
          <button
            onClick={onClose}
            className="rounded-full p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition"
          >
            <FaTimes />
          </button>
        </div>

        {/* Printable Card Area */}
        <div className="space-y-6">
          <div className="flex justify-center bg-slate-50 p-6 rounded-xl border border-slate-200">
            {/* The Actual ID Card Design */}
            <div
              id="printable-card"
              className="w-[420px] rounded-xl border border-emerald-300 bg-white p-4 shadow-lg flex flex-col justify-between relative overflow-hidden text-slate-800"
            >
              {/* Background Accent */}
              <div className="absolute top-0 right-0 w-32 h-32 bg-emerald-500/10 rounded-full blur-2xl pointer-events-none" />
              <div className="absolute bottom-0 left-0 w-28 h-28 bg-amber-500/10 rounded-full blur-xl pointer-events-none" />

              {/* Card Header Brand */}
              <div className="flex items-center gap-3 border-b border-slate-200 pb-2.5">
                <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-emerald-700 text-amber-300 shadow">
                  <span className="text-xl font-black">★</span>
                </div>
                <div>
                  <h4 className="text-sm font-extrabold tracking-wider text-emerald-950 uppercase leading-none">
                    KARTU SISWA
                  </h4>
                  <h5 className="text-xs font-bold text-emerald-800 uppercase mt-0.5">DAR EL-IMAN</h5>
                  <p className="text-[9px] font-semibold text-slate-500 uppercase tracking-tight">
                    SISTEM PENDIDIKAN ISLAMI TERPADU
                  </p>
                </div>
              </div>

              {/* Card Main Info Body */}
              <div className="my-3 grid grid-cols-12 gap-3 items-center">
                {/* Photo */}
                <div className="col-span-4 flex flex-col items-center">
                  <div className="h-28 w-24 overflow-hidden rounded-lg border-2 border-emerald-600 bg-slate-100 shadow-sm">
                    <img src={fotoUrl} alt={student.nama} className="h-full w-full object-cover" />
                  </div>
                </div>

                {/* Details */}
                <div className="col-span-5 space-y-1 text-[11px]">
                  <div>
                    <span className="font-medium text-slate-500 block text-[9px] uppercase">NIS</span>
                    <span className="font-bold text-slate-900">{student.nis || '23001'}</span>
                  </div>
                  <div>
                    <span className="font-medium text-slate-500 block text-[9px] uppercase">Nama</span>
                    <span className="font-bold text-emerald-950 leading-snug block">{student.nama || 'Ahmad Zaky'}</span>
                  </div>
                  <div>
                    <span className="font-medium text-slate-500 block text-[9px] uppercase">Unit Pendidikan</span>
                    <span className="font-semibold text-slate-800 leading-tight block">
                      {student.unit || student.raw?.metadata?.akademik?.unit_pendidikan || 'SDIT 2 Dar el-Iman - Padang'}
                    </span>
                  </div>
                  <div>
                    <span className="font-medium text-slate-500 block text-[9px] uppercase">Kelas</span>
                    <span className="font-semibold text-slate-800">{student.kelas || '6A'}</span>
                  </div>
                </div>

                {/* QR Code Absensi */}
                <div className="col-span-3 flex flex-col items-center justify-center border-l border-slate-200 pl-2">
                  <div className="flex h-16 w-16 items-center justify-center rounded-lg border border-slate-300 bg-white p-1 shadow-inner text-slate-800">
                    <FaQrcode className="h-full w-full text-slate-900" />
                  </div>
                  <span className="mt-1 text-[8px] font-bold tracking-wider text-slate-500 uppercase">
                    QR ABSENSI
                  </span>
                </div>
              </div>

              {/* Address Row */}
              <div className="px-1 py-1 text-[9px] text-slate-600 border-t border-slate-100 leading-tight">
                <span className="font-bold text-slate-700">Alamat: </span>
                {student.alamat || student.raw?.address || 'Jl. Khatib Sulaiman No. 10 Kel. Lolong Belanti Kec. Padang Utara Padang'}
              </div>

              {/* Card Footer Banner */}
              <div className="-mx-4 -mb-4 mt-2 bg-[#064e3b] py-1.5 px-3 text-center text-[10px] font-bold tracking-widest text-amber-200 uppercase shadow-inner">
                Berakhlak | Berilmu | Berprestasi
              </div>
            </div>
          </div>

          {/* Print Settings Options */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4 border-t border-slate-200 pt-4">
            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-2">Pilih Template</label>
              <div className="flex gap-3">
                <button
                  type="button"
                  onClick={() => setTemplate('standard')}
                  className={`flex-1 rounded-lg border p-2 text-left transition text-xs ${
                    template === 'standard'
                      ? 'border-emerald-600 bg-emerald-50 text-emerald-900 font-semibold shadow-sm'
                      : 'border-slate-200 hover:bg-slate-50 text-slate-700'
                  }`}
                >
                  <p className="font-bold">Template Hijau Standard</p>
                  <p className="text-[10px] text-slate-500">Desain resmi Dar El-Iman</p>
                </button>
                <button
                  type="button"
                  onClick={() => setTemplate('modern')}
                  className={`flex-1 rounded-lg border p-2 text-left transition text-xs ${
                    template === 'modern'
                      ? 'border-emerald-600 bg-emerald-50 text-emerald-900 font-semibold shadow-sm'
                      : 'border-slate-200 hover:bg-slate-50 text-slate-700'
                  }`}
                >
                  <p className="font-bold">Template Modern QR</p>
                  <p className="text-[10px] text-slate-500">Layout QR besar di kanan</p>
                </button>
              </div>
            </div>

            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-2">Ukuran Cetak</label>
              <select
                value={ukuran}
                onChange={(e) => setUkuran(e.target.value)}
                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs font-medium focus:border-emerald-500 focus:outline-none"
              >
                <option value="CR80 (86 x 54 mm)">CR80 (86 x 54 mm) - Standard ID Card</option>
                <option value="A4 (Batch 8 Cards)">A4 (Sheet Isi 8 Kartu)</option>
              </select>
            </div>
          </div>
        </div>

        {/* Action Buttons Footer */}
        <div className="flex justify-end gap-2 border-t border-slate-200 pt-4 mt-6">
          <button
            type="button"
            onClick={onClose}
            className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition"
          >
            Tutup
          </button>
          <button
            type="button"
            onClick={handlePrint}
            className="flex items-center gap-2 rounded-lg bg-[#064e3b] px-5 py-2 text-xs font-bold text-white shadow-md hover:bg-emerald-800 transition"
          >
            <FaPrint /> Cetak Kartu
          </button>
        </div>
      </div>
    </div>
  )
}
