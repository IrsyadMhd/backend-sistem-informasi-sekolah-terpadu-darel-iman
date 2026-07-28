import React, { useState, useMemo } from 'react'
import { useNavigate } from 'react-router-dom'
import {
  Cell,
  Line,
  LineChart,
  BarChart,
  Bar,
  Pie,
  PieChart,
  ResponsiveContainer,
  Tooltip as RechartsTooltip,
  XAxis,
  YAxis,
  CartesianGrid,
} from 'recharts'
import {
  Building2,
  UserCheck,
  Users,
  GraduationCap,
  HeartHandshake,
  Sparkles,
  School,
  Layers,
  FileSpreadsheet,
  Upload,
  Plus,
  Search,
  BookOpen,
  CheckCircle2,
  Target,
  Award,
  Activity,
  DollarSign,
  Calendar,
  Clock,
  ChevronRight,
  Eye,
  Edit,
  Settings,
  BellRing,
  ExternalLink,
  ShieldCheck,
  TrendingUp,
  TrendingDown,
} from 'lucide-react'
import Swal from 'sweetalert2'
import StatCard from '../components/StatCard'
import { Badge } from '../components/ui/badge'
import { Button } from '../components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../components/ui/card'
import { Modal } from '../components/ui/modal'

const PRESTASI_COLORS = ['#0284c7', '#10b981', '#8b5cf6', '#f59e0b', '#ef4444']

