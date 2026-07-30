import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import Swal from 'sweetalert2'
import { FaEdit, FaKey, FaPlus, FaSearch, FaTimes, FaTrash, FaUserCheck, FaUserTimes } from 'react-icons/fa'
import { hakAksesService } from '../../services/hakAksesService'

const emptyForm = {
  name: '', email: '', phone: '', role: '', is_active: true,
  password: '', password_confirmation: '',
}

function AccountModal({ open, user, roles, saving, onClose, onSave }) {
  const [form, setForm] = useState(emptyForm)
  const editing = Boolean(user?.id)

  useEffect(() => {
    if (!open) return
    setForm(user ? {
      ...emptyForm,
      name: user.name || '',
      email: user.email || '',
      phone: user.phone || '',
      role: user.roles?.[0] || '',
      is_active: Boolean(user.is_active),
    } : { ...emptyForm, role: roles[0] || '' })
  }, [open, user, roles])

  if (!open) return null

  const change = (key, value) => setForm((current) => ({ ...current, [key]: value }))
  const submit = (event) => {
    event.preventDefault()
    if (!editing && form.password !== form.password_confirmation) {
      Swal.fire('Password tidak sama', 'Konfirmasi password harus sama dengan password.', 'warning')
      return
    }
    const payload = editing
      ? { name: form.name, email: form.email, phone: form.phone || null, role: form.role, is_active: form.is_active }
      : form
    onSave(payload)
  }

  return (
    <div className="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm">
      <form onSubmit={submit} className="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div className="flex items-center justify-between border-b px-6 py-4">
          <div>
            <h3 className="font-black text-slate-900">{editing ? 'Edit Akun Login' : 'Tambah Akun Login'}</h3>
            <p className="text-xs text-slate-500">Password tersimpan terenkripsi dan tidak dapat dilihat kembali.</p>
          </div>
          <button type="button" onClick={onClose} className="rounded-lg p-2 text-slate-400 hover:bg-slate-100"><FaTimes /></button>
        </div>
        <div className="grid gap-4 p-6 sm:grid-cols-2">
          <label className="sm:col-span-2 text-xs font-bold text-slate-700">Nama lengkap
            <input required value={form.name} onChange={(e) => change('name', e.target.value)} className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 font-medium outline-none focus:ring-2 focus:ring-emerald-600" />
          </label>
          <label className="sm:col-span-2 text-xs font-bold text-slate-700">Email login
            <input required type="email" value={form.email} onChange={(e) => change('email', e.target.value)} className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 font-medium outline-none focus:ring-2 focus:ring-emerald-600" />
          </label>
          <label className="text-xs font-bold text-slate-700">Nomor HP
            <input value={form.phone} onChange={(e) => change('phone', e.target.value)} className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 font-medium outline-none focus:ring-2 focus:ring-emerald-600" />
          </label>
          <label className="text-xs font-bold text-slate-700">Role
            <select required value={form.role} onChange={(e) => change('role', e.target.value)} className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 font-medium outline-none focus:ring-2 focus:ring-emerald-600">
              <option value="">Pilih role</option>
              {roles.map((role) => <option key={role} value={role}>{role}</option>)}
            </select>
          </label>
          {!editing && <>
            <label className="text-xs font-bold text-slate-700">Password awal
              <input required minLength={8} type="password" value={form.password} onChange={(e) => change('password', e.target.value)} className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 font-medium outline-none focus:ring-2 focus:ring-emerald-600" />
            </label>
            <label className="text-xs font-bold text-slate-700">Konfirmasi password
              <input required minLength={8} type="password" value={form.password_confirmation} onChange={(e) => change('password_confirmation', e.target.value)} className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 font-medium outline-none focus:ring-2 focus:ring-emerald-600" />
            </label>
            <p className="sm:col-span-2 text-[11px] text-slate-500">Minimal 8 karakter dan wajib memiliki huruf besar, huruf kecil, angka, serta simbol.</p>
          </>}
          <label className="sm:col-span-2 flex items-center gap-2 text-xs font-bold text-slate-700">
            <input type="checkbox" checked={form.is_active} onChange={(e) => change('is_active', e.target.checked)} className="h-4 w-4 rounded text-emerald-700" />
            Akun aktif dan dapat login
          </label>
        </div>
        <div className="flex justify-end gap-2 border-t bg-slate-50 px-6 py-4">
          <button type="button" onClick={onClose} className="rounded-xl border px-4 py-2 text-xs font-bold text-slate-600">Batal</button>
          <button disabled={saving} className="rounded-xl bg-emerald-800 px-5 py-2 text-xs font-bold text-white disabled:opacity-50">{saving ? 'Menyimpan...' : 'Simpan akun'}</button>
        </div>
      </form>
    </div>
  )
}

