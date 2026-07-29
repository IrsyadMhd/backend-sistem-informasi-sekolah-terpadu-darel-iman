import React from 'react'
import { FaTimes, FaStar } from 'react-icons/fa'

export default function TahunAjaranDetailModal({ isOpen, onClose, data }) {
  if (!isOpen || !data) return null

  return (
    <div className="ui-backdrop fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-900/60 p-4 backdrop-blur-sm">
      <div className="ui-modal my-6 w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-2xl">
        {/* Modal Header */}
        <div className="flex items-center justify-between border-b border-slate-100 bg-white px-7 py-5">
          <h2 className="text-xl font-black text-[#0f172a]">
            Detail Master Data Tahun Ajaran
          </h2>
          <button
            type="button"
            onClick={onClose}
            className="rounded-full p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors"
          >
            <FaTimes className="w-4 h-4" />
          </button>
        </div>

        {/* Modal Content */}
        <div className="p-7 space-y-5">
          <div className="p-5 rounded-2xl bg-[#f8fafc] border border-slate-200/90 flex items-center justify-between">
            <div>
              <span className="text-xs font-bold text-slate-500 uppercase tracking-wider block">
                Nama Tahun Ajaran
              </span>
              <h3 className="text-2xl font-black text-[#0f172a] mt-1">{data.name}</h3>
              {data.kode && (
                <span className="text-xs font-mono font-bold text-slate-500 mt-0.5 block">
                  {data.kode}
                </span>
              )}
            </div>
            <div>
              {data.is_active ? (
                <span className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-extrabold bg-[#054e3b] text-white shadow-xs">
                  <FaStar className="w-3 h-3 text-amber-300" /> Aktif Utama
                </span>
              ) : (
                <span className="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-slate-200 text-slate-700">
                  Tidak Aktif
                </span>
              )}
            </div>
          </div>

          <div className="grid grid-cols-2 gap-4 text-xs">
            <div className="p-4 rounded-xl bg-white border border-slate-200/90">
              <span className="font-bold text-slate-500 uppercase tracking-wider block mb-1">
                Tanggal Mulai
              </span>
              <span className="font-extrabold text-slate-900 text-sm">{data.start_date || '-'}</span>
            </div>

            <div className="p-4 rounded-xl bg-white border border-slate-200/90">
              <span className="font-bold text-slate-500 uppercase tracking-wider block mb-1">
                Tanggal Selesai
              </span>
              <span className="font-extrabold text-slate-900 text-sm">{data.end_date || '-'}</span>
            </div>
          </div>

          <div className="p-4 rounded-xl bg-white border border-slate-200/90 text-xs space-y-1">
            <span className="font-bold text-slate-500 uppercase tracking-wider block mb-1">
              Deskripsi / Keterangan
            </span>
            <p className="text-slate-700 font-medium leading-relaxed">
              {data.keterangan || data.metadata?.keterangan || 'Tidak ada catatan khusus.'}
            </p>
          </div>

          <div className="grid grid-cols-2 gap-4 text-[11px] text-slate-400 pt-3 border-t border-slate-100">
            <div>
              <span className="block font-semibold">Dibuat Pada:</span>
              <span className="text-slate-600 font-medium">{data.created_at || '-'}</span>
            </div>
            <div>
              <span className="block font-semibold">Terakhir Diperbarui:</span>
              <span className="text-slate-600 font-medium">{data.updated_at || '-'}</span>
            </div>
          </div>
        </div>

        {/* Modal Bottom Action Footer */}
        <div className="flex items-center justify-end border-t border-slate-100 bg-white px-7 py-4">
          <button
            type="button"
            onClick={onClose}
            className="ui-button rounded-xl bg-emerald-800 px-6 py-2.5 text-xs font-semibold text-white shadow-md shadow-emerald-800/20 transition-all hover:bg-emerald-900"
          >
            Tutup Detail
          </button>
        </div>
      </div>
    </div>
  )
}
