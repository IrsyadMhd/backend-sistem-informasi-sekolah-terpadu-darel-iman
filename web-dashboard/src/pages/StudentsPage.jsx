import { useMemo, useState } from 'react'
import Swal from 'sweetalert2'
import {
  FaBook,
  FaDownload,
  FaEdit,
  FaEye,
  FaFilePdf,
  FaFilter,
  FaPlus,
  FaPrint,
  FaRedo,
  FaSearch,
  FaTrash,
  FaUserGraduate,
  FaUsers,
} from 'react-icons/fa'
import { useDaftarKelas } from '../hooks/useReferenceData'
import { useAksiSiswa, useDaftarSiswa } from '../hooks/useStudents'
import { studentService } from '../services/studentService'

const tahunAjaranList = ['2024/2025', '2023/2024', '2022/2023']

function ambilFoto(row) {
  const metadata = row?.metadata || {}
  return metadata?.foto_url || `https://ui-avatars.com/api/?name=${encodeURIComponent(row?.full_name || 'Siswa')}&background=0D8ABC&color=fff`
}

function statusBadge(status) {
  const value = String(status || '').toLowerCase()
  if (value === 'aktif') return 'bg-emerald-100 text-emerald-700'
  if (value === 'mutasi') return 'bg-amber-100 text-amber-700'
  if (value === 'lulus') return 'bg-blue-100 text-blue-700'
  return 'bg-rose-100 text-rose-700'
}

function genderBadge(gender) {
  return String(gender || '').toLowerCase() === 'perempuan' ? 'bg-pink-100 text-pink-700' : 'bg-blue-100 text-blue-700'
}

function initialFormState() {
  return {
    id: null,
    class_id: '',
    nis: '',
    full_name: '',
    gender: 'male',
    birth_date: '',
    birth_place: '',
    address: '',
    is_active: true,
    metadata_nisn: '',
    metadata_unit_pendidikan: '',
  }
}

