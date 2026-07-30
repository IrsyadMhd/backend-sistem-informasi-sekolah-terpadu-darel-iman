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
  FaArrowRight,
  FaLayerGroup,
} from 'react-icons/fa'
import { hakAksesService } from '../services/hakAksesService'
import UserAccountManagement from '../components/auth/UserAccountManagement'

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
      <div className="my-6 w-full max-w-3xl overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-2xl">
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
              className="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-800 shadow-sm transition-all placeholder:text-slate-400 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-emerald-600"
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
            <button type="submit" disabled={isSubmitting} className="flex items-center gap-1.5 rounded-xl bg-emerald-800 px-6 py-2.5 text-xs font-semibold text-white shadow-md shadow-emerald-800/20 transition-all hover:bg-emerald-900 disabled:opacity-50">
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
      <div className="my-6 w-full max-w-md overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-2xl">
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
              className="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-800 shadow-sm transition-all placeholder:text-slate-400 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-emerald-600"
            />
            {error && <p className="mt-1 text-xs text-rose-500">{error}</p>}
            <p className="mt-1.5 text-[11px] text-slate-500">Format: <code className="bg-slate-100 px-1 rounded">modul.aksi</code> — misalnya: <code className="bg-slate-100 px-1 rounded">tahfizh.monitoring_target</code></p>
          </div>

          <div className="flex items-center justify-between border-t border-slate-100 pt-4">
            <button type="button" onClick={onClose} className="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition-colors">
              Batal
            </button>
            <button type="submit" disabled={isSubmitting} className="flex items-center gap-1.5 rounded-xl bg-emerald-800 px-6 py-2.5 text-xs font-semibold text-white shadow-md shadow-emerald-800/20 transition-all hover:bg-emerald-900 disabled:opacity-50">
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
      <div className="my-6 w-full max-w-2xl overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-2xl">
        <div className="flex items-center justify-between border-b border-slate-100 bg-white px-7 py-5">
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
                className="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-800 shadow-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-emerald-600"
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
              className="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm font-semibold text-slate-800 shadow-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-emerald-600"
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
            <button type="submit" disabled={isSubmitting} className="flex items-center gap-1.5 rounded-xl bg-emerald-800 px-6 py-2.5 text-xs font-semibold text-white shadow-md shadow-emerald-800/20 transition-all hover:bg-emerald-900 disabled:opacity-50">
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

  const [activeTab, setActiveTab] = useState('roles')
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

  const tabConfig = {
    roles: {
      label: 'Role',
      description: 'Kelompok akses pengguna',
      icon: FaShieldAlt,
      count: stats.total_role ?? 0,
      search: 'Cari nama role...',
    },
    permissions: {
      label: 'Izin akses',
      description: 'Aksi yang dapat dilakukan',
      icon: FaKey,
      count: stats.total_permission ?? 0,
      search: 'Cari modul atau izin akses...',
    },
    pegawai: {
      label: 'Akses pegawai',
      description: 'Role setiap anggota tim',
      icon: FaUserShield,
      count: metaPegawai.total ?? '—',
      search: 'Cari nama, NIY, atau email pegawai...',
    },
    akun: {
      label: 'Akun login',
      description: 'CRUD akun, role, status, dan password',
      icon: FaUserCog,
      count: '—',
      search: 'Pencarian tersedia pada tabel akun...',
    },
  }

  const activeTabConfig = tabConfig[activeTab]
  const ActiveTabIcon = activeTabConfig.icon

  return (
    <div className="space-y-6 pb-12">
      {/* ───── Page header ───── */}
      <section className="flex flex-col justify-between gap-4 rounded-2xl border border-slate-200/80 bg-white p-6 shadow-xs md:flex-row md:items-center">
          <div>
            <h1 className="text-2xl font-black tracking-tight text-slate-800">
              Manajemen Hak Akses
            </h1>
            <p className="mt-1 text-xs text-slate-500">
              Atur siapa yang dapat melihat dan mengelola setiap bagian sistem sekolah melalui role dan izin yang terstruktur.
            </p>
          </div>
          <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
            <button
              onClick={() => { setActiveTab('permissions'); setIsPermModalOpen(true) }}
              className="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-xs font-semibold text-slate-700 transition-all hover:bg-slate-100"
            >
              <FaKey className="h-3.5 w-3.5 text-slate-500" />
              <span>Tambah izin</span>
            </button>
            <button
              onClick={() => { setActiveTab('roles'); handleOpenCreateRole() }}
              className="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl bg-emerald-800 px-4 text-xs font-semibold text-white shadow-md shadow-emerald-800/20 transition-all hover:bg-emerald-900 hover:shadow-lg"
            >
              <FaPlus className="w-3.5 h-3.5" />
              <span>Tambah role</span>
            </button>
          </div>
      </section>

      {/* ───── Stats Cards ───── */}
      <section className="grid grid-cols-2 gap-4 lg:grid-cols-4">
        {[
          { label: 'Role aktif', value: stats.total_role ?? 0, icon: FaShieldAlt, tone: 'emerald', sub: 'Kelompok akses' },
          { label: 'Izin tersedia', value: stats.total_permission ?? 0, icon: FaKey, tone: 'blue', sub: 'Aksi terdaftar' },
          { label: 'Modul terlindungi', value: stats.total_modul ?? 0, icon: FaLayerGroup, tone: 'violet', sub: 'Area sistem' },
          { label: 'Belum digunakan', value: stats.role_tanpa_user ?? 0, icon: FaUsers, tone: 'amber', sub: 'Role tanpa pengguna' },
        ].map((card) => (
          <div key={card.label} className="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm sm:p-5">
            <div className={`mb-4 flex h-10 w-10 items-center justify-center rounded-xl ${
              card.tone === 'emerald' ? 'bg-emerald-50 text-emerald-700' :
              card.tone === 'blue' ? 'bg-blue-50 text-blue-700' :
              card.tone === 'violet' ? 'bg-violet-50 text-violet-700' :
              'bg-amber-50 text-amber-700'
            }`}>
              <card.icon className="w-5 h-5" />
            </div>
            <p className="text-2xl font-black leading-none text-slate-900 sm:text-3xl">{card.value}</p>
            <p className="mt-2 truncate text-xs font-extrabold text-slate-700">{card.label}</p>
            <p className="mt-0.5 truncate text-[11px] text-slate-400">{card.sub}</p>
          </div>
        ))}
      </section>

      {/* ───── Tabs ───── */}
      <section className="flex gap-1 overflow-x-auto rounded-2xl border border-slate-200/80 bg-white p-2 shadow-xs">
          {Object.entries(tabConfig).map(([key, tab]) => {
            const TabIcon = tab.icon
            const isActive = activeTab === key
            return (
              <button
                key={key}
                type="button"
                onClick={() => { setActiveTab(key); setSearch(''); setPage(1) }}
                className={`flex items-center gap-2 whitespace-nowrap rounded-xl px-4 py-2.5 text-xs font-bold transition-all ${
                  isActive
                    ? 'bg-emerald-800 text-white shadow-md shadow-emerald-800/20'
                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                }`}
              >
                <TabIcon className={`h-4 w-4 ${isActive ? 'text-amber-300' : 'text-slate-400'}`} />
                <span>{tab.label}</span>
                <span className={`rounded-full px-2 py-0.5 text-[10px] font-bold ${isActive ? 'bg-white/15 text-white' : 'bg-slate-100 text-slate-500'}`}>
                  {tab.count}
                </span>
              </button>
            )
          })}
      </section>

      {/* ───── Content toolbar ───── */}
      {activeTab !== 'akun' && <section className="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div className="flex items-center gap-2">
            <ActiveTabIcon className="h-4 w-4 text-emerald-700" />
            <div>
              <h2 className="text-sm font-bold text-slate-800">{activeTabConfig.label}</h2>
              <p className="text-[11px] text-slate-400">{activeTabConfig.description}</p>
            </div>
          </div>
          <div className="relative w-full sm:max-w-sm">
            <FaSearch className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs" />
            <input
              type="text"
              value={search}
              onChange={(e) => { setSearch(e.target.value); setPage(1) }}
              placeholder={activeTabConfig.search}
              aria-label={activeTabConfig.search}
              className="min-h-10 w-full rounded-xl border border-slate-200 bg-white pl-9 pr-9 text-xs font-semibold text-slate-700 shadow-sm outline-none transition-all placeholder:text-slate-400 focus:border-transparent focus:ring-2 focus:ring-emerald-600"
            />
            {search && (
              <button type="button" onClick={() => setSearch('')} aria-label="Hapus pencarian" className="absolute right-3 top-1/2 -translate-y-1/2 rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                <FaTimes className="h-3 w-3" />
              </button>
            )}
          </div>
        </div>
      </section>}

      {/* ───── TAB: ROLES ───── */}
      {activeTab === 'roles' && (
        <div className="w-full overflow-x-auto rounded-2xl border border-slate-200/80 bg-white shadow-sm">
          <table className="w-full min-w-[780px] border-collapse text-left text-slate-800">
            <thead>
              <tr className="border-b border-slate-200/80 bg-slate-50/80 text-[10px] font-black uppercase tracking-[0.12em] text-slate-500">
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
                <tr><td colSpan={6} className="py-16 text-center text-slate-400 text-xs font-medium">Memuat daftar role...</td></tr>
              ) : roles.length === 0 ? (
                <tr>
                  <td colSpan={6} className="px-4 py-16 text-center">
                    <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700"><FaShieldAlt className="h-5 w-5" /></div>
                    <p className="mt-3 text-sm font-extrabold text-slate-800">{search ? 'Role tidak ditemukan' : 'Belum ada role'}</p>
                    <p className="mt-1 text-xs text-slate-400">{search ? 'Coba gunakan kata kunci yang berbeda.' : 'Buat role pertama untuk mulai mengatur akses pengguna.'}</p>
                    {!search && <button type="button" onClick={handleOpenCreateRole} className="mt-4 inline-flex items-center gap-2 rounded-xl bg-[#064e3b] px-4 py-2 text-xs font-bold text-white"><FaPlus className="h-3 w-3" />Tambah role</button>}
                  </td>
                </tr>
              ) : roles.map((role, idx) => (
                <tr key={role.id} className="group hover:bg-emerald-50/30 transition-colors">
                  <td className="py-3.5 px-4 text-center text-xs font-bold text-slate-500">{idx + 1}</td>
                  <td className="py-3.5 px-4">
                    <div className="flex items-center gap-2.5">
                      <div className="w-8 h-8 rounded-xl bg-[#dcfce7] flex items-center justify-center shrink-0">
                        <FaShieldAlt className="w-3.5 h-3.5 text-[#15803d]" />
                      </div>
                      <div>
                        <p className="font-extrabold text-slate-900 text-sm">{role.name}</p>
                        <p className="text-[10px] text-slate-400">Guard: {role.guard_name}</p>
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
                      {(role.permissions || []).length === 0 && <span className="text-[11px] italic text-slate-400">Belum ada izin</span>}
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
        <div className="grid gap-4 lg:grid-cols-2">
          {isLoadingPerms ? (
            <div className="col-span-full rounded-[22px] border border-slate-200/80 bg-white p-16 text-center text-xs font-medium text-slate-400">Memuat izin akses...</div>
          ) : permissionsGrouped.length === 0 ? (
            <div className="col-span-full rounded-[22px] border border-slate-200/80 bg-white p-16 text-center">
              <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50 text-blue-700"><FaKey className="h-5 w-5" /></div>
              <p className="mt-3 text-sm font-extrabold text-slate-800">{search ? 'Izin tidak ditemukan' : 'Belum ada izin akses'}</p>
              <p className="mt-1 text-xs text-slate-400">{search ? 'Periksa kembali kata kunci pencarian.' : 'Tambahkan izin untuk mendefinisikan aksi di setiap modul.'}</p>
            </div>
          ) : permissionsGrouped.map((group) => (
            <div key={group.modul} className="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
              {/* Header Modul */}
              <div className="flex items-center justify-between border-b border-slate-100 bg-slate-50/70 px-5 py-4">
                <div className="flex items-center gap-2.5">
                  <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700">
                    <FaLock className="w-3 h-3" />
                  </div>
                  <span className="text-xs font-black uppercase tracking-wider text-slate-800">{group.modul}</span>
                </div>
                <span className="text-[11px] font-bold text-slate-500 bg-white border border-slate-200 px-2.5 py-0.5 rounded-full">
                  {group.total} izin
                </span>
              </div>
              {/* Permission List */}
              <div className="flex flex-wrap gap-2 p-5">
                {(group.izin || []).map((perm) => (
                  <div key={perm.id} className="group flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 transition-colors hover:border-emerald-200 hover:bg-emerald-50/50">
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
          <div className="w-full overflow-x-auto rounded-2xl border border-slate-200/80 bg-white shadow-sm">
            <table className="w-full min-w-[900px] border-collapse text-left text-slate-800">
              <thead>
                <tr className="border-b border-slate-200/80 bg-slate-50/80 text-[10px] font-black uppercase tracking-[0.12em] text-slate-500">
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
                  <tr><td colSpan={6} className="px-4 py-16 text-center"><FaUsers className="mx-auto h-6 w-6 text-slate-300" /><p className="mt-3 text-sm font-extrabold text-slate-800">Pegawai tidak ditemukan</p><p className="mt-1 text-xs text-slate-400">Coba gunakan nama, NIY, atau email yang berbeda.</p></td></tr>
                ) : listPegawai.map((emp, idx) => (
                  <tr key={emp.id} className="hover:bg-emerald-50/30 transition-colors">
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
                        className="inline-flex min-h-9 items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3 text-xs font-bold text-emerald-800 transition-all hover:border-emerald-700 hover:bg-emerald-700 hover:text-white"
                      >
                        <FaUserCog className="w-3.5 h-3.5" />
                        <span>Kelola akses</span>
                        <FaArrowRight className="h-2.5 w-2.5" />
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

      {activeTab === 'akun' && (
        <UserAccountManagement roles={availableRoleNames} />
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
