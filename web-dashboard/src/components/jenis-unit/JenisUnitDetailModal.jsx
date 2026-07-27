import React from 'react'
import { FaTimes, FaSchool, FaInfoCircle, FaCalendarAlt, FaUser } from 'react-icons/fa'
import { renderJenisUnitIcon } from './JenisUnitTable'

export default function JenisUnitDetailModal({ isOpen, onClose, data }) {
  if (!isOpen || !data) return null

  const badgeColor = data.warna_badge || '#10B981'

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
      <div className="bg-white rounded-2xl shadow-2xl border border-emerald-100 w-full max-w-xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
        {/* Header Modal */}
        <div className="bg-gradient-to-r from-emerald-800 to-emerald-700 p-5 text-white flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="p-2 bg-white/10 rounded-lg">
              <FaSchool className="w-5 h-5 text-emerald-200" />
            </div>
            <div>
              <h2 className="text-lg font-bold">Detail Jenis Unit Pendidikan</h2>
              <p className="text-xs text-emerald-100">Informasi lengkap secara read-only.</p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="p-2 rounded-lg text-emerald-100 hover:bg-white/10 hover:text-white transition-colors"
          >
            <FaTimes className="w-5 h-5" />
          </button>
        </div>

        {/* Content Body */}
        <div className="p-6 space-y-6 text-sm text-gray-700">
          {/* Main Info Card */}
          <div className="p-4 bg-emerald-50/60 rounded-xl border border-emerald-100 flex items-center justify-between">
            <div className="flex items-center gap-4">
              <div className="p-3 bg-white rounded-xl shadow-xs border border-emerald-100 text-emerald-700">
                {renderJenisUnitIcon(data.icon, 'w-7 h-7')}
              </div>
              <div>
                <div className="flex items-center gap-2">
                  <span className="text-xs font-bold px-2 py-0.5 rounded bg-emerald-800 text-white uppercase">
                    {data.kode_jenis}
                  </span>
                  <span className="text-xs font-semibold px-2 py-0.5 rounded bg-emerald-200 text-emerald-800">
                    {data.jenjang}
                  </span>
                </div>
                <h3 className="text-base font-bold text-gray-900 mt-1">{data.nama_jenis}</h3>
                <p className="text-xs text-gray-500">Singkatan: {data.singkatan || '-'}</p>
              </div>
            </div>

            <div className="text-right">
              <span
                className={`inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold ${
                  data.status
                    ? 'bg-emerald-100 text-emerald-700 border border-emerald-200'
                    : 'bg-gray-100 text-gray-600 border border-gray-200'
                }`}
              >
                {data.status ? '• Aktif' : '• Tidak Aktif'}
              </span>
            </div>
          </div>

          {/* Details Table Grid */}
          <div className="grid grid-cols-2 gap-4">
            <div className="p-3.5 bg-gray-50 rounded-xl border border-gray-100">
              <p className="text-xs text-gray-500 font-medium">Urutan Tampilan</p>
              <p className="text-base font-bold text-gray-800 mt-0.5">{data.urutan}</p>
            </div>

            <div className="p-3.5 bg-gray-50 rounded-xl border border-gray-100">
              <p className="text-xs text-gray-500 font-medium">Warna Badge</p>
              <div className="flex items-center gap-2 mt-1">
                <span
                  className="w-4 h-4 rounded-full border border-gray-300"
                  style={{ backgroundColor: badgeColor }}
                ></span>
                <span className="text-sm font-mono font-bold text-gray-700 uppercase">
                  {badgeColor}
                </span>
              </div>
            </div>
          </div>

          {/* Keterangan */}
          <div className="p-3.5 bg-gray-50 rounded-xl border border-gray-100">
            <p className="text-xs text-gray-500 font-medium mb-1">Keterangan / Deskripsi</p>
            <p className="text-sm text-gray-700 leading-relaxed">
              {data.keterangan || 'Tidak ada keterangan tambahan.'}
            </p>
          </div>

          {/* Audit Trail Info */}
          <div className="pt-4 border-t border-gray-100 text-xs text-gray-500 space-y-2">
            <div className="flex items-center justify-between">
              <span className="flex items-center gap-1.5">
                <FaCalendarAlt className="w-3.5 h-3.5 text-gray-400" />
                Tanggal Dibuat:
              </span>
              <span className="font-semibold text-gray-700">{data.created_at || '-'}</span>
            </div>
            <div className="flex items-center justify-between">
              <span className="flex items-center gap-1.5">
                <FaUser className="w-3.5 h-3.5 text-gray-400" />
                Dibuat Oleh:
              </span>
              <span className="font-semibold text-gray-700">{data.created_by_name || 'Sistem'}</span>
            </div>
            {data.updated_at && (
              <div className="flex items-center justify-between">
                <span className="flex items-center gap-1.5">
                  <FaCalendarAlt className="w-3.5 h-3.5 text-gray-400" />
                  Terakhir Diubah:
                </span>
                <span className="font-semibold text-gray-700">{data.updated_at}</span>
              </div>
            )}
          </div>
        </div>

        {/* Modal Footer */}
        <div className="bg-gray-50 p-4 border-t border-gray-100 text-right">
          <button
            onClick={onClose}
            className="px-5 py-2 bg-gray-700 text-white rounded-lg text-sm font-semibold hover:bg-gray-800 transition-colors"
          >
            Tutup
          </button>
        </div>
      </div>
    </div>
  )
}
