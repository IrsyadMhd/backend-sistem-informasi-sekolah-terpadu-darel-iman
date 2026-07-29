import React from 'react'
import {
  FaEye,
  FaEdit,
  FaTrash,
  FaUndo,
  FaCheckCircle,
  FaCalendarAlt,
  FaCalendarCheck,
  FaStar,
} from 'react-icons/fa'

export default function TahunAjaranTable({
  data = [],
  isLoading = false,
  page = 1,
  perPage = 15,
  onDetail,
  onEdit,
  onSetAktif,
  onDelete,
  onRestore,
}) {
  if (isLoading) {
    return (
      <div className="ui-enter overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm">
        <div className="animate-pulse space-y-4">
          <div className="h-10 bg-emerald-50 rounded-xl w-full"></div>
          {[...Array(5)].map((_, i) => (
            <div key={i} className="h-14 bg-gray-50 rounded-xl w-full flex items-center px-4 gap-4">
              <div className="w-8 h-4 bg-gray-200 rounded"></div>
              <div className="w-32 h-4 bg-gray-200 rounded"></div>
              <div className="w-48 h-4 bg-gray-200 rounded"></div>
              <div className="w-20 h-6 bg-gray-200 rounded-full"></div>
              <div className="ml-auto w-24 h-8 bg-gray-200 rounded-xl"></div>
            </div>
          ))}
        </div>
      </div>
    )
  }

  if (!data || data.length === 0) {
    return (
      <div className="ui-enter rounded-2xl border border-slate-200/80 bg-white p-12 text-center shadow-sm">
        <div className="w-16 h-16 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center mx-auto mb-4 border border-emerald-100">
          <FaCalendarAlt className="w-8 h-8" />
        </div>
        <h3 className="text-lg font-bold text-slate-800">Tahun ajaran tidak ditemukan</h3>
        <p className="mx-auto mt-1 max-w-sm text-sm text-slate-500">
          Ubah kata pencarian atau filter, lalu coba kembali.
        </p>
      </div>
    )
  }

  return (
    <div className="ui-enter overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm" style={{ animationDelay: '250ms' }}>
      <div className="overflow-x-auto">
        <table className="w-full text-left border-collapse">
          <thead>
            <tr className="border-b border-slate-200/80 bg-amber-50/60 text-[10px] font-black uppercase tracking-[0.12em] text-slate-700">
              <th className="py-4 px-4 text-center w-12">No</th>
              <th className="py-4 px-4">Nama Tahun Ajaran</th>
              <th className="py-4 px-4">Periode Tanggal</th>
              <th className="py-4 px-4 text-center">Status Keaktifan</th>
              <th className="py-4 px-4">Keterangan</th>
              <th className="py-4 px-4 text-center w-48">Aksi</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100 text-sm">
            {data.map((item, idx) => {
              const rowNumber = (page - 1) * perPage + idx + 1
              const isDeleted = !!item.deleted_at

              return (
                <tr
                  key={item.id}
                  className={`ui-row transition-colors duration-150 ${
                    isDeleted
                      ? 'bg-rose-50/40 text-gray-400'
                      : item.is_active
                      ? 'bg-emerald-50/30 hover:bg-emerald-50/60'
                      : 'hover:bg-gray-50/80'
                  }`}
                  style={{ animationDelay: `${Math.min(idx, 8) * 35}ms` }}
                >
                  {/* No */}
                  <td className="py-3.5 px-4 text-center font-semibold text-gray-500">
                    {rowNumber}
                  </td>

                  {/* Nama Tahun Ajaran */}
                  <td className="py-3.5 px-4 font-bold text-gray-900">
                    <div className="flex items-center gap-2">
                      <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-emerald-100 bg-emerald-50 text-emerald-800">
                        <FaCalendarCheck className="h-5 w-5 text-emerald-700" />
                      </span>
                      <div>
                        <span className="text-base text-gray-900 font-extrabold">{item.name}</span>
                        {item.is_active && (
                          <span className="ml-2 inline-flex items-center gap-1 rounded-full bg-emerald-700 px-2.5 py-1 text-[10px] font-bold text-white shadow-xs">
                            <FaStar className="h-2.5 w-2.5 text-amber-300" /> Periode Aktif
                          </span>
                        )}
                      </div>
                    </div>
                  </td>

                  {/* Periode Tanggal */}
                  <td className="py-3.5 px-4 font-medium text-gray-700 whitespace-nowrap">
                    <div className="flex items-center gap-1.5 text-xs">
                      <span className="px-2 py-1 rounded-lg bg-gray-100 text-gray-800 font-bold border border-gray-200">
                        {item.start_date || '-'}
                      </span>
                      <span className="text-gray-400 font-bold">s/d</span>
                      <span className="px-2 py-1 rounded-lg bg-gray-100 text-gray-800 font-bold border border-gray-200">
                        {item.end_date || '-'}
                      </span>
                    </div>
                  </td>

                  {/* Status Keaktifan */}
                  <td className="py-3.5 px-4 text-center">
                    {item.is_active ? (
                      <span className="inline-flex items-center gap-1.5 rounded-full border border-emerald-300 bg-emerald-100 px-3 py-1 text-xs font-extrabold text-emerald-800 shadow-xs">
                        <FaCheckCircle className="w-3.5 h-3.5 text-emerald-600" />
                        Aktif
                      </span>
                    ) : (
                      <span className="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-600 border border-gray-200">
                        Tidak Aktif
                      </span>
                    )}
                  </td>

                  {/* Keterangan */}
                  <td className="py-3.5 px-4 text-xs text-gray-600 max-w-xs truncate">
                    {item.keterangan || item.metadata?.keterangan || '-'}
                  </td>

                  {/* Aksi */}
                  <td className="py-3.5 px-4 text-center">
                    <div className="flex items-center justify-center gap-1.5">
                      {isDeleted ? (
                        <button
                          onClick={() => onRestore(item)}
                          title="Pulihkan Data"
                          aria-label={`Pulihkan tahun ajaran ${item.name}`}
                          className="ui-button rounded-xl border border-amber-200 bg-amber-50 p-2 text-amber-700 shadow-xs transition-all hover:bg-amber-100 focus:outline-none focus:ring-2 focus:ring-emerald-600"
                        >
                          <FaUndo className="w-3.5 h-3.5" />
                        </button>
                      ) : (
                        <>
                          {/* Set Aktif Button */}
                          {!item.is_active && (
                            <button
                              onClick={() => onSetAktif(item)}
                              title="Jadikan Tahun Ajaran Aktif Utama"
                              className="ui-button flex items-center gap-1 rounded-xl border border-emerald-200 bg-emerald-50 px-2.5 py-1.5 text-xs font-bold text-emerald-700 shadow-xs transition-all hover:bg-emerald-800 hover:text-white focus:outline-none focus:ring-2 focus:ring-emerald-600"
                            >
                              <FaStar className="w-3 h-3 text-amber-500" /> Aktifkan
                            </button>
                          )}

                          {/* Detail Button */}
                          <button
                            onClick={() => onDetail(item)}
                            title="Lihat Detail"
                            aria-label={`Lihat detail tahun ajaran ${item.name}`}
                            className="ui-button flex h-8 w-8 items-center justify-center rounded-lg border border-blue-200 bg-blue-50 text-blue-600 shadow-xs transition-all hover:border-blue-300 hover:bg-blue-100 hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500/40"
                          >
                            <FaEye className="h-4 w-4" />
                          </button>

                          {/* Edit Button */}
                          <button
                            onClick={() => onEdit(item)}
                            title="Edit Data"
                            aria-label={`Edit tahun ajaran ${item.name}`}
                            className="ui-button flex h-8 w-8 items-center justify-center rounded-lg border border-amber-200 bg-amber-50 text-amber-600 shadow-xs transition-all hover:border-amber-300 hover:bg-amber-100 hover:text-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500/40"
                          >
                            <FaEdit className="h-4 w-4" />
                          </button>

                          {/* Delete Button */}
                          <button
                            onClick={() => onDelete(item)}
                            title="Hapus Data"
                            aria-label={`Hapus tahun ajaran ${item.name}`}
                            className="ui-button flex h-8 w-8 items-center justify-center rounded-lg border border-red-200 bg-red-50 text-red-600 shadow-xs transition-all hover:border-red-300 hover:bg-red-100 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-500/40"
                          >
                            <FaTrash className="h-4 w-4" />
                          </button>
                        </>
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
