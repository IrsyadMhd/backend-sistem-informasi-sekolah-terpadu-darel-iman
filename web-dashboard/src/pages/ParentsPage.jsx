import { useState } from 'react'
import {
  FaPlus,
  FaSearch,
  FaFilter,
  FaUserFriends,
  FaPhoneAlt,
  FaUserGraduate,
  FaCheckCircle,
  FaEye,
  FaEdit,
  FaTrash,
  FaTimes,
  FaDownload,
  FaBriefcase,
  FaMapMarkerAlt,
} from 'react-icons/fa'
import Swal from 'sweetalert2'

const initialParents = [
  {
    id: 1,
    nama: 'Budi Santoso, S.T.',
    hubungan: 'Ayah',
    namaSiswa: 'Ahmad Raihan',
    kelasSiswa: '7-A',
    unitPendidikan: 'SMP Islam Terpadu',
    pekerjaan: 'Wiraswasta / Pengusaha',
    noHp: '0812-3456-7890',
    email: 'budi.santoso@gmail.com',
    alamat: 'Jl. Melati No. 12, Kebayoran Baru, Jakarta Selatan',
    status: 'Aktif',
  },
  {
    id: 2,
    nama: 'Siti Aminah, S.Pd.',
    hubungan: 'Ibu',
    namaSiswa: 'Aisyah Putri',
    kelasSiswa: '8-B',
    unitPendidikan: 'SMP Islam Terpadu',
    pekerjaan: 'Guru / Pendidik',
    noHp: '0813-9876-5432',
    email: 'siti.aminah@yahoo.com',
    alamat: 'Jl. Mawar No. 45, Tebet, Jakarta Selatan',
    status: 'Aktif',
  },
  {
    id: 3,
    nama: 'H. Hendra Wijaya',
    hubungan: 'Ayah',
    namaSiswa: 'Farhan Azhar',
    kelasSiswa: '10-IPA-1',
    unitPendidikan: 'SMA Islam Terpadu',
    pekerjaan: 'Pegawai Negeri Sipil',
    noHp: '0857-1122-3344',
    email: 'hendra.wijaya@gov.id',
    alamat: 'Jl. Anggrek Raya No. 8, Cilandak, Jakarta Selatan',
    status: 'Aktif',
  },
  {
    id: 4,
    nama: 'Dr. Ratna Sari',
    hubungan: 'Ibu',
    namaSiswa: 'Zahra Nabila',
    kelasSiswa: '11-IPA-2',
    unitPendidikan: 'SMA Islam Terpadu',
    pekerjaan: 'Dokter Spesialis',
    noHp: '0811-2233-4455',
    email: 'ratna.sari@klinik.co.id',
    alamat: 'Jl. Kemang Raya No. 102, Pasar Minggu, Jakarta Selatan',
    status: 'Aktif',
  },
  {
    id: 5,
    nama: 'Drs. H. Bambang Kusuma',
    hubungan: 'Wali',
    namaSiswa: 'Muhammad Rizky',
    kelasSiswa: '5-A',
    unitPendidikan: 'SD Islam Terpadu',
    pekerjaan: 'Pensiun / Karyawan',
    noHp: '0878-5566-7788',
    email: 'bambang.kusuma@gmail.com',
    alamat: 'Jl. Dahlia No. 19, Pancoran, Jakarta Selatan',
    status: 'Aktif',
  },
]

