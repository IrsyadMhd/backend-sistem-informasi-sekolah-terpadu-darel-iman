import { useState } from 'react'
import { FaCheckCircle, FaGraduationCap, FaPrint, FaQrcode, FaStar, FaTimes } from 'react-icons/fa'
import { usePengaturanStore } from '../../stores/pengaturanStore'

export default function CetakKartuSiswaModal({ student, onClose }) {
  const [template, setTemplate] = useState('standard')
  const [ukuran, setUkuran] = useState('CR80 (86 x 54 mm)')
  const pengaturan = usePengaturanStore((state) => state.pengaturan)

  if (!student) return null

  const namaSekolah = pengaturan?.namaSekolah || 'DAR EL-IMAN'
  const logoUrl = pengaturan?.logoUrl

  const handlePrint = () => {
    window.print()
  }

  // Extract student attributes safely
  const nis = student.nis || student.nisn || '23001'
  const nama = student.nama || student.nama_lengkap || 'Ahmad Zaky'
  const unit =
    student.unit ||
    student.unit_pendidikan ||
    student.raw?.metadata?.akademik?.unit_pendidikan ||
    'SDIT 2 Dar el-Iman - Padang'
  const kelas = student.kelas || student.rombel || '6A'
  const alamat =
    student.alamat ||
    student.raw?.address ||
    'Jl. Khatib Sulaiman No. 10 Kel. Lolong Belanti Kec. Padang Utara Padang - Sumatera Barat'
  const fotoUrl =
    student.foto ||
    student.raw?.metadata?.foto_url ||
    `https://ui-avatars.com/api/?name=${encodeURIComponent(nama)}&background=0D8ABC&color=fff`

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/70 p-4 backdrop-blur-sm overflow-y-auto">
      {/* Print Specific CSS Override */}
      <style>{`
        @media print {
          body * {
            visibility: hidden !important;
          }
          #printable-card, #printable-card * {
            visibility: visible !important;
          }
          #printable-card {
            position: fixed !important;
            left: 50% !important;
            top: 50% !important;
            transform: translate(-50%, -50%) scale(1.1) !important;
            box-shadow: none !important;
            margin: 0 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
          }
        }
      `}</style>

      <div className="relative w-full max-w-2xl rounded-2xl bg-white shadow-2xl transition-all border border-slate-200 my-6 overflow-hidden">
        {/* Container Header Banner (Dark Green) */}
        <div className="bg-[#064e3b] px-6 py-4 flex items-center justify-between text-white shadow-md">
          <div className="flex items-center gap-3">
            <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-700/80 text-amber-300 border border-emerald-500/40">
              <FaGraduationCap className="text-xl" />
            </div>
            <div>
              <h3 className="text-base font-extrabold tracking-wider uppercase leading-tight">CETAK KARTU SISWA</h3>
              <p className="text-[11px] text-emerald-200/80 font-medium">Preview & Cetak Kartu Identitas Siswa Resmi</p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="rounded-full p-2 text-emerald-200 hover:bg-emerald-800 hover:text-white transition"
          >
            <FaTimes className="text-base" />
          </button>
        </div>

        {/* Modal Main Content */}
        <div className="p-6 space-y-6 bg-slate-50/60">
          {/* Card Preview Container */}
          <div className="flex justify-center">
            {/* Standard Green Template */}
            {template === 'standard' && (
              <div
                id="printable-card"
                className="w-[490px] h-[310px] rounded-2xl border-2 border-emerald-500/30 bg-white p-5 shadow-xl flex flex-col justify-between relative overflow-hidden text-slate-800 select-none"
              >
                {/* Decorative Background Elements */}
                <div className="absolute -top-10 -right-10 w-32 h-32 bg-amber-400/20 rounded-full blur-xl pointer-events-none" />
                <div className="absolute top-0 right-0 w-24 h-24 bg-gradient-to-bl from-amber-400/30 via-emerald-500/10 to-transparent pointer-events-none rotate-45 transform translate-x-8 -translate-y-8" />

                {/* Card Top Brand Header */}
                <div className="flex items-center justify-between border-b border-slate-200 pb-3 relative z-10">
                  <div className="flex items-center gap-3">
                    <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-800 to-emerald-950 text-amber-400 shadow-md border border-amber-300/40">
                      {logoUrl ? (
                        <img src={logoUrl} alt="Logo" className="h-9 w-9 object-contain" />
                      ) : (
                        <FaStar className="text-2xl text-amber-300" />
                      )}
                    </div>
                    <div>
                      <h4 className="text-base font-black tracking-wider text-emerald-950 uppercase leading-none">
                        KARTU SISWA
                      </h4>
                      <h5 className="text-xs font-bold text-emerald-700 uppercase mt-0.5 tracking-wide">
                        {namaSekolah}
                      </h5>
                      <p className="text-[9px] font-bold text-slate-400 uppercase tracking-wider mt-0.5">
                        SISTEM PENDIDIKAN ISLAMI TERPADU
                      </p>
                    </div>
                  </div>
                </div>

                {/* Card Main Body Grid */}
                <div className="my-2 grid grid-cols-12 gap-3 items-center relative z-10">
                  {/* Photo Column */}
                  <div className="col-span-3 flex flex-col items-center">
                    <div className="h-28 w-24 overflow-hidden rounded-xl border-2 border-emerald-600 bg-slate-100 shadow-md">
                      <img src={fotoUrl} alt={nama} className="h-full w-full object-cover" />
                    </div>
                  </div>

                  {/* Details Column */}
                  <div className="col-span-6 space-y-1.5 text-slate-800">
                    <div className="grid grid-cols-12 text-[11px] leading-snug">
                      <span className="col-span-4 font-bold text-slate-500 text-[10px] uppercase">NIS</span>
                      <span className="col-span-8 font-black text-slate-900">{nis}</span>
                    </div>
                    <div className="grid grid-cols-12 text-[11px] leading-snug">
                      <span className="col-span-4 font-bold text-slate-500 text-[10px] uppercase">Nama</span>
                      <span className="col-span-8 font-extrabold text-emerald-950 leading-tight">{nama}</span>
                    </div>
                    <div className="grid grid-cols-12 text-[11px] leading-snug">
                      <span className="col-span-4 font-bold text-slate-500 text-[10px] uppercase">Unit Pendidikan</span>
                      <span className="col-span-8 font-bold text-slate-800 leading-tight">{unit}</span>
                    </div>
                    <div className="grid grid-cols-12 text-[11px] leading-snug">
                      <span className="col-span-4 font-bold text-slate-500 text-[10px] uppercase">Kelas</span>
                      <span className="col-span-8 font-bold text-slate-800">{kelas}</span>
                    </div>
                    <div className="grid grid-cols-12 text-[10px] leading-snug pt-0.5 border-t border-slate-100">
                      <span className="col-span-4 font-bold text-slate-500 text-[9px] uppercase">Alamat</span>
                      <span className="col-span-8 text-slate-600 line-clamp-2 font-medium leading-tight">
                        {alamat}
                      </span>
                    </div>
                  </div>

                  {/* QR Code Column */}
                  <div className="col-span-3 flex flex-col items-center justify-center border-l border-slate-200/80 pl-2">
                    <div className="flex h-20 w-20 items-center justify-center rounded-xl border border-slate-300 bg-white p-1.5 shadow-inner">
                      <FaQrcode className="h-full w-full text-slate-900" />
                    </div>
                    <span className="mt-1.5 text-[9px] font-black tracking-wider text-slate-500 uppercase">
                      QR ABSENSI
                    </span>
                  </div>
                </div>

                {/* Card Footer Banner */}
                <div className="-mx-5 -mb-5 mt-1 bg-[#064e3b] py-2 px-4 flex items-center justify-center relative overflow-hidden shadow-inner">
                  <div className="absolute right-0 top-0 bottom-0 w-8 bg-amber-400 transform skew-x-12 translate-x-4 opacity-80" />
                  <span className="text-[11px] font-extrabold tracking-widest text-white uppercase drop-shadow-sm z-10">
                    Berakhlak | Berilmu | Berprestasi
                  </span>
                </div>
              </div>
            )}

            {/* Premium Gold Template */}
            {template === 'modern' && (
              <div
                id="printable-card"
                className="w-[490px] h-[310px] rounded-2xl border-2 border-amber-400/40 bg-gradient-to-br from-slate-900 via-emerald-950 to-slate-900 p-5 shadow-xl flex flex-col justify-between relative overflow-hidden text-white select-none"
              >
                <div className="flex items-center justify-between border-b border-amber-400/30 pb-3">
                  <div className="flex items-center gap-3">
                    <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-amber-400 text-slate-900 shadow font-black">
                      <FaStar className="text-2xl" />
                    </div>
                    <div>
                      <h4 className="text-base font-black tracking-wider text-amber-300 uppercase leading-none">
                        KARTU SISWA
                      </h4>
                      <h5 className="text-xs font-bold text-emerald-200 uppercase mt-0.5 tracking-wide">
                        {namaSekolah}
                      </h5>
                    </div>
                  </div>
                </div>

                <div className="my-2 grid grid-cols-12 gap-3 items-center">
                  <div className="col-span-3 flex flex-col items-center">
                    <div className="h-28 w-24 overflow-hidden rounded-xl border-2 border-amber-400 bg-slate-800 shadow-md">
                      <img src={fotoUrl} alt={nama} className="h-full w-full object-cover" />
                    </div>
                  </div>

                  <div className="col-span-6 space-y-1 text-emerald-100 text-[11px]">
                    <div>
                      <span className="text-slate-400 text-[9px] block uppercase font-semibold">NIS</span>
                      <span className="font-bold text-amber-300">{nis}</span>
                    </div>
                    <div>
                      <span className="text-slate-400 text-[9px] block uppercase font-semibold">Nama</span>
                      <span className="font-extrabold text-white text-xs">{nama}</span>
                    </div>
                    <div>
                      <span className="text-slate-400 text-[9px] block uppercase font-semibold">Unit & Kelas</span>
                      <span className="font-medium text-emerald-200">{unit} ({kelas})</span>
                    </div>
                    <div>
                      <span className="text-slate-400 text-[9px] block uppercase font-semibold">Alamat</span>
                      <span className="text-slate-300 text-[10px] line-clamp-2">{alamat}</span>
                    </div>
                  </div>

                  <div className="col-span-3 flex flex-col items-center justify-center border-l border-amber-400/20 pl-2">
                    <div className="flex h-20 w-20 items-center justify-center rounded-xl bg-white p-1.5 shadow">
                      <FaQrcode className="h-full w-full text-slate-950" />
                    </div>
                    <span className="mt-1 text-[9px] font-bold text-amber-300 uppercase tracking-wider">
                      QR ABSENSI
                    </span>
                  </div>
                </div>

                <div className="-mx-5 -mb-5 bg-gradient-to-r from-amber-500 via-amber-400 to-amber-600 py-2 text-center text-[11px] font-black text-slate-950 tracking-widest uppercase">
                  Berakhlak | Berilmu | Berprestasi
                </div>
              </div>
            )}

            {/* Clean White Minimalist Template */}
            {template === 'minimal' && (
              <div
                id="printable-card"
                className="w-[490px] h-[310px] rounded-2xl border-2 border-slate-300 bg-white p-5 shadow-xl flex flex-col justify-between relative overflow-hidden text-slate-800 select-none"
              >
                <div className="flex items-center justify-between border-b border-slate-200 pb-3">
                  <div className="flex items-center gap-3">
                    <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-slate-800 text-white shadow font-bold">
                      <FaStar className="text-xl" />
                    </div>
                    <div>
                      <h4 className="text-base font-extrabold tracking-wider text-slate-900 uppercase leading-none">
                        KARTU SISWA
                      </h4>
                      <h5 className="text-xs font-semibold text-slate-600 uppercase mt-0.5">
                        {namaSekolah}
                      </h5>
                    </div>
                  </div>
                </div>

                <div className="my-2 grid grid-cols-12 gap-3 items-center">
                  <div className="col-span-3 flex flex-col items-center">
                    <div className="h-28 w-24 overflow-hidden rounded-xl border border-slate-300 bg-slate-50 shadow-sm">
                      <img src={fotoUrl} alt={nama} className="h-full w-full object-cover" />
                    </div>
                  </div>

                  <div className="col-span-6 space-y-1.5 text-[11px]">
                    <div>
                      <span className="text-slate-400 text-[9px] block uppercase font-bold">NIS</span>
                      <span className="font-bold text-slate-900">{nis}</span>
                    </div>
                    <div>
                      <span className="text-slate-400 text-[9px] block uppercase font-bold">Nama Lengkap</span>
                      <span className="font-extrabold text-slate-900">{nama}</span>
                    </div>
                    <div>
                      <span className="text-slate-400 text-[9px] block uppercase font-bold">Unit / Kelas</span>
                      <span className="font-semibold text-slate-700">{unit} - {kelas}</span>
                    </div>
                    <div>
                      <span className="text-slate-400 text-[9px] block uppercase font-bold">Alamat</span>
                      <span className="text-slate-600 text-[10px] line-clamp-2">{alamat}</span>
                    </div>
                  </div>

                  <div className="col-span-3 flex flex-col items-center justify-center border-l border-slate-200 pl-2">
                    <div className="flex h-20 w-20 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 p-1.5">
                      <FaQrcode className="h-full w-full text-slate-800" />
                    </div>
                    <span className="mt-1 text-[9px] font-bold text-slate-500 uppercase tracking-wider">
                      QR ABSENSI
                    </span>
                  </div>
                </div>

                <div className="-mx-5 -mb-5 bg-slate-800 py-2 text-center text-[11px] font-bold text-white tracking-widest uppercase">
                  Berakhlak | Berilmu | Berprestasi
                </div>
              </div>
            )}
          </div>

          {/* Controls Form Grid */}
          <div className="space-y-4 border-t border-slate-200 pt-5">
            {/* Template Chooser */}
            <div>
              <label className="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2.5">
                Pilih Template
              </label>
              <div className="grid grid-cols-3 gap-3">
                {/* Option 1: Standard Green */}
                <button
                  type="button"
                  onClick={() => setTemplate('standard')}
                  className={`relative rounded-xl border-2 p-2.5 text-left transition flex items-center gap-3 ${
                    template === 'standard'
                      ? 'border-emerald-600 bg-emerald-50/80 shadow-md ring-2 ring-emerald-600/20'
                      : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50'
                  }`}
                >
                  <div className="h-10 w-14 rounded-lg bg-emerald-800 flex items-center justify-center text-amber-300 font-bold shrink-0 border border-emerald-600">
                    <FaStar className="text-xs" />
                  </div>
                  <div className="min-w-0 flex-1">
                    <p className="text-xs font-bold text-slate-900 leading-tight truncate">Hijau Standard</p>
                    <p className="text-[10px] text-slate-500 truncate">Resmi Dar El-Iman</p>
                  </div>
                  {template === 'standard' && (
                    <FaCheckCircle className="text-emerald-600 text-base shrink-0" />
                  )}
                </button>

                {/* Option 2: Modern Gold */}
                <button
                  type="button"
                  onClick={() => setTemplate('modern')}
                  className={`relative rounded-xl border-2 p-2.5 text-left transition flex items-center gap-3 ${
                    template === 'modern'
                      ? 'border-emerald-600 bg-emerald-50/80 shadow-md ring-2 ring-emerald-600/20'
                      : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50'
                  }`}
                >
                  <div className="h-10 w-14 rounded-lg bg-slate-900 flex items-center justify-center text-amber-400 font-bold shrink-0 border border-amber-400/40">
                    <FaStar className="text-xs" />
                  </div>
                  <div className="min-w-0 flex-1">
                    <p className="text-xs font-bold text-slate-900 leading-tight truncate">Dark Premium</p>
                    <p className="text-[10px] text-slate-500 truncate">Aksen Emas Elegant</p>
                  </div>
                  {template === 'modern' && (
                    <FaCheckCircle className="text-emerald-600 text-base shrink-0" />
                  )}
                </button>

                {/* Option 3: Minimal White */}
                <button
                  type="button"
                  onClick={() => setTemplate('minimal')}
                  className={`relative rounded-xl border-2 p-2.5 text-left transition flex items-center gap-3 ${
                    template === 'minimal'
                      ? 'border-emerald-600 bg-emerald-50/80 shadow-md ring-2 ring-emerald-600/20'
                      : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50'
                  }`}
                >
                  <div className="h-10 w-14 rounded-lg bg-slate-100 flex items-center justify-center text-slate-700 font-bold shrink-0 border border-slate-300">
                    <FaStar className="text-xs" />
                  </div>
                  <div className="min-w-0 flex-1">
                    <p className="text-xs font-bold text-slate-900 leading-tight truncate">Clean White</p>
                    <p className="text-[10px] text-slate-500 truncate">Minimalist Clean</p>
                  </div>
                  {template === 'minimal' && (
                    <FaCheckCircle className="text-emerald-600 text-base shrink-0" />
                  )}
                </button>
              </div>
            </div>

            {/* Paper Size Option */}
            <div>
              <label className="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                Ukuran
              </label>
              <select
                value={ukuran}
                onChange={(e) => setUkuran(e.target.value)}
                className="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-xs font-medium text-slate-800 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
              >
                <option value="CR80 (86 x 54 mm)">CR80 (86 x 54 mm) - Standard PVC Card Size</option>
                <option value="A4 (Batch 8 Cards)">A4 Sheet (Isi 8 Kartu per Halaman)</option>
              </select>
            </div>

            {/* Print Action Button */}
            <div className="pt-2">
              <button
                type="button"
                onClick={handlePrint}
                className="w-full py-3 px-6 bg-[#064e3b] hover:bg-emerald-800 text-white font-extrabold rounded-xl shadow-lg hover:shadow-xl flex items-center justify-center gap-2.5 text-sm tracking-wider uppercase transition-all duration-200 active:scale-[0.99]"
              >
                <FaPrint className="text-base" /> Cetak Kartu
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}

