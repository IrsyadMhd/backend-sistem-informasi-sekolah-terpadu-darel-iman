import React, { useState, useMemo } from 'react'
import { useNavigate } from 'react-router-dom'
import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip as RechartsTooltip,
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
  Building2,
  GraduationCap,
  UserCheck,
  DollarSign,
  Award,
  BookOpen,
  Calendar,
  Clock,
  CheckCircle2,
  AlertTriangle,
  FileText,
  Users,
  ShieldCheck,
  Sparkles,
  HeartHandshake,
  ArrowUpRight,
  QrCode,
  Plus,
  TrendingUp,
  Layers,
  ChevronRight,
  BellRing,
  AlertCircle,
} from 'lucide-react'
import StatCard from '../components/StatCard'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '../components/ui/card'
import { Button } from '../components/ui/button'
import { Badge } from '../components/ui/badge'

const COLORS = ['#0E5C44', '#38A169', '#D97706', '#0284C7', '#8B5CF6']

export default function MultiRoleDashboardPage() {
  const navigate = useNavigate()
  const [activeRole, setActiveRole] = useState('Kepsek') // Default to Kepsek Managerial Dashboard
  const [selectedUnit, setSelectedUnit] = useState('SDIT')

  const roles = [
    { id: 'Kepsek', label: 'Kepala Sekolah (Unit)', icon: GraduationCap, badge: 'Manajerial Unit' },
    { id: 'Yayasan', label: 'Ketua Yayasan (Executive)', icon: Building2, badge: 'Eksekutif All Units' },
    { id: 'Guru', label: 'Guru & Wali Kelas', icon: Users, badge: 'Pengajar' },
    { id: 'OrangTua', label: 'Orang Tua / Wali', icon: HeartHandshake, badge: 'Wali Murid' },
    { id: 'Siswa', label: 'Siswa / Santri', icon: Sparkles, badge: 'Peserta Didik' },
  ]

  // Mock data for Kepala Sekolah Unit SDIT
  const kepsekData = {
    unitName: 'SDIT Dar El-Iman',
    totalGuru: 42,
    totalPegawai: 14,
    totalSiswa: 580,
    totalKelas: 18,
    totalRombel: 20,
    presensiHarianSiswa: '98.2%',
    rataRataNilai: '88.5',
    capaianTahfizh: '92.4%',
    totalPrestasi: '24 Piala',
    trendPresensi: [
      { hari: 'Senin', Hadir: 98.2, Target: 98.0 },
      { hari: 'Selasa', Hadir: 97.8, Target: 98.0 },
      { hari: 'Rabu', Hadir: 98.5, Target: 98.0 },
      { hari: 'Kamis', Hadir: 99.1, Target: 98.0 },
      { hari: 'Jumat', Hadir: 96.9, Target: 98.0 },
    ],
    guruBelumInputNilai: [
      { id: 1, nama: 'Ustadz Ahmad Ridwan, S.Pd', mapel: 'Matematika 5B', deadline: 'Besok, 16:00' },
      { id: 2, nama: 'Ustadzah Siti Aminah, M.Pd', mapel: 'BAHASA INDONESIA 6A', deadline: 'Hari Ini, 23:59' },
      { id: 3, nama: 'Ustadz Hendra Kurniawan', mapel: 'IPAS 4C', deadline: '2 Hari lalu' },
    ],
    guruBelumInputTahfizh: [
      { id: 1, nama: 'Ustadz Hamzah Fansuri', kelas: '5 Usamah (Juz 29)', status: 'Belum Setoran' },
      { id: 2, nama: 'Ustadzah Fatimah Azzahra', kelas: '4 Khadijah (Juz 30)', status: '2 Santri Pending' },
    ],
    agendaSekolah: [
      { tgl: '28 Jul', event: 'Munaqasyah Tahfizh Juz 30 SDIT', tempat: 'Masjid SDIT' },
      { tgl: '01 Agu', event: 'Supervisi Pembelajaran Guru Ganjil', tempat: 'Ruang Rapat Unit' },
      { tgl: '05 Agu', event: 'Pertemuan Rutin Komite Sekolah & Ortu', tempat: 'Aula Utama' },
    ],
  }

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

  return (
    <div className="space-y-6">
      {/* ROLE SWITCHER HEADER */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 p-4 rounded-2xl bg-white border border-slate-200/80 shadow-xs dark:bg-slate-900 dark:border-slate-800">
        <div className="flex items-center gap-3">
          <div className="h-10 w-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-bold shadow-xs">
            <GraduationCap className="h-6 w-6 stroke-[1.8]" />
          </div>
          <div>
            <h1 className="text-base font-bold text-slate-900 dark:text-white">Role Dashboard Selector</h1>
            <p className="text-xs text-slate-500">Pilih tampilan sesuai wewenang manajerial / operasional</p>
          </div>
        </div>

        {/* Role pills */}
        <div className="flex flex-wrap items-center gap-2">
          {roles.map((r) => {
            const Icon = r.icon
            const isActive = activeRole === r.id
            return (
              <button
                key={r.id}
                type="button"
                onClick={() => setActiveRole(r.id)}
                className={`flex items-center gap-2 px-3 py-1.5 rounded-xl text-xs font-semibold transition ${
                  isActive
                    ? 'bg-emerald-600 text-white shadow-xs'
                    : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'
                }`}
              >
                <Icon className="h-4 w-4" />
                <span>{r.label}</span>
              </button>
            )
          })}
        </div>
      </div>

      {/* VIEW KEPALA SEKOLAH (MANAGERIAL SCOPE UNIT) */}
      {activeRole === 'Kepsek' && (
        <div className="space-y-6 animate-in fade-in duration-300">
          {/* Unit Scope Bar */}
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-4 rounded-2xl bg-emerald-900 text-white shadow-md">
            <div>
              <span className="text-[10px] font-bold uppercase tracking-wider text-emerald-300">Dashboard Manajerial</span>
              <h2 className="text-xl font-black">{kepsekData.unitName}</h2>
              <p className="text-xs text-emerald-100/90 font-medium">Fokus Pengawasan & Evaluasi Operasional Unit Sekolah</p>
            </div>
            <div className="flex items-center gap-2">
              <span className="text-xs font-semibold">Pilih Unit:</span>
              <select
                value={selectedUnit}
                onChange={(e) => setSelectedUnit(e.target.value)}
                className="rounded-xl bg-emerald-950 text-white px-3 py-1.5 text-xs font-bold border border-emerald-700 focus:outline-none"
              >
                <option value="SDIT">SDIT Dar El-Iman</option>
                <option value="SMPIT">SMPIT Dar El-Iman</option>
                <option value="SMAIT">SMAIT Dar El-Iman</option>
                <option value="TKIT">TKIT Dar El-Iman</option>
                <option value="PONPES">Pondok Pesantren</option>
              </select>
            </div>
          </div>

          {/* 9 KPI Stat Cards Grid (Unit Scope) */}
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <StatCard
              title="Guru"
              value={`${kepsekData.totalGuru} Guru`}
              subtitle="41 Hadir Hari Ini"
              trend="97.6% Hadir"
              trendType="up"
              onClickTo="/dashboard/employees?role=guru"
            />

            <StatCard
              title="Pegawai"
              value={`${kepsekData.totalPegawai} Staf`}
              subtitle="Operasional Unit"
              trend="100% Hadir"
              trendType="up"
              onClickTo="/dashboard/employees"
            />

            <StatCard
              title="Siswa"
              value={`${kepsekData.totalSiswa} Siswa`}
              subtitle="Terdaftar di Unit SDIT"
              trend="+3.2%"
              trendType="up"
              onClickTo="/dashboard/students"
            />

            <StatCard
              title="Kelas"
              value={`${kepsekData.totalKelas} Ruang`}
              subtitle="Gedung Unit SDIT"
              trend="Kondisi Baik"
              trendType="up"
              onClickTo="/dashboard/students/rombel"
            />

            <StatCard
              title="Rombel"
              value={`${kepsekData.totalRombel} Rombel`}
              subtitle="Rombongan Belajar"
              trend="Terstruktur"
              trendType="up"
              onClickTo="/dashboard/students/rombel"
            />

            <StatCard
              title="Kehadiran Hari Ini"
              value={kepsekData.presensiHarianSiswa}
              subtitle="570 / 580 Siswa Hadir"
              trend="Sangat Baik"
              trendType="up"
              onClickTo="/dashboard/attendance"
            />

            <StatCard
              title="Kurikulum & Mapel"
              value={kepsekData.rataRataNilai}
              subtitle="Rata-rata Rapor Kelas"
              trend="+1.5 Poin"
              trendType="up"
              onClickTo="/dashboard/laporan-akademik"
            />

            <StatCard
              title="Target Tahfizh Sekolah"
              value={kepsekData.capaianTahfizh}
              subtitle="Target Juz 30 & 29"
              trend="92.4% Tuntas"
              trendType="up"
              onClickTo="/dashboard/tahfizh"
            />
          </div>

          {/* CRITICAL ALERTS: GURU BELUM INPUT NILAI & TAHFIZH */}
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {/* Warning Widget: Guru Belum Input Nilai */}
            <Card className="border-rose-200 bg-rose-50/30 dark:border-rose-900/60 dark:bg-rose-950/20">
              <CardHeader className="flex flex-row items-center justify-between pb-3">
                <div>
                  <CardTitle className="text-base font-bold text-rose-800 dark:text-rose-400 flex items-center gap-2">
                    <AlertTriangle className="h-5 w-5" />
                    Guru Belum Input Nilai Rapor ({kepsekData.guruBelumInputNilai.length})
                  </CardTitle>
                  <CardDescription className="text-rose-600 dark:text-rose-300">
                    Daftar pengajar yang belum melengkapi nilai akhir semester
                  </CardDescription>
                </div>
                <Badge variant="danger">Perlu Tindakan</Badge>
              </CardHeader>
              <CardContent className="space-y-3">
                {kepsekData.guruBelumInputNilai.map((g) => (
                  <div
                    key={g.id}
                    className="flex items-center justify-between p-3 rounded-xl bg-white border border-rose-200/80 shadow-xs dark:bg-slate-900 dark:border-rose-950"
                  >
                    <div>
                      <h5 className="text-xs font-bold text-slate-900 dark:text-white">{g.nama}</h5>
                      <p className="text-[11px] text-slate-500 dark:text-slate-400">{g.mapel}</p>
                    </div>
                    <div className="text-right">
                      <span className="text-[10px] font-bold text-rose-600 dark:text-rose-400">{g.deadline}</span>
                      <button
                        type="button"
                        onClick={() => navigate('/dashboard/employees')}
                        className="block mt-1 text-[10px] font-semibold text-emerald-600 hover:underline dark:text-emerald-400"
                      >
                        Ingatkan Guru
                      </button>
                    </div>
                  </div>
                ))}
              </CardContent>
            </Card>

            {/* Warning Widget: Guru Belum Input Tahfizh */}
            <Card className="border-amber-200 bg-amber-50/30 dark:border-amber-900/60 dark:bg-amber-950/20">
              <CardHeader className="flex flex-row items-center justify-between pb-3">
                <div>
                  <CardTitle className="text-base font-bold text-amber-800 dark:text-amber-400 flex items-center gap-2">
                    <AlertCircle className="h-5 w-5" />
                    Guru/Musyrif Belum Input Setoran Tahfizh ({kepsekData.guruBelumInputTahfizh.length})
                  </CardTitle>
                  <CardDescription className="text-amber-700 dark:text-amber-300">
                    Daftar penanggung jawab kelompok mutabaah hafalan
                  </CardDescription>
                </div>
                <Badge variant="warning">Perhatian</Badge>
              </CardHeader>
              <CardContent className="space-y-3">
                {kepsekData.guruBelumInputTahfizh.map((g) => (
                  <div
                    key={g.id}
                    className="flex items-center justify-between p-3 rounded-xl bg-white border border-amber-200/80 shadow-xs dark:bg-slate-900 dark:border-amber-950"
                  >
                    <div>
                      <h5 className="text-xs font-bold text-slate-900 dark:text-white">{g.nama}</h5>
                      <p className="text-[11px] text-slate-500 dark:text-slate-400">{g.kelas}</p>
                    </div>
                    <div className="text-right">
                      <span className="text-[10px] font-bold text-amber-600 dark:text-amber-400">{g.status}</span>
                      <button
                        type="button"
                        onClick={() => navigate('/dashboard/tahfizh')}
                        className="block mt-1 text-[10px] font-semibold text-emerald-600 hover:underline dark:text-emerald-400"
                      >
                        Lihat Mutabaah
                      </button>
                    </div>
                  </div>
                ))}
              </CardContent>
            </Card>
          </div>

          {/* AGENDA SEKOLAH & TREND PRESENSI */}
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <Card className="glass-card lg:col-span-2">
              <CardHeader>
                <CardTitle className="text-base flex items-center gap-2">
                  <TrendingUp className="h-4 w-4 text-emerald-600" />
                  Tren Kehadiran Harian Siswa SDIT (%)
                </CardTitle>
                <CardDescription>Target Kehadiran 98.0% per Hari</CardDescription>
              </CardHeader>
              <CardContent>
                <div className="h-64">
                  <ResponsiveContainer width="100%" height="100%">
                    <AreaChart data={kepsekData.trendPresensi}>
                      <CartesianGrid strokeDasharray="3 3" opacity={0.3} />
                      <XAxis dataKey="hari" stroke="#94a3b8" fontSize={11} />
                      <YAxis stroke="#94a3b8" domain={[95, 100]} fontSize={11} />
                      <RechartsTooltip contentStyle={{ borderRadius: '12px', border: '1px solid #E2E8F0' }} />
                      <Area type="monotone" name="Presensi Siswa (%)" dataKey="Hadir" stroke="#0E5C44" fill="#0E5C44" fillOpacity={0.2} />
                    </AreaChart>
                  </ResponsiveContainer>
                </div>
              </CardContent>
            </Card>

            <Card className="glass-card">
              <CardHeader>
                <CardTitle className="text-base flex items-center gap-2">
                  <Calendar className="h-4 w-4 text-emerald-600" />
                  Agenda Unit SDIT
                </CardTitle>
                <CardDescription>Kegiatan mendatang minggu ini</CardDescription>
              </CardHeader>
              <CardContent className="space-y-3">
                {kepsekData.agendaSekolah.map((ag, idx) => (
                  <div key={idx} className="flex items-center gap-3 p-3 rounded-xl border border-slate-100 bg-slate-50/50 dark:border-slate-800 dark:bg-slate-800/40">
                    <div className="h-10 w-10 shrink-0 rounded-xl bg-emerald-100 text-emerald-800 font-bold flex flex-col items-center justify-center text-xs dark:bg-emerald-950 dark:text-emerald-300">
                      <span>{ag.tgl.split(' ')[0]}</span>
                      <span className="text-[9px] uppercase">{ag.tgl.split(' ')[1]}</span>
                    </div>
                    <div className="min-w-0 flex-1">
                      <h5 className="text-xs font-bold text-slate-900 dark:text-white truncate">{ag.event}</h5>
                      <p className="text-[10px] text-slate-500 dark:text-slate-400">{ag.tempat}</p>
                    </div>
                  </div>
                ))}
              </CardContent>
            </Card>
          </div>
        </div>
      )}

      {/* VIEW YAYASAN EXECUTIVE BOARD */}
      {activeRole === 'Yayasan' && (
        <div className="space-y-6 animate-in fade-in duration-300">
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <Card>
              <CardContent className="p-5 flex items-center justify-between">
                <div>
                  <p className="text-xs text-slate-500 dark:text-slate-400">Pemasukan Infaq & SPP</p>
                  <h3 className="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400 mt-1">{yayasanData.totalRevenue}</h3>
                </div>
                <div className="p-3 bg-emerald-50 text-emerald-600 rounded-xl dark:bg-emerald-950 dark:text-emerald-400">
                  <DollarSign className="h-6 w-6" />
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardContent className="p-5 flex items-center justify-between">
                <div>
                  <p className="text-xs text-slate-500 dark:text-slate-400">Total Peserta Didik</p>
                  <h3 className="text-2xl font-extrabold text-slate-900 dark:text-white mt-1">{yayasanData.totalSiswa} Siswa</h3>
                </div>
                <div className="p-3 bg-emerald-50 text-emerald-600 rounded-xl dark:bg-emerald-950 dark:text-emerald-400">
                  <Users className="h-6 w-6" />
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardContent className="p-5 flex items-center justify-between">
                <div>
                  <p className="text-xs text-slate-500 dark:text-slate-400">Total Guru & SDM</p>
                  <h3 className="text-2xl font-extrabold text-slate-900 dark:text-white mt-1">{yayasanData.totalPegawai} Orang</h3>
                </div>
                <div className="p-3 bg-emerald-50 text-emerald-600 rounded-xl dark:bg-emerald-950 dark:text-emerald-400">
                  <UserCheck className="h-6 w-6" />
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardContent className="p-5 flex items-center justify-between">
                <div>
                  <p className="text-xs text-slate-500 dark:text-slate-400">Skor Kepatuhan Audit</p>
                  <h3 className="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400 mt-1">{yayasanData.auditScore}</h3>
                </div>
                <div className="p-3 bg-emerald-50 text-emerald-600 rounded-xl dark:bg-emerald-950 dark:text-emerald-400">
                  <ShieldCheck className="h-6 w-6" />
                </div>
              </CardContent>
            </Card>
          </div>
        </div>
      )}

      {/* FALLBACK FOR OTHER ROLES */}
      {activeRole !== 'Kepsek' && activeRole !== 'Yayasan' && (
        <Card className="p-8 text-center glass-card">
          <Sparkles className="h-10 w-10 text-emerald-600 mx-auto mb-3" />
          <h3 className="text-lg font-bold text-slate-900 dark:text-white">Tampilan Dashboard Role: {activeRole}</h3>
          <p className="text-xs text-slate-500 max-w-md mx-auto mt-1">
            Modul per-role telah terkonfigurasi dengan scope hak akses sesuai standar SIMSIT.
          </p>
          <Button variant="primary" size="sm" onClick={() => setActiveRole('Kepsek')} className="mt-4">
            Kembali ke Kepala Sekolah
          </Button>
        </Card>
      )}
    </div>
  )
}