export default function StudentsPage() {
  const [searchInput, setSearchInput] = useState('')
  const [searchApplied, setSearchApplied] = useState('')
  const [kelasFilter, setKelasFilter] = useState('')
  const [statusFilter, setStatusFilter] = useState('')
  const [tahunAjaranFilter, setTahunAjaranFilter] = useState('2024/2025')
  const [unitFilter, setUnitFilter] = useState('')
  const [halaman, setHalaman] = useState(1)
  const [selectedStudent, setSelectedStudent] = useState(null)
  const [showForm, setShowForm] = useState(false)
  const [isEdit, setIsEdit] = useState(false)
  const [form, setForm] = useState(initialFormState())
  const perHalaman = 10

  const { data: dashboardData } = useDaftarSiswa({ dashboard: true, per_page: 200, search: searchApplied })
  const { data: daftarSiswa } = useDaftarSiswa({ search: searchApplied, per_page: 200 })
  const { data: daftarKelas } = useDaftarKelas({ per_page: 200 })
  const { tambah, ubah, hapus } = useAksiSiswa()

  const classesRaw = daftarKelas?.data || []
  const studentsRaw = daftarSiswa?.data || []
  const dashboard = dashboardData?.statistik ? dashboardData : null

  const kelasOptions = useMemo(() => {
    return classesRaw
      .map((k) => ({
        id: k?.id,
        label: [k?.level, k?.name].filter(Boolean).join(' ') || k?.name || '-',
        name: k?.name || '-',
        level: k?.level || '',
        unit: k?.metadata?.unit_pendidikan || '',
      }))
      .filter((k) => k.id)
  }, [classesRaw])

  const unitPendidikanOptions = useMemo(() => {
    const unitsFromClass = kelasOptions.map((k) => k.unit).filter(Boolean)
    const unitsFromStudent = studentsRaw.map((row) => row?.metadata?.akademik?.unit_pendidikan || row?.metadata?.unit_pendidikan).filter(Boolean)
    return Array.from(new Set([...unitsFromClass, ...unitsFromStudent]))
  }, [kelasOptions, studentsRaw])

  const siswaList = useMemo(() => {
    return studentsRaw.map((row, index) => {
      const metadata = row?.metadata || {}
      const akademik = metadata?.akademik || {}
      const kelasLabel = akademik?.kelas || [row?.class?.level, row?.class?.name].filter(Boolean).join(' ') || '-'
      const statusSiswaRaw = String(akademik?.status_siswa || (row?.is_active ? 'aktif' : 'nonaktif')).toLowerCase()
      const status = ['pindah', 'keluar', 'dropout', 'nonaktif'].includes(statusSiswaRaw)
        ? 'Nonaktif'
        : statusSiswaRaw === 'lulus'
          ? 'Lulus'
          : statusSiswaRaw === 'mutasi'
            ? 'Mutasi'
            : 'Aktif'

      return {
        id: row.id,
        no: index + 1,
        foto: ambilFoto(row),
        nis: row?.nis || '-',
        nisn: metadata?.nisn || '-',
        nama: row?.full_name || '-',
        kelas: kelasLabel,
        classId: row?.class_id || '',
        gender: row?.gender === 'female' ? 'Perempuan' : 'Laki-laki',
        orangTua: metadata?.ayah?.nama || metadata?.ibu?.nama || metadata?.wali?.nama || '-',
        noHp: metadata?.ayah?.hp || metadata?.ibu?.hp || metadata?.wali?.hp || '-',
        status,
        tahunAjaran: akademik?.tahun_ajaran_masuk || '2024/2025',
        unit: akademik?.unit_pendidikan || metadata?.unit_pendidikan || '-',
        raw: row,
      }
    })
  }, [studentsRaw])

  const filteredRows = useMemo(() => {
    return siswaList.filter((row) => {
      const matchKelas = !kelasFilter || row.classId === kelasFilter || row.kelas === kelasFilter
      const matchStatus = !statusFilter || String(row.status).toLowerCase() === String(statusFilter).toLowerCase()
      const matchTahun = !tahunAjaranFilter || row.tahunAjaran === tahunAjaranFilter
      const matchUnit = !unitFilter || row.unit === unitFilter
      return matchKelas && matchStatus && matchTahun && matchUnit
    })
  }, [siswaList, kelasFilter, statusFilter, tahunAjaranFilter, unitFilter])

  const totalData = filteredRows.length
  const totalHalaman = Math.max(1, Math.ceil(totalData / perHalaman))
  const halamanAktif = Math.min(halaman, totalHalaman)

  const rows = useMemo(() => {
    const start = (halamanAktif - 1) * perHalaman
    return filteredRows.slice(start, start + perHalaman).map((row, idx) => ({ ...row, no: start + idx + 1 }))
  }, [filteredRows, halamanAktif])

  const dashboardStats = dashboard?.statistik || {
    total_siswa: siswaList.length,
    total_kelas: kelasOptions.length,
    siswa_baru: siswaList.filter((row) => row.tahunAjaran === tahunAjaranFilter).length,
    mutasi_keluar: siswaList.filter((row) => String(row.status).toLowerCase() === 'mutasi').length,
    alumni: siswaList.filter((row) => String(row.status).toLowerCase() === 'lulus').length,
  }

  const laporan = dashboard?.laporan_siswa || {
    siswa_baru: dashboardStats.siswa_baru || 0,
    mutasi_masuk: 0,
    mutasi_keluar: dashboardStats.mutasi_keluar || 0,
    siswa_lulus: dashboardStats.alumni || 0,
    grafik_tahunan: [],
  }

  const kelasRombel = dashboard?.kelas_rombel || kelasOptions.map((k) => ({
    id: k.id,
    nama: k.name,
    level: k.level,
    wali_kelas: '-',
    kapasitas: 35,
    jumlah_siswa: siswaList.filter((s) => s.classId === k.id).length,
  }))

  const daftarSiswaDashboard = dashboard?.daftar_siswa || rows.map((row) => ({
    id: row.id,
    nis: row.nis,
    nama: row.nama,
    kelas: row.kelas,
    aktif: String(row.status).toLowerCase() === 'aktif',
  }))

  const dataLengkap = selectedStudent || dashboard?.siswa_terpilih || null

  const applyFilter = () => {
    setSearchApplied(searchInput.trim())
    setHalaman(1)
  }

  const resetFilter = () => {
    setSearchInput('')
    setSearchApplied('')
    setKelasFilter('')
    setStatusFilter('')
    setTahunAjaranFilter('2024/2025')
    setUnitFilter('')
    setHalaman(1)
  }

  const bukaTambah = () => {
    setIsEdit(false)
    setForm(initialFormState())
    setShowForm(true)
  }

  const bukaEdit = (row) => {
    const metadata = row?.raw?.metadata || {}
    setIsEdit(true)
    setForm({
      id: row.id,
      class_id: row.raw?.class_id || '',
      nis: row.raw?.nis || '',
      full_name: row.raw?.full_name || '',
      gender: row.raw?.gender || 'male',
      birth_date: row.raw?.birth_date ? String(row.raw.birth_date).slice(0, 10) : '',
      birth_place: row.raw?.birth_place || '',
      address: row.raw?.address || '',
      is_active: !!row.raw?.is_active,
      metadata_nisn: metadata?.nisn || '',
      metadata_unit_pendidikan: metadata?.akademik?.unit_pendidikan || metadata?.unit_pendidikan || '',
    })
    setShowForm(true)
  }

  const submitForm = async (event) => {
    event.preventDefault()

    const payload = {
      class_id: form.class_id || null,
      nis: form.nis,
      full_name: form.full_name,
      gender: form.gender,
      birth_date: form.birth_date || null,
      birth_place: form.birth_place || null,
      address: form.address || null,
      is_active: !!form.is_active,
      metadata: {
        nisn: form.metadata_nisn || null,
        unit_pendidikan: form.metadata_unit_pendidikan || null,
        akademik: {
          unit_pendidikan: form.metadata_unit_pendidikan || null,
          tahun_ajaran_masuk: tahunAjaranFilter,
        },
      },
    }

    if (isEdit && form.id) {
      await ubah.mutateAsync({ id: form.id, payload })
    } else {
      await tambah.mutateAsync(payload)
    }

    setShowForm(false)
    setForm(initialFormState())
  }

  const hapusData = async (row) => {
    const result = await Swal.fire({
      title: 'Hapus data siswa?',
      text: `Data ${row.nama} akan dihapus permanen.`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Ya, Hapus',
      cancelButtonText: 'Batal',
    })

    if (!result.isConfirmed) return
    await hapus.mutateAsync(row.id)
  }

  const lihatDetail = async (row) => {
    try {
      const detail = await studentService.getDetail(row.id)
      const d = detail?.data || detail
      setSelectedStudent({
        id: d?.id,
        nis: d?.nis || '-',
        nama: d?.full_name || '-',
        jenis_kelamin: d?.gender || '-',
        tempat_lahir: d?.birth_place || '-',
        tanggal_lahir: d?.birth_date || '-',
        alamat: d?.address || '-',
        status: d?.is_active ? 'Aktif' : 'Nonaktif',
        kelas: d?.metadata?.kelas_label || row.kelas || '-',
        tahun_masuk: d?.metadata?.tahun_masuk || tahunAjaranFilter,
        orang_tua: {
          nama_ayah: d?.metadata?.nama_ayah || '-',
          nama_ibu: d?.metadata?.nama_ibu || '-',
          no_hp: d?.metadata?.no_hp || '-',
          pekerjaan_ayah: d?.metadata?.pekerjaan_ayah || '-',
          pekerjaan_ibu: d?.metadata?.pekerjaan_ibu || '-',
        },
      })
    } catch {
      setSelectedStudent(null)
    }
  }

  return (
    <section className="space-y-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
      <header className="space-y-3">
        <div>
          <h2 className="text-3xl font-extrabold uppercase tracking-wide text-slate-900">Manajemen Data Siswa</h2>
          <p className="text-sm text-slate-600">Pengelolaan Seluruh Data Administrasi Siswa</p>
        </div>
        <div className="flex flex-wrap items-center justify-between gap-2">
          <div className="text-sm text-slate-600">Dashboard <span className="mx-1">{'>'}</span> Siswa <span className="mx-1">{'>'}</span> <strong>Data Seluruh Siswa</strong></div>
          <div className="flex flex-wrap gap-2">
            <button className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-bold text-emerald-700"><FaDownload className="mr-2 inline" /> Export Excel</button>
            <button className="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-bold text-slate-700"><FaPrint className="mr-2 inline" /> Cetak PDF</button>
            <button onClick={bukaTambah} className="rounded-md bg-emerald-700 px-4 py-2 text-sm font-bold text-white"><FaPlus className="mr-2 inline" /> Tambah Data Siswa</button>
          </div>
        </div>
      </header>

      <article className="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
        <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Siswa</p>
          <h3 className="text-3xl font-extrabold text-slate-900">{dashboardStats.total_siswa || 0}</h3>
          <p className="mt-1 text-xs text-slate-500">Siswa Aktif</p>
        </div>
        <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Kelas</p>
          <h3 className="text-3xl font-extrabold text-slate-900">{dashboardStats.total_kelas || 0}</h3>
          <p className="mt-1 text-xs text-slate-500">Rombongan Belajar</p>
        </div>
        <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Siswa Baru</p>
          <h3 className="text-3xl font-extrabold text-slate-900">{dashboardStats.siswa_baru || 0}</h3>
          <p className="mt-1 text-xs text-slate-500">Tahun Ajaran {tahunAjaranFilter}</p>
        </div>
        <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Mutasi Keluar</p>
          <h3 className="text-3xl font-extrabold text-slate-900">{dashboardStats.mutasi_keluar || 0}</h3>
          <p className="mt-1 text-xs text-slate-500">Tahun Ajaran {tahunAjaranFilter}</p>
        </div>
        <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Alumni (Lulus)</p>
          <h3 className="text-3xl font-extrabold text-slate-900">{dashboardStats.alumni || 0}</h3>
          <p className="mt-1 text-xs text-slate-500">Data Kelulusan</p>
        </div>
      </article>

      <article className="space-y-3 rounded-xl border border-slate-200 bg-white p-3">
        <div className="grid gap-2 lg:grid-cols-6">
          <div className="relative lg:col-span-2">
            <FaSearch className="absolute left-3 top-3 text-xs text-slate-400" />
            <input
              value={searchInput}
              onChange={(e) => setSearchInput(e.target.value)}
              placeholder="Cari nama siswa, NIS, NISN..."
              className="w-full rounded-md border border-slate-300 py-2 pl-8 pr-2 text-sm outline-none focus:border-emerald-500"
            />
          </div>

          <select value={kelasFilter} onChange={(e) => setKelasFilter(e.target.value)} className="rounded-md border border-slate-300 px-2 py-2 text-sm">
            <option value="">Semua Kelas/Rombel</option>
            {kelasOptions.map((k) => <option key={k.id} value={k.id}>{k.label}</option>)}
          </select>

          <select value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)} className="rounded-md border border-slate-300 px-2 py-2 text-sm">
            <option value="">Semua Status</option>
            <option value="Aktif">Aktif</option>
            <option value="Mutasi">Mutasi</option>
            <option value="Lulus">Lulus</option>
            <option value="Nonaktif">Nonaktif</option>
          </select>

          <select value={tahunAjaranFilter} onChange={(e) => setTahunAjaranFilter(e.target.value)} className="rounded-md border border-slate-300 px-2 py-2 text-sm">
            {tahunAjaranList.map((t) => <option key={t} value={t}>{t}</option>)}
          </select>

          <div className="flex gap-2">
            <button onClick={applyFilter} className="flex-1 rounded-md bg-emerald-700 px-3 py-2 text-sm font-bold text-white"><FaFilter className="mr-1 inline" /> Filter</button>
            <button onClick={resetFilter} className="flex-1 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-bold text-slate-700"><FaRedo className="mr-1 inline" /> Reset</button>
          </div>
        </div>

        <div>
          <label className="mb-1 block text-xs font-semibold text-slate-500">Unit Pendidikan (dari database)</label>
          <select value={unitFilter} onChange={(e) => setUnitFilter(e.target.value)} className="w-full rounded-md border border-slate-300 px-2 py-2 text-sm">
            <option value="">Semua Unit Pendidikan</option>
            {unitPendidikanOptions.map((u) => <option key={u} value={u}>{u}</option>)}
          </select>
        </div>
      </article>

      <article className="grid gap-3 xl:grid-cols-3">
        <div className="rounded-xl border border-slate-200 bg-white p-3">
          <h3 className="mb-3 flex items-center gap-2 text-lg font-bold text-slate-900"><FaUsers className="text-emerald-700" /> Data Lengkap Siswa</h3>
          <div className="space-y-2">
            {(daftarSiswaDashboard || []).slice(0, 8).map((s) => (
              <div key={s.id} className="flex items-center justify-between rounded border border-slate-200 px-3 py-2 text-sm">
                <div>
                  <p className="font-semibold text-slate-800">{s.nama}</p>
                  <p className="text-xs text-slate-500">NIS {s.nis} • {s.kelas}</p>
                </div>
                <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${s.aktif ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'}`}>
                  {s.aktif ? 'Aktif' : 'Nonaktif'}
                </span>
              </div>
            ))}
          </div>
        </div>

        <div className="rounded-xl border border-slate-200 bg-white p-3">
          <h3 className="mb-3 flex items-center gap-2 text-lg font-bold text-slate-900"><FaBook className="text-emerald-700" /> Kelas &amp; Rombel</h3>
          <div className="space-y-2">
            {kelasRombel.slice(0, 8).map((k) => (
              <div key={k.id} className="rounded border border-slate-200 px-3 py-2 text-sm">
                <p className="font-semibold text-slate-800">{[k.level, k.nama].filter(Boolean).join(' ')}</p>
                <p className="text-xs text-slate-500">Wali: {k.wali_kelas || '-'} • {k.jumlah_siswa || 0} siswa • Kapasitas {k.kapasitas || 0}</p>
              </div>
            ))}
          </div>
        </div>

        <div className="rounded-xl border border-slate-200 bg-white p-3">
          <h3 className="mb-3 flex items-center gap-2 text-lg font-bold text-slate-900"><FaUserGraduate className="text-emerald-700" /> Laporan Siswa Masuk &amp; Keluar</h3>
          <div className="space-y-2 text-sm">
            <div className="flex items-center justify-between rounded border border-slate-200 px-3 py-2">
              <span>Siswa Baru</span>
              <strong>{laporan.siswa_baru || 0}</strong>
            </div>
            <div className="flex items-center justify-between rounded border border-slate-200 px-3 py-2">
              <span>Mutasi Masuk</span>
              <strong>{laporan.mutasi_masuk || 0}</strong>
            </div>
            <div className="flex items-center justify-between rounded border border-slate-200 px-3 py-2">
              <span>Mutasi Keluar</span>
              <strong>{laporan.mutasi_keluar || 0}</strong>
            </div>
            <div className="flex items-center justify-between rounded border border-slate-200 px-3 py-2">
              <span>Siswa Lulus</span>
              <strong>{laporan.siswa_lulus || 0}</strong>
            </div>
          </div>
        </div>
      </article>

      {showForm && (
        <article className="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
          <h3 className="mb-3 text-lg font-bold text-emerald-800">{isEdit ? 'Edit Data Siswa' : 'Tambah Data Siswa'}</h3>
          <form onSubmit={submitForm} className="grid gap-2 md:grid-cols-2">
            <input value={form.nis} onChange={(e) => setForm((p) => ({ ...p, nis: e.target.value }))} required placeholder="NIS" className="rounded-md border border-emerald-300 px-2 py-2 text-sm" />
            <input value={form.full_name} onChange={(e) => setForm((p) => ({ ...p, full_name: e.target.value }))} required placeholder="Nama Lengkap" className="rounded-md border border-emerald-300 px-2 py-2 text-sm" />
            <input value={form.metadata_nisn} onChange={(e) => setForm((p) => ({ ...p, metadata_nisn: e.target.value }))} placeholder="NISN" className="rounded-md border border-emerald-300 px-2 py-2 text-sm" />
            <select value={form.class_id} onChange={(e) => setForm((p) => ({ ...p, class_id: e.target.value }))} className="rounded-md border border-emerald-300 px-2 py-2 text-sm">
              <option value="">Pilih Kelas/Rombel</option>
              {kelasOptions.map((k) => <option key={k.id} value={k.id}>{k.label}</option>)}
            </select>
            <select value={form.gender} onChange={(e) => setForm((p) => ({ ...p, gender: e.target.value }))} className="rounded-md border border-emerald-300 px-2 py-2 text-sm">
              <option value="male">Laki-laki</option>
              <option value="female">Perempuan</option>
            </select>
            <input value={form.metadata_unit_pendidikan} onChange={(e) => setForm((p) => ({ ...p, metadata_unit_pendidikan: e.target.value }))} placeholder="Unit Pendidikan" className="rounded-md border border-emerald-300 px-2 py-2 text-sm" />
            <input value={form.birth_place} onChange={(e) => setForm((p) => ({ ...p, birth_place: e.target.value }))} placeholder="Tempat Lahir" className="rounded-md border border-emerald-300 px-2 py-2 text-sm" />
            <input type="date" value={form.birth_date} onChange={(e) => setForm((p) => ({ ...p, birth_date: e.target.value }))} className="rounded-md border border-emerald-300 px-2 py-2 text-sm" />
            <input value={form.address} onChange={(e) => setForm((p) => ({ ...p, address: e.target.value }))} placeholder="Alamat" className="rounded-md border border-emerald-300 px-2 py-2 text-sm md:col-span-2" />
            <label className="inline-flex items-center gap-2 text-sm text-slate-700">
              <input type="checkbox" checked={form.is_active} onChange={(e) => setForm((p) => ({ ...p, is_active: e.target.checked }))} />
              Status Aktif
            </label>
            <div className="md:col-span-2 flex gap-2">
              <button type="submit" className="rounded-md bg-emerald-700 px-4 py-2 text-sm font-bold text-white">{isEdit ? 'Simpan Perubahan' : 'Simpan Data'}</button>
              <button type="button" onClick={() => setShowForm(false)} className="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-bold text-slate-700">Batal</button>
            </div>
          </form>
        </article>
      )}

      {dataLengkap && (
        <article className="rounded-xl border border-slate-200 bg-white p-3">
          <h3 className="mb-2 text-lg font-bold text-slate-900">Detail Siswa Terpilih</h3>
          <div className="grid gap-2 text-sm md:grid-cols-2">
            <p><span className="font-semibold">NIS:</span> {dataLengkap.nis || '-'}</p>
            <p><span className="font-semibold">Nama:</span> {dataLengkap.nama || '-'}</p>
            <p><span className="font-semibold">Jenis Kelamin:</span> {dataLengkap.jenis_kelamin || '-'}</p>
            <p><span className="font-semibold">Tempat/Tgl Lahir:</span> {dataLengkap.tempat_lahir || '-'}, {dataLengkap.tanggal_lahir || '-'}</p>
            <p><span className="font-semibold">Kelas:</span> {dataLengkap.kelas || '-'}</p>
            <p><span className="font-semibold">Status:</span> {dataLengkap.status || '-'}</p>
            <p className="md:col-span-2"><span className="font-semibold">Alamat:</span> {dataLengkap.alamat || '-'}</p>
          </div>
        </article>
      )}

      <article className="overflow-hidden rounded-xl border border-slate-200">
        <div className="overflow-x-auto">
          <table className="min-w-[1200px] divide-y divide-slate-200 text-sm">
            <thead className="bg-slate-50 text-slate-600">
              <tr>
                <th className="px-3 py-3 text-left">No</th>
                <th className="px-3 py-3 text-left">Foto</th>
                <th className="px-3 py-3 text-left">NIS</th>
                <th className="px-3 py-3 text-left">NISN</th>
                <th className="px-3 py-3 text-left">Nama Siswa</th>
                <th className="px-3 py-3 text-left">Kelas</th>
                <th className="px-3 py-3 text-left">Jenis Kelamin</th>
                <th className="px-3 py-3 text-left">Orang Tua</th>
                <th className="px-3 py-3 text-left">No HP</th>
                <th className="px-3 py-3 text-left">Status</th>
                <th className="px-3 py-3 text-left">Aksi</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 bg-white">
              {rows.map((row) => (
                <tr key={row.id}>
                  <td className="px-3 py-2">{row.no}</td>
                  <td className="px-3 py-2"><img src={row.foto} alt={row.nama} className="h-10 w-10 rounded-md object-cover" /></td>
                  <td className="px-3 py-2">{row.nis}</td>
                  <td className="px-3 py-2">{row.nisn}</td>
                  <td className="px-3 py-2 font-semibold text-slate-900">{row.nama}</td>
                  <td className="px-3 py-2">{row.kelas}</td>
                  <td className="px-3 py-2">
                    <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-semibold ${genderBadge(row.gender)}`}>{row.gender}</span>
                  </td>
                  <td className="px-3 py-2">{row.orangTua}</td>
                  <td className="px-3 py-2">{row.noHp}</td>
                  <td className="px-3 py-2">
                    <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-semibold ${statusBadge(row.status)}`}>{row.status}</span>
                  </td>
                  <td className="px-3 py-2">
                    <div className="flex gap-1">
                      <button onClick={() => lihatDetail(row)} className="rounded bg-blue-600 px-2 py-1 text-xs text-white"><FaEye /></button>
                      <button onClick={() => bukaEdit(row)} className="rounded bg-amber-500 px-2 py-1 text-xs text-white"><FaEdit /></button>
                      <button onClick={() => hapusData(row)} className="rounded bg-red-600 px-2 py-1 text-xs text-white"><FaTrash /></button>
                    </div>
                  </td>
                </tr>
              ))}
              {rows.length === 0 && (
                <tr>
                  <td colSpan={11} className="px-3 py-6 text-center text-sm text-slate-500">Tidak ada data siswa untuk filter yang dipilih.</td>
                </tr>
              )}
            </tbody>
          </table>
        </div>

        <footer className="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 bg-white px-3 py-3 text-sm">
          <p className="text-slate-600">
            Menampilkan {(halamanAktif - 1) * perHalaman + (rows.length ? 1 : 0)} - {(halamanAktif - 1) * perHalaman + rows.length} dari {totalData} siswa
          </p>
          <div className="flex items-center gap-2">
            <button disabled={halamanAktif <= 1} onClick={() => setHalaman((p) => Math.max(1, p - 1))} className="rounded border border-slate-300 px-3 py-1.5 text-xs font-bold text-slate-700 disabled:opacity-50">Sebelumnya</button>
            <span className="rounded bg-emerald-700 px-3 py-1.5 text-xs font-bold text-white">{halamanAktif}</span>
            <button disabled={halamanAktif >= totalHalaman} onClick={() => setHalaman((p) => Math.min(totalHalaman, p + 1))} className="rounded border border-slate-300 px-3 py-1.5 text-xs font-bold text-slate-700 disabled:opacity-50">Selanjutnya</button>
          </div>
        </footer>
      </article>

      <div className="hidden">
        <FaFilePdf />
      </div>
    </section>
  )
}
