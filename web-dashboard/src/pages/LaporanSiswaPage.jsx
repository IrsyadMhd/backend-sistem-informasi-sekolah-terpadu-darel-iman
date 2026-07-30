import { useEffect, useMemo, useState } from 'react'
import {
  Building2, ChevronLeft, ChevronRight, Download, FileSpreadsheet, FileText,
  GraduationCap, MoreVertical, Printer, RefreshCw, Search, UserCheck,
  UserMinus, Users, UserX,
} from 'lucide-react'
import {
  Bar, BarChart, CartesianGrid, Cell, Legend, Line, LineChart, Pie, PieChart,
  ResponsiveContainer, Tooltip, XAxis, YAxis,
} from 'recharts'
import { exportCsv } from '../components/reports/ReportKit'
import { studentService } from '../services/studentService'

const angka = (nilai) => new Intl.NumberFormat('id-ID').format(Number(nilai || 0))
const warnaPie = ['#1677ff', '#00af76', '#ff9418', '#9147ed', '#d35da5', '#ff3d71']

function Persen({ nilai, total }) {
  const persen = total ? (Number(nilai || 0) / total) * 100 : 0
  return <span>{persen.toLocaleString('id-ID', { maximumFractionDigits: 1 })}% dari total data</span>
}

function KartuStatistik({ icon: Icon, label, nilai, total, tone }) {
  return (
    <article className={`ls-stat ls-stat--${tone}`}>
      <span className="ls-stat-icon"><Icon size={24} /></span>
      <div><small>{label}</small><strong>{angka(nilai)}</strong><Persen nilai={nilai} total={total} /></div>
    </article>
  )
}

function Kartu({ title, children, className = '' }) {
  return <section className={`ls-card ${className}`}><h2>{title}</h2>{children}</section>
}

