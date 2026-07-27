import React, { useState, useMemo } from 'react'
import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  Legend,
  ResponsiveContainer,
  PieChart,
  Pie,
  Cell,
  AreaChart,
  Area,
  RadarChart,
  PolarGrid,
  PolarAngleAxis,
  PolarRadiusAxis,
  Radar,
} from 'recharts'
import {
  LuBuilding2,
  LuGraduationCap,
  LuUserCheck,
  LuDollarSign,
  LuAward,
  LuBookOpen,
  LuCalendar,
  LuClock,
  LuCircleCheck,
  LuCircleAlert,
  LuFileText,
  LuUsers,
  LuShieldCheck,
  LuSparkles,
  LuHeartHandshake,
  LuArrowUpRight,
  LuQrCode,
  LuPlus,
  LuTrendingUp,
} from 'react-icons/lu'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '../components/ui/card'
import { Button } from '../components/ui/button'
import { Badge } from '../components/ui/badge'

// Color Palette Enterprise Islam Terpadu
const COLORS = ['#10b981', '#3b82f6', '#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4']

export default function MultiRoleDashboardPage() {
  const [activeRole, setActiveRole] = useState('Yayasan') // Options: Yayasan, Kepsek, Guru, OrangTua, Siswa
  const [selectedUnit, setSelectedUnit] = useState('SEMUA')

  // Roles list configuration
  const roles = [
    { id: 'Yayasan', label: 'Yayasan (Executive)', icon: LuBuilding2, badge: 'Eksekutif' },
    { id: 'Kepsek', label: 'Kepala Sekolah', icon: LuGraduationCap, badge: 'Manajerial' },
    { id: 'Guru', label: 'Guru & Wali Kelas', icon: LuUsers, badge: 'Pengajar' },
    { id: 'OrangTua', label: 'Orang Tua / Wali', icon: LuHeartHandshake, badge: 'Wali Murid' },
    { id: 'Siswa', label: 'Siswa / Santri', icon: LuSparkles, badge: 'Peserta Didik' },
  ]

  // Mock Data per Role
  const yayasanData = {
    totalRevenue: 'Rp 2.450.000.000',
    totalSiswa: 1420,
    totalPegawai: 118,
    auditScore: '98.5%',
    unitComparison: [
      { unit: 'TKIT', siswa: 180, anggaran: 350, capaian: 95 },
      { unit: 'SDIT', siswa: 580, anggaran: 920, capaian: 98 },
      { unit: 'SMPIT', siswa: 410, anggaran: 750, capaian: 94 },
      { unit: 'SMAIT', siswa: 250, anggaran: 430, capaian: 96 },
    ],
    pemasukanVsPengeluaran: [
      { bulan: 'Jan', Pemasukan: 380, Pengeluaran: 290 },
      { bulan: 'Feb', Pemasukan: 410, Pengeluaran: 310 },
      { bulan: 'Mar', Pemasukan: 390, Pengeluaran: 300 },
      { bulan: 'Apr', Pemasukan: 450, Pengeluaran: 320 },
      { bulan: 'Mei', Pemasukan: 420, Pengeluaran: 310 },
      { bulan: 'Jun', Pemasukan: 480, Pengeluaran: 340 },
    ],
  }

  const kepsekData = {
    presensiHarianSiswa: '97.2%',
    presensiGuru: '99.1%',
    targetKurikulum: '88.5%',
    persetujuanPending: 4,
    trendPresensi: [
      { hari: 'Senin', Hadir: 98, Izin: 1.5, Sakit: 0.5 },
      { hari: 'Selasa', Hadir: 97, Izin: 2.0, Sakit: 1.0 },
      { hari: 'Rabu', Hadir: 96.5, Izin: 2.5, Sakit: 1.0 },
      { hari: 'Kamis', Hadir: 98.2, Izin: 1.0, Sakit: 0.8 },
      { hari: 'Jumat', Hadir: 95.8, Izin: 3.0, Sakit: 1.2 },
    ],
    ikuDivisi: [
      { iku: 'Disiplin', nilai: 95 },
      { iku: 'Tahfizh', nilai: 90 },
      { iku: 'Akademik', nilai: 88 },
      { iku: 'Karakter', nilai: 96 },
      { iku: 'Ekskul', nilai: 85 },
    ],
  }

  const guruData = {
    kelasAjar: '4 Kelas (118 Siswa)',
    presensiHariIni: '28/30 Hadir',
    inputNilai: '92% Selesai',
    setoranTahfizh: '16 Siswa Hari Ini',
    jadwalMengajar: [
      { jam: '07:30 - 09:00', mapel: 'Pendidikan Agama Islam', kelas: '5 SDIT Al-Kindi', ruang: 'Ruang 5A' },
      { jam: '09:30 - 11:00', mapel: 'Tahfizh Al-Qur\'an (Juz 30)', kelas: '5 SDIT Ibnu Sina', ruang: 'Masjid Utama' },
      { jam: '13:00 - 14:30', mapel: 'Bina Pribadi Islam (BPI)', kelas: '6 SDIT Al-Farabi', ruang: 'Ruang 6B' },
    ],
  }

  const orangTuaData = {
    namaAnak: 'Ahmad Zaki Al-Faruq',
    kelas: 'Kelas 5A SDIT',
    presensi: '98.5% (Hadir)',
    tahfizhProgress: 'Juz 30 (Mumtaz - 100%)',
    sppStatus: 'Lunas (Juli 2026)',
    mutabaahIbadah: [
      { ibadah: 'Sholat 5 Waktu', persen: 100 },
      { ibadah: 'Tilawah Qur\'an', persen: 90 },
      { ibadah: 'Dzikir Pagi/Petang', persen: 85 },
      { ibadah: 'Puasa Sunnah', persen: 75 },
      { ibadah: 'Sholat Dhuha', persen: 95 },
    ],
  }

  const siswaData = {
    namaSiswa: 'Ahmad Zaki Al-Faruq',
    nisn: '0051234567',
    rataNilai: '93.5',
    capaianTahfizh: 'Juz 30 Selesai',
    poinKebaikan: '+180 Poin',
    peringkat: 'Peringkat 2 Paralel',
    tugasMendatang: [
      { mapel: 'Tahfizh Al-Qur\'an', tugas: 'Munaqosyah Surah An-Naba ayat 1-40', tenggat: 'Besok, 08:00' },
      { mapel: 'Bahasa Arab', tugas: 'Latihan Mufradat Bab 4', tenggat: '29 Juli 2026' },
      { mapel: 'IPA Terpadu', tugas: 'Laporan Praktikum Ekosistem', tenggat: '30 Juli 2026' },
    ],
  }

  return (
    <div className="p-6 max-w-7xl mx-auto space-y-6 text-slate-100">
      {/* Header Dashboard & Role Switcher */}
      <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between bg-slate-900/90 p-6 rounded-2xl border border-slate-800 shadow-xl backdrop-blur-md">
        <div>
          <div className="flex items-center space-x-2">
            <span className="bg-emerald-500/10 text-emerald-400 p-2 rounded-lg border border-emerald-500/20">
              <LuBuilding2 className="h-6 w-6" />
            </span>
            <h1 className="text-2xl font-bold tracking-tight text-white">
              Dashboard Multi-Role SIMS Islam Terpadu
            </h1>
          </div>
          <p className="text-sm text-slate-400 mt-1">
            Simulasi antarmuka terpadu sesuai peran pengakses dalam ekosistem sekolah.
          </p>
        </div>

        {/* Unit Selector */}
        <div className="flex items-center space-x-2">
          <label className="text-xs font-medium text-slate-400">Unit Sekolah:</label>
          <select
            value={selectedUnit}
            onChange={(e) => setSelectedUnit(e.target.value)}
            className="h-9 rounded-lg border border-slate-700 bg-slate-950 px-3 text-xs text-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
          >
            <option value="SEMUA">Semua Unit (TK/SD/SMP/SMA)</option>
            <option value="TKIT">TKIT Islam Terpadu</option>
            <option value="SDIT">SDIT Islam Terpadu</option>
            <option value="SMPIT">SMPIT Islam Terpadu</option>
            <option value="SMAIT">SMAIT Islam Terpadu</option>
          </select>
        </div>
      </div>

      {/* Dynamic Role Switcher Navigation Bar */}
      <div className="flex items-center space-x-2 overflow-x-auto pb-2 scrollbar-none">
        {roles.map((role) => {
          const Icon = role.icon
          const isActive = activeRole === role.id
          return (
            <button
              key={role.id}
              onClick={() => setActiveRole(role.id)}
              className={`flex items-center space-x-2.5 px-4 py-3 rounded-xl border font-semibold text-sm transition-all cursor-pointer whitespace-nowrap ${
                isActive
                  ? 'bg-emerald-600 border-emerald-500 text-white shadow-lg shadow-emerald-950/50 scale-[1.02]'
                  : 'bg-slate-900/80 border-slate-800 text-slate-400 hover:bg-slate-800 hover:text-white'
              }`}
            >
              <Icon className={`h-4 w-4 ${isActive ? 'text-white' : 'text-emerald-400'}`} />
              <span>{role.label}</span>
              <span
                className={`text-[10px] px-2 py-0.5 rounded-full ${
                  isActive ? 'bg-emerald-700 text-white' : 'bg-slate-800 text-slate-400'
                }`}
              >
                {role.badge}
              </span>
            </button>
          )
        })}
      </div>

      {/* DYNAMIC DASHBOARD VIEWS BASED ON ACTIVE ROLE */}

      {/* ========================================================= */}
      {/* 1. VIEW YAYASAN (EXECUTIVE BOARD) */}
      {/* ========================================================= */}
      {activeRole === 'Yayasan' && (
        <div className="space-y-6 animate-in fade-in duration-300">
          {/* Executive Stat Cards */}
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <Card className="border-emerald-800/40 bg-emerald-950/20">
              <CardContent className="p-5 flex items-center justify-between">
                <div>
                  <p className="text-xs text-slate-400 font-medium">Pemasukan Infaq & SPP</p>
                  <h3 className="text-2xl font-bold text-emerald-400 mt-1">{yayasanData.totalRevenue}</h3>
                  <span className="text-[11px] text-emerald-300/80 flex items-center mt-1">
                    <LuTrendingUp className="h-3 w-3 mr-1" /> +12.4% dari bulan lalu
                  </span>
                </div>
                <div className="p-3 bg-emerald-500/20 text-emerald-400 rounded-xl border border-emerald-500/30">
                  <LuDollarSign className="h-6 w-6" />
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardContent className="p-5 flex items-center justify-between">
                <div>
                  <p className="text-xs text-slate-400 font-medium">Total Peserta Didik</p>
                  <h3 className="text-2xl font-bold text-white mt-1">{yayasanData.totalSiswa} Siswa</h3>
                  <span className="text-[11px] text-slate-400 mt-1 block">Tersebar di 4 Unit Pendidikan</span>
                </div>
                <div className="p-3 bg-blue-500/20 text-blue-400 rounded-xl border border-blue-500/30">
                  <LuUsers className="h-6 w-6" />
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardContent className="p-5 flex items-center justify-between">
                <div>
                  <p className="text-xs text-slate-400 font-medium">Total Guru & SDM</p>
                  <h3 className="text-2xl font-bold text-white mt-1">{yayasanData.totalPegawai} Orang</h3>
                  <span className="text-[11px] text-emerald-400 mt-1 block">Rasio 1:12 (Ideal)</span>
                </div>
                <div className="p-3 bg-purple-500/20 text-purple-400 rounded-xl border border-purple-500/30">
                  <LuGraduationCap className="h-6 w-6" />
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardContent className="p-5 flex items-center justify-between">
                <div>
                  <p className="text-xs text-slate-400 font-medium">Skor Kepatuhan Audit</p>
                  <h3 className="text-2xl font-bold text-emerald-400 mt-1">{yayasanData.auditScore}</h3>
                  <span className="text-[11px] text-slate-400 mt-1 block">Predikat SANGAT BAIK</span>
                </div>
                <div className="p-3 bg-amber-500/20 text-amber-400 rounded-xl border border-amber-500/30">
                  <LuShieldCheck className="h-6 w-6" />
                </div>
              </CardContent>
            </Card>
          </div>

          {/* Charts Row */}
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <Card>
              <CardHeader>
                <CardTitle className="text-base flex items-center justify-between">
                  <span>Pemasukan vs Pengeluaran Yayasan (Juta Rp)</span>
                  <Badge variant="outline">T.A 2025/2026</Badge>
                </CardTitle>
              </CardHeader>
              <CardContent>
                <div className="h-72">
                  <ResponsiveContainer width="100%" height="100%">
                    <BarChart data={yayasanData.pemasukanVsPengeluaran}>
                      <CartesianGrid strokeDasharray="3 3" stroke="#334155" />
                      <XAxis dataKey="bulan" stroke="#94a3b8" />
                      <YAxis stroke="#94a3b8" />
                      <Tooltip contentStyle={{ backgroundColor: '#0f172a', borderColor: '#334155' }} />
                      <Legend />
                      <Bar dataKey="Pemasukan" fill="#10b981" radius={[4, 4, 0, 0]} />
                      <Bar dataKey="Pengeluaran" fill="#ef4444" radius={[4, 4, 0, 0]} />
                    </BarChart>
                  </ResponsiveContainer>
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle className="text-base">Distribusi Siswa & Anggaran per Unit</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="h-72">
                  <ResponsiveContainer width="100%" height="100%">
                    <BarChart data={yayasanData.unitComparison} layout="vertical">
                      <CartesianGrid strokeDasharray="3 3" stroke="#334155" />
                      <XAxis type="number" stroke="#94a3b8" />
                      <YAxis dataKey="unit" type="category" stroke="#94a3b8" />
                      <Tooltip contentStyle={{ backgroundColor: '#0f172a', borderColor: '#334155' }} />
                      <Legend />
                      <Bar dataKey="siswa" name="Jumlah Siswa" fill="#3b82f6" radius={[0, 4, 4, 0]} />
                      <Bar dataKey="anggaran" name="Alokasi (Juta Rp)" fill="#8b5cf6" radius={[0, 4, 4, 0]} />
                    </BarChart>
                  </ResponsiveContainer>
                </div>
              </CardContent>
            </Card>
          </div>
        </div>
      )}

      {/* ========================================================= */}
      {/* 2. VIEW KEPALA SEKOLAH (PRINCIPAL) */}
      {/* ========================================================= */}
      {activeRole === 'Kepsek' && (
        <div className="space-y-6 animate-in fade-in duration-300">
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <Card>
              <CardContent className="p-5 flex items-center justify-between">
                <div>
                  <p className="text-xs text-slate-400 font-medium">Presensi Siswa Hari Ini</p>
                  <h3 className="text-2xl font-bold text-emerald-400 mt-1">{kepsekData.presensiHarianSiswa}</h3>
                  <span className="text-[11px] text-slate-400 mt-1 block">564/580 Siswa Hadir</span>
                </div>
                <div className="p-3 bg-emerald-500/20 text-emerald-400 rounded-xl">
                  <LuUserCheck className="h-6 w-6" />
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardContent className="p-5 flex items-center justify-between">
                <div>
                  <p className="text-xs text-slate-400 font-medium">Presensi Guru & Staf</p>
                  <h3 className="text-2xl font-bold text-blue-400 mt-1">{kepsekData.presensiGuru}</h3>
                  <span className="text-[11px] text-slate-400 mt-1 block">41/42 Guru Hadir</span>
                </div>
                <div className="p-3 bg-blue-500/20 text-blue-400 rounded-xl">
                  <LuUsers className="h-6 w-6" />
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardContent className="p-5 flex items-center justify-between">
                <div>
                  <p className="text-xs text-slate-400 font-medium">Capaian Kurikulum</p>
                  <h3 className="text-2xl font-bold text-amber-400 mt-1">{kepsekData.targetKurikulum}</h3>
                  <span className="text-[11px] text-emerald-400 mt-1 block">Sesuai Target Semester</span>
                </div>
                <div className="p-3 bg-amber-500/20 text-amber-400 rounded-xl">
                  <LuBookOpen className="h-6 w-6" />
                </div>
              </CardContent>
            </Card>

            <Card className="border-amber-800/40 bg-amber-950/20">
              <CardContent className="p-5 flex items-center justify-between">
                <div>
                  <p className="text-xs text-amber-300 font-medium">Persetujuan Pending</p>
                  <h3 className="text-2xl font-bold text-amber-400 mt-1">{kepsekData.persetujuanPending} Berkas</h3>
                  <span className="text-[11px] text-amber-300/80 mt-1 block">Cuti & Pengajuan Anggaran</span>
                </div>
                <div className="p-3 bg-amber-500/20 text-amber-400 rounded-xl">
                  <LuCircleAlert className="h-6 w-6" />
                </div>
              </CardContent>
            </Card>
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <Card>
              <CardHeader>
                <CardTitle className="text-base">Tren Kehadiran Siswa Mingguan (%)</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="h-72">
                  <ResponsiveContainer width="100%" height="100%">
                    <AreaChart data={kepsekData.trendPresensi}>
                      <CartesianGrid strokeDasharray="3 3" stroke="#334155" />
                      <XAxis dataKey="hari" stroke="#94a3b8" />
                      <YAxis stroke="#94a3b8" domain={[90, 100]} />
                      <Tooltip contentStyle={{ backgroundColor: '#0f172a', borderColor: '#334155' }} />
                      <Area type="monotone" dataKey="Hadir" stroke="#10b981" fill="#10b981" fillOpacity={0.2} />
                    </AreaChart>
                  </ResponsiveContainer>
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle className="text-base">Evaluasi Indikator Kinerja Utama (IKU)</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="h-72">
                  <ResponsiveContainer width="100%" height="100%">
                    <RadarChart cx="50%" cy="50%" outerRadius="80%" data={kepsekData.ikuDivisi}>
                      <PolarGrid stroke="#334155" />
                      <PolarAngleAxis dataKey="iku" stroke="#94a3b8" />
                      <PolarRadiusAxis angle={30} domain={[0, 100]} stroke="#64748b" />
                      <Radar name="Skor IKU" dataKey="nilai" stroke="#10b981" fill="#10b981" fillOpacity={0.4} />
                    </RadarChart>
                  </ResponsiveContainer>
                </div>
              </CardContent>
            </Card>
          </div>
        </div>
      )}

      {/* ========================================================= */}
      {/* 3. VIEW GURU & WALI KELAS */}
      {/* ========================================================= */}
      {activeRole === 'Guru' && (
        <div className="space-y-6 animate-in fade-in duration-300">
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <Card>
              <CardContent className="p-5 flex items-center justify-between">
                <div>
                  <p className="text-xs text-slate-400 font-medium">Beban Mengajar</p>
                  <h3 className="text-lg font-bold text-white mt-1">{guruData.kelasAjar}</h3>
                </div>
                <div className="p-3 bg-emerald-500/20 text-emerald-400 rounded-xl">
                  <LuBookOpen className="h-6 w-6" />
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardContent className="p-5 flex items-center justify-between">
                <div>
                  <p className="text-xs text-slate-400 font-medium">Presensi Kelas Saya</p>
                  <h3 className="text-xl font-bold text-emerald-400 mt-1">{guruData.presensiHariIni}</h3>
                </div>
                <div className="p-3 bg-blue-500/20 text-blue-400 rounded-xl">
                  <LuUserCheck className="h-6 w-6" />
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardContent className="p-5 flex items-center justify-between">
                <div>
                  <p className="text-xs text-slate-400 font-medium">Input Nilai Rapor</p>
                  <h3 className="text-xl font-bold text-amber-400 mt-1">{guruData.inputNilai}</h3>
                </div>
                <div className="p-3 bg-amber-500/20 text-amber-400 rounded-xl">
                  <LuFileText className="h-6 w-6" />
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardContent className="p-5 flex items-center justify-between">
                <div>
                  <p className="text-xs text-slate-400 font-medium">Setoran Tahfizh Siswa</p>
                  <h3 className="text-xl font-bold text-purple-400 mt-1">{guruData.setoranTahfizh}</h3>
                </div>
                <div className="p-3 bg-purple-500/20 text-purple-400 rounded-xl">
                  <LuSparkles className="h-6 w-6" />
                </div>
              </CardContent>
            </Card>
          </div>

          {/* Agenda & Jadwal Mengajar */}
          <Card>
            <CardHeader>
              <CardTitle className="text-base flex items-center justify-between">
                <span>Jadwal Mengajar & Agenda Hari Ini</span>
                <Button size="sm" className="bg-emerald-600 hover:bg-emerald-700">
                  <LuQrCode className="h-4 w-4 mr-1.5" /> Absen Kelas Cepat
                </Button>
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="space-y-3">
                {guruData.jadwalMengajar.map((item, idx) => (
                  <div key={idx} className="flex flex-col sm:flex-row sm:items-center justify-between p-4 bg-slate-950/60 rounded-xl border border-slate-800">
                    <div className="flex items-center space-x-3">
                      <div className="p-2 bg-emerald-950/80 text-emerald-400 rounded-lg border border-emerald-800/60 font-mono text-xs font-bold">
                        {item.jam}
                      </div>
                      <div>
                        <h4 className="font-semibold text-white text-sm">{item.mapel}</h4>
                        <p className="text-xs text-slate-400">{item.kelas} • {item.ruang}</p>
                      </div>
                    </div>
                    <div className="mt-3 sm:mt-0 flex items-center space-x-2">
                      <Button variant="outline" size="sm">Input Nilai</Button>
                      <Button size="sm">Setoran Tahfizh</Button>
                    </div>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>
        </div>
      )}

      {/* ========================================================= */}
      {/* 4. VIEW ORANG TUA (PARENTS) */}
      {/* ========================================================= */}
      {activeRole === 'OrangTua' && (
        <div className="space-y-6 animate-in fade-in duration-300">
          <div className="p-4 bg-emerald-950/40 border border-emerald-800/60 rounded-2xl flex flex-col sm:flex-row sm:items-center justify-between">
            <div className="flex items-center space-x-3">
              <div className="w-12 h-12 rounded-full bg-emerald-600 flex items-center justify-center font-bold text-white text-lg">
                AZ
              </div>
              <div>
                <h3 className="font-bold text-white text-lg">{orangTuaData.namaAnak}</h3>
                <p className="text-xs text-emerald-300">{orangTuaData.kelas} • SDIT Islam Terpadu</p>
              </div>
            </div>
            <Badge variant="success" className="mt-3 sm:mt-0 self-start sm:self-center">
              Status Siswa: AKTIF
            </Badge>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <Card>
              <CardContent className="p-5">
                <p className="text-xs text-slate-400 font-medium">Kehadiran Bulan Ini</p>
                <h3 className="text-xl font-bold text-emerald-400 mt-1">{orangTuaData.presensi}</h3>
                <p className="text-xs text-slate-400 mt-1">100% Hadir Tepat Waktu</p>
              </CardContent>
            </Card>

            <Card>
              <CardContent className="p-5">
                <p className="text-xs text-slate-400 font-medium">Hafalan Al-Qur'an</p>
                <h3 className="text-xl font-bold text-amber-400 mt-1">{orangTuaData.tahfizhProgress}</h3>
                <p className="text-xs text-slate-400 mt-1">Surah An-Naba s/d An-Nas</p>
              </CardContent>
            </Card>

            <Card>
              <CardContent className="p-5">
                <p className="text-xs text-slate-400 font-medium">Status SPP Sekolah</p>
                <h3 className="text-xl font-bold text-blue-400 mt-1">{orangTuaData.sppStatus}</h3>
                <p className="text-xs text-slate-400 mt-1">Tidak Ada Tunggakan</p>
              </CardContent>
            </Card>
          </div>

          <Card>
            <CardHeader>
              <CardTitle className="text-base">Grafik Mutabaah Yaumiyah & Ibadah Harian Siswa</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="h-64">
                <ResponsiveContainer width="100%" height="100%">
                  <BarChart data={orangTuaData.mutabaahIbadah}>
                    <CartesianGrid strokeDasharray="3 3" stroke="#334155" />
                    <XAxis dataKey="ibadah" stroke="#94a3b8" />
                    <YAxis stroke="#94a3b8" domain={[0, 100]} />
                    <Tooltip contentStyle={{ backgroundColor: '#0f172a', borderColor: '#334155' }} />
                    <Bar dataKey="persen" name="Capaian (%)" fill="#10b981" radius={[4, 4, 0, 0]} />
                  </BarChart>
                </ResponsiveContainer>
              </div>
            </CardContent>
          </Card>
        </div>
      )}

      {/* ========================================================= */}
      {/* 5. VIEW SISWA (STUDENT) */}
      {/* ========================================================= */}
      {activeRole === 'Siswa' && (
        <div className="space-y-6 animate-in fade-in duration-300">
          <div className="p-6 bg-gradient-to-r from-emerald-900/80 to-slate-900 border border-emerald-700/50 rounded-2xl flex flex-col md:flex-row md:items-center justify-between">
            <div>
              <h2 className="text-2xl font-bold text-white">Assalamu'alaikum, {siswaData.namaSiswa}! 👋</h2>
              <p className="text-sm text-emerald-200 mt-1">
                NISN: {siswaData.nisn} • {siswaData.peringkat}
              </p>
            </div>
            <div className="mt-4 md:mt-0 flex items-center space-x-2">
              <Badge variant="success" className="px-3 py-1 text-sm font-bold">
                {siswaData.poinKebaikan}
              </Badge>
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <Card>
              <CardContent className="p-5">
                <p className="text-xs text-slate-400 font-medium">Rata-Rata Nilai Akademik</p>
                <h3 className="text-2xl font-bold text-emerald-400 mt-1">{siswaData.rataNilai}</h3>
                <p className="text-xs text-slate-400 mt-1">Predikat: SANGAT BAIK</p>
              </CardContent>
            </Card>

            <Card>
              <CardContent className="p-5">
                <p className="text-xs text-slate-400 font-medium">Capaian Tahfizh</p>
                <h3 className="text-2xl font-bold text-amber-400 mt-1">{siswaData.capaianTahfizh}</h3>
                <p className="text-xs text-slate-400 mt-1">Target Juz 29 Berikutnya</p>
              </CardContent>
            </Card>

            <Card>
              <CardContent className="p-5">
                <p className="text-xs text-slate-400 font-medium">Kehadiran Kelas</p>
                <h3 className="text-2xl font-bold text-blue-400 mt-1">100% (Hadir)</h3>
                <p className="text-xs text-slate-400 mt-1">Tanpa Alpha / Izin</p>
              </CardContent>
            </Card>
          </div>

          <Card>
            <CardHeader>
              <CardTitle className="text-base">Daftar Tugas & Setoran Mendatang</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="space-y-3">
                {siswaData.tugasMendatang.map((tugas, idx) => (
                  <div key={idx} className="flex flex-col sm:flex-row sm:items-center justify-between p-4 bg-slate-950/60 rounded-xl border border-slate-800">
                    <div>
                      <span className="text-xs font-semibold text-emerald-400">{tugas.mapel}</span>
                      <h4 className="font-semibold text-white text-sm">{tugas.tugas}</h4>
                    </div>
                    <div className="mt-2 sm:mt-0 flex items-center space-x-2">
                      <span className="text-xs text-amber-400 font-mono">Tenggat: {tugas.tenggat}</span>
                      <Button size="sm" variant="outline">Kirim Tugas</Button>
                    </div>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>
        </div>
      )}
    </div>
  )
}
