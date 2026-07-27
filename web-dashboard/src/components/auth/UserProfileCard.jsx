import { useRef, useState } from 'react'
import { FiCamera, FiCheck, FiUser } from 'react-icons/fi'
import Swal from 'sweetalert2'
import { useAuthStore } from '../../stores/authStore'

export default function UserProfileCard() {
  const { user, setSession, token } = useAuthStore()
  const fileInputRef = useRef(null)

  const [profile, setProfile] = useState({
    fullName: user?.name || user?.fullName || 'Ahmad Zaky',
    nip: user?.nip || 'ADM001',
    email: user?.email || 'ahmadzaky@dareliman.sch.id',
    phone: user?.phone || '0812-3456-7890',
    role: user?.role || 'Super Admin',
    unit: user?.unit || 'SDIT Dar El-Iman',
    avatar: user?.avatar || null,
  })
  const [saved, setSaved] = useState(false)

  const handlePhotoClick = () => {
    if (fileInputRef.current) {
      fileInputRef.current.click()
    }
  }

  const handleFileChange = (e) => {
    const file = e.target.files?.[0]
    if (!file) return

    if (file.size > 2 * 1024 * 1024) {
      Swal.fire('Ukuran File Terlalu Besar', 'Ukuran foto maksimal adalah 2MB.', 'warning')
      return
    }

    const reader = new FileReader()
    reader.onloadend = () => {
      setProfile((prev) => ({ ...prev, avatar: reader.result }))
    }
    reader.readAsDataURL(file)
  }

  const handleReset = () => {
    setProfile({
      fullName: user?.name || user?.fullName || 'Ahmad Zaky',
      nip: user?.nip || 'ADM001',
      email: user?.email || 'ahmadzaky@dareliman.sch.id',
      phone: user?.phone || '0812-3456-7890',
      role: user?.role || 'Super Admin',
      unit: user?.unit || 'SDIT Dar El-Iman',
      avatar: user?.avatar || null,
    })
  }

  const handleSubmit = (e) => {
    e.preventDefault()

    // Update global auth store state and localStorage
    const updatedUser = {
      ...user,
      name: profile.fullName,
      fullName: profile.fullName,
      email: profile.email,
      phone: profile.phone,
      role: profile.role,
      unit: profile.unit,
      avatar: profile.avatar,
    }

    setSession({ token, user: updatedUser })

    setSaved(true)
    Swal.fire({
      icon: 'success',
      title: 'Profil Berhasil Diperbarui',
      text: 'Data profil dan informasi akun Anda telah disimpan.',
      timer: 2000,
      showConfirmButton: false,
    })
    setTimeout(() => setSaved(false), 2500)
  }

  return (
    <div className="w-full bg-white rounded-2xl shadow-sm border border-slate-200/80 p-6 lg:p-8">
      {/* Hidden File Input for Avatar Upload */}
      <input
        type="file"
        ref={fileInputRef}
        onChange={handleFileChange}
        accept="image/png, image/jpeg, image/jpg"
        className="hidden"
      />

      {/* Breadcrumb */}
      <div className="flex items-center text-xs text-slate-400 mb-6 gap-2 border-b border-slate-100 pb-3">
        <span>Dashboard</span>
        <span>/</span>
        <span className="text-emerald-700 font-semibold">Profil</span>
      </div>

      {saved && (
        <div className="mb-6 bg-emerald-50 border border-emerald-200 rounded-xl p-3.5 text-emerald-800 text-xs font-semibold flex items-center gap-2">
          <FiCheck className="w-4 h-4 text-emerald-600 shrink-0" />
          <span>Perubahan profil berhasil disimpan!</span>
        </div>
      )}

      <form onSubmit={handleSubmit}>
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
          {/* Left Avatar Card */}
          <div className="lg:col-span-4 bg-slate-50/70 p-6 rounded-2xl border border-slate-200/80 flex flex-col items-center text-center">
            <div className="relative mb-4">
              <div className="w-28 h-28 rounded-full ring-4 ring-emerald-600/20 shadow-md overflow-hidden bg-emerald-800 flex items-center justify-center text-white">
                {profile.avatar ? (
                  <img src={profile.avatar} alt={profile.fullName} className="h-full w-full object-cover" />
                ) : (
                  <FiUser className="w-14 h-14" />
                )}
              </div>
              <button
                type="button"
                onClick={handlePhotoClick}
                className="absolute bottom-0 right-0 p-2 bg-emerald-700 hover:bg-emerald-800 text-white rounded-full shadow-md border-2 border-white transition-colors"
                title="Ganti Foto Profil"
              >
                <FiCamera className="w-3.5 h-3.5" />
              </button>
            </div>

            <h3 className="text-base font-bold text-slate-800">{profile.fullName}</h3>
            <span className="inline-block px-3 py-1 bg-emerald-100 text-emerald-800 text-xs font-semibold rounded-full mt-1">
              {profile.role}
            </span>
            <p className="text-[11px] text-slate-400 mt-0.5">Administrator</p>

            <div className="mt-5 w-full pt-4 border-t border-slate-200/60 space-y-2">
              <button
                type="button"
                onClick={handlePhotoClick}
                className="w-full py-2 px-3 bg-white border border-slate-200 hover:bg-slate-100 text-slate-700 font-semibold text-xs rounded-xl transition-all shadow-sm flex items-center justify-center gap-2"
              >
                <FiCamera className="w-3.5 h-3.5 text-slate-500" />
                <span>Ubah Foto</span>
              </button>
              <p className="text-[10px] text-slate-400">Maks. 2MB (JPG, PNG)</p>
            </div>
          </div>

          {/* Right Form Fields */}
          <div className="lg:col-span-8 space-y-5">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {/* Nama Lengkap */}
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                  Nama Lengkap
                </label>
                <input
                  type="text"
                  value={profile.fullName}
                  onChange={(e) => setProfile({ ...profile, fullName: e.target.value })}
                  className="w-full px-3.5 py-2.5 bg-white text-slate-800 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:border-transparent transition-all shadow-sm"
                  required
                />
              </div>

              {/* NIP / ID User */}
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                  NIP / ID User
                </label>
                <input
                  type="text"
                  value={profile.nip}
                  onChange={(e) => setProfile({ ...profile, nip: e.target.value })}
                  className="w-full px-3.5 py-2.5 bg-slate-100 text-slate-600 text-sm rounded-xl border border-slate-200 cursor-not-allowed"
                  disabled
                />
              </div>

              {/* Email */}
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                  Email
                </label>
                <input
                  type="email"
                  value={profile.email}
                  onChange={(e) => setProfile({ ...profile, email: e.target.value })}
                  className="w-full px-3.5 py-2.5 bg-white text-slate-800 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:border-transparent transition-all shadow-sm"
                  required
                />
              </div>

              {/* No Handphone */}
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                  No. Handphone
                </label>
                <input
                  type="text"
                  value={profile.phone}
                  onChange={(e) => setProfile({ ...profile, phone: e.target.value })}
                  className="w-full px-3.5 py-2.5 bg-white text-slate-800 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:border-transparent transition-all shadow-sm"
                  required
                />
              </div>

              {/* Role / Jabatan */}
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                  Role / Jabatan
                </label>
                <select
                  value={profile.role}
                  onChange={(e) => setProfile({ ...profile, role: e.target.value })}
                  className="w-full px-3.5 py-2.5 bg-white text-slate-800 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:border-transparent transition-all shadow-sm"
                >
                  <option value="Super Admin">Super Admin</option>
                  <option value="Kepala Sekolah">Kepala Sekolah</option>
                  <option value="Guru">Guru</option>
                  <option value="Tata Usaha">Tata Usaha</option>
                  <option value="Divisi Pendidikan">Divisi Pendidikan</option>
                </select>
              </div>

              {/* Unit Pendidikan */}
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                  Unit Pendidikan
                </label>
                <select
                  value={profile.unit}
                  onChange={(e) => setProfile({ ...profile, unit: e.target.value })}
                  className="w-full px-3.5 py-2.5 bg-white text-slate-800 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:border-transparent transition-all shadow-sm"
                >
                  <option value="SDIT Dar El-Iman">SDIT Dar El-Iman</option>
                  <option value="TKIT Dar El-Iman">TKIT Dar El-Iman</option>
                  <option value="SMPIT Dar El-Iman">SMPIT Dar El-Iman</option>
                  <option value="SMAIT Dar El-Iman">SMAIT Dar El-Iman</option>
                  <option value="Pondok Pesantren">Pondok Pesantren</option>
                  <option value="Ma'had">Ma'had</option>
                </select>
              </div>
            </div>

            {/* Form Actions */}
            <div className="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
              <button
                type="button"
                onClick={handleReset}
                className="py-2.5 px-5 bg-white border border-slate-200 hover:bg-slate-100 text-slate-700 font-semibold text-xs rounded-xl transition-all"
              >
                Batal
              </button>
              <button
                type="submit"
                className="py-2.5 px-6 bg-emerald-800 hover:bg-emerald-900 text-white font-semibold text-xs rounded-xl shadow-md shadow-emerald-800/20 hover:shadow-lg transition-all"
              >
                Simpan Perubahan
              </button>
            </div>
          </div>
        </div>
      </form>
    </div>
  )
}

