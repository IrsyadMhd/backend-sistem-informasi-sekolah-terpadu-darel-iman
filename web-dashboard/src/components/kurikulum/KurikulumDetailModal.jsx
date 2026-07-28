import React from 'react'
import {
  X,
  BookOpen,
  Building2,
  Calendar,
  Tag,
  CheckCircle,
  XCircle,
  User,
  Clock,
  FileText,
  ShieldAlert,
} from 'lucide-react'

export default function KurikulumDetailModal({ isOpen, onClose, data }) {
  if (!isOpen || !data) return null

  const isTerhapus = !!data.deleted_at

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
              <span className="font-mono text-[10px] font-bold text-emerald-200 uppercase bg-emerald-800/80 px-2 py-0.5 rounded-md border border-emerald-600/40">
                {data.kode_kurikulum}
              </span>
              <h2 className="text-lg font-bold mt-1">{data.nama_kurikulum}</h2>
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
        <div className="p-6 space-y-6 text-xs text-slate-700">
          {/* Status Banner */}
          <div className="flex items-center justify-between p-4 rounded-2xl bg-slate-50 border border-slate-200">
            <div className="flex items-center gap-3">
              {isTerhapus ? (
                <div className="p-2 rounded-xl bg-rose-100 text-rose-700">
                  <ShieldAlert className="w-5 h-5" />
                </div>
              ) : data.status ? (
                <div className="p-2 rounded-xl bg-emerald-100 text-emerald-700">
                  <CheckCircle className="w-5 h-5" />
                </div>
              ) : (
                <div className="p-2 rounded-xl bg-amber-100 text-amber-700">
                  <XCircle className="w-5 h-5" />
                </div>
              )}

              <div>
                <p className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                  Status Operasional
                </p>
                <h4 className="text-sm font-black text-slate-900 mt-0.5">
                  {isTerhapus ? 'Terhapus (Soft Deleted)' : data.status ? 'Berlaku & Aktif' : 'Nonaktif'}
                </h4>
              </div>
            </div>

            <div className="flex items-center gap-2">
              <span className="px-3 py-1 rounded-lg font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                {data.jenis_kurikulum}
              </span>
              <span className="px-2.5 py-1 rounded-lg font-bold bg-slate-100 text-slate-700 uppercase border border-slate-200">
                {data.jenjang}
              </span>
            </div>
          </div>

          {/* Details Grid */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {/* Unit Pendidikan */}
            <div className="p-4 rounded-2xl bg-white border border-slate-100 shadow-xs space-y-1">
              <div className="flex items-center gap-2 text-slate-400 font-bold uppercase tracking-wider text-[10px]">
                <Building2 className="w-3.5 h-3.5 text-emerald-600" />
                Unit Pendidikan
              </div>
              <p className="font-extrabold text-slate-900 text-sm">{data.unit_pendidikan_nama || '-'}</p>
            </div>

            {/* Tahun Ajaran */}
            <div className="p-4 rounded-2xl bg-white border border-slate-100 shadow-xs space-y-1">
              <div className="flex items-center gap-2 text-slate-400 font-bold uppercase tracking-wider text-[10px]">
                <Calendar className="w-3.5 h-3.5 text-emerald-600" />
                Tahun Ajaran & Semester
              </div>
              <p className="font-extrabold text-slate-900 text-sm">
                {data.tahun_ajaran_nama || '-'} {data.semester_nama ? `(${data.semester_nama})` : ''}
              </p>
            </div>

            {/* Tanggal Mulai */}
            <div className="p-4 rounded-2xl bg-white border border-slate-100 shadow-xs space-y-1">
              <div className="flex items-center gap-2 text-slate-400 font-bold uppercase tracking-wider text-[10px]">
                <Clock className="w-3.5 h-3.5 text-emerald-600" />
                Mulai Berlaku
              </div>
              <p className="font-bold text-slate-800 text-xs">{data.tanggal_mulai || '-'}</p>
            </div>

            {/* Tanggal Selesai */}
            <div className="p-4 rounded-2xl bg-white border border-slate-100 shadow-xs space-y-1">
              <div className="flex items-center gap-2 text-slate-400 font-bold uppercase tracking-wider text-[10px]">
                <Clock className="w-3.5 h-3.5 text-slate-400" />
                Selesai Berlaku
              </div>
              <p className="font-bold text-slate-800 text-xs">{data.tanggal_selesai || 'Sampai Sekarang'}</p>
            </div>
          </div>

          {/* Deskripsi */}
          {data.deskripsi && (
            <div className="p-4 rounded-2xl bg-slate-50/70 border border-slate-200 space-y-1.5">
              <div className="flex items-center gap-2 text-slate-500 font-bold uppercase tracking-wider text-[10px]">
                <FileText className="w-3.5 h-3.5 text-emerald-600" />
                Deskripsi Kurikulum
              </div>
              <p className="text-slate-700 leading-relaxed font-medium">{data.deskripsi}</p>
            </div>
          )}

          {/* Audit Log Box */}
          <div className="p-4 rounded-2xl bg-emerald-50/40 border border-emerald-100 space-y-3">
            <h5 className="font-bold text-emerald-900 text-xs flex items-center gap-2 uppercase tracking-wider">
              <User className="w-4 h-4 text-emerald-700" /> Informasi Audit Log
            </h5>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-[11px]">
              <div>
                <span className="text-slate-400 font-semibold block">Dibuat oleh:</span>
                <span className="font-bold text-slate-800">{data.creator_name || 'Sistem / Admin'}</span>
                <span className="text-slate-400 block text-[10px]">{data.created_at || '-'}</span>
              </div>
              <div>
                <span className="text-slate-400 font-semibold block">Terakhir diperbarui:</span>
                <span className="font-bold text-slate-800">{data.updater_name || '-'}</span>
                <span className="text-slate-400 block text-[10px]">{data.updated_at || '-'}</span>
              </div>
            </div>
          </div>
        </div>

        {/* Modal Footer */}
        <div className="p-4 bg-slate-50 border-t border-slate-100 flex justify-end">
          <button
            onClick={onClose}
            className="px-5 py-2 rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-800 font-bold transition-colors"
          >
            Tutup
          </button>
        </div>
      </div>
    </div>
  )
}
