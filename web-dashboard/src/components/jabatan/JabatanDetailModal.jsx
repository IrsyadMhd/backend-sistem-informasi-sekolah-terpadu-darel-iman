import React from 'react'
import { FaTimes, FaBriefcase, FaUsers, FaUserEdit, FaTag } from 'react-icons/fa'

export default function JabatanDetailModal({ isOpen, onClose, jabatan = null }) {
  if (!isOpen || !jabatan) return null

  const badgeWarna = jabatan.warna || '#3B82F6'

  return (
    <div className="ui-backdrop fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-900/60 p-4 backdrop-blur-xs" role="dialog" aria-modal="true" aria-labelledby="detail-jabatan-title">
      <div className="ui-modal my-8 w-full max-w-2xl overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-2xl">
        {/* Header Bar (Persis UI UX Referensi) */}
        <div className="flex items-center justify-between px-7 py-5 border-b border-slate-100 bg-white">
          <div className="flex items-center space-x-3">
            <div
              className="w-10 h-10 rounded-xl flex items-center justify-center text-white shadow-md font-bold text-lg shrink-0"
              style={{ backgroundColor: badgeWarna }}
            >
              <FaBriefcase className="w-5 h-5" />
            </div>
            <div>
              <h3 id="detail-jabatan-title" className="flex items-center space-x-2 text-xl font-black text-slate-800">
                <span>{jabatan.nama_jabatan || jabatan.name}</span>
                <span className="text-xs font-mono px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-700 font-bold">
                  {jabatan.kode_jabatan || jabatan.code}
                </span>
              </h3>
              <p className="text-xs text-slate-500 mt-0.5">
                Detail atribut master jabatan & riwayat audit trail.
              </p>
            </div>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="rounded-full p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors"
            title="Tutup detail jabatan"
            aria-label="Tutup detail jabatan"
          >
            <FaTimes className="w-4 h-4" />
          </button>
        </div>

        {/* Content Body */}
        <div className="p-7 space-y-6 max-h-[75vh] overflow-y-auto">
          {/* Status Bar */}
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 bg-[#f8fafc] p-4 rounded-2xl border border-slate-200/90">
            <div>
              <span className="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Status</span>
              <span className={`inline-block mt-1 px-3 py-0.5 rounded-full text-xs font-extrabold ${
                jabatan.status === 'Aktif' || jabatan.is_active
                  ? 'bg-[#dcfce7] text-[#15803d] border border-emerald-200'
                  : 'bg-slate-200 text-slate-700'
              }`}>
                {jabatan.status || (jabatan.is_active ? 'Aktif' : 'Nonaktif')}
              </span>
            </div>

            <div>
              <span className="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Level Hirarki</span>
              <span className="text-xs font-extrabold text-slate-900 block mt-1">
                Level {jabatan.level_jabatan}: {jabatan.level_label}
              </span>
            </div>

            <div>
              <span className="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Bagan Struktur</span>
              <span className="text-xs font-bold text-blue-600 block mt-1">
                {jabatan.tampil_struktur ? 'Ditampilkan' : 'Disembunyikan'}
              </span>
            </div>

            <div>
              <span className="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Login Sistem</span>
              <span className={`text-xs font-extrabold block mt-1 ${
                jabatan.boleh_login ? 'text-emerald-700' : 'text-amber-700'
              }`}>
                {jabatan.boleh_login ? 'Diizinkan Login' : 'Hanya Struktural'}
              </span>
            </div>
          </div>

          {/* Detailed Info Grid */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div className="space-y-3">
              <h4 className="text-xs font-bold uppercase tracking-wider text-slate-500 flex items-center space-x-1.5">
                <FaTag className="w-3.5 h-3.5 text-[#054e3b]" />
                <span>Atribut Jabatan</span>
              </h4>

	              <div className="bg-white rounded-2xl p-4 border border-slate-200/90 space-y-2 text-xs">
	                <div className="flex justify-between border-b border-slate-100 pb-2">
	                  <span className="text-slate-500">Satuan Kerja:</span>
	                  <span className="font-bold text-slate-800">{jabatan.satuan_kerja || '-'}</span>
	                </div>
	                <div className="flex justify-between border-b border-slate-100 pb-2">
	                  <span className="text-slate-500">Cakupan Akses:</span>
	                  <span className="font-bold text-slate-800">{jabatan.scope_akses_label || '-'}</span>
	                </div>
                <div className="flex justify-between border-b border-slate-100 pb-2">
                  <span className="text-slate-500">Unit Sekolah:</span>
                  <span className="font-bold text-slate-800">
                    {jabatan.unit_sekolah ? `${jabatan.unit_sekolah.nama} (${jabatan.unit_sekolah.kode})` : 'Seluruh Unit / Yayasan'}
                  </span>
                </div>
                <div className="flex justify-between border-b border-slate-100 pb-2">
                  <span className="text-slate-500">Atasan Langsung:</span>
                  <span className="font-bold text-slate-800">
	                    {jabatan.atasan_pegawai?.nama_pegawai || (jabatan.atasan_langsung ? jabatan.atasan_langsung.nama_jabatan : 'Pimpinan Tertinggi')}
                  </span>
                </div>
                <div className="flex justify-between border-b border-slate-100 pb-2">
                  <span className="text-slate-500">Role Sistem:</span>
                  <span className="font-bold text-slate-800">
                    {jabatan.role_sistem?.name || 'Default / Manual'}
                  </span>
                </div>
                <div className="flex justify-between">
                  <span className="text-slate-500">Urutan Tampilan:</span>
                  <span className="font-bold text-slate-800">
                    Ke-{jabatan.urutan ?? 0}
                  </span>
                </div>
              </div>
            </div>

            <div className="space-y-3">
              <h4 className="text-xs font-bold uppercase tracking-wider text-slate-500 flex items-center space-x-1.5">
                <FaUsers className="w-3.5 h-3.5 text-[#054e3b]" />
                <span>Statistik Pemakaian</span>
              </h4>

              <div className="bg-white rounded-2xl p-4 border border-slate-200/90 space-y-2 text-xs">
                <div className="flex justify-between border-b border-slate-100 pb-2">
                  <span className="text-slate-500">Total Pegawai Menjabat:</span>
                  <span className="font-extrabold text-[#054e3b]">
                    {jabatan.jumlah_pegawai ?? 0} Orang
                  </span>
                </div>
                <div className="flex justify-between border-b border-slate-100 pb-2">
                  <span className="text-slate-500">Pilihan Ikon:</span>
                  <span className="font-bold font-mono text-slate-800">
                    {jabatan.ikon || 'UserCheck'}
                  </span>
                </div>
                <div className="flex justify-between">
                  <span className="text-slate-500">Kode Warna Hex:</span>
                  <span className="font-bold font-mono text-slate-800 flex items-center">
                    <span className="w-3 h-3 rounded-full mr-1.5" style={{ backgroundColor: badgeWarna }} />
                    {badgeWarna}
                  </span>
                </div>
              </div>
            </div>
          </div>

          {/* Deskripsi */}
          <div>
            <h4 className="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">
              Deskripsi & Job Description
            </h4>
            <div className="p-4 bg-[#f8fafc] rounded-2xl border border-slate-200/90 text-xs text-slate-700 leading-relaxed font-medium">
              {jabatan.deskripsi || jabatan.description || 'Tidak ada deskripsi rinci yang dimasukkan.'}
            </div>
          </div>

          {/* Audit Trail Section */}
          <div className="border-t border-slate-100 pt-4 space-y-2">
            <h4 className="text-xs font-bold uppercase tracking-wider text-slate-500 flex items-center space-x-1.5">
              <FaUserEdit className="w-3.5 h-3.5 text-[#054e3b]" />
              <span>Audit Trail (Riwayat Pembuatan)</span>
            </h4>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs bg-[#f8fafc] p-4 rounded-2xl border border-slate-200/90">
              <div>
                <span className="text-slate-400 block text-[10px] font-medium">Dibuat Pada:</span>
                <span className="font-bold text-slate-800">
                  {jabatan.created_at ? new Date(jabatan.created_at).toLocaleString('id-ID') : '-'}
                </span>
                {jabatan.created_by?.name && (
                  <span className="block text-[11px] text-slate-500 font-semibold">Oleh: {jabatan.created_by.name}</span>
                )}
              </div>
              <div>
                <span className="text-slate-400 block text-[10px] font-medium">Terakhir Diperbarui:</span>
                <span className="font-bold text-slate-800">
                  {jabatan.updated_at ? new Date(jabatan.updated_at).toLocaleString('id-ID') : '-'}
                </span>
                {jabatan.updated_by?.name && (
                  <span className="block text-[11px] text-slate-500 font-semibold">Oleh: {jabatan.updated_by.name}</span>
                )}
              </div>
            </div>
          </div>
        </div>

        {/* Footer Bar */}
        <div className="px-7 py-4 border-t border-slate-100 bg-white flex justify-end">
          <button
            type="button"
            onClick={onClose}
            className="ui-button rounded-xl bg-emerald-800 px-6 py-2.5 text-xs font-semibold text-white shadow-md shadow-emerald-800/20 hover:bg-emerald-900"
          >
            Tutup
          </button>
        </div>
      </div>
    </div>
  )
}
