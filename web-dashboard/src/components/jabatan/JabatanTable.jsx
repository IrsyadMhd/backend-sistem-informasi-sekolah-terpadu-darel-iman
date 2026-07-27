import React from 'react'
import {
  FaEdit,
  FaTrash,
  FaRedo,
  FaEye,
  FaUsers,
  FaSitemap,
  FaLockOpen,
  FaLock,
} from 'react-icons/fa'

export default function JabatanTable({
  data = [],
  isLoading = false,
  onDetail,
  onEdit,
  onDelete,
  onRestore,
}) {
  if (isLoading) {
    return (
      <div className="w-full p-12 text-center bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800">
        <div className="inline-block animate-spin rounded-full h-8 w-8 border-4 border-blue-600 border-t-transparent mb-3"></div>
        <p className="text-sm font-medium text-slate-500 dark:text-slate-400">Memuat data master jabatan...</p>
      </div>
    )
  }

  if (!data || data.length === 0) {
    return (
      <div className="w-full p-12 text-center bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800">
        <p className="text-base font-semibold text-slate-700 dark:text-slate-300">Tidak ada data jabatan ditemukan</p>
        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
          Coba sesuaikan kata kunci pencarian atau kriteria filter yang diterapkan.
        </p>
      </div>
    )
  }

  return (
    <div className="w-full overflow-x-auto rounded-[20px] border border-slate-200/90 bg-white shadow-sm">
      <table className="w-full text-left border-collapse text-slate-800">
        <thead>
          <tr className="bg-[#f4efe6] border-b border-slate-200/90 text-xs font-black uppercase tracking-wider text-slate-700">
            <th className="py-3.5 px-4 w-12 text-center">NO</th>
            <th className="py-3.5 px-4">KODE & NAMA JABATAN</th>
            <th className="py-3.5 px-4">LEVEL & UNIT SEKOLAH</th>
            <th className="py-3.5 px-4">ATASAN LANGSUNG</th>
            <th className="py-3.5 px-4 text-center">STRUKTUR & LOGIN</th>
            <th className="py-3.5 px-4 text-center">STATUS</th>
            <th className="py-3.5 px-4 text-center">PEGAWAI</th>
            <th className="py-3.5 px-4 text-center w-36">AKSI</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-200 dark:divide-slate-800 text-sm">
          {data.map((item, index) => {
            const isTrashed = item.terhapus
            const badgeWarna = item.warna || '#3B82F6'

            return (
              <tr
                key={item.id}
                className={`transition-colors hover:bg-slate-50/60 dark:hover:bg-slate-800/40 ${
                  isTrashed ? 'bg-rose-50/40 dark:bg-rose-950/10 opacity-75' : ''
                }`}
              >
                {/* No & Urutan */}
                <td className="py-3.5 px-4 text-center font-medium text-slate-500 text-xs">
                  {item.urutan ?? index + 1}
                </td>

                {/* Kode & Nama Jabatan */}
                <td className="py-3.5 px-4">
                  <div className="flex items-center space-x-3">
                    <span
                      className="w-3 h-3 rounded-full flex-shrink-0"
                      style={{ backgroundColor: badgeWarna }}
                      title={`Kode Warna: ${badgeWarna}`}
                    />
                    <div>
                      <div className="font-bold text-slate-900 dark:text-slate-100 flex items-center space-x-2">
                        <span>{item.nama_jabatan || item.name}</span>
                        {isTrashed && (
                          <span className="px-1.5 py-0.5 text-[10px] font-semibold bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-400 rounded">
                            Terhapus
                          </span>
                        )}
                      </div>
                      <span className="text-xs font-mono text-slate-500 dark:text-slate-400">
                        {item.kode_jabatan || item.code}
                      </span>
                    </div>
                  </div>
                </td>

                {/* Level & Unit Sekolah */}
                <td className="py-3.5 px-4">
                  <div className="space-y-1">
                    <span className="inline-block px-2.5 py-0.5 text-xs font-semibold rounded-full bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                      Level {item.level_jabatan}: {item.level_label}
                    </span>
                    <p className="text-xs text-slate-500 dark:text-slate-400">
                      {item.unit_sekolah ? (
                        <span className="font-medium text-blue-600 dark:text-blue-400">
                          {item.unit_sekolah.nama} ({item.unit_sekolah.kode})
                        </span>
                      ) : (
                        <span className="italic text-slate-400">Seluruh Unit / Yayasan</span>
                      )}
                    </p>
                  </div>
                </td>

                {/* Atasan Langsung */}
                <td className="py-3.5 px-4 text-xs">
                  {item.atasan_langsung ? (
                    <div className="font-medium text-slate-800 dark:text-slate-200">
                      {item.atasan_langsung.nama_jabatan}
                      <span className="block text-[11px] text-slate-500 font-mono">
                        ({item.atasan_langsung.kode_jabatan})
                      </span>
                    </div>
                  ) : (
                    <span className="text-slate-400 italic">Pimpinan Tertinggi</span>
                  )}
                </td>

                {/* Struktur & Login */}
                <td className="py-3.5 px-4 text-center">
                  <div className="flex items-center justify-center space-x-2">
                    {/* Tampil Struktur */}
                    <span
                      className={`inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium ${
                        item.tampil_struktur
                          ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300 border border-blue-200 dark:border-blue-800'
                          : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'
                      }`}
                      title="Visibilitas Bagan Struktur Organisasi"
                    >
                      <FaSitemap className="w-3 h-3 mr-1" />
                      {item.tampil_struktur ? 'Struktur' : 'Sembunyi'}
                    </span>

                    {/* Boleh Login */}
                    <span
                      className={`inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium ${
                        item.boleh_login
                          ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800'
                          : 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300 border border-amber-200 dark:border-amber-800'
                      }`}
                      title="Hak Akses Login Akun Sistem"
                    >
                      {item.boleh_login ? <FaLockOpen className="w-3 h-3 mr-1" /> : <FaLock className="w-3 h-3 mr-1" />}
                      {item.boleh_login ? 'Login' : 'Non-Login'}
                    </span>
                  </div>
                </td>

                {/* Status */}
                <td className="py-3.5 px-4 text-center">
                  <span
                    className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold ${
                      item.status === 'Aktif' || item.is_active
                        ? 'bg-[#dcfce7] text-[#15803d] border border-emerald-200'
                        : 'bg-slate-100 text-slate-600 border border-slate-200'
                    }`}
                  >
                    <span
                      className={`w-1.5 h-1.5 rounded-full ${
                        item.status === 'Aktif' || item.is_active ? 'bg-[#16a34a]' : 'bg-slate-400'
                      }`}
                    />
                    {item.status || (item.is_active ? 'Aktif' : 'Nonaktif')}
                  </span>
                </td>

                {/* Jumlah Pegawai */}
                <td className="py-3.5 px-4 text-center">
                  <span className="inline-flex items-center font-extrabold text-xs text-slate-700 bg-slate-100 px-2.5 py-1 rounded-lg">
                    <FaUsers className="w-3.5 h-3.5 mr-1 text-slate-400" />
                    {item.jumlah_pegawai ?? 0}
                  </span>
                </td>

                {/* Actions */}
                <td className="py-3.5 px-4 text-center">
                  <div className="flex items-center justify-center gap-1.5">
                    <button
                      onClick={() => onDetail(item)}
                      className="flex h-8 w-8 items-center justify-center rounded-lg border border-blue-200 bg-[#eff6ff] text-[#2563eb] hover:bg-blue-100 transition-colors"
                      title="Lihat Detail Jabatan"
                    >
                      <FaEye className="w-3.5 h-3.5" />
                    </button>

                    {!isTrashed ? (
                      <>
                        <button
                          onClick={() => onEdit(item)}
                          className="flex h-8 w-8 items-center justify-center rounded-lg border border-amber-200 bg-[#fffbe6] text-[#d97706] hover:bg-amber-100 transition-colors"
                          title="Edit Jabatan"
                        >
                          <FaEdit className="w-3.5 h-3.5" />
                        </button>
                        <button
                          onClick={() => onDelete(item)}
                          className="flex h-8 w-8 items-center justify-center rounded-lg border border-red-200 bg-[#fef2f2] text-[#dc2626] hover:bg-red-100 transition-colors"
                          title="Hapus Jabatan"
                        >
                          <FaTrash className="w-3.5 h-3.5" />
                        </button>
                      </>
                    ) : (
                      <button
                        onClick={() => onRestore(item)}
                        className="flex h-8 w-8 items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-600 hover:bg-emerald-100 transition-colors"
                        title="Pulihkan Jabatan"
                      >
                        <FaRedo className="w-3.5 h-3.5" />
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
  )
}
