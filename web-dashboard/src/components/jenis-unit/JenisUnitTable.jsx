import React from 'react'
import {
  FaEye,
  FaEdit,
  FaTrash,
  FaUndo,
  FaSchool,
  FaBuilding,
  FaBook,
  FaMosque,
  FaGraduationCap,
  FaUniversity,
  FaChild,
  FaHome,
} from 'react-icons/fa'

const ICON_MAP = {
  Building: FaBuilding,
  School: FaSchool,
  Book: FaBook,
  Mosque: FaMosque,
  Graduation: FaGraduationCap,
  University: FaUniversity,
  Children: FaChild,
  Home: FaHome,
}

export function renderJenisUnitIcon(iconName, className = 'w-4 h-4') {
  const IconComp = ICON_MAP[iconName] || FaSchool
  return <IconComp className={className} />
}

export default function JenisUnitTable({
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
      <div className="p-8 text-center bg-white rounded-xl shadow-sm border border-emerald-100">
        <div className="inline-block animate-spin rounded-full h-8 w-8 border-4 border-emerald-500 border-t-transparent"></div>
        <p className="mt-3 text-sm font-medium text-emerald-700">Memuat data Jenis Unit Pendidikan...</p>
      </div>
    )
  }

  if (data.length === 0) {
    return (
      <div className="p-12 text-center bg-white rounded-xl shadow-sm border border-emerald-100">
        <div className="w-16 h-16 mx-auto mb-4 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-500">
          <FaSchool className="w-8 h-8" />
        </div>
        <h3 className="text-base font-semibold text-gray-800">Data Tidak Ditemukan</h3>
        <p className="text-sm text-gray-500 mt-1">Belum ada data jenis unit pendidikan yang sesuai dengan kriteria.</p>
      </div>
    )
  }

  return (
    <div className="bg-white rounded-xl shadow-sm border border-emerald-100 overflow-hidden">
      <div className="overflow-x-auto">
        <table className="w-full text-left text-sm border-collapse">
          <thead>
            <tr className="bg-emerald-800 text-white font-semibold uppercase text-xs tracking-wider">
              <th className="py-3.5 px-4 text-center w-12">NO</th>
              <th className="py-3.5 px-4">KODE JENIS</th>
              <th className="py-3.5 px-4">NAMA JENIS UNIT</th>
              <th className="py-3.5 px-4">SINGKATAN</th>
              <th className="py-3.5 px-4">JENJANG</th>
              <th className="py-3.5 px-4 text-center">WARNA BADGE</th>
              <th className="py-3.5 px-4 text-center">ICON</th>
              <th className="py-3.5 px-4 text-center">URUTAN</th>
              <th className="py-3.5 px-4 text-center">STATUS</th>
              <th className="py-3.5 px-4">KETERANGAN</th>
              <th className="py-3.5 px-4">TANGGAL DIBUAT</th>
              <th className="py-3.5 px-4 text-center">AKSI</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100 text-gray-700">
            {data.map((item, index) => {
              const rowNo = (page - 1) * perPage + index + 1
              const badgeColor = item.warna_badge || '#10B981'

              return (
                <tr
                  key={item.id || item.uuid}
                  className={`hover:bg-emerald-50/50 transition-colors ${
                    item.is_deleted ? 'bg-red-50/40 opacity-75' : ''
                  }`}
                >
                  <td className="py-3.5 px-4 text-center font-medium text-gray-500">{rowNo}</td>
                  <td className="py-3.5 px-4 font-bold text-emerald-800 tracking-wide">
                    {item.kode_jenis}
                  </td>
                  <td className="py-3.5 px-4 font-semibold text-gray-900">{item.nama_jenis}</td>
                  <td className="py-3.5 px-4 font-medium text-gray-600">{item.singkatan || '-'}</td>
                  <td className="py-3.5 px-4">
                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200">
                      {item.jenjang}
                    </span>
                  </td>
                  <td className="py-3.5 px-4 text-center">
                    <div className="flex items-center justify-center gap-1.5">
                      <span
                        className="w-4 h-4 rounded-full border border-gray-200 shadow-xs inline-block"
                        style={{ backgroundColor: badgeColor }}
                      ></span>
                      <span className="text-xs font-mono text-gray-500 uppercase">{badgeColor}</span>
                    </div>
                  </td>
                  <td className="py-3.5 px-4 text-center">
                    <div className="inline-flex p-1.5 rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-100">
                      {renderJenisUnitIcon(item.icon, 'w-4 h-4')}
                    </div>
                  </td>
                  <td className="py-3.5 px-4 text-center font-bold text-gray-700">{item.urutan}</td>
                  <td className="py-3.5 px-4 text-center">
                    {item.status ? (
                      <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700 border border-emerald-200">
                        • Aktif
                      </span>
                    ) : (
                      <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600 border border-gray-200">
                        • Tidak Aktif
                      </span>
                    )}
                  </td>
                  <td className="py-3.5 px-4 max-w-xs truncate text-xs text-gray-500" title={item.keterangan}>
                    {item.keterangan || '-'}
                  </td>
                  <td className="py-3.5 px-4 text-xs text-gray-500 whitespace-nowrap">
                    {item.created_at || '-'}
                  </td>
                  <td className="py-3.5 px-4 text-center whitespace-nowrap">
                    <div className="flex items-center justify-center gap-1.5">
                      <button
                        onClick={() => onDetail && onDetail(item)}
                        className="p-1.5 rounded-lg bg-sky-50 text-sky-600 hover:bg-sky-100 hover:text-sky-700 transition-colors"
                        title="👁 Detail"
                      >
                        <FaEye className="w-4 h-4" />
                      </button>
                      {!item.is_deleted ? (
                        <>
                          <button
                            onClick={() => onEdit && onEdit(item)}
                            className="p-1.5 rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-100 hover:text-amber-700 transition-colors"
                            title="✏ Edit"
                          >
                            <FaEdit className="w-4 h-4" />
                          </button>
                          <button
                            onClick={() => onDelete && onDelete(item)}
                            className="p-1.5 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-100 hover:text-rose-700 transition-colors"
                            title="🗑 Hapus"
                          >
                            <FaTrash className="w-4 h-4" />
                          </button>
                        </>
                      ) : (
                        <button
                          onClick={() => onRestore && onRestore(item)}
                          className="p-1.5 rounded-lg bg-emerald-50 text-emerald-600 hover:bg-emerald-100 hover:text-emerald-700 transition-colors"
                          title="Pulihkan Data"
                        >
                          <FaUndo className="w-4 h-4" />
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