export default function LaporanSiswaPage() {
  const [memuat, setMemuat] = useState(true)
  const [gagal, setGagal] = useState('')
  const [dashboard, setDashboard] = useState(null)
  const [pencarian, setPencarian] = useState('')
  const [status, setStatus] = useState('semua')
  const [unit, setUnit] = useState('semua')
  const [kelas, setKelas] = useState('semua')
  const [halaman, setHalaman] = useState(1)
  const [perHalaman, setPerHalaman] = useState(10)

  const muatData = async () => {
    try {
      setMemuat(true)
      setGagal('')
      setDashboard(await studentService.getDashboard())
    } catch (error) {
      setGagal(error?.response?.data?.message || 'Gagal memuat laporan data siswa.')
    } finally {
      setMemuat(false)
    }
  }

  useEffect(() => { muatData() }, [])

  const siswa = useMemo(() => dashboard?.daftar_siswa || [], [dashboard])
  const statistik = dashboard?.statistik || {}
  const laporan = dashboard?.laporan_siswa || {}
  const total = Number(statistik.total_siswa || 0)
  const aktif = Number(statistik.siswa_aktif ?? siswa.filter((item) => item.aktif).length)
  const nonaktif = Number(statistik.siswa_nonaktif ?? Math.max(total - aktif, 0))
  const alumni = Number(statistik.alumni || 0)
  const mutasi = Number(statistik.mutasi_keluar || 0)

  const daftarKelas = useMemo(() => [...new Set(siswa.map((item) => item.kelas).filter(Boolean))], [siswa])
  const daftarUnit = useMemo(() => [...new Set(siswa.map((item) => item.unit).filter((item) => item && item !== '-'))], [siswa])
  const hasilFilter = useMemo(() => siswa.filter((item) => {
    const cocokCari = `${item.nis} ${item.nama} ${item.kelas} ${item.unit || ''}`.toLowerCase().includes(pencarian.toLowerCase())
    const cocokStatus = status === 'semua' || (status === 'aktif' ? item.aktif : !item.aktif)
    return cocokCari && cocokStatus
      && (unit === 'semua' || item.unit === unit)
      && (kelas === 'semua' || item.kelas === kelas)
  }), [siswa, pencarian, status, unit, kelas])

  const totalHalaman = Math.max(Math.ceil(hasilFilter.length / perHalaman), 1)
  const baris = hasilFilter.slice((halaman - 1) * perHalaman, halaman * perHalaman)
  useEffect(() => { setHalaman(1) }, [pencarian, status, unit, kelas, perHalaman])

  const dataKelas = useMemo(() => (dashboard?.kelas_rombel || []).map((item) => ({
    nama: item.level || item.nama,
    jumlah: Number(item.jumlah_siswa || 0),
  })).reduce((acc, item) => {
    const ada = acc.find((x) => x.nama === item.nama)
    if (ada) ada.jumlah += item.jumlah
    else acc.push(item)
    return acc
  }, []), [dashboard])

  const dataUnit = useMemo(() => {
    const map = new Map()
    siswa.forEach((item) => {
      const nama = item.unit || item.kelas || 'Belum ditentukan'
      map.set(nama, (map.get(nama) || 0) + 1)
    })
    return [...map].map(([nama, jumlah]) => ({ nama, jumlah }))
  }, [siswa])

  const gender = dashboard?.komposisi_gender || {
    laki_laki: siswa.filter((item) => ['L', 'Laki-laki'].includes(item.jenis_kelamin)).length,
    perempuan: siswa.filter((item) => ['P', 'Perempuan'].includes(item.jenis_kelamin)).length,
  }
  const totalGender = Number(gender.laki_laki || 0) + Number(gender.perempuan || 0)
  const tren = laporan.grafik_tahunan || []
  const kolomCsv = [
    { key: 'nis', label: 'NIS' }, { key: 'nama', label: 'Nama Siswa' },
    { key: 'unit', label: 'Unit Pendidikan' }, { key: 'kelas', label: 'Kelas/Rombel' },
    { key: 'jenis_kelamin', label: 'Jenis Kelamin' },
    { key: 'aktif', label: 'Status', export: (row) => row.aktif ? 'Aktif' : 'Nonaktif' },
  ]

  if (memuat) return <div className="ls-page"><div className="ls-state"><RefreshCw className="ls-spin" /> Memuat laporan siswa...</div></div>
  if (gagal) return <div className="ls-page"><div className="ls-state ls-state--error"><p>{gagal}</p><button onClick={muatData}>Coba lagi</button></div></div>

  return (
    <div className="ls-page">
      <header className="ls-header">
        <div><h1>Laporan Siswa</h1><p>Dashboard <b>›</b> Laporan <b>›</b> Laporan Siswa</p></div>
        <div className="ls-header-actions">
          <button onClick={() => exportCsv('laporan-siswa.csv', kolomCsv, hasilFilter)}><FileSpreadsheet size={17} /> Export Excel</button>
          <button onClick={() => window.print()}><FileText size={17} /> Export PDF</button>
          <button className="ls-primary" onClick={() => window.print()}><Printer size={17} /> Cetak Laporan</button>
        </div>
      </header>

      <div className="ls-stats">
        <KartuStatistik icon={Users} label="Total Siswa" nilai={total} total={total} tone="green" />
        <KartuStatistik icon={GraduationCap} label="Siswa Aktif" nilai={aktif} total={total} tone="blue" />
        <KartuStatistik icon={UserCheck} label="Siswa Alumni" nilai={alumni} total={total} tone="orange" />
        <KartuStatistik icon={UserMinus} label="Siswa Mutasi Keluar" nilai={mutasi} total={total} tone="purple" />
        <KartuStatistik icon={UserX} label="Siswa Non Aktif" nilai={nonaktif} total={total} tone="pink" />
      </div>

      <div className="ls-layout">
        <main className="ls-main">
          <div className="ls-chart-row">
            <Kartu title="Siswa per Unit Pendidikan">
              <div className="ls-donut-wrap">
                <div className="ls-chart-box">
                  <ResponsiveContainer width="100%" height="100%">
                    <PieChart><Pie data={dataUnit} dataKey="jumlah" nameKey="nama" innerRadius="62%" outerRadius="88%" paddingAngle={1}>
                      {dataUnit.map((_, i) => <Cell key={i} fill={warnaPie[i % warnaPie.length]} />)}
                    </Pie><Tooltip formatter={(v) => angka(v)} /></PieChart>
                  </ResponsiveContainer>
                  <div className="ls-donut-center"><strong>{angka(total)}</strong><span>Total Siswa</span></div>
                </div>
                <div className="ls-legend">
                  {dataUnit.slice(0, 6).map((item, i) => <div key={item.nama}><i style={{ background: warnaPie[i % warnaPie.length] }} /><span>{item.nama}</span><b>{angka(item.jumlah)}</b></div>)}
                </div>
              </div>
            </Kartu>
            <Kartu title="Siswa per Jenjang">
              <div className="ls-bar-chart"><ResponsiveContainer width="100%" height="100%">
                <BarChart data={dataKelas} margin={{ top: 18, right: 5, left: -20, bottom: 5 }}>
                  <CartesianGrid strokeDasharray="3 3" vertical={false} /><XAxis dataKey="nama" tick={{ fontSize: 11 }} />
                  <YAxis tick={{ fontSize: 11 }} /><Tooltip formatter={(v) => angka(v)} />
                  <Bar dataKey="jumlah" fill="#087a4f" radius={[6, 6, 0, 0]} maxBarSize={42} />
                </BarChart>
              </ResponsiveContainer></div>
            </Kartu>
          </div>

          <div className="ls-insight-row">
            <Kartu title="Siswa per Jenis Kelamin">
              <div className="ls-gender">
                <div><span className="ls-gender-icon ls-male"><Users size={20} /></span><small>Laki-laki</small><strong>{angka(gender.laki_laki)}</strong><b>{totalGender ? Math.round(gender.laki_laki / totalGender * 100) : 0}%</b></div>
                <div><span className="ls-gender-icon ls-female"><Users size={20} /></span><small>Perempuan</small><strong>{angka(gender.perempuan)}</strong><b>{totalGender ? Math.round(gender.perempuan / totalGender * 100) : 0}%</b></div>
              </div>
              <div className="ls-gender-bar"><i style={{ width: `${totalGender ? gender.laki_laki / totalGender * 100 : 50}%` }} /></div>
            </Kartu>
            <Kartu title="Siswa per Status">
              <div className="ls-status-chart"><ResponsiveContainer width="52%" height="100%">
                <PieChart><Pie data={[{ nama: 'Aktif', jumlah: aktif }, { nama: 'Alumni', jumlah: alumni }, { nama: 'Non Aktif', jumlah: nonaktif }]} dataKey="jumlah" innerRadius="58%" outerRadius="88%">
                  <Cell fill="#07804f" /><Cell fill="#ff9c20" /><Cell fill="#b8c1d3" />
                </Pie><Tooltip /></PieChart>
              </ResponsiveContainer><div><p><i className="hijau" /> Aktif <b>{angka(aktif)}</b></p><p><i className="oranye" /> Alumni <b>{angka(alumni)}</b></p><p><i className="abu" /> Non Aktif <b>{angka(nonaktif)}</b></p></div></div>
            </Kartu>
            <Kartu title="Tren Jumlah Siswa">
              <div className="ls-line-chart"><ResponsiveContainer width="100%" height="100%">
                <LineChart data={tren} margin={{ top: 10, right: 14, left: -24, bottom: 0 }}>
                  <CartesianGrid strokeDasharray="3 3" /><XAxis dataKey="tahun" tick={{ fontSize: 10 }} /><YAxis tick={{ fontSize: 10 }} />
                  <Tooltip formatter={(v) => angka(v)} /><Legend /><Line name="Jumlah Siswa" dataKey="jumlah" stroke="#07804f" strokeWidth={2.5} dot={{ r: 4 }} />
                </LineChart>
              </ResponsiveContainer></div>
            </Kartu>
          </div>

          <Kartu title="Rincian Data Siswa" className="ls-table-card">
            <div className="ls-table-tools"><div className="ls-search"><Search size={16} /><input value={pencarian} onChange={(e) => setPencarian(e.target.value)} placeholder="Cari NIS, nama, kelas..." /></div></div>
            <div className="ls-table-scroll"><table><thead><tr><th>No</th><th>NIS</th><th>Nama Siswa</th><th>Unit Pendidikan</th><th>Kelas / Rombel</th><th>JK</th><th>Status</th><th>Aksi</th></tr></thead>
              <tbody>{baris.length ? baris.map((item, i) => <tr key={item.id}><td>{(halaman - 1) * perHalaman + i + 1}</td><td>{item.nis || '-'}</td><td><b>{item.nama}</b></td><td>{item.unit || '-'}</td><td>{item.kelas || '-'}</td><td>{item.jenis_kelamin || '-'}</td><td><span className={`ls-badge ${item.aktif ? 'aktif' : 'nonaktif'}`}>{item.aktif ? 'Aktif' : 'Non Aktif'}</span></td><td><button className="ls-icon-button" title="Menu siswa"><MoreVertical size={16} /></button></td></tr>) : <tr><td colSpan="8" className="ls-empty">Tidak ada data sesuai filter.</td></tr>}</tbody>
            </table></div>
            <footer className="ls-table-footer"><span>Menampilkan {baris.length ? (halaman - 1) * perHalaman + 1 : 0} sampai {Math.min(halaman * perHalaman, hasilFilter.length)} dari {angka(hasilFilter.length)} data</span>
              <div><button disabled={halaman === 1} onClick={() => setHalaman((v) => v - 1)}><ChevronLeft size={16} /></button><b>{halaman}</b><button disabled={halaman === totalHalaman} onClick={() => setHalaman((v) => v + 1)}><ChevronRight size={16} /></button><select value={perHalaman} onChange={(e) => setPerHalaman(Number(e.target.value))}><option value="10">10 / halaman</option><option value="25">25 / halaman</option><option value="50">50 / halaman</option></select></div>
            </footer>
          </Kartu>
        </main>

        <aside className="ls-aside">
          <Kartu title="Filter Laporan">
            {dashboard?.akses?.semua_unit && <label>Unit Pendidikan<select value={unit} onChange={(e) => setUnit(e.target.value)}><option value="semua">Semua Unit Pendidikan</option>{daftarUnit.map((item) => <option key={item}>{item}</option>)}</select></label>}
            {!dashboard?.akses?.semua_unit && dashboard?.akses?.unit_nama && <div className="ls-access-unit"><Building2 size={15} /><span>Akses Unit</span><b>{dashboard.akses.unit_nama}</b></div>}
            <label>Kelas / Rombel<select value={kelas} onChange={(e) => setKelas(e.target.value)}><option value="semua">Semua Kelas</option>{daftarKelas.map((item) => <option key={item}>{item}</option>)}</select></label>
            <label>Status Siswa<select value={status} onChange={(e) => setStatus(e.target.value)}><option value="semua">Semua Status</option><option value="aktif">Aktif</option><option value="nonaktif">Non Aktif</option></select></label>
            <label>Pencarian<input value={pencarian} onChange={(e) => setPencarian(e.target.value)} placeholder="Nama atau NIS" /></label>
            <div className="ls-filter-actions"><button onClick={() => { setUnit('semua'); setKelas('semua'); setStatus('semua'); setPencarian('') }}>Reset</button><button className="ls-primary">Terapkan</button></div>
          </Kartu>
          <Kartu title="Aksi Cepat" className="ls-quick">
            <button onClick={() => exportCsv('laporan-siswa.csv', kolomCsv, hasilFilter)}><FileSpreadsheet /><span><b>Export Excel</b><small>Unduh laporan format CSV</small></span><Download size={15} /></button>
            <button onClick={() => window.print()}><FileText /><span><b>Export PDF</b><small>Simpan melalui dialog cetak</small></span></button>
            <button onClick={() => window.print()}><Printer /><span><b>Cetak Laporan</b><small>Cetak laporan data siswa</small></span></button>
            <button onClick={muatData}><RefreshCw /><span><b>Muat Ulang Data</b><small>Sinkronkan data terbaru</small></span></button>
          </Kartu>
          <Kartu title="Ringkasan Kelas" className="ls-class-summary">
            <p><Building2 /> Total kelas / rombel <b>{angka(statistik.total_kelas)}</b></p>
            <p><UserCheck /> Siswa baru tahun ini <b>{angka(statistik.siswa_baru)}</b></p>
          </Kartu>
        </aside>
      </div>
    </div>
  )
}
