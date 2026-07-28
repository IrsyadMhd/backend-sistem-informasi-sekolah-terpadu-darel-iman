import React, { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import Swal from 'sweetalert2'
import {
  FaShieldAlt,
  FaPlus,
  FaSearch,
  FaEdit,
  FaTrash,
  FaTimes,
  FaLock,
  FaKey,
  FaUsers,
  FaCheckCircle,
  FaSave,
  FaUserShield,
  FaUserCheck,
  FaUserTimes,
  FaUserCog,
  FaBuilding,
  FaBriefcase,
  FaIdCard,
} from 'react-icons/fa'
import { hakAksesService } from '../services/hakAksesService'

// ─────────────────────────────────────────────────────────────────
// MODAL ROLE FORM
// ─────────────────────────────────────────────────────────────────
function RoleFormModal({ isOpen, onClose, onSubmit, initialData = null, allPermissions = [], isSubmitting = false }) {
  const isEdit = Boolean(initialData?.id)
  const [name, setName] = useState(initialData?.name || '')
  const [selectedPerms, setSelectedPerms] = useState(initialData?.permissions || [])
  const [error, setError] = useState('')

  React.useEffect(() => {
    if (isOpen) {
      setName(initialData?.name || '')
      setSelectedPerms(initialData?.permissions || [])
      setError('')
    }
  }, [isOpen, initialData])

  if (!isOpen) return null

  const togglePerm = (perm) => {
    setSelectedPerms((prev) =>
      prev.includes(perm) ? prev.filter((p) => p !== perm) : [...prev, perm]
    )
  }

  const toggleAll = (modulsPerms) => {
    const allSelected = modulsPerms.every((p) => selectedPerms.includes(p))
    if (allSelected) {
      setSelectedPerms((prev) => prev.filter((p) => !modulsPerms.includes(p)))
    } else {
      setSelectedPerms((prev) => [...new Set([...prev, ...modulsPerms])])
    }
  }

  const handleSubmit = (e) => {
    e.preventDefault()
    if (!name.trim()) { setError('Nama role tidak boleh kosong.'); return }
    onSubmit({ name: name.trim(), permissions: selectedPerms })
  }

  // Kelompokkan permissions berdasarkan modul (prefix sebelum titik)
  const grouped = allPermissions.reduce((acc, p) => {
    const modul = p.split('.')[0] || 'lainnya'
    if (!acc[modul]) acc[modul] = []
    acc[modul].push(p)
    return acc
  }, {})

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm overflow-y-auto">
      <div className="bg-white rounded-[24px] shadow-2xl border border-slate-100 w-full max-w-3xl overflow-hidden my-6">
        {/* Header */}
        <div className="flex items-center justify-between px-7 py-5 border-b border-slate-100">
          <h2 className="text-xl font-black text-[#0f172a]">
            {isEdit ? 'Edit Role Akses' : 'Tambah Role Akses Baru'}
          </h2>
          <button type="button" onClick={onClose} className="rounded-full p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors">
            <FaTimes className="w-4 h-4" />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="p-7 space-y-5 max-h-[80vh] overflow-y-auto">
          {/* Nama Role */}
          <div>
            <label className="block text-xs font-bold text-[#0f172a] mb-1.5">
              Nama Role <span className="text-rose-500">*</span>
            </label>
            <input
              type="text"
              value={name}
              onChange={(e) => { setName(e.target.value); setError('') }}
              placeholder="Contoh: Kepala Sekolah, Divisi Pendidikan, Tata Usaha, Guru"
              className="w-full rounded-xl border border-slate-200/90 px-4 py-2.5 text-sm text-[#0f172a] placeholder:text-slate-400 focus:border-[#054e3b] focus:ring-2 focus:ring-[#054e3b]/10 focus:outline-none transition-all bg-white"
            />
            {error && <p className="mt-1 text-xs text-rose-500">{error}</p>}
          </div>

          {/* Permission Checklist dikelompokkan per modul */}
          <div>
            <label className="block text-xs font-bold text-[#0f172a] mb-2">
              Izin Akses <span className="text-slate-400 font-normal">({selectedPerms.length} dipilih)</span>
            </label>
            <div className="space-y-3 max-h-72 overflow-y-auto rounded-2xl border border-slate-200/90 p-4 bg-[#f8fafc]">
              {Object.entries(grouped).map(([modul, perms]) => {
                const allModulSelected = perms.every((p) => selectedPerms.includes(p))
                const someSelected = perms.some((p) => selectedPerms.includes(p))
                return (
                  <div key={modul} className="rounded-xl border border-slate-200 bg-white overflow-hidden">
                    {/* Header Modul */}
                    <div
                      className="flex items-center justify-between px-4 py-2.5 bg-slate-50 cursor-pointer hover:bg-slate-100 transition-colors"
                      onClick={() => toggleAll(perms)}
                    >
                      <div className="flex items-center gap-2.5">
                        <div className={`w-4 h-4 rounded border-2 flex items-center justify-center transition-all ${
                          allModulSelected ? 'bg-[#054e3b] border-[#054e3b]' : someSelected ? 'bg-[#054e3b]/30 border-[#054e3b]' : 'border-slate-300'
                        }`}>
                          {allModulSelected && <FaCheckCircle className="w-2.5 h-2.5 text-white" />}
                        </div>
                        <span className="text-xs font-extrabold text-slate-800 uppercase tracking-wider">{modul}</span>
                      </div>
                      <span className="text-[10px] text-slate-500 font-medium">{perms.filter(p => selectedPerms.includes(p)).length}/{perms.length} dipilih</span>
                    </div>
                    {/* Daftar Permission */}
                    <div className="px-4 py-2 flex flex-wrap gap-1.5">
                      {perms.map((perm) => (
                        <label key={perm} className="flex items-center gap-1.5 cursor-pointer">
                          <input
                            type="checkbox"
                            checked={selectedPerms.includes(perm)}
                            onChange={() => togglePerm(perm)}
                            className="w-3.5 h-3.5 rounded text-[#054e3b] focus:ring-[#054e3b] border-slate-300"
                          />
                          <span className={`text-xs px-2 py-0.5 rounded-full font-medium border ${
                            selectedPerms.includes(perm)
                              ? 'bg-[#dcfce7] text-[#15803d] border-emerald-200'
                              : 'bg-slate-100 text-slate-600 border-slate-200'
                          }`}>
                            {perm.split('.')[1] || perm}
                          </span>
                        </label>
                      ))}
                    </div>
                  </div>
                )
              })}
            </div>
          </div>

          {/* Footer */}
          <div className="flex items-center justify-between border-t border-slate-100 pt-4">
            <button type="button" onClick={onClose} className="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition-colors">
              Batal
            </button>
            <button type="submit" disabled={isSubmitting} className="rounded-xl bg-[#046c4e] hover:bg-[#03543d] px-6 py-2.5 text-xs font-bold text-white shadow-md disabled:opacity-50 transition-colors flex items-center gap-1.5">
              <FaSave className="w-3.5 h-3.5" />
              <span>{isSubmitting ? 'Menyimpan...' : isEdit ? 'Simpan Perubahan' : 'Tambah Role'}</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}

// ─────────────────────────────────────────────────────────────────
// MODAL PERMISSION FORM
// ─────────────────────────────────────────────────────────────────
function PermissionFormModal({ isOpen, onClose, onSubmit, isSubmitting = false }) {
  const [name, setName] = useState('')
  const [error, setError] = useState('')

  React.useEffect(() => {
    if (isOpen) { setName(''); setError('') }
  }, [isOpen])

  if (!isOpen) return null

  const handleSubmit = (e) => {
    e.preventDefault()
    if (!name.trim()) { setError('Nama izin akses tidak boleh kosong.'); return }
    if (!name.includes('.')) { setError('Nama harus dalam format "modul.aksi", contoh: kehadiran.siswa.monitoring'); return }
    onSubmit({ name: name.trim() })
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm overflow-y-auto">
      <div className="bg-white rounded-[24px] shadow-2xl border border-slate-100 w-full max-w-md overflow-hidden my-6">
        <div className="flex items-center justify-between px-7 py-5 border-b border-slate-100">
          <h2 className="text-xl font-black text-[#0f172a]">Tambah Izin Akses Baru</h2>
          <button type="button" onClick={onClose} className="rounded-full p-2 text-slate-400 hover:bg-slate-100 transition-colors">
            <FaTimes className="w-4 h-4" />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="p-7 space-y-5">
          <div>
            <label className="block text-xs font-bold text-[#0f172a] mb-1.5">
              Nama Izin Akses <span className="text-rose-500">*</span>
            </label>
            <input
              type="text"
              value={name}
              onChange={(e) => { setName(e.target.value); setError('') }}
              placeholder="Contoh: tahfizh.monitoring_target, kesiswaan.kelulusan"
              className="w-full rounded-xl border border-slate-200/90 px-4 py-2.5 text-sm text-[#0f172a] placeholder:text-slate-400 focus:border-[#054e3b] focus:ring-2 focus:ring-[#054e3b]/10 focus:outline-none transition-all bg-white"
            />
            {error && <p className="mt-1 text-xs text-rose-500">{error}</p>}
            <p className="mt-1.5 text-[11px] text-slate-500">Format: <code className="bg-slate-100 px-1 rounded">modul.aksi</code> — misalnya: <code className="bg-slate-100 px-1 rounded">tahfizh.monitoring_target</code></p>
          </div>

          <div className="flex items-center justify-between border-t border-slate-100 pt-4">
            <button type="button" onClick={onClose} className="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition-colors">
              Batal
            </button>
            <button type="submit" disabled={isSubmitting} className="rounded-xl bg-[#046c4e] hover:bg-[#03543d] px-6 py-2.5 text-xs font-bold text-white shadow-md disabled:opacity-50 transition-colors flex items-center gap-1.5">
              <FaKey className="w-3.5 h-3.5" />
              <span>{isSubmitting ? 'Menyimpan...' : 'Tambah Izin Akses'}</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}

// ─────────────────────────────────────────────────────────────────
// MODAL PEGAWAI ROLE & HAK AKSES FORM
// ─────────────────────────────────────────────────────────────────
function PegawaiRoleModal({ isOpen, onClose, onSubmit, employee = null, availableRoles = [], allPermissions = [], isSubmitting = false }) {
  const [roleName, setRoleName] = useState('')
  const [selectedPerms, setSelectedPerms] = useState([])
  const [password, setPassword] = useState('')

  React.useEffect(() => {
    if (isOpen && employee) {
      setRoleName(employee.primary_role !== 'Belum Ada Role' ? employee.primary_role : (availableRoles[0] || 'Guru'))
      setSelectedPerms(employee.direct_permissions || [])
      setPassword('')
    }
  }, [isOpen, employee, availableRoles])

  if (!isOpen || !employee) return null

  const handleSubmit = (e) => {
    e.preventDefault()
    onSubmit({
      employeeId: employee.id,
      payload: {
        role_name: roleName,
        permissions: selectedPerms,
        ...(password.trim() ? { password: password.trim() } : {}),
      },
    })
  }

  const togglePerm = (perm) => {
    setSelectedPerms((prev) =>
      prev.includes(perm) ? prev.filter((p) => p !== perm) : [...prev, perm]
    )
  }

  // Kelompokkan permissions berdasarkan modul
  const grouped = allPermissions.reduce((acc, p) => {
    const modul = p.split('.')[0] || 'lainnya'
    if (!acc[modul]) acc[modul] = []
    acc[modul].push(p)
    return acc
  }, {})

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm overflow-y-auto">
      <div className="bg-white rounded-[24px] shadow-2xl border border-slate-100 w-full max-w-2xl overflow-hidden my-6">
        <div className="flex items-center justify-between px-7 py-5 border-b border-slate-100 bg-[#f4efe6]">
          <div>
            <span className="text-[10px] font-extrabold uppercase tracking-wider text-emerald-800 bg-emerald-100 px-2.5 py-0.5 rounded-full">
              PENETAPAN HAK AKSES PEGAWAI
            </span>
            <h2 className="text-xl font-black text-[#0f172a] mt-1">{employee.nama_lengkap}</h2>
            <p className="text-xs text-slate-600 font-medium mt-0.5">
              NIY/NIP: {employee.niy || '-'} | Jabatan: {employee.position?.nama || '-'} | Unit: {employee.unit?.nama || '-'}
            </p>
          </div>
          <button type="button" onClick={onClose} className="rounded-full p-2 text-slate-400 hover:bg-slate-200 transition-colors">
            <FaTimes className="w-4 h-4" />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="p-7 space-y-5 max-h-[75vh] overflow-y-auto">
          {/* Status Akun */}
          <div className={`p-4 rounded-2xl border flex items-center justify-between ${employee.has_user ? 'bg-emerald-50/80 border-emerald-200' : 'bg-amber-50/80 border-amber-200'}`}>
            <div className="flex items-center gap-3">
              <div className={`w-9 h-9 rounded-xl flex items-center justify-center shrink-0 ${employee.has_user ? 'bg-emerald-600 text-white' : 'bg-amber-500 text-white'}`}>
                {employee.has_user ? <FaUserCheck className="w-4 h-4" /> : <FaUserTimes className="w-4 h-4" />}
              </div>
              <div>
                <p className="text-xs font-bold text-slate-900">
                  {employee.has_user ? `Akun Terhubung (${employee.user_email || 'Aktif'})` : 'Belum Memiliki Akun User'}
                </p>
                <p className="text-[11px] text-slate-500">
                  {employee.has_user ? 'Role dan permission akan disinkronkan langsung ke akun pengguna ini.' : 'Sistem akan otomatis membuat akun login untuk pegawai ini.'}
                </p>
              </div>
            </div>
          </div>

          {!employee.has_user && (
            <div>
              <label className="block text-xs font-bold text-[#0f172a] mb-1">
                Password Awal Akun <span className="text-slate-400 font-normal">(Opsional, default: 12345678)</span>
              </label>
              <input
                type="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="Masukkan password awal untuk login..."
                className="w-full rounded-xl border border-slate-200/90 px-4 py-2.5 text-xs text-[#0f172a] focus:border-[#054e3b] focus:ring-2 focus:ring-[#054e3b]/10 focus:outline-none bg-white"
              />
            </div>
          )}

          {/* Pilihan Role Utama */}
          <div>
            <label className="block text-xs font-bold text-[#0f172a] mb-1.5">
              Pilih Role Utama Pegawai <span className="text-rose-500">*</span>
            </label>
            <select
              value={roleName}
              onChange={(e) => setRoleName(e.target.value)}
              className="w-full rounded-xl border border-slate-200/90 px-4 py-2.5 text-xs font-extrabold text-[#0f172a] focus:border-[#054e3b] focus:ring-2 focus:ring-[#054e3b]/10 focus:outline-none bg-white"
            >
              {availableRoles.map((r) => (
                <option key={r} value={r}>{r}</option>
              ))}
            </select>
          </div>

          {/* Direct Custom Permissions */}
          <div>
            <label className="block text-xs font-bold text-[#0f172a] mb-2">
              Izin Akses Tambahan Khusus (Direct Permissions) <span className="text-slate-400 font-normal">({selectedPerms.length} dipilih)</span>
            </label>
            <div className="space-y-3 max-h-56 overflow-y-auto rounded-2xl border border-slate-200/90 p-4 bg-[#f8fafc]">
              {Object.entries(grouped).map(([modul, perms]) => (
                <div key={modul} className="rounded-xl border border-slate-200 bg-white p-3">
                  <span className="text-[11px] font-extrabold uppercase text-slate-700 tracking-wider block mb-2">{modul}</span>
                  <div className="flex flex-wrap gap-1.5">
                    {perms.map((perm) => (
                      <label key={perm} className="flex items-center gap-1.5 cursor-pointer">
                        <input
                          type="checkbox"
                          checked={selectedPerms.includes(perm)}
                          onChange={() => togglePerm(perm)}
                          className="w-3.5 h-3.5 rounded text-[#054e3b] focus:ring-[#054e3b] border-slate-300"
                        />
                        <span className={`text-[11px] px-2 py-0.5 rounded-full font-medium border ${
                          selectedPerms.includes(perm)
                            ? 'bg-[#dcfce7] text-[#15803d] border-emerald-200'
                            : 'bg-slate-100 text-slate-600 border-slate-200'
                        }`}>
                          {perm.split('.')[1] || perm}
                        </span>
                      </label>
                    ))}
                  </div>
                </div>
              ))}
            </div>
          </div>

          {/* Footer */}
          <div className="flex items-center justify-between border-t border-slate-100 pt-4">
            <button type="button" onClick={onClose} className="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition-colors">
              Batal
            </button>
            <button type="submit" disabled={isSubmitting} className="rounded-xl bg-[#046c4e] hover:bg-[#03543d] px-6 py-2.5 text-xs font-bold text-white shadow-md disabled:opacity-50 transition-colors flex items-center gap-1.5">
              <FaSave className="w-3.5 h-3.5" />
              <span>{isSubmitting ? 'Menyimpan...' : 'Simpan Hak Akses Pegawai'}</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}

// ─────────────────────────────────────────────────────────────────
// HALAMAN UTAMA
// ─────────────────────────────────────────────────────────────────
export default function MasterHakAksesPage() {
  const queryClient = useQueryClient()

  const [activeTab, setActiveTab] = useState('roles') // 'roles' | 'permissions' | 'pegawai'
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)

  // Role modal
  const [isRoleModalOpen, setIsRoleModalOpen] = useState(false)
  const [selectedRole, setSelectedRole] = useState(null)

  // Permission modal
  const [isPermModalOpen, setIsPermModalOpen] = useState(false)

  // Pegawai Hak Akses modal
  const [isPegawaiModalOpen, setIsPegawaiModalOpen] = useState(false)
  const [selectedEmployee, setSelectedEmployee] = useState(null)

  // Query Stats
  const { data: stats = {} } = useQuery({
    queryKey: ['hak-akses-stats'],
    queryFn: () => hakAksesService.getStats(),
    staleTime: 30000,
  })

  // Query Roles
  const { data: rolesData = {}, isLoading: isLoadingRoles } = useQuery({
    queryKey: ['hak-akses-roles', search],
    queryFn: () => hakAksesService.getDaftarRole({ search }),
    staleTime: 15000,
  })

  // Query Permissions
  const { data: permData = {}, isLoading: isLoadingPerms } = useQuery({
    queryKey: ['hak-akses-permissions', search],
    queryFn: () => hakAksesService.getDaftarPermission({ search }),
    staleTime: 15000,
  })

  // Query Pegawai (Menarik Data Pegawai)
  const { data: pegawaiData = {}, isLoading: isLoadingPegawai } = useQuery({
    queryKey: ['hak-akses-pegawai', search, page],
    queryFn: () => hakAksesService.getPegawaiHakAkses({ search, page }),
    enabled: activeTab === 'pegawai',
    staleTime: 15000,
  })

  const roles = rolesData?.data || []
  const availableRoleNames = roles.map((r) => r.name)
  const permissionsGrouped = permData?.data || []
  const allPerms = permData?.flat_list || []

  const listPegawai = pegawaiData?.data || []
  const metaPegawai = pegawaiData?.meta || {}

  // Mutations Role
  const tambahRoleMutation = useMutation({
    mutationFn: (payload) => hakAksesService.tambahRole(payload),
    onSuccess: (res) => {
      queryClient.invalidateQueries(['hak-akses-roles'])
      queryClient.invalidateQueries(['hak-akses-stats'])
      setIsRoleModalOpen(false)
      Swal.fire({ icon: 'success', title: 'Berhasil!', text: res?.message, timer: 2000, showConfirmButton: false })
    },
    onError: (err) => Swal.fire('Error', err.response?.data?.message || 'Gagal menyimpan role.', 'error'),
  })

  const ubahRoleMutation = useMutation({
    mutationFn: ({ id, payload }) => hakAksesService.ubahRole({ id, payload }),
    onSuccess: (res) => {
      queryClient.invalidateQueries(['hak-akses-roles'])
      setIsRoleModalOpen(false)
      setSelectedRole(null)
      Swal.fire({ icon: 'success', title: 'Berhasil!', text: res?.message, timer: 2000, showConfirmButton: false })
    },
    onError: (err) => Swal.fire('Error', err.response?.data?.message || 'Gagal memperbarui role.', 'error'),
  })

  const hapusRoleMutation = useMutation({
    mutationFn: (id) => hakAksesService.hapusRole(id),
    onSuccess: (res) => {
      queryClient.invalidateQueries(['hak-akses-roles'])
      queryClient.invalidateQueries(['hak-akses-stats'])
      Swal.fire({ icon: 'success', title: 'Terhapus!', text: res?.message, timer: 2000, showConfirmButton: false })
    },
    onError: (err) => Swal.fire('Gagal!', err.response?.data?.message || 'Gagal menghapus role.', 'error'),
  })

  // Mutations Permission
  const tambahPermMutation = useMutation({
    mutationFn: (payload) => hakAksesService.tambahPermission(payload),
    onSuccess: (res) => {
      queryClient.invalidateQueries(['hak-akses-permissions'])
      queryClient.invalidateQueries(['hak-akses-stats'])
      setIsPermModalOpen(false)
      Swal.fire({ icon: 'success', title: 'Berhasil!', text: res?.message, timer: 2000, showConfirmButton: false })
    },
    onError: (err) => Swal.fire('Error', err.response?.data?.message || 'Gagal menyimpan izin akses.', 'error'),
  })

  const hapusPermMutation = useMutation({
    mutationFn: (id) => hakAksesService.hapusPermission(id),
    onSuccess: () => {
      queryClient.invalidateQueries(['hak-akses-permissions'])
      queryClient.invalidateQueries(['hak-akses-stats'])
      Swal.fire({ icon: 'success', title: 'Terhapus!', timer: 1500, showConfirmButton: false })
    },
    onError: (err) => Swal.fire('Gagal!', err.response?.data?.message || 'Gagal menghapus izin.', 'error'),
  })

  // Mutation Pegawai Hak Akses
  const assignPegawaiRoleMutation = useMutation({
    mutationFn: ({ employeeId, payload }) => hakAksesService.assignPegawaiRole({ employeeId, payload }),
    onSuccess: (res) => {
      queryClient.invalidateQueries(['hak-akses-pegawai'])
      queryClient.invalidateQueries(['hak-akses-roles'])
      queryClient.invalidateQueries(['hak-akses-stats'])
      setIsPegawaiModalOpen(false)
      setSelectedEmployee(null)
      Swal.fire({ icon: 'success', title: 'Berhasil!', text: res?.message, timer: 2000, showConfirmButton: false })
    },
    onError: (err) => Swal.fire('Error', err.response?.data?.message || 'Gagal memperbarui hak akses pegawai.', 'error'),
  })

  // Handlers
  const handleOpenCreateRole = () => { setSelectedRole(null); setIsRoleModalOpen(true) }

  const handleOpenEditRole = async (role) => {
    try {
      const detail = await hakAksesService.getDetailRole(role.id)
      setSelectedRole(detail)
      setIsRoleModalOpen(true)
    } catch {
      setSelectedRole(role)
      setIsRoleModalOpen(true)
    }
  }

  const handleDeleteRole = (role) => {
    Swal.fire({
      title: `Hapus Role "${role.name}"?`,
      text: 'Role yang memiliki pengguna aktif tidak dapat dihapus.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#dc2626',
      cancelButtonText: 'Batal',
      confirmButtonText: 'Ya, Hapus!',
    }).then((result) => { if (result.isConfirmed) hapusRoleMutation.mutate(role.id) })
  }

  const handleDeletePerm = (perm) => {
    Swal.fire({
      title: `Hapus izin "${perm.name}"?`,
      text: 'Izin yang dihapus akan dicabut dari semua role.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#dc2626',
      cancelButtonText: 'Batal',
      confirmButtonText: 'Ya, Hapus!',
    }).then((result) => { if (result.isConfirmed) hapusPermMutation.mutate(perm.id) })
  }

  const handleRoleSubmit = (formData) => {
    if (selectedRole?.id) {
      ubahRoleMutation.mutate({ id: selectedRole.id, payload: formData })
    } else {
      tambahRoleMutation.mutate(formData)
    }
  }

  const handleOpenPegawaiModal = (employee) => {
    setSelectedEmployee(employee)
    setIsPegawaiModalOpen(true)
  }

  const isRoleSubmitting = tambahRoleMutation.isPending || ubahRoleMutation.isPending
  const isPermSubmitting = tambahPermMutation.isPending
  const isPegawaiSubmitting = assignPegawaiRoleMutation.isPending

  return (
    <div className="space-y-6 pb-12">
      {/* ───── Header Banner Emerald ───── */}
      <div className="bg-[#054e3b] rounded-[24px] p-7 text-white shadow-lg border border-emerald-800/40">
        <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
          <div>
            <span className="bg-[#086a52] text-emerald-200 text-[11px] font-extrabold px-3.5 py-1 rounded-full uppercase tracking-wider">
              MASTER DATA AKSES
            </span>
            <h1 className="text-3xl md:text-4xl font-black mt-2 tracking-tight">
              Manajemen Hak Akses Sistem
            </h1>
            <p className="text-emerald-100/90 text-sm mt-1">
              Kelola Role pengguna, Izin Akses (Permission), dan Pengaturan Hak Akses Pegawai secara terintegrasi.
            </p>
          </div>
          <div className="flex items-center gap-2">
            <button
              onClick={() => { setActiveTab('permissions'); setIsPermModalOpen(true) }}
              className="flex items-center gap-2 bg-[#086a52] hover:bg-[#065c45] border border-emerald-500/30 text-white px-4 py-2.5 rounded-xl text-xs font-bold shadow-sm transition-colors"
            >
              <FaKey className="w-3.5 h-3.5" />
              <span>+ Izin Akses</span>
            </button>
            <button
              onClick={() => { setActiveTab('roles'); handleOpenCreateRole() }}
              className="flex items-center gap-2 bg-[#00b981] hover:bg-[#059669] text-white px-4 py-2.5 rounded-xl text-xs font-bold shadow-md transition-colors"
            >
              <FaPlus className="w-3.5 h-3.5" />
              <span>+ Tambah Role</span>
            </button>
          </div>
        </div>
      </div>

      {/* ───── Stats Cards ───── */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        {[
          { label: 'Total Role', value: stats.total_role ?? 0, icon: FaShieldAlt, bg: 'bg-[#dcfce7]', color: 'text-[#15803d]', sub: 'Terdaftar di sistem' },
          { label: 'Total Izin Akses', value: stats.total_permission ?? 0, icon: FaKey, bg: 'bg-[#dbeafe]', color: 'text-[#1d4ed8]', sub: 'Permission aktif' },
          { label: 'Total Modul', value: stats.total_modul ?? 0, icon: FaLock, bg: 'bg-[#f3e8ff]', color: 'text-[#7e22ce]', sub: 'Area sistem tercakup' },
          { label: 'Role Tanpa User', value: stats.role_tanpa_user ?? 0, icon: FaUsers, bg: 'bg-[#fef9c3]', color: 'text-[#ca8a04]', sub: 'Belum digunakan' },
        ].map((card) => (
          <div key={card.label} className="bg-white border border-slate-200/90 rounded-[20px] p-5 shadow-sm flex items-center gap-4">
            <div className={`${card.bg} ${card.color} rounded-2xl p-3 shrink-0`}>
              <card.icon className="w-5 h-5" />
            </div>
            <div className="min-w-0">
              <p className="text-[11px] font-bold text-slate-500 truncate">{card.label}</p>
              <p className="text-3xl font-black text-slate-900">{card.value}</p>
              <p className={`text-[11px] font-bold ${card.color} mt-0.5`}>{card.sub}</p>
            </div>
          </div>
        ))}
      </div>

      {/* ───── Tab + Search ───── */}
      <div className="bg-white rounded-[20px] border border-slate-200/90 shadow-sm p-5">
        <div className="flex flex-col sm:flex-row items-start sm:items-center gap-3 justify-between">
          {/* Tab */}
          <div className="flex gap-1 bg-slate-100 rounded-xl p-1 overflow-x-auto max-w-full">
            <button
              onClick={() => setActiveTab('roles')}
              className={`px-4 py-2 rounded-lg text-xs font-bold transition-all whitespace-nowrap ${activeTab === 'roles' ? 'bg-[#054e3b] text-white shadow-sm' : 'text-slate-600 hover:text-slate-800'}`}
            >
              <FaShieldAlt className="inline w-3.5 h-3.5 mr-1.5" />
              Role Akses
            </button>
            <button
              onClick={() => setActiveTab('permissions')}
              className={`px-4 py-2 rounded-lg text-xs font-bold transition-all whitespace-nowrap ${activeTab === 'permissions' ? 'bg-[#054e3b] text-white shadow-sm' : 'text-slate-600 hover:text-slate-800'}`}
            >
              <FaKey className="inline w-3.5 h-3.5 mr-1.5" />
              Izin Akses (Permissions)
            </button>
            <button
              onClick={() => setActiveTab('pegawai')}
              className={`px-4 py-2 rounded-lg text-xs font-bold transition-all whitespace-nowrap ${activeTab === 'pegawai' ? 'bg-[#054e3b] text-white shadow-sm' : 'text-slate-600 hover:text-slate-800'}`}
            >
              <FaUserShield className="inline w-3.5 h-3.5 mr-1.5" />
              Hak Akses Pegawai (Menarik Data Pegawai)
            </button>
          </div>
          {/* Search */}
          <div className="relative flex-1 max-w-xs">
            <FaSearch className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs" />
            <input
              type="text"
              value={search}
              onChange={(e) => { setSearch(e.target.value); setPage(1) }}
              placeholder={activeTab === 'roles' ? 'Cari nama role...' : activeTab === 'permissions' ? 'Cari izin akses...' : 'Cari NIP, nama, atau email pegawai...'}
              className="w-full rounded-full border border-slate-200 bg-[#f8fafc] pl-9 pr-4 py-2 text-xs font-semibold text-slate-700 placeholder:text-slate-400 focus:border-[#054e3b] focus:outline-none focus:ring-2 focus:ring-[#054e3b]/10 transition-all"
            />
          </div>
        </div>
      </div>

      {/* ───── TAB: ROLES ───── */}
      {activeTab === 'roles' && (
        <div className="w-full overflow-x-auto rounded-[20px] border border-slate-200/90 bg-white shadow-sm">
          <table className="w-full text-left border-collapse text-slate-800">
            <thead>
              <tr className="bg-[#f4efe6] border-b border-slate-200/90 text-xs font-black uppercase tracking-wider text-slate-700">
                <th className="py-3.5 px-4 w-10 text-center">NO</th>
                <th className="py-3.5 px-4">NAMA ROLE</th>
                <th className="py-3.5 px-4 text-center">JUMLAH IZIN</th>
                <th className="py-3.5 px-4 text-center">PENGGUNA</th>
                <th className="py-3.5 px-4">IZIN AKSES (PREVIEW)</th>
                <th className="py-3.5 px-4 text-center w-28">AKSI</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 text-sm">
              {isLoadingRoles ? (
                <tr><td colSpan={6} className="py-12 text-center text-slate-400 text-xs font-medium">Memuat data role...</td></tr>
              ) : roles.length === 0 ? (
                <tr><td colSpan={6} className="py-12 text-center text-slate-400 text-xs font-medium">Belum ada data role.</td></tr>
              ) : roles.map((role, idx) => (
                <tr key={role.id} className="hover:bg-slate-50/80 transition-colors">
                  <td className="py-3.5 px-4 text-center text-xs font-bold text-slate-500">{idx + 1}</td>
                  <td className="py-3.5 px-4">
                    <div className="flex items-center gap-2.5">
                      <div className="w-8 h-8 rounded-xl bg-[#dcfce7] flex items-center justify-center shrink-0">
                        <FaShieldAlt className="w-3.5 h-3.5 text-[#15803d]" />
                      </div>
                      <div>
                        <p className="font-extrabold text-slate-900 text-sm">{role.name}</p>
                        <p className="text-[11px] text-slate-500">{role.guard_name}</p>
                      </div>
                    </div>
                  </td>
                  <td className="py-3.5 px-4 text-center">
                    <span className="inline-flex items-center gap-1 font-extrabold text-xs text-[#1d4ed8] bg-[#dbeafe] border border-blue-200 px-2.5 py-1 rounded-lg">
                      <FaKey className="w-3 h-3" />
                      {role.jumlah_izin ?? 0}
                    </span>
                  </td>
                  <td className="py-3.5 px-4 text-center">
                    <span className="inline-flex items-center gap-1 font-extrabold text-xs text-slate-700 bg-slate-100 px-2.5 py-1 rounded-lg">
                      <FaUsers className="w-3 h-3 text-slate-400" />
                      {role.jumlah_pengguna ?? 0}
                    </span>
                  </td>
                  <td className="py-3.5 px-4">
                    <div className="flex flex-wrap gap-1">
                      {(role.permissions || []).slice(0, 4).map((p) => (
                        <span key={p} className="text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 px-1.5 py-0.5 rounded-full">
                          {p}
                        </span>
                      ))}
                      {(role.permissions || []).length > 4 && (
                        <span className="text-[10px] font-bold bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded-full">
                          +{role.permissions.length - 4} lainnya
                        </span>
                      )}
                    </div>
                  </td>
                  <td className="py-3.5 px-4 text-center">
                    <div className="flex items-center justify-center gap-1.5">
                      <button
                        onClick={() => handleOpenEditRole(role)}
                        className="flex h-8 w-8 items-center justify-center rounded-lg border border-amber-200 bg-[#fffbe6] text-[#d97706] hover:bg-amber-100 transition-colors"
                        title="Edit Role"
                      >
                        <FaEdit className="w-3.5 h-3.5" />
                      </button>
                      <button
                        onClick={() => handleDeleteRole(role)}
                        className="flex h-8 w-8 items-center justify-center rounded-lg border border-red-200 bg-[#fef2f2] text-[#dc2626] hover:bg-red-100 transition-colors"
                        title="Hapus Role"
                      >
                        <FaTrash className="w-3.5 h-3.5" />
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* ───── TAB: PERMISSIONS ───── */}
      {activeTab === 'permissions' && (
        <div className="space-y-4">
          {isLoadingPerms ? (
            <div className="bg-white rounded-[20px] border border-slate-200/90 p-12 text-center text-slate-400 text-xs font-medium shadow-sm">Memuat data izin akses...</div>
          ) : permissionsGrouped.length === 0 ? (
            <div className="bg-white rounded-[20px] border border-slate-200/90 p-12 text-center text-slate-400 text-xs font-medium shadow-sm">Belum ada data izin akses.</div>
          ) : permissionsGrouped.map((group) => (
            <div key={group.modul} className="bg-white rounded-[20px] border border-slate-200/90 shadow-sm overflow-hidden">
              {/* Header Modul */}
              <div className="flex items-center justify-between px-5 py-3.5 bg-[#f4efe6] border-b border-slate-200/90">
                <div className="flex items-center gap-2.5">
                  <div className="w-7 h-7 rounded-lg bg-[#054e3b] flex items-center justify-center">
                    <FaLock className="w-3 h-3 text-white" />
                  </div>
                  <span className="text-xs font-black uppercase tracking-wider text-slate-800">{group.modul}</span>
                </div>
                <span className="text-[11px] font-bold text-slate-500 bg-white border border-slate-200 px-2.5 py-0.5 rounded-full">
                  {group.total} izin
                </span>
              </div>
              {/* Permission List */}
              <div className="p-4 flex flex-wrap gap-2">
                {(group.izin || []).map((perm) => (
                  <div key={perm.id} className="flex items-center gap-1.5 bg-[#f8fafc] border border-slate-200 rounded-xl px-3 py-1.5 group">
                    <span className="text-xs font-bold text-slate-700">{perm.name}</span>
                    <button
                      onClick={() => handleDeletePerm(perm)}
                      className="text-slate-300 hover:text-red-500 transition-colors opacity-0 group-hover:opacity-100"
                      title="Hapus izin"
                    >
                      <FaTimes className="w-3 h-3" />
                    </button>
                  </div>
                ))}
              </div>
            </div>
          ))}
        </div>
      )}

      {/* ───── TAB: HAK AKSES PEGAWAI (MENARIK DATA PEGAWAI) ───── */}
      {activeTab === 'pegawai' && (
        <div className="space-y-4">
          <div className="w-full overflow-x-auto rounded-[20px] border border-slate-200/90 bg-white shadow-sm">
            <table className="w-full text-left border-collapse text-slate-800">
              <thead>
                <tr className="bg-[#f4efe6] border-b border-slate-200/90 text-xs font-black uppercase tracking-wider text-slate-700">
                  <th className="py-3.5 px-4 w-10 text-center">NO</th>
                  <th className="py-3.5 px-4">DATA PEGAWAI</th>
                  <th className="py-3.5 px-4">JABATAN & UNIT</th>
                  <th className="py-3.5 px-4 text-center">STATUS AKUN</th>
                  <th className="py-3.5 px-4">ROLE SAAT INI</th>
                  <th className="py-3.5 px-4 text-center w-36">AKSI</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 text-sm">
                {isLoadingPegawai ? (
                  <tr><td colSpan={6} className="py-12 text-center text-slate-400 text-xs font-medium">Memuat data pegawai...</td></tr>
                ) : listPegawai.length === 0 ? (
                  <tr><td colSpan={6} className="py-12 text-center text-slate-400 text-xs font-medium">Tidak ada data pegawai yang sesuai.</td></tr>
                ) : listPegawai.map((emp, idx) => (
                  <tr key={emp.id} className="hover:bg-slate-50/80 transition-colors">
                    <td className="py-3.5 px-4 text-center text-xs font-bold text-slate-500">
                      {((metaPegawai.current_page || 1) - 1) * (metaPegawai.per_page || 15) + idx + 1}
                    </td>
                    <td className="py-3.5 px-4">
                      <div>
                        <p className="font-extrabold text-slate-900 text-sm">{emp.nama_lengkap}</p>
                        <p className="text-[11px] text-slate-500 font-medium">NIY: {emp.niy || '-'} | Email: {emp.email || '-'}</p>
                      </div>
                    </td>
                    <td className="py-3.5 px-4">
                      <div className="space-y-0.5">
                        <p className="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                          <FaBriefcase className="w-3 h-3 text-slate-400" />
                          {emp.position?.nama || '-'}
                        </p>
                        <p className="text-[11px] text-slate-500 flex items-center gap-1.5">
                          <FaBuilding className="w-3 h-3 text-slate-400" />
                          {emp.unit?.nama || '-'}
                        </p>
                      </div>
                    </td>
                    <td className="py-3.5 px-4 text-center">
                      {emp.has_user ? (
                        <span className="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2.5 py-1 rounded-full">
                          <FaUserCheck className="w-3 h-3" />
                          Terhubung
                        </span>
                      ) : (
                        <span className="inline-flex items-center gap-1 text-[11px] font-bold text-amber-700 bg-amber-50 border border-amber-200 px-2.5 py-1 rounded-full">
                          <FaUserTimes className="w-3 h-3" />
                          Belum Punya Akun
                        </span>
                      )}
                    </td>
                    <td className="py-3.5 px-4">
                      <span className="inline-flex items-center gap-1 font-extrabold text-xs text-[#054e3b] bg-emerald-100 border border-emerald-300 px-3 py-1 rounded-xl">
                        <FaShieldAlt className="w-3 h-3 text-[#054e3b]" />
                        {emp.primary_role}
                      </span>
                    </td>
                    <td className="py-3.5 px-4 text-center">
                      <button
                        onClick={() => handleOpenPegawaiModal(emp)}
                        className="inline-flex items-center gap-1.5 bg-[#054e3b] hover:bg-[#033b2c] text-white px-3 py-1.5 rounded-xl text-xs font-bold shadow-sm transition-all"
                      >
                        <FaUserCog className="w-3.5 h-3.5" />
                        <span>Atur Hak Akses</span>
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {/* Pagination Controls */}
          {metaPegawai.last_page > 1 && (
            <div className="flex items-center justify-between bg-white rounded-2xl border border-slate-200/90 p-4 shadow-sm">
              <p className="text-xs text-slate-500 font-medium">
                Menampilkan <span className="font-bold text-slate-800">{listPegawai.length}</span> dari <span className="font-bold text-slate-800">{metaPegawai.total}</span> data pegawai
              </p>
              <div className="flex gap-2">
                <button
                  disabled={page <= 1}
                  onClick={() => setPage((p) => Math.max(p - 1, 1))}
                  className="px-3.5 py-1.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-700 disabled:opacity-40 hover:bg-slate-50 transition-colors"
                >
                  Sebelumnya
                </button>
                <span className="px-3.5 py-1.5 rounded-xl bg-slate-100 text-xs font-extrabold text-slate-800">
                  {page} / {metaPegawai.last_page}
                </span>
                <button
                  disabled={page >= metaPegawai.last_page}
                  onClick={() => setPage((p) => Math.min(p + 1, metaPegawai.last_page))}
                  className="px-3.5 py-1.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-700 disabled:opacity-40 hover:bg-slate-50 transition-colors"
                >
                  Selanjutnya
                </button>
              </div>
            </div>
          )}
        </div>
      )}

      {/* ───── Modals ───── */}
      <RoleFormModal
        isOpen={isRoleModalOpen}
        onClose={() => { setIsRoleModalOpen(false); setSelectedRole(null) }}
        onSubmit={handleRoleSubmit}
        initialData={selectedRole}
        allPermissions={allPerms}
        isSubmitting={isRoleSubmitting}
      />

      <PermissionFormModal
        isOpen={isPermModalOpen}
        onClose={() => setIsPermModalOpen(false)}
        onSubmit={(payload) => tambahPermMutation.mutate(payload)}
        isSubmitting={isPermSubmitting}
      />

      <PegawaiRoleModal
        isOpen={isPegawaiModalOpen}
        onClose={() => { setIsPegawaiModalOpen(false); setSelectedEmployee(null) }}
        onSubmit={(data) => assignPegawaiRoleMutation.mutate(data)}
        employee={selectedEmployee}
        availableRoles={availableRoleNames}
        allPermissions={allPerms}
        isSubmitting={isPegawaiSubmitting}
      />
    </div>
  )
}
