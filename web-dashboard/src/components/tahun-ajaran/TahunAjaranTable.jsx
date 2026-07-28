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
      <div className="bg-white rounded-2xl shadow-sm border border-emerald-100 p-6 overflow-hidden">
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
      <div className="bg-white rounded-2xl shadow-sm border border-emerald-100 p-12 text-center">
        <div className="w-16 h-16 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center mx-auto mb-4 border border-emerald-100">
          <FaCalendarAlt className="w-8 h-8" />
        </div>
        <h3 className="text-lg font-bold text-gray-800">Tidak ada data Tahun Ajaran</h3>
        <p className="text-sm text-gray-500 mt-1 max-w-sm mx-auto">
          Belum ada data tahun ajaran yang sesuai dengan kriteria pencarian atau filter yang Anda pilih.
        </p>
      </div>
    )
  }

  return (
    <div className="bg-white rounded-2xl shadow-sm border border-emerald-100 overflow-hidden">
      <div className="overflow-x-auto">
        <table className="w-full text-left border-collapse">
          <thead>
            <tr className="bg-gradient-to-r from-emerald-50 via-emerald-50/60 to-white border-b border-emerald-100 text-xs font-bold text-emerald-900 uppercase tracking-wider">
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
                  className={`transition-colors duration-150 ${
                    isDeleted
                      ? 'bg-rose-50/40 text-gray-400'
                      : item.is_active
                      ? 'bg-emerald-50/30 hover:bg-emerald-50/60'
                      : 'hover:bg-gray-50/80'
                  }`}
                >
                  {/* No */}
                  <td className="py-3.5 px-4 text-center font-semibold text-gray-500">
                    {rowNumber}
                  </td>

                  {/* Nama Tahun Ajaran */}
                  <td className="py-3.5 px-4 font-bold text-gray-900">
                    <div className="flex items-center gap-2">
                      <span className="p-2 rounded-xl bg-emerald-100/70 text-emerald-800 border border-emerald-200/60">
                        <FaCalendarCheck className="w-4 h-4 text-emerald-700" />
                      </span>
                      <div>
                        <span className="text-base text-gray-900 font-extrabold">{item.name}</span>
                        {item.is_active && (
                          <span className="ml-2 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-600 text-white shadow-xs">
                            <FaStar className="w-2.5 h-2.5" /> Periode Aktif
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
                      <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-extrabold bg-emerald-100 text-emerald-800 border border-emerald-300 shadow-xs">
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
                          className="p-2 rounded-xl bg-amber-50 text-amber-600 hover:bg-amber-100 border border-amber-200 transition-all active:scale-95 shadow-xs"
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
                              className="px-2.5 py-1.5 rounded-xl bg-emerald-50 hover:bg-emerald-600 text-emerald-700 hover:text-white font-bold text-xs border border-emerald-200 transition-all active:scale-95 shadow-xs flex items-center gap-1"
                            >
                              <FaStar className="w-3 h-3 text-amber-500" /> Aktifkan
                            </button>
                          )}

                          {/* Detail Button */}
                          <button
                            onClick={() => onDetail(item)}
                            title="Lihat Detail"
                            className="p-2 rounded-xl bg-gray-50 hover:bg-emerald-50 text-gray-600 hover:text-emerald-700 border border-gray-200 transition-all active:scale-95 shadow-xs"
                          >
                            <FaEye className="w-3.5 h-3.5" />
                          </button>

                          {/* Edit Button */}
                          <button
                            onClick={() => onEdit(item)}
                            title="Edit Data"
                            className="p-2 rounded-xl bg-blue-50 hover:bg-blue-100 text-blue-600 border border-blue-200 transition-all active:scale-95 shadow-xs"
                          >
                            <FaEdit className="w-3.5 h-3.5" />
                          </button>

                          {/* Delete Button */}
                          <button
                            onClick={() => onDelete(item)}
                            title="Hapus Data"
                            className="p-2 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-600 border border-rose-200 transition-all active:scale-95 shadow-xs"
                          >
                            <FaTrash className="w-3.5 h-3.5" />
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