export default function UserAccountManagement({ roles }) {
  const queryClient = useQueryClient()
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [modalOpen, setModalOpen] = useState(false)
  const [selected, setSelected] = useState(null)

  const { data = {}, isLoading } = useQuery({
    queryKey: ['hak-akses-users', search, page],
    queryFn: () => hakAksesService.getUsers({ search, page }),
  })

  const finish = (response) => {
    queryClient.invalidateQueries({ queryKey: ['hak-akses-users'] })
    queryClient.invalidateQueries({ queryKey: ['hak-akses-roles'] })
    setModalOpen(false)
    setSelected(null)
    Swal.fire({ icon: 'success', title: 'Berhasil', text: response?.message, timer: 1800, showConfirmButton: false })
  }
  const fail = (error) => Swal.fire('Gagal', error.response?.data?.message || Object.values(error.response?.data?.errors || {})[0]?.[0] || 'Operasi akun gagal.', 'error')
  const create = useMutation({ mutationFn: hakAksesService.tambahUser, onSuccess: finish, onError: fail })
  const update = useMutation({ mutationFn: hakAksesService.ubahUser, onSuccess: finish, onError: fail })
  const remove = useMutation({ mutationFn: hakAksesService.hapusUser, onSuccess: finish, onError: fail })

  const save = (payload) => selected
    ? update.mutate({ id: selected.id, payload })
    : create.mutate(payload)

  const resetPassword = async (user) => {
    const result = await Swal.fire({
      title: `Reset password ${user.name}`,
      html: '<input id="new-password" type="password" class="swal2-input" placeholder="Password baru"><input id="confirm-password" type="password" class="swal2-input" placeholder="Konfirmasi password"><p style="font-size:12px;color:#64748b">Minimal 8 karakter: huruf besar-kecil, angka, dan simbol.</p>',
      showCancelButton: true,
      confirmButtonText: 'Reset password',
      cancelButtonText: 'Batal',
      confirmButtonColor: '#065f46',
      preConfirm: () => {
        const password = document.getElementById('new-password').value
        const confirmation = document.getElementById('confirm-password').value
        if (!password || password !== confirmation) {
          Swal.showValidationMessage('Password dan konfirmasi harus sama.')
          return false
        }
        return { password, password_confirmation: confirmation }
      },
    })
    if (!result.isConfirmed) return
    try {
      const response = await hakAksesService.resetPassword({ id: user.id, payload: result.value })
      finish(response)
    } catch (error) { fail(error) }
  }

  const deleteUser = async (user) => {
    const result = await Swal.fire({ title: `Hapus akun ${user.name}?`, text: 'Akun akan dikeluarkan dari semua sesi dan tidak dapat login lagi.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Hapus akun', cancelButtonText: 'Batal', confirmButtonColor: '#dc2626' })
    if (result.isConfirmed) remove.mutate(user.id)
  }

  const users = data.data || []
  const meta = data.meta || {}
  return (
    <div className="space-y-4">
      <div className="flex flex-col justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:flex-row sm:items-center">
        <div className="relative w-full sm:max-w-sm">
          <FaSearch className="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400" />
          <input value={search} onChange={(e) => { setSearch(e.target.value); setPage(1) }} placeholder="Cari nama atau email login..." className="w-full rounded-xl border border-slate-200 py-2.5 pl-9 pr-3 text-xs font-semibold outline-none focus:ring-2 focus:ring-emerald-600" />
        </div>
        <button onClick={() => { setSelected(null); setModalOpen(true) }} className="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-800 px-4 py-2.5 text-xs font-bold text-white"><FaPlus /> Tambah akun</button>
      </div>
      <div className="overflow-x-auto rounded-2xl border border-slate-200 bg-white">
        <table className="w-full min-w-[800px] text-left text-sm">
          <thead className="border-b bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-500"><tr><th className="px-4 py-3">Pengguna</th><th className="px-4 py-3">Role</th><th className="px-4 py-3">Status</th><th className="px-4 py-3">Password</th><th className="px-4 py-3 text-center">Aksi</th></tr></thead>
          <tbody className="divide-y divide-slate-100">
            {isLoading ? <tr><td colSpan={5} className="p-12 text-center text-xs text-slate-400">Memuat akun...</td></tr>
              : users.length === 0 ? <tr><td colSpan={5} className="p-12 text-center text-xs text-slate-400">Akun tidak ditemukan.</td></tr>
              : users.map((user) => <tr key={user.id} className="hover:bg-emerald-50/30">
                <td className="px-4 py-3"><p className="font-extrabold text-slate-900">{user.name}</p><p className="text-xs text-slate-500">{user.email}</p></td>
                <td className="px-4 py-3"><span className="rounded-lg bg-blue-50 px-2.5 py-1 text-xs font-bold text-blue-700">{user.roles?.[0] || 'Tanpa role'}</span></td>
                <td className="px-4 py-3">{user.is_active ? <span className="inline-flex items-center gap-1 text-xs font-bold text-emerald-700"><FaUserCheck /> Aktif</span> : <span className="inline-flex items-center gap-1 text-xs font-bold text-rose-600"><FaUserTimes /> Nonaktif</span>}</td>
                <td className="px-4 py-3 text-xs text-slate-500">{user.must_change_password ? 'Wajib diganti' : 'Sudah diatur'}</td>
                <td className="px-4 py-3"><div className="flex justify-center gap-1">
                  <button title="Edit akun" onClick={() => { setSelected(user); setModalOpen(true) }} className="rounded-lg p-2 text-blue-700 hover:bg-blue-50"><FaEdit /></button>
                  <button title="Reset password" onClick={() => resetPassword(user)} className="rounded-lg p-2 text-amber-700 hover:bg-amber-50"><FaKey /></button>
                  <button title="Hapus akun" onClick={() => deleteUser(user)} className="rounded-lg p-2 text-rose-700 hover:bg-rose-50"><FaTrash /></button>
                </div></td>
              </tr>)}
          </tbody>
        </table>
      </div>
      {meta.last_page > 1 && <div className="flex items-center justify-end gap-3 text-xs"><button disabled={page <= 1} onClick={() => setPage((value) => value - 1)} className="rounded-lg border px-3 py-2 disabled:opacity-40">Sebelumnya</button><span>{page} / {meta.last_page}</span><button disabled={page >= meta.last_page} onClick={() => setPage((value) => value + 1)} className="rounded-lg border px-3 py-2 disabled:opacity-40">Berikutnya</button></div>}
      <AccountModal open={modalOpen} user={selected} roles={roles} saving={create.isPending || update.isPending} onClose={() => { setModalOpen(false); setSelected(null) }} onSave={save} />
    </div>
  )
}