export default function ParentsPage() {
  const [parents, setParents] = useState(initialParents)
  const [searchTerm, setSearchTerm] = useState('')
  const [filterHubungan, setFilterHubungan] = useState('Semua')
  const [filterUnit, setFilterUnit] = useState('Semua')

  // Modal States
  const [selectedParent, setSelectedParent] = useState(null)
  const [isDetailOpen, setIsDetailOpen] = useState(false)
  const [isFormOpen, setIsFormOpen] = useState(false)
  const [isEditing, setIsEditing] = useState(false)

  const [formData, setFormData] = useState({
    id: null,
    nama: '',
    hubungan: 'Ayah',
    namaSiswa: '',
    kelasSiswa: '',
    unitPendidikan: 'SMP Islam Terpadu',
    pekerjaan: '',
    noHp: '',
    email: '',
    alamat: '',
    status: 'Aktif',
  })

  // Filtered Parents
  const filteredParents = parents.filter((p) => {
    const matchSearch =
      p.nama.toLowerCase().includes(searchTerm.toLowerCase()) ||
      p.namaSiswa.toLowerCase().includes(searchTerm.toLowerCase()) ||
      p.noHp.includes(searchTerm)
    const matchHubungan = filterHubungan === 'Semua' || p.hubungan === filterHubungan
    const matchUnit = filterUnit === 'Semua' || p.unitPendidikan === filterUnit
    return matchSearch && matchHubungan && matchUnit
  })

  // Handlers
  const handleOpenDetail = (parent) => {
    setSelectedParent(parent)
    setIsDetailOpen(true)
  }

  const handleOpenCreate = () => {
    setFormData({
      id: null,
      nama: '',
      hubungan: 'Ayah',
      namaSiswa: '',
      kelasSiswa: '',
      unitPendidikan: 'SMP Islam Terpadu',
      pekerjaan: '',
      noHp: '',
      email: '',
      alamat: '',
      status: 'Aktif',
    })
    setIsEditing(false)
    setIsFormOpen(true)
  }

  const handleOpenEdit = (parent) => {
    setFormData(parent)
    setIsEditing(true)
    setIsFormOpen(true)
  }

  const handleDelete = async (id, nama) => {
    const result = await Swal.fire({
      title: 'Hapus Data Orang Tua?',
      text: `Apakah Anda yakin ingin menghapus data "${nama}"?`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#ef4444',
      cancelButtonColor: '#64748b',
      confirmButtonText: 'Ya, Hapus!',
      cancelButtonText: 'Batal',
    })

    if (result.isConfirmed) {
      setParents((prev) => prev.filter((item) => item.id !== id))
      Swal.fire('Terhapus!', 'Data orang tua/wali berhasil dihapus.', 'success')
    }
  }

  const handleFormSubmit = (e) => {
    e.preventDefault()
    if (!formData.nama || !formData.namaSiswa || !formData.noHp) {
      Swal.fire('Peringatan', 'Nama, Nama Siswa, dan No. HP wajib diisi.', 'warning')
      return
    }

    if (isEditing) {
      setParents((prev) => prev.map((item) => (item.id === formData.id ? formData : item)))
      Swal.fire('Berhasil', 'Data orang tua/wali berhasil diperbarui.', 'success')
    } else {
      const newParent = {
        ...formData,
        id: Date.now(),
      }
      setParents((prev) => [newParent, ...prev])
      Swal.fire('Berhasil', 'Data orang tua/wali baru berhasil ditambahkan.', 'success')
    }
    setIsFormOpen(false)
  }

  const handleExport = () => {
    Swal.fire('Export Data', 'Data Orang Tua / Wali berhasil di-export ke format Excel.', 'success')
  }

  return (
    <section className="space-y-6">
      {/* Header Banner */}
      <div className="bg-gradient-to-r from-emerald-800 to-teal-700 rounded-2xl p-6 text-white shadow-lg">
        <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
          <div>
            <span className="bg-emerald-600/50 text-emerald-100 text-xs font-semibold px-3 py-1 rounded-full uppercase tracking-wider">
              Master Data Sekolah
            </span>
            <h1 className="text-2xl md:text-3xl font-bold mt-2">Data Orang Tua / Wali</h1>
            <p className="text-emerald-100 text-sm mt-1">
              Manajemen informasi wali murid, kontak darurat, pekerjaan, dan relasi siswa terpaut.
            </p>
          </div>
          <div className="flex items-center gap-2">
            <button
              onClick={handleExport}
              className="bg-white/10 hover:bg-white/20 text-white font-medium px-4 py-2.5 rounded-xl border border-white/20 transition flex items-center gap-2 text-sm backdrop-blur-sm"
            >
              <FaDownload /> Export Excel
            </button>
            <button
              onClick={handleOpenCreate}
              className="bg-emerald-500 hover:bg-emerald-400 text-white font-semibold px-5 py-2.5 rounded-xl transition flex items-center gap-2 text-sm shadow-md"
            >
              <FaPlus /> Tambah Orang Tua
            </button>
          </div>
        </div>
      </div>

      {/* Quick Summary Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center gap-4">
          <div className="w-12 h-12 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center text-xl font-bold">
            <FaUserFriends />
          </div>
          <div>
            <p className="text-xs text-slate-500 font-medium">Total Wali Murid</p>
            <h3 className="text-2xl font-bold text-slate-800">{parents.length}</h3>
            <span className="text-[11px] text-emerald-600 font-medium">Terdaftar di sistem</span>
          </div>
        </div>

        <div className="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center gap-4">
          <div className="w-12 h-12 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center text-xl font-bold">
            <FaUserGraduate />
          </div>
          <div>
            <p className="text-xs text-slate-500 font-medium">Ayah / Ibu Kandung</p>
            <h3 className="text-2xl font-bold text-slate-800">
              {parents.filter((p) => p.hubungan === 'Ayah' || p.hubungan === 'Ibu').length}
            </h3>
            <span className="text-[11px] text-blue-600 font-medium">Relasi Utama</span>
          </div>
        </div>

        <div className="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center gap-4">
          <div className="w-12 h-12 rounded-xl bg-purple-100 text-purple-600 flex items-center justify-center text-xl font-bold">
            <FaPhoneAlt />
          </div>
          <div>
            <p className="text-xs text-slate-500 font-medium">Kontak Terverifikasi</p>
            <h3 className="text-2xl font-bold text-slate-800">100%</h3>
            <span className="text-[11px] text-purple-600 font-medium">Siap Notifikasi WA</span>
          </div>
        </div>

        <div className="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center gap-4">
          <div className="w-12 h-12 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center text-xl font-bold">
            <FaCheckCircle />
          </div>
          <div>
            <p className="text-xs text-slate-500 font-medium">Status Aktif</p>
            <h3 className="text-2xl font-bold text-slate-800">
              {parents.filter((p) => p.status === 'Aktif').length}
            </h3>
            <span className="text-[11px] text-amber-600 font-medium">Akun Pengawas Siswa</span>
          </div>
        </div>
      </div>

      {/* Filter and Search Bar */}
      <div className="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col md:flex-row gap-4 items-center justify-between">
        <div className="relative w-full md:w-80">
          <FaSearch className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm" />
          <input
            type="text"
            placeholder="Cari wali, siswa, atau no hp..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white transition"
          />
        </div>

        <div className="flex flex-wrap items-center gap-3 w-full md:w-auto justify-end">
          <div className="flex items-center gap-2 text-xs font-semibold text-slate-500">
            <FaFilter /> Filter:
          </div>
          <select
            value={filterHubungan}
            onChange={(e) => setFilterHubungan(e.target.value)}
            className="bg-slate-50 border border-slate-200 text-slate-700 text-xs font-medium rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500"
          >
            <option value="Semua">Semua Hubungan</option>
            <option value="Ayah">Ayah</option>
            <option value="Ibu">Ibu</option>
            <option value="Wali">Wali</option>
          </select>

          <select
            value={filterUnit}
            onChange={(e) => setFilterUnit(e.target.value)}
            className="bg-slate-50 border border-slate-200 text-slate-700 text-xs font-medium rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500"
          >
            <option value="Semua">Semua Unit</option>
            <option value="SD Islam Terpadu">SD Islam Terpadu</option>
            <option value="SMP Islam Terpadu">SMP Islam Terpadu</option>
            <option value="SMA Islam Terpadu">SMA Islam Terpadu</option>
          </select>
        </div>
      </div>

      {/* Main Table */}
      <div className="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse">
            <thead>
              <tr className="bg-slate-50 border-b border-slate-200 text-slate-600 text-xs uppercase tracking-wider font-semibold">
                <th className="py-3.5 px-4 text-center w-12">#</th>
                <th className="py-3.5 px-4">Nama Orang Tua / Wali</th>
                <th className="py-3.5 px-4">Hubungan</th>
                <th className="py-3.5 px-4">Anak / Siswa</th>
                <th className="py-3.5 px-4">Pekerjaan</th>
                <th className="py-3.5 px-4">Kontak / No. HP</th>
                <th className="py-3.5 px-4 text-center">Status</th>
                <th className="py-3.5 px-4 text-center">Aksi</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 text-sm text-slate-700">
              {filteredParents.length === 0 ? (
                <tr>
                  <td colSpan="8" className="text-center py-10 text-slate-400">
                    Tidak ada data orang tua / wali yang ditemukan.
                  </td>
                </tr>
              ) : (
                filteredParents.map((item, idx) => (
                  <tr key={item.id} className="hover:bg-slate-50/80 transition">
                    <td className="py-3.5 px-4 text-center font-mono text-xs text-slate-400">
                      {idx + 1}
                    </td>
                    <td className="py-3.5 px-4">
                      <div className="font-semibold text-slate-800">{item.nama}</div>
                      <div className="text-xs text-slate-400">{item.email}</div>
                    </td>
                    <td className="py-3.5 px-4">
                      <span
                        className={`inline-block px-2.5 py-0.5 rounded-full text-xs font-semibold ${
                          item.hubungan === 'Ayah'
                            ? 'bg-blue-100 text-blue-700'
                            : item.hubungan === 'Ibu'
                            ? 'bg-pink-100 text-pink-700'
                            : 'bg-amber-100 text-amber-700'
                        }`}
                      >
                        {item.hubungan}
                      </span>
                    </td>
                    <td className="py-3.5 px-4">
                      <div className="font-medium text-emerald-800">{item.namaSiswa}</div>
                      <div className="text-xs text-slate-500">
                        {item.unitPendidikan} ({item.kelasSiswa})
                      </div>
                    </td>
                    <td className="py-3.5 px-4 text-xs text-slate-600 font-medium">
                      {item.pekerjaan}
                    </td>
                    <td className="py-3.5 px-4 font-mono text-xs text-slate-700">
                      {item.noHp}
                    </td>
                    <td className="py-3.5 px-4 text-center">
                      <span className="inline-block px-2.5 py-0.5 bg-emerald-100 text-emerald-800 rounded-full text-xs font-medium">
                        {item.status}
                      </span>
                    </td>
                    <td className="py-3.5 px-4 text-center">
                      <div className="flex items-center justify-center gap-1.5">
                        <button
                          onClick={() => handleOpenDetail(item)}
                          className="p-1.5 text-slate-600 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition"
                          title="Detail Wali"
                        >
                          <FaEye />
                        </button>
                        <button
                          onClick={() => handleOpenEdit(item)}
                          className="p-1.5 text-slate-600 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition"
                          title="Edit"
                        >
                          <FaEdit />
                        </button>
                        <button
                          onClick={() => handleDelete(item.id, item.nama)}
                          className="p-1.5 text-slate-600 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition"
                          title="Hapus"
                        >
                          <FaTrash />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Detail Modal */}
      {isDetailOpen && selectedParent && (
        <div className="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-5 animate-in fade-in zoom-in duration-150">
            <div className="flex justify-between items-center border-b pb-3">
              <h3 className="text-lg font-bold text-slate-800">Detail Orang Tua / Wali</h3>
              <button
                onClick={() => setIsDetailOpen(false)}
                className="text-slate-400 hover:text-slate-600 text-lg"
              >
                <FaTimes />
              </button>
            </div>

            <div className="space-y-4 text-sm text-slate-700">
              <div className="flex items-center gap-3 p-3 bg-emerald-50 rounded-xl border border-emerald-100">
                <div className="w-10 h-10 rounded-full bg-emerald-600 text-white flex items-center justify-center font-bold text-lg">
                  {selectedParent.nama.charAt(0)}
                </div>
                <div>
                  <h4 className="font-bold text-emerald-950 text-base">{selectedParent.nama}</h4>
                  <p className="text-xs text-emerald-700 font-medium">
                    Hubungan: {selectedParent.hubungan}
                  </p>
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3 text-xs">
                <div className="p-3 bg-slate-50 rounded-xl border border-slate-100">
                  <span className="text-slate-400 font-semibold block mb-0.5">Siswa Terhubung</span>
                  <strong className="text-slate-800 text-sm block">{selectedParent.namaSiswa}</strong>
                  <span className="text-emerald-600 font-medium">{selectedParent.kelasSiswa} ({selectedParent.unitPendidikan})</span>
                </div>

                <div className="p-3 bg-slate-50 rounded-xl border border-slate-100">
                  <span className="text-slate-400 font-semibold block mb-0.5">Pekerjaan</span>
                  <strong className="text-slate-800 text-sm block flex items-center gap-1">
                    <FaBriefcase className="text-slate-400 text-xs" /> {selectedParent.pekerjaan}
                  </strong>
                </div>
              </div>

              <div className="p-3 bg-slate-50 rounded-xl border border-slate-100 space-y-2 text-xs">
                <div>
                  <span className="text-slate-400 font-semibold block">No. Telepon / WhatsApp:</span>
                  <strong className="text-slate-800 text-sm font-mono flex items-center gap-1">
                    <FaPhoneAlt className="text-emerald-500 text-xs" /> {selectedParent.noHp}
                  </strong>
                </div>
                <div>
                  <span className="text-slate-400 font-semibold block">Alamat Email:</span>
                  <span className="text-slate-700 font-medium">{selectedParent.email || '-'}</span>
                </div>
                <div>
                  <span className="text-slate-400 font-semibold block">Alamat Tempat Tinggal:</span>
                  <p className="text-slate-700 font-medium flex items-start gap-1 mt-0.5">
                    <FaMapMarkerAlt className="text-rose-500 text-xs shrink-0 mt-0.5" />
                    {selectedParent.alamat}
                  </p>
                </div>
              </div>
            </div>

            <div className="pt-3 border-t flex justify-end">
              <button
                onClick={() => setIsDetailOpen(false)}
                className="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded-xl text-sm font-medium transition"
              >
                Tutup
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Add / Edit Form Modal */}
      {isFormOpen && (
        <div className="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl max-w-xl w-full p-6 shadow-2xl space-y-4 animate-in fade-in zoom-in duration-150 max-h-[90vh] overflow-y-auto">
            <div className="flex justify-between items-center border-b pb-3">
              <h3 className="text-lg font-bold text-slate-800">
                {isEditing ? 'Edit Data Orang Tua / Wali' : 'Tambah Orang Tua / Wali Baru'}
              </h3>
              <button
                onClick={() => setIsFormOpen(false)}
                className="text-slate-400 hover:text-slate-600 text-lg"
              >
                <FaTimes />
              </button>
            </div>

            <form onSubmit={handleFormSubmit} className="space-y-4 text-xs">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label className="block font-semibold text-slate-700 mb-1">
                    Nama Orang Tua / Wali <span className="text-rose-500">*</span>
                  </label>
                  <input
                    type="text"
                    required
                    value={formData.nama}
                    onChange={(e) => setFormData({ ...formData, nama: e.target.value })}
                    placeholder="Contoh: Budi Santoso, S.T."
                    className="w-full px-3 py-2 border rounded-xl focus:ring-2 focus:ring-emerald-500 text-xs"
                  />
                </div>

                <div>
                  <label className="block font-semibold text-slate-700 mb-1">Hubungan</label>
                  <select
                    value={formData.hubungan}
                    onChange={(e) => setFormData({ ...formData, hubungan: e.target.value })}
                    className="w-full px-3 py-2 border rounded-xl focus:ring-2 focus:ring-emerald-500 text-xs"
                  >
                    <option value="Ayah">Ayah</option>
                    <option value="Ibu">Ibu</option>
                    <option value="Wali">Wali</option>
                  </select>
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label className="block font-semibold text-slate-700 mb-1">
                    Nama Siswa (Anak) <span className="text-rose-500">*</span>
                  </label>
                  <input
                    type="text"
                    required
                    value={formData.namaSiswa}
                    onChange={(e) => setFormData({ ...formData, namaSiswa: e.target.value })}
                    placeholder="Contoh: Ahmad Raihan"
                    className="w-full px-3 py-2 border rounded-xl focus:ring-2 focus:ring-emerald-500 text-xs"
                  />
                </div>

                <div>
                  <label className="block font-semibold text-slate-700 mb-1">Kelas Siswa</label>
                  <input
                    type="text"
                    value={formData.kelasSiswa}
                    onChange={(e) => setFormData({ ...formData, kelasSiswa: e.target.value })}
                    placeholder="Contoh: 7-A"
                    className="w-full px-3 py-2 border rounded-xl focus:ring-2 focus:ring-emerald-500 text-xs"
                  />
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label className="block font-semibold text-slate-700 mb-1">Unit Pendidikan</label>
                  <select
                    value={formData.unitPendidikan}
                    onChange={(e) => setFormData({ ...formData, unitPendidikan: e.target.value })}
                    className="w-full px-3 py-2 border rounded-xl focus:ring-2 focus:ring-emerald-500 text-xs"
                  >
                    <option value="SD Islam Terpadu">SD Islam Terpadu</option>
                    <option value="SMP Islam Terpadu">SMP Islam Terpadu</option>
                    <option value="SMA Islam Terpadu">SMA Islam Terpadu</option>
                  </select>
                </div>

                <div>
                  <label className="block font-semibold text-slate-700 mb-1">Pekerjaan</label>
                  <input
                    type="text"
                    value={formData.pekerjaan}
                    onChange={(e) => setFormData({ ...formData, pekerjaan: e.target.value })}
                    placeholder="Contoh: Wiraswasta / PNS"
                    className="w-full px-3 py-2 border rounded-xl focus:ring-2 focus:ring-emerald-500 text-xs"
                  />
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label className="block font-semibold text-slate-700 mb-1">
                    No. HP / WhatsApp <span className="text-rose-500">*</span>
                  </label>
                  <input
                    type="text"
                    required
                    value={formData.noHp}
                    onChange={(e) => setFormData({ ...formData, noHp: e.target.value })}
                    placeholder="0812-3456-7890"
                    className="w-full px-3 py-2 border rounded-xl focus:ring-2 focus:ring-emerald-500 text-xs font-mono"
                  />
                </div>

                <div>
                  <label className="block font-semibold text-slate-700 mb-1">Email</label>
                  <input
                    type="email"
                    value={formData.email}
                    onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                    placeholder="wali@email.com"
                    className="w-full px-3 py-2 border rounded-xl focus:ring-2 focus:ring-emerald-500 text-xs"
                  />
                </div>
              </div>

              <div>
                <label className="block font-semibold text-slate-700 mb-1">Alamat Lengkap</label>
                <textarea
                  rows="2"
                  value={formData.alamat}
                  onChange={(e) => setFormData({ ...formData, alamat: e.target.value })}
                  placeholder="Jl. Jalan No. XX..."
                  className="w-full px-3 py-2 border rounded-xl focus:ring-2 focus:ring-emerald-500 text-xs"
                ></textarea>
              </div>

              <div className="pt-3 border-t flex justify-end gap-2">
                <button
                  type="button"
                  onClick={() => setIsFormOpen(false)}
                  className="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded-xl text-xs font-medium transition"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  className="bg-emerald-600 hover:bg-emerald-500 text-white px-5 py-2 rounded-xl text-xs font-semibold shadow transition"
                >
                  {isEditing ? 'Simpan Perubahan' : 'Tambah Data'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </section>
  )
}