export default function DashboardPage() {
  const navigate = useNavigate()
  const [isImportModalOpen, setIsImportModalOpen] = useState(false)
  const [tabTahfizh, setTabTahfizh] = useState('unit')

  // Donut chart data for Prestasi Siswa
  const dataPrestasiDonut = [
    { name: 'Akademik', value: 72, percent: '29%', color: '#0284c7' },
    { name: 'Tahfizh', value: 46, percent: '23%', color: '#10b981' },
    { name: 'Olimpiade', value: 36, percent: '19%', color: '#8b5cf6' },
    { name: 'Seni', value: 18, percent: '10%', color: '#f59e0b' },
    { name: 'Olahraga', value: 14, percent: '7%', color: '#ef4444' },
  ]

  // Grouped Bar chart data for Target vs Realisasi Tahfizh
  const dataTargetTahfizh = [
    { unit: 'TKIT', target: 20, realisasi: 35 },
    { unit: 'PAUD', target: 15, realisasi: 25 },
    { unit: 'SDIT', target: 60, realisasi: 85 },
    { unit: 'SMPIT', target: 75, realisasi: 105 },
    { unit: 'SMAIT', target: 70, realisasi: 95 },
    { unit: 'PONPES', target: 90, realisasi: 120 },
    { unit: 'MA\'HAD', target: 80, realisasi: 110 },
  ]

  // Line chart data for Tren Kehadiran Bulanan
  const dataKehadiranBulanan = [
    { bulan: 'Jan', guru: 98, siswa: 95 },
    { bulan: 'Feb', guru: 97, siswa: 94 },
    { bulan: 'Mar', guru: 99, siswa: 96 },
    { bulan: 'Apr', guru: 96, siswa: 93 },
    { bulan: 'Mei', guru: 98, siswa: 95 },
    { bulan: 'Jun', guru: 97, siswa: 94 },
    { bulan: 'Jul', guru: 99, siswa: 97 },
  ]

  // Table data for Data Unit Pendidikan
  const dataUnitPendidikan = [
    { no: 1, name: 'SDIT Dar El-Iman - Padang', siswa: 812, guru: 48, pegawai: 15, kelas: 20, rombel: 32, presensiSiswa: '96%', presensiGuru: '98%', tahfizh: '92%' },
    { no: 2, name: 'SMPIT Dar El-Iman - Padang', siswa: 642, guru: 36, pegawai: 12, kelas: 20, rombel: 24, presensiSiswa: '95%', presensiGuru: '97%', tahfizh: '90%' },
    { no: 3, name: 'SMAIT Dar El-Iman - Padang', siswa: 528, guru: 32, pegawai: 10, kelas: 16, rombel: 18, presensiSiswa: '94%', presensiGuru: '96%', tahfizh: '88%' },
    { no: 4, name: 'PONPES Dar El-Iman - Padang', siswa: 1256, guru: 64, pegawai: 24, kelas: 24, rombel: 28, presensiSiswa: '97%', presensiGuru: '99%', tahfizh: '95%' },
    { no: 5, name: 'MA\'HAD Dar El-Iman - Padang', siswa: 520, guru: 28, pegawai: 12, kelas: 12, rombel: 16, presensiSiswa: '95%', presensiGuru: '97%', tahfizh: '90%' },
  ]

  const handleExportData = () => {
    Swal.fire({
      icon: 'success',
      title: 'Mengeksport Excel',
      text: 'Rekap data eksekutif yayasan sedang diunduh.',
      confirmButtonColor: '#0E5C44',
    })
  }

  const handleImportSubmit = (e) => {
    e.preventDefault()
    setIsImportModalOpen(false)
    Swal.fire({
      icon: 'success',
      title: 'Import Berhasil',
      text: 'Data master telah diperbarui.',
      confirmButtonColor: '#0E5C44',
    })
  }

  return (
    <div className="space-y-6">
      {/* 1. HERO BANNER WITH ISLAMIC MOSQUE PATTERN */}
      <div className="relative overflow-hidden rounded-[20px] bg-gradient-to-r from-[#083A2A] via-[#0E5C44] to-[#1E8E5A] p-6 md:p-7 text-white shadow-lg border border-emerald-500/20">
        {/* Geometric Islamic Star & Mosque Silhouette Pattern */}
        <div className="absolute inset-0 opacity-15 pointer-events-none">
          <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg">
            <defs>
              <pattern id="islamicHeroPattern" width="60" height="60" patternUnits="userSpaceOnUse">
                <path d="M 30,0 L 60,30 L 30,60 L 0,30 Z" fill="none" stroke="#FFFFFF" strokeWidth="1" />
                <circle cx="30" cy="30" r="12" fill="none" stroke="#FFFFFF" strokeWidth="0.8" />
              </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#islamicHeroPattern)" />
          </svg>
        </div>

        {/* Mosque Dome Silhouette Vector */}
        <svg className="absolute right-0 bottom-0 opacity-20 h-full w-auto pointer-events-none hidden md:block" viewBox="0 0 600 200" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M350 200V120C350 90 380 70 410 70C440 70 470 90 470 120V200H350Z" fill="white"/>
          <path d="M410 70V45M410 45C405 45 410 25 410 25C410 25 415 45 410 45" stroke="white" strokeWidth="3"/>
          <path d="M480 200V100C480 75 505 60 530 60C555 60 580 75 580 100V200H480Z" fill="white"/>
          <path d="M530 60V35M530 35C525 35 530 15 530 15C530 15 535 35 530 35" stroke="white" strokeWidth="3"/>
          <path d="M260 200V140C260 115 285 100 310 100C335 100 360 115 360 140V200H260Z" fill="white"/>
        </svg>

        <div className="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div className="space-y-1">
            <h1 className="text-xl md:text-2xl font-bold tracking-tight font-sans text-emerald-100">
              Selamat Datang,
            </h1>
            <h2 className="text-2xl md:text-3xl font-extrabold text-white flex items-center gap-2">
              <span>Ketua Yayasan</span>
              <span className="text-2xl">👋</span>
            </h2>
            <p className="text-xs md:text-sm text-emerald-100/90 font-medium pt-0.5">
              Monitoring seluruh unit pendidikan dalam satu dashboard
            </p>
          </div>

          <div className="flex flex-wrap items-center gap-2.5">
            <Button
              variant="secondary"
              size="sm"
              onClick={handleExportData}
              className="gap-2 font-bold bg-white text-[#0E5C44] hover:bg-emerald-50 shadow-sm border border-white/40 text-xs rounded-xl"
            >
              <FileSpreadsheet className="h-4 w-4 text-[#0E5C44]" />
              <span>Export Excel</span>
            </Button>

            <Button
              variant="secondary"
              size="sm"
              onClick={() => setIsImportModalOpen(true)}
              className="gap-2 font-bold bg-white/15 text-white hover:bg-white/25 border border-white/30 text-xs rounded-xl backdrop-blur-md"
            >
              <Upload className="h-4 w-4" />
              <span>Import Excel</span>
            </Button>

            <Button
              variant="primary"
              size="sm"
              onClick={() => navigate('/dashboard/students?action=add')}
              className="gap-2 font-bold bg-[#10B981] hover:bg-[#059669] text-white border-none text-xs rounded-xl shadow-md"
            >
              <Plus className="h-4 w-4" />
              <span>Tambah Data</span>
            </Button>
          </div>
        </div>
      </div>

      {/* 2. 8 KPI STAT CARDS ROW */}
      <div className="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3.5">
        <StatCard title="Unit Pendidikan" value="15" trend="2 Unit" trendType="up" trendText="dari bulan lalu" onClickTo="/dashboard/students/unit-pendidikan" />
        <StatCard title="Guru" value="236" trend="18" trendType="up" trendText="dari bulan lalu" onClickTo="/dashboard/employees?role=guru" />
        <StatCard title="Pegawai" value="118" trend="4" trendType="up" trendText="dari bulan lalu" onClickTo="/dashboard/employees" />
        <StatCard title="Siswa" value="4.856" trend="385" trendType="up" trendText="dari bulan lalu" onClickTo="/dashboard/students" />
        <StatCard title="Orang Tua" value="4.780" trend="312" trendType="up" trendText="dari bulan lalu" onClickTo="/dashboard/parents" />
        <StatCard title="Alumni" value="1.620" trend="120" trendType="up" trendText="dari bulan lalu" onClickTo="/dashboard/laporan-alumni" />
        <StatCard title="Kelas" value="198" trend="12" trendType="up" trendText="dari bulan lalu" onClickTo="/dashboard/students/rombel" />
        <StatCard title="Rombel" value="176" trend="10" trendType="up" trendText="dari bulan lalu" onClickTo="/dashboard/students/rombel" />
      </div>

      {/* 3. ROW 2: MONITORING AKADEMIK & PRESTASI SISWA */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">
        {/* Monitoring Akademik (Left 2 cols) */}
        <Card className="lg:col-span-2 rounded-[20px] border border-slate-200/80 dark:border-slate-800 dark:bg-[#13221f]">
          <CardHeader className="pb-3">
            <CardTitle className="text-base font-bold text-slate-900 dark:text-white">
              Monitoring Akademik
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            {/* Progress Bars Grid */}
            <div className="grid grid-cols-1 sm:grid-cols-5 gap-3 text-xs">
              <div className="space-y-1.5 p-2.5 rounded-xl bg-slate-50 dark:bg-[#1b302c]/50">
                <div className="flex items-center justify-between text-slate-600 dark:text-slate-300 font-semibold">
                  <span>Kehadiran Guru</span>
                </div>
                <div className="flex items-baseline gap-1.5">
                  <span className="text-lg font-extrabold text-slate-900 dark:text-white">98%</span>
                  <span className="text-[11px] font-bold text-emerald-600 dark:text-emerald-400">↑ 2%</span>
                </div>
                <div className="h-1.5 w-full rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden">
                  <div className="h-full bg-emerald-500 rounded-full" style={{ width: '98%' }} />
                </div>
              </div>

              <div className="space-y-1.5 p-2.5 rounded-xl bg-slate-50 dark:bg-[#1b302c]/50">
                <div className="flex items-center justify-between text-slate-600 dark:text-slate-300 font-semibold">
                  <span>Kehadiran Siswa</span>
                </div>
                <div className="flex items-baseline gap-1.5">
                  <span className="text-lg font-extrabold text-slate-900 dark:text-white">96%</span>
                  <span className="text-[11px] font-bold text-emerald-600 dark:text-emerald-400">↑ 1%</span>
                </div>
                <div className="h-1.5 w-full rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden">
                  <div className="h-full bg-emerald-500 rounded-full" style={{ width: '96%' }} />
                </div>
              </div>

              <div className="space-y-1.5 p-2.5 rounded-xl bg-slate-50 dark:bg-[#1b302c]/50">
                <div className="flex items-center justify-between text-slate-600 dark:text-slate-300 font-semibold">
                  <span>Input Nilai</span>
                </div>
                <div className="flex items-baseline gap-1.5">
                  <span className="text-lg font-extrabold text-slate-900 dark:text-white">95%</span>
                  <span className="text-[11px] font-bold text-emerald-600 dark:text-emerald-400">↑ 2%</span>
                </div>
                <div className="h-1.5 w-full rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden">
                  <div className="h-full bg-emerald-500 rounded-full" style={{ width: '95%' }} />
                </div>
                <p className="text-[10px] text-slate-400">Guru sudah input</p>
              </div>

              <div className="space-y-1.5 p-2.5 rounded-xl bg-slate-50 dark:bg-[#1b302c]/50">
                <div className="flex items-center justify-between text-slate-600 dark:text-slate-300 font-semibold">
                  <span>Input Tahfizh</span>
                </div>
                <div className="flex items-baseline gap-1.5">
                  <span className="text-lg font-extrabold text-slate-900 dark:text-white">91%</span>
                  <span className="text-[11px] font-bold text-emerald-600 dark:text-emerald-400">↑ 3%</span>
                </div>
                <div className="h-1.5 w-full rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden">
                  <div className="h-full bg-emerald-500 rounded-full" style={{ width: '91%' }} />
                </div>
                <p className="text-[10px] text-slate-400">Guru sudah input</p>
              </div>

              <div className="space-y-1.5 p-2.5 rounded-xl bg-slate-50 dark:bg-[#1b302c]/50">
                <div className="flex items-center justify-between text-slate-600 dark:text-slate-300 font-semibold">
                  <span>Input Mutabaah</span>
                </div>
                <div className="flex items-baseline gap-1.5">
                  <span className="text-lg font-extrabold text-slate-900 dark:text-white">90%</span>
                  <span className="text-[11px] font-bold text-emerald-600 dark:text-emerald-400">↑ 2%</span>
                </div>
                <div className="h-1.5 w-full rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden">
                  <div className="h-full bg-emerald-500 rounded-full" style={{ width: '90%' }} />
                </div>
                <p className="text-[10px] text-slate-400">Data mutabaah</p>
              </div>
            </div>

            {/* Bottom Row Badges: Terlambat & Tidak Hadir */}
            <div className="flex items-center gap-4 pt-1">
              <div className="flex items-center gap-2 text-xs font-semibold">
                <span className="text-slate-600 dark:text-slate-300">Terlambat Hari Ini</span>
                <span className="text-base font-extrabold text-slate-900 dark:text-white">28</span>
                <span className="text-rose-600 dark:text-rose-400 font-bold text-[11px]">↓ 8% <span className="text-slate-400 font-normal">dari kemarin</span></span>
              </div>
              <div className="h-4 w-[1px] bg-slate-200 dark:bg-slate-700" />
              <div className="flex items-center gap-2 text-xs font-semibold">
                <span className="text-slate-600 dark:text-slate-300">Tidak Hadir</span>
                <span className="text-base font-extrabold text-slate-900 dark:text-white">16</span>
                <span className="text-rose-600 dark:text-rose-400 font-bold text-[11px]">↓ 12% <span className="text-slate-400 font-normal">dari kemarin</span></span>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Prestasi Siswa (Semua Unit) Donut Card */}
        <Card className="rounded-[20px] border border-slate-200/80 dark:border-slate-800 dark:bg-[#13221f]">
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-base font-bold text-slate-900 dark:text-white">
              Prestasi Siswa <span className="text-xs text-slate-400 font-normal">(Semua Unit)</span>
            </CardTitle>
            <button
              onClick={() => navigate('/dashboard/laporan-siswa')}
              className="text-xs font-bold text-blue-600 dark:text-blue-400 hover:underline"
            >
              Lihat Semua
            </button>
          </CardHeader>
          <CardContent className="flex items-center gap-2">
            {/* Donut Chart with Center Text */}
            <div className="relative h-44 w-44 shrink-0">
              <ResponsiveContainer width="100%" height="100%">
                <PieChart>
                  <Pie
                    data={dataPrestasiDonut}
                    cx="50%"
                    cy="50%"
                    innerRadius={52}
                    outerRadius={75}
                    paddingAngle={3}
                    dataKey="value"
                  >
                    {dataPrestasiDonut.map((entry, index) => (
                      <Cell key={`cell-${index}`} fill={entry.color} />
                    ))}
                  </Pie>
                </PieChart>
              </ResponsiveContainer>
              <div className="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                <span className="text-xl font-extrabold text-slate-900 dark:text-white">186</span>
                <span className="text-[9px] font-semibold text-slate-400">Total Prestasi</span>
              </div>
            </div>

            {/* Legend List */}
            <div className="space-y-1.5 flex-1 text-xs font-semibold">
              {dataPrestasiDonut.map((item) => (
                <div key={item.name} className="flex items-center justify-between">
                  <div className="flex items-center gap-1.5">
                    <span className="h-2 w-2 rounded-full shrink-0" style={{ backgroundColor: item.color }} />
                    <span className="text-slate-700 dark:text-slate-300">{item.name}</span>
                  </div>
                  <span className="text-slate-900 font-bold dark:text-white text-[11px]">
                    {item.value} <span className="text-slate-400 font-normal">({item.percent})</span>
                  </span>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      </div>

      {/* 4. ROW 3: TARGET VS REALISASI TAHFIDZ, MONITORING IBADAH, RANKING UNIT, AGENDA YAYASAN */}
      <div className="grid grid-cols-1 lg:grid-cols-4 gap-5">
        {/* Target vs Realisasi Tahfizh */}
        <Card className="rounded-[20px] border border-slate-200/80 dark:border-slate-800 dark:bg-[#13221f]">
          <CardHeader className="pb-2">
            <div className="flex items-center justify-between">
              <CardTitle className="text-xs font-bold text-slate-900 dark:text-white">
                Target vs Realisasi Tahfizh <span className="text-[10px] text-slate-400 font-normal">(Semua Unit)</span>
              </CardTitle>
            </div>
            {/* Filter Tabs */}
            <div className="flex items-center gap-1 mt-2 bg-slate-100 dark:bg-slate-800/80 p-0.5 rounded-lg w-fit text-[11px] font-bold">
              <button
                onClick={() => setTabTahfizh('unit')}
                className={`px-2 py-0.5 rounded-md transition ${tabTahfizh === 'unit' ? 'bg-[#0E5C44] text-white shadow-xs' : 'text-slate-500 dark:text-slate-400'}`}
              >
                Per Unit
              </button>
              <button
                onClick={() => setTabTahfizh('guru')}
                className={`px-2 py-0.5 rounded-md transition ${tabTahfizh === 'guru' ? 'bg-[#0E5C44] text-white shadow-xs' : 'text-slate-500 dark:text-slate-400'}`}
              >
                Per Guru
              </button>
              <button
                onClick={() => setTabTahfizh('kelas')}
                className={`px-2 py-0.5 rounded-md transition ${tabTahfizh === 'kelas' ? 'bg-[#0E5C44] text-white shadow-xs' : 'text-slate-500 dark:text-slate-400'}`}
              >
                Per Kelas
              </button>
            </div>
          </CardHeader>
          <CardContent className="pt-0">
            {/* Chart Legend */}
            <div className="flex items-center gap-3 text-[10px] font-semibold text-slate-500 dark:text-slate-400 mb-2">
              <span className="flex items-center gap-1">
                <span className="h-2 w-2 rounded-full bg-emerald-500 inline-block" />
                Target (Halaman)
              </span>
              <span className="flex items-center gap-1">
                <span className="h-2 w-2 rounded-full bg-blue-500 inline-block" />
                Realisasi (Halaman)
              </span>
            </div>

            <div className="h-44 w-full">
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={dataTargetTahfizh} margin={{ top: 5, right: 5, left: -25, bottom: 0 }}>
                  <CartesianGrid strokeDasharray="3 3" opacity={0.15} />
                  <XAxis dataKey="unit" fontSize={10} stroke="#94A3B8" />
                  <YAxis fontSize={10} stroke="#94A3B8" />
                  <RechartsTooltip contentStyle={{ borderRadius: '10px', fontSize: '11px' }} />
                  <Bar dataKey="target" fill="#10B981" radius={[4, 4, 0, 0]} />
                  <Bar dataKey="realisasi" fill="#6366F1" radius={[4, 4, 0, 0]} />
                </BarChart>
              </ResponsiveContainer>
            </div>
          </CardContent>
        </Card>

        {/* Monitoring Ibadah (4 Gauges) */}
        <Card className="rounded-[20px] border border-slate-200/80 dark:border-slate-800 dark:bg-[#13221f]">
          <CardHeader className="pb-2">
            <CardTitle className="text-xs font-bold text-slate-900 dark:text-white">
              Monitoring Ibadah
            </CardTitle>
          </CardHeader>
          <CardContent className="pt-2">
            <div className="grid grid-cols-2 gap-3 text-center">
              {/* Shalat */}
              <div className="space-y-1 p-2 rounded-xl bg-slate-50 dark:bg-[#1b302c]/50">
                <div className="relative h-16 w-16 mx-auto flex items-center justify-center">
                  <svg className="h-full w-full transform -rotate-90" viewBox="0 0 36 36">
                    <path className="text-slate-200 dark:text-slate-700" strokeWidth="3.5" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                    <path className="text-emerald-500" strokeDasharray="96, 100" strokeWidth="3.5" strokeLinecap="round" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                  </svg>
                  <span className="absolute text-xs font-bold text-slate-900 dark:text-white">96%</span>
                </div>
                <p className="text-xs font-bold text-slate-800 dark:text-slate-200">Shalat</p>
                <span className="text-[10px] font-semibold text-emerald-600 dark:text-emerald-400">Baik</span>
              </div>

              {/* Tilawah */}
              <div className="space-y-1 p-2 rounded-xl bg-slate-50 dark:bg-[#1b302c]/50">
                <div className="relative h-16 w-16 mx-auto flex items-center justify-center">
                  <svg className="h-full w-full transform -rotate-90" viewBox="0 0 36 36">
                    <path className="text-slate-200 dark:text-slate-700" strokeWidth="3.5" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                    <path className="text-blue-500" strokeDasharray="88, 100" strokeWidth="3.5" strokeLinecap="round" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                  </svg>
                  <span className="absolute text-xs font-bold text-slate-900 dark:text-white">88%</span>
                </div>
                <p className="text-xs font-bold text-slate-800 dark:text-slate-200">Tilawah</p>
                <span className="text-[10px] font-semibold text-emerald-600 dark:text-emerald-400">Baik</span>
              </div>

              {/* Munaqasyah */}
              <div className="space-y-1 p-2 rounded-xl bg-slate-50 dark:bg-[#1b302c]/50">
                <div className="relative h-16 w-16 mx-auto flex items-center justify-center">
                  <svg className="h-full w-full transform -rotate-90" viewBox="0 0 36 36">
                    <path className="text-slate-200 dark:text-slate-700" strokeWidth="3.5" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                    <path className="text-indigo-500" strokeDasharray="91, 100" strokeWidth="3.5" strokeLinecap="round" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                  </svg>
                  <span className="absolute text-xs font-bold text-slate-900 dark:text-white">91%</span>
                </div>
                <p className="text-xs font-bold text-slate-800 dark:text-slate-200">Munaqasyah</p>
                <span className="text-[10px] font-semibold text-emerald-600 dark:text-emerald-400">Baik</span>
              </div>

              {/* Mutabaah */}
              <div className="space-y-1 p-2 rounded-xl bg-slate-50 dark:bg-[#1b302c]/50">
                <div className="relative h-16 w-16 mx-auto flex items-center justify-center">
                  <svg className="h-full w-full transform -rotate-90" viewBox="0 0 36 36">
                    <path className="text-slate-200 dark:text-slate-700" strokeWidth="3.5" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                    <path className="text-amber-500" strokeDasharray="84, 100" strokeWidth="3.5" strokeLinecap="round" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                  </svg>
                  <span className="absolute text-xs font-bold text-slate-900 dark:text-white">84%</span>
                </div>
                <p className="text-xs font-bold text-slate-800 dark:text-slate-200">Mutabaah</p>
                <span className="text-[10px] font-semibold text-amber-600 dark:text-amber-400">Cukup</span>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Ranking Unit Pendidikan */}
        <Card className="rounded-[20px] border border-slate-200/80 dark:border-slate-800 dark:bg-[#13221f]">
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-xs font-bold text-slate-900 dark:text-white">
              Ranking Unit Pendidikan
            </CardTitle>
            <button onClick={() => navigate('/dashboard/students/unit-pendidikan')} className="text-xs font-bold text-blue-600 dark:text-blue-400 hover:underline">
              Lihat Semua
            </button>
          </CardHeader>
          <CardContent className="space-y-3 pt-1 text-xs">
            <div className="space-y-1">
              <div className="flex items-center justify-between">
                <span className="font-semibold text-slate-800 dark:text-slate-200">1. SDIT Dar El-Iman</span>
                <span className="font-bold text-emerald-600 dark:text-emerald-400">98%</span>
              </div>
              <div className="h-1.5 w-full bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
                <div className="h-full bg-emerald-500 rounded-full" style={{ width: '98%' }} />
              </div>
            </div>

            <div className="space-y-1">
              <div className="flex items-center justify-between">
                <span className="font-semibold text-slate-800 dark:text-slate-200">2. SMPIT Dar El-Iman</span>
                <span className="font-bold text-emerald-600 dark:text-emerald-400">97%</span>
              </div>
              <div className="h-1.5 w-full bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
                <div className="h-full bg-emerald-500 rounded-full" style={{ width: '97%' }} />
              </div>
            </div>

            <div className="space-y-1">
              <div className="flex items-center justify-between">
                <span className="font-semibold text-slate-800 dark:text-slate-200">3. PONPES Dar El-Iman</span>
                <span className="font-bold text-emerald-600 dark:text-emerald-400">96%</span>
              </div>
              <div className="h-1.5 w-full bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
                <div className="h-full bg-emerald-500 rounded-full" style={{ width: '96%' }} />
              </div>
            </div>

            <div className="space-y-1">
              <div className="flex items-center justify-between">
                <span className="font-semibold text-slate-800 dark:text-slate-200">4. SMAIT Dar El-Iman</span>
                <span className="font-bold text-emerald-600 dark:text-emerald-400">95%</span>
              </div>
              <div className="h-1.5 w-full bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
                <div className="h-full bg-emerald-500 rounded-full" style={{ width: '95%' }} />
              </div>
            </div>

            <div className="space-y-1">
              <div className="flex items-center justify-between">
                <span className="font-semibold text-slate-800 dark:text-slate-200">5. MA'HAD Dar El-Iman</span>
                <span className="font-bold text-emerald-600 dark:text-emerald-400">94%</span>
              </div>
              <div className="h-1.5 w-full bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
                <div className="h-full bg-emerald-500 rounded-full" style={{ width: '94%' }} />
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Agenda Yayasan */}
        <Card className="rounded-[20px] border border-slate-200/80 dark:border-slate-800 dark:bg-[#13221f]">
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-xs font-bold text-slate-900 dark:text-white">
              Agenda Yayasan
            </CardTitle>
            <button onClick={() => navigate('/dashboard/pengaturan')} className="text-xs font-bold text-blue-600 dark:text-blue-400 hover:underline">
              Lihat Semua
            </button>
          </CardHeader>
          <CardContent className="space-y-3 pt-1 text-xs">
            <div className="flex items-start gap-2.5 p-2 rounded-xl bg-slate-50 dark:bg-[#1b302c]/50">
              <div className="flex h-7 w-12 shrink-0 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 font-bold text-[10px]">
                08:00
              </div>
              <div className="min-w-0 flex-1">
                <p className="font-bold text-slate-900 dark:text-white truncate">Rapat Pengurus Bulanan</p>
                <p className="text-[10px] text-slate-400 truncate">Aula Utama Yayasan</p>
              </div>
            </div>

            <div className="flex items-start gap-2.5 p-2 rounded-xl bg-slate-50 dark:bg-[#1b302c]/50">
              <div className="flex h-7 w-12 shrink-0 items-center justify-center rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400 font-bold text-[10px]">
                10:00
              </div>
              <div className="min-w-0 flex-1">
                <p className="font-bold text-slate-900 dark:text-white truncate">Evaluasi Tengah Semester</p>
                <p className="text-[10px] text-slate-400 truncate">Seluruh Unit</p>
              </div>
            </div>

            <div className="flex items-start gap-2.5 p-2 rounded-xl bg-slate-50 dark:bg-[#1b302c]/50">
              <div className="flex h-7 w-12 shrink-0 items-center justify-center rounded-lg bg-purple-500/10 text-purple-600 dark:text-purple-400 font-bold text-[10px]">
                13:00
              </div>
              <div className="min-w-0 flex-1">
                <p className="font-bold text-slate-900 dark:text-white truncate">Monitoring Tahfizh</p>
                <p className="text-[10px] text-slate-400 truncate">Seluruh Unit</p>
              </div>
            </div>

            <div className="flex items-start gap-2.5 p-2 rounded-xl bg-slate-50 dark:bg-[#1b302c]/50">
              <div className="flex h-7 w-12 shrink-0 items-center justify-center rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400 font-bold text-[10px]">
                15:00
              </div>
              <div className="min-w-0 flex-1">
                <p className="font-bold text-slate-900 dark:text-white truncate">Laporan Bulanan Unit</p>
                <p className="text-[10px] text-slate-400 truncate">Kantor Yayasan</p>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      {/* 5. ROW 4: TREN KEHADIRAN BULANAN, AKTIVITAS TERBARU, PENGUMUMAN, AKSES CEPAT */}
      <div className="grid grid-cols-1 lg:grid-cols-4 gap-5">
        {/* Tren Kehadiran Bulanan */}
        <Card className="rounded-[20px] border border-slate-200/80 dark:border-slate-800 dark:bg-[#13221f]">
          <CardHeader className="pb-2">
            <CardTitle className="text-xs font-bold text-slate-900 dark:text-white">
              Tren Kehadiran Bulanan <span className="text-[10px] text-slate-400 font-normal">(Semua Unit)</span>
            </CardTitle>
          </CardHeader>
          <CardContent className="pt-0">
            <div className="flex items-center gap-3 text-[10px] font-semibold text-slate-500 dark:text-slate-400 mb-2">
              <span className="flex items-center gap-1">
                <span className="h-2 w-2 rounded-full bg-emerald-500 inline-block" />
                Guru
              </span>
              <span className="flex items-center gap-1">
                <span className="h-2 w-2 rounded-full bg-purple-500 inline-block" />
                Siswa
              </span>
            </div>
            <div className="h-44 w-full">
              <ResponsiveContainer width="100%" height="100%">
                <LineChart data={dataKehadiranBulanan} margin={{ top: 5, right: 5, left: -25, bottom: 0 }}>
                  <CartesianGrid strokeDasharray="3 3" opacity={0.15} />
                  <XAxis dataKey="bulan" fontSize={10} stroke="#94A3B8" />
                  <YAxis domain={[90, 100]} fontSize={10} stroke="#94A3B8" />
                  <RechartsTooltip contentStyle={{ borderRadius: '10px', fontSize: '11px' }} />
                  <Line type="monotone" dataKey="guru" stroke="#10B981" strokeWidth={2} dot={{ r: 3 }} />
                  <Line type="monotone" dataKey="siswa" stroke="#A855F7" strokeWidth={2} dot={{ r: 3 }} />
                </LineChart>
              </ResponsiveContainer>
            </div>
          </CardContent>
        </Card>

        {/* Aktivitas Terbaru */}
        <Card className="rounded-[20px] border border-slate-200/80 dark:border-slate-800 dark:bg-[#13221f]">
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-xs font-bold text-slate-900 dark:text-white">
              Aktivitas Terbaru
            </CardTitle>
            <button onClick={() => navigate('/dashboard/attendance')} className="text-xs font-bold text-blue-600 dark:text-blue-400 hover:underline">
              Lihat Semua
            </button>
          </CardHeader>
          <CardContent className="space-y-3 pt-1 text-xs">
            <div className="flex items-start justify-between border-b border-slate-100 dark:border-slate-800/80 pb-2">
              <div>
                <p className="font-bold text-slate-900 dark:text-white">Ahmad Zaky (6A) hadir</p>
                <p className="text-[10px] text-slate-400">Presensi Tepat Waktu</p>
              </div>
              <span className="text-[10px] text-slate-400 shrink-0">07:15 WIB</span>
            </div>

            <div className="flex items-start justify-between border-b border-slate-100 dark:border-slate-800/80 pb-2">
              <div>
                <p className="font-bold text-slate-900 dark:text-white">Guru mengunggah nilai baru</p>
                <p className="text-[10px] text-slate-400">Matematika - Kelas 6A</p>
              </div>
              <span className="text-[10px] text-slate-400 shrink-0">08:30 WIB</span>
            </div>

            <div className="flex items-start justify-between border-b border-slate-100 dark:border-slate-800/80 pb-2">
              <div>
                <p className="font-bold text-slate-900 dark:text-white">Orang tua mengajukan izin</p>
                <p className="text-[10px] text-slate-400">Aisyah Humaira - Kelas 6A</p>
              </div>
              <span className="text-[10px] text-slate-400 shrink-0">10:15 WIB</span>
            </div>

            <div className="flex items-start justify-between">
              <div>
                <p className="font-bold text-slate-900 dark:text-white">Laporan bulanan tersedia</p>
                <p className="text-[10px] text-slate-400">Laporan Kehadiran - Jul 2026</p>
              </div>
              <span className="text-[10px] text-slate-400 shrink-0">16:45 WIB</span>
            </div>
          </CardContent>
        </Card>

        {/* Pengumuman */}
        <Card className="rounded-[20px] border border-slate-200/80 dark:border-slate-800 dark:bg-[#13221f]">
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-xs font-bold text-slate-900 dark:text-white">
              Pengumuman
            </CardTitle>
            <button onClick={() => navigate('/dashboard/pengaturan')} className="text-xs font-bold text-blue-600 dark:text-blue-400 hover:underline">
              Lihat Semua
            </button>
          </CardHeader>
          <CardContent className="space-y-3 pt-1 text-xs">
            <div className="flex items-start justify-between border-b border-slate-100 dark:border-slate-800/80 pb-2">
              <div>
                <p className="font-bold text-slate-900 dark:text-white">Libur Tahun Baru Islam</p>
                <p className="text-[10px] text-slate-400">1 Muharram 1448 H</p>
              </div>
              <span className="text-[10px] text-slate-400 shrink-0">25 Jul 2026</span>
            </div>

            <div className="flex items-start justify-between border-b border-slate-100 dark:border-slate-800/80 pb-2">
              <div>
                <p className="font-bold text-slate-900 dark:text-white">Parent Meeting Kelas 7</p>
                <p className="text-[10px] text-slate-400">Sabtu, 2 Agustus 2026</p>
              </div>
              <span className="text-[10px] text-slate-400 shrink-0">24 Jul 2026</span>
            </div>

            <div className="flex items-start justify-between">
              <div>
                <p className="font-bold text-slate-900 dark:text-white">Penerimaan Rapor</p>
                <p className="text-[10px] text-slate-400">Akhir Semester Genap</p>
              </div>
              <span className="text-[10px] text-slate-400 shrink-0">23 Jul 2026</span>
            </div>
          </CardContent>
        </Card>

        {/* Akses Cepat (8 App Icons) */}
        <Card className="rounded-[20px] border border-slate-200/80 dark:border-slate-800 dark:bg-[#13221f]">
          <CardHeader className="pb-2">
            <CardTitle className="text-xs font-bold text-slate-900 dark:text-white">
              Akses Cepat
            </CardTitle>
          </CardHeader>
          <CardContent className="pt-1">
            <div className="grid grid-cols-4 gap-2 text-center">
              <button onClick={() => navigate('/dashboard/attendance')} className="p-2 rounded-xl bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 transition flex flex-col items-center gap-1">
                <CheckCircle2 className="h-5 w-5" />
                <span className="text-[10px] font-bold">Absensi</span>
              </button>

              <button onClick={() => navigate('/dashboard/tahfizh')} className="p-2 rounded-xl bg-purple-500/10 hover:bg-purple-500/20 text-purple-600 dark:text-purple-400 transition flex flex-col items-center gap-1">
                <BookOpen className="h-5 w-5" />
                <span className="text-[10px] font-bold">Tahfizh</span>
              </button>

              <button onClick={() => navigate('/dashboard/academic')} className="p-2 rounded-xl bg-blue-500/10 hover:bg-blue-500/20 text-blue-600 dark:text-blue-400 transition flex flex-col items-center gap-1">
                <School className="h-5 w-5" />
                <span className="text-[10px] font-bold">Akademik</span>
              </button>

              <button onClick={() => navigate('/dashboard/laporan-akademik')} className="p-2 rounded-xl bg-amber-500/10 hover:bg-amber-500/20 text-amber-600 dark:text-amber-400 transition flex flex-col items-center gap-1">
                <Award className="h-5 w-5" />
                <span className="text-[10px] font-bold">Nilai</span>
              </button>

              <button onClick={() => navigate('/dashboard/pengaturan')} className="p-2 rounded-xl bg-teal-500/10 hover:bg-teal-500/20 text-teal-600 dark:text-teal-400 transition flex flex-col items-center gap-1">
                <DollarSign className="h-5 w-5" />
                <span className="text-[10px] font-bold">Keuangan</span>
              </button>

              <button onClick={() => navigate('/dashboard/laporan-siswa')} className="p-2 rounded-xl bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 transition flex flex-col items-center gap-1">
                <FileSpreadsheet className="h-5 w-5" />
                <span className="text-[10px] font-bold">Laporan</span>
              </button>

              <button onClick={() => navigate('/dashboard/pengaturan')} className="p-2 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-600 dark:text-rose-400 transition flex flex-col items-center gap-1">
                <BellRing className="h-5 w-5" />
                <span className="text-[10px] font-bold">Pengumuman</span>
              </button>

              <button onClick={() => navigate('/dashboard/pengaturan')} className="p-2 rounded-xl bg-slate-500/10 hover:bg-slate-500/20 text-slate-600 dark:text-slate-400 transition flex flex-col items-center gap-1">
                <Settings className="h-5 w-5" />
                <span className="text-[10px] font-bold">Pengaturan</span>
              </button>
            </div>
          </CardContent>
        </Card>
      </div>

      {/* 6. ROW 5: DATA UNIT PENDIDIKAN TABLE */}
      <Card className="rounded-[20px] border border-slate-200/80 dark:border-slate-800 dark:bg-[#13221f]">
        <CardHeader className="flex flex-row items-center justify-between pb-3">
          <CardTitle className="text-sm font-bold text-slate-900 dark:text-white">
            Data Unit Pendidikan
          </CardTitle>
          <button onClick={() => navigate('/dashboard/students/unit-pendidikan')} className="text-xs font-bold text-blue-600 dark:text-blue-400 hover:underline">
            Lihat Semua
          </button>
        </CardHeader>

        <CardContent className="pt-0">
          <div className="overflow-x-auto rounded-xl border border-slate-200/60 dark:border-slate-800">
            <table className="w-full text-left text-xs border-collapse">
              <thead className="bg-slate-50 text-slate-600 font-semibold dark:bg-[#1b302c] dark:text-slate-300">
                <tr>
                  <th className="p-3">No</th>
                  <th className="p-3">Unit Pendidikan</th>
                  <th className="p-3">Siswa</th>
                  <th className="p-3">Guru</th>
                  <th className="p-3">Pegawai</th>
                  <th className="p-3">Kelas</th>
                  <th className="p-3">Rombel</th>
                  <th className="p-3">Kehadiran Siswa</th>
                  <th className="p-3">Kehadiran Guru</th>
                  <th className="p-3">Tahfizh</th>
                  <th className="p-3 text-center">Aksi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-800/80">
                {dataUnitPendidikan.map((unit) => (
                  <tr key={unit.no} className="hover:bg-slate-50/60 dark:hover:bg-slate-800/40">
                    <td className="p-3 font-medium text-slate-500 dark:text-slate-400">{unit.no}</td>
                    <td className="p-3 font-bold text-slate-900 dark:text-white">{unit.name}</td>
                    <td className="p-3 font-semibold text-slate-700 dark:text-slate-300">{unit.siswa}</td>
                    <td className="p-3 font-semibold text-slate-700 dark:text-slate-300">{unit.guru}</td>
                    <td className="p-3 font-semibold text-slate-700 dark:text-slate-300">{unit.pegawai}</td>
                    <td className="p-3 font-semibold text-slate-700 dark:text-slate-300">{unit.kelas}</td>
                    <td className="p-3 font-semibold text-slate-700 dark:text-slate-300">{unit.rombel}</td>
                    <td className="p-3 font-bold text-emerald-600 dark:text-emerald-400">{unit.presensiSiswa}</td>
                    <td className="p-3 font-bold text-emerald-600 dark:text-emerald-400">{unit.presensiGuru}</td>
                    <td className="p-3 font-bold text-emerald-600 dark:text-emerald-400">{unit.tahfizh}</td>
                    <td className="p-3 text-center">
                      <div className="flex items-center justify-center gap-1 text-slate-400">
                        <button onClick={() => navigate('/dashboard/students/unit-pendidikan')} className="p-1 hover:text-emerald-600 dark:hover:text-emerald-400 transition" title="Lihat Detail">
                          <Eye className="h-4 w-4" />
                        </button>
                        <button onClick={() => navigate('/dashboard/students/unit-pendidikan')} className="p-1 hover:text-blue-600 dark:hover:text-blue-400 transition" title="Edit Unit">
                          <Edit className="h-4 w-4" />
                        </button>
                        <button onClick={() => navigate('/dashboard/pengaturan')} className="p-1 hover:text-slate-700 dark:hover:text-slate-200 transition" title="Pengaturan">
                          <Settings className="h-4 w-4" />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </CardContent>
      </Card>

      {/* IMPORT EXCEL MODAL */}
      <Modal
        isOpen={isImportModalOpen}
        onClose={() => setIsImportModalOpen(false)}
        title="Import Data Master Excel"
        footer={
          <>
            <Button variant="secondary" size="sm" onClick={() => setIsImportModalOpen(false)}>
              Batal
            </Button>
            <Button variant="primary" size="sm" onClick={handleImportSubmit} className="bg-[#0E5C44] text-white hover:bg-[#1E8E5A]">
              Unggah & Import
            </Button>
          </>
        }
      >
        <form onSubmit={handleImportSubmit} className="space-y-4 text-xs">
          <p className="text-slate-600 dark:text-slate-300">
            Pilih file rekap Excel (.xlsx, .csv) data unit pendidikan, siswa, atau guru.
          </p>
          <div className="border-2 border-dashed border-slate-300 dark:border-slate-700 rounded-2xl p-6 text-center bg-slate-50 dark:bg-[#1b302c]/40">
            <Upload className="h-8 w-8 text-[#0E5C44] dark:text-[#3FBF75] mx-auto mb-2" />
            <p className="font-bold text-slate-700 dark:text-slate-200">Klik untuk jelajah berkas</p>
            <p className="text-[10px] text-slate-400 mt-1">Maksimal 10MB (.xlsx)</p>
            <input type="file" className="hidden" id="excelFileInput" />
            <label htmlFor="excelFileInput" className="inline-block mt-3 px-4 py-1.5 rounded-xl bg-[#0E5C44] text-white text-xs font-bold cursor-pointer hover:bg-[#1E8E5A]">
              Pilih Berkas
            </label>
          </div>
        </form>
      </Modal>
    </div>
  )
}
