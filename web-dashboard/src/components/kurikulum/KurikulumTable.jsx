import React from 'react'
import {
  Eye,
  Edit,
  Trash2,
  RotateCcw,
  BookOpen,
  Calendar,
  Building2,
  CheckCircle,
  XCircle,
  Tag,
} from 'lucide-react'

export default function KurikulumTable({
  data = [],
  isLoading = false,
  page = 1,
  perPage = 15,
  onDetail,
  onEdit,
  onDelete,
  onRestore,
}) {
  if (isLoading) {
    return (
      <div className="bg-white rounded-2xl border border-emerald-100 p-6 shadow-xs overflow-hidden">
        <div className="animate-pulse space-y-4">
          <div className="h-10 bg-slate-100 rounded-xl w-full"></div>
          {[...Array(5)].map((_, i) => (
            <div key={i} className="h-14 bg-slate-50 rounded-xl w-full"></div>
          ))}
        </div>
      </div>
    )
  }

  if (!data || data.length === 0) {
    return (
      <div className="bg-white rounded-2xl border border-emerald-100 p-12 text-center shadow-xs">
        <div className="w-16 h-16 bg-emerald-50 rounded-2xl flex items-center justify-center mx-auto text-emerald-600 mb-4 border border-emerald-100">
          <BookOpen className="w-8 h-8" />
        </div>
        <h3 className="text-lg font-bold text-slate-800">Data Master Kurikulum Tidak Ditemukan</h3>
        <p className="text-sm text-slate-500 mt-1 max-w-md mx-auto">
          Belum ada data kurikulum yang tersimpan atau data yang Anda cari tidak cocok dengan filter.
        </p>
      </div>
    )
  }

  const getJenisBadgeColor = (jenis) => {
    switch (jenis) {
      case 'SIT':
        return 'bg-emerald-50 text-emerald-700 border-emerald-200'
      case 'Merdeka':
        return 'bg-blue-50 text-blue-700 border-blue-200'
      case 'Nasional':
        return 'bg-indigo-50 text-indigo-700 border-indigo-200'
      case 'Pesantren':
        return 'bg-amber-50 text-amber-700 border-amber-200'
      case 'Lokal':
        return 'bg-purple-50 text-purple-700 border-purple-200'
      default:
        return 'bg-slate-50 text-slate-700 border-slate-200'
    }
  }

  return (
    <div className="bg-white rounded-2xl border border-emerald-100 shadow-sm overflow-hidden transition-all">
      <div className="overflow-x-auto">
        <table className="w-full text-left border-collapse">
          <thead>
            <tr className="bg-slate-50/80 border-b border-emerald-100 text-[11px] font-extrabold uppercase tracking-wider text-slate-500">
              <th className="py-3.5 px-4 w-12 text-center">No</th>
              <th className="py-3.5 px-4">Kode & Nama Kurikulum</th>
              <th className="py-3.5 px-4">Jenis & Jenjang</th>
              <th className="py-3.5 px-4">Unit Pendidikan</th>
              <th className="py-3.5 px-4">Tahun Ajaran</th>
              <th className="py-3.5 px-4 text-center">Status</th>
              <th className="py-3.5 px-4 text-right pr-6">Aksi</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100 text-xs text-slate-700 font-medium">
            {data.map((item, idx) => {
              const rowNumber = (page - 1) * perPage + idx + 1
              const isTerhapus = !!item.deleted_at

              return (
                <tr
                  key={item.id}
                  className={`hover:bg-emerald-50/40 transition-colors ${
                    isTerhapus ? 'bg-rose-50/30 opacity-75' : ''
                  }`}
                >
                  {/* No */}
                  <td className="py-3.5 px-4 text-center text-slate-400 font-bold">
                    {rowNumber}
                  </td>

                  {/* Kode & Nama */}
                  <td className="py-3.5 px-4">
                    <div className="flex flex-col">
                      <span className="font-mono text-[11px] font-bold text-emerald-800 bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-200 w-fit mb-1">
                        {item.kode_kurikulum}
                      </span>
                      <span className="font-bold text-slate-900 text-sm leading-snug">
                        {item.nama_kurikulum}
                      </span>
                    </div>
                  </td>

                  {/* Jenis & Jenjang */}
                  <td className="py-3.5 px-4">
                    <div className="flex items-center gap-2 flex-wrap">
                      <span
                        className={`inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-bold border ${getJenisBadgeColor(
                          item.jenis_kurikulum
                        )}`}
                      >
                        <Tag className="w-3 h-3" />
                        {item.jenis_kurikulum}
                      </span>
                      <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-extrabold bg-slate-100 text-slate-600 uppercase border border-slate-200">
                        {item.jenjang}
                      </span>
                    </div>
                  </td>

                  {/* Unit Pendidikan */}
                  <td className="py-3.5 px-4">
                    <div className="flex items-center gap-1.5 font-semibold text-slate-700">
                      <Building2 className="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                      <span>{item.unit_pendidikan_nama || '-'}</span>
                    </div>
                  </td>

                  {/* Tahun Ajaran */}
                  <td className="py-3.5 px-4">
                    <div className="flex items-center gap-1.5 font-semibold text-slate-700">
                      <Calendar className="w-3.5 h-3.5 text-slate-400 shrink-0" />
                      <span>{item.tahun_ajaran_nama || '-'}</span>
                    </div>
                    {item.semester_nama && (
                      <span className="text-[10px] text-slate-400 block mt-0.5">
                        {item.semester_nama}
                      </span>
                    )}
                  </td>

                  {/* Status */}
                  <td className="py-3.5 px-4 text-center">
                    {isTerhapus ? (
                      <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-rose-100 text-rose-700 border border-rose-200">
                        <XCircle className="w-3 h-3" /> Terhapus
                      </span>
                    ) : item.status ? (
                      <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-emerald-100 text-emerald-800 border border-emerald-200">
                        <CheckCircle className="w-3 h-3 text-emerald-600" /> Aktif
                      </span>
                    ) : (
                      <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-amber-100 text-amber-800 border border-amber-200">
                        <XCircle className="w-3 h-3 text-amber-600" /> Nonaktif
                      </span>
                    )}
                  </td>

                  {/* Action Buttons */}
                  <td className="py-3.5 px-4 text-right pr-6">
                    <div className="flex items-center justify-end gap-1.5">
                      {/* Detail */}
                      <button
                        onClick={() => onDetail(item)}
                        className="p-1.5 rounded-lg text-slate-500 hover:text-emerald-700 hover:bg-emerald-50 transition-colors"
                        title="Lihat Detail Detail Kurikulum"
                      >
                        <Eye className="w-4 h-4" />
                      </button>

                      {!isTerhapus ? (
                        <>
                          {/* Edit */}
                          <button
                            onClick={() => onEdit(item)}
                            className="p-1.5 rounded-lg text-slate-500 hover:text-blue-600 hover:bg-blue-50 transition-colors"
                            title="Edit Data Kurikulum"
                          >
                            <Edit className="w-4 h-4" />
                          </button>

                          {/* Hapus */}
                          <button
                            onClick={() => onDelete(item)}
                            className="p-1.5 rounded-lg text-slate-500 hover:text-rose-600 hover:bg-rose-50 transition-colors"
                            title="Hapus Data Kurikulum"
                          >
                            <Trash2 className="w-4 h-4" />
                          </button>
                        </>
                      ) : (
                        /* Pulihkan */
                        <button
                          onClick={() => onRestore(item)}
                          className="p-1.5 rounded-lg text-emerald-600 hover:bg-emerald-100 transition-colors"
                          title="Pulihkan Data Kurikulum"
                        >
                          <RotateCcw className="w-4 h-4" />
                        </button>
                      )}
                    </div>
                  </td>
                </tr>
              )
            })}
          </tbody>
        </table>
      </div>
    </div>
  )
}
