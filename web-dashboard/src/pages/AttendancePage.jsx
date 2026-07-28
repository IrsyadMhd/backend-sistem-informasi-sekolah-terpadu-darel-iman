import React, { useState, useMemo } from 'react'
import Swal from 'sweetalert2'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { attendanceService } from '../services/attendanceService'
import { DataTable } from '../components/common/DataTable'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '../components/ui/card'
import { Button } from '../components/ui/button'
import { Badge } from '../components/ui/badge'
import { Input } from '../components/ui/input'
import { Dialog, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '../components/ui/dialog'
import {
  LuClipboardCheck,
  LuUserCheck,
  LuClock,
  LuCircleAlert,
  LuPlus,
  LuQrCode,
  LuCalendar,
  LuRefreshCw,
  LuPencil,
  LuTrash2,
  LuFileText,
  LuX,
  LuSave,
} from 'react-icons/lu'

export default function AttendancePage() {
  const queryClient = useQueryClient()
  const [search, setSearch] = useState('')
  const [dateFilter, setDateFilter] = useState(new Date().toISOString().split('T')[0])
  const [statusFilter, setStatusFilter] = useState('')
  const [tipeFilter, setTipeFilter] = useState('')

  // Modal State
  const [isModalOpen, setIsModalOpen] = useState(false)
  const [modalForm, setModalForm] = useState({
    tipe_presensi: 'Siswa',
    nama: '',
    status: 'HADIR',
    attendance_method: 'MANUAL',
    keterangan: '',
    location: 'Gedung Utama Sekolah',
  })

  // Queries
  const { data: statsData, isLoading: loadingStats, refetch: refetchStats } = useQuery({
    queryKey: ['attendance-stats', dateFilter],
    queryFn: () => attendanceService.getStats({ date: dateFilter }),
  })

  const { data: reportData, isLoading: loadingReport, isFetching, refetch: refetchReport } = useQuery({
    queryKey: ['attendance-report', dateFilter, statusFilter, tipeFilter, search],
    queryFn: () =>
      attendanceService.report({
        start_date: dateFilter,
        end_date: dateFilter,
        status: statusFilter,
        tipe_presensi: tipeFilter,
        search,
      }),
  })

  // Checkin Mutation
  const checkinMutation = useMutation({
    mutationFn: (payload) => attendanceService.checkin(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['attendance-stats'] })
      queryClient.invalidateQueries({ queryKey: ['attendance-report'] })
      setIsModalOpen(false)
      Swal.fire({
        icon: 'success',
        title: 'Presensi Dicatat!',
        text: 'Data presensi berhasil disimpan ke sistem.',
        timer: 1500,
        showConfirmButton: false,
      })
    },
    onError: (err) => {
      Swal.fire('Gagal Menyimpan', err?.response?.data?.message || 'Terjadi kesalahan sistem.', 'error')
    },
  })

  // Delete Mutation
  const deleteMutation = useMutation({
    mutationFn: (id) => attendanceService.hapus(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['attendance-stats'] })
      queryClient.invalidateQueries({ queryKey: ['attendance-report'] })
      Swal.fire('Terhapus!', 'Data presensi telah dihapus.', 'success')
    },
  })

  const handleCreateCheckin = (e) => {
    e.preventDefault()
    if (!modalForm.nama.trim()) {
      Swal.fire('Form Belum Lengkap', 'Silakan masukkan nama siswa atau pegawai.', 'warning')
      return
    }

    checkinMutation.mutate({
      tipe_presensi: modalForm.tipe_presensi,
      attendance_date: dateFilter,
      status: modalForm.status,
      attendance_method: modalForm.attendance_method,
      location: modalForm.location,
      keterangan: modalForm.keterangan || `${modalForm.tipe_presensi} - ${modalForm.nama}`,
      metadata: { nama: modalForm.nama },
    })
  }

  const handleDelete = (item) => {
    Swal.fire({
      title: 'Hapus Data Presensi?',
      text: `Apakah Anda yakin ingin menghapus presensi tanggal ${item.attendance_date}?`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#ef4444',
      cancelButtonColor: '#475569',
      confirmButtonText: 'Ya, Hapus!',
      cancelButtonText: 'Batal',
      background: '#0f172a',
      color: '#f8fafc',
    }).then((result) => {
      if (result.isConfirmed) {
        deleteMutation.mutate(item.id)
      }
    })
  }

  const stats = statsData?.data || {
    total_presensi: 0,
    hadir: 0,
    terlambat: 0,
    sakit: 0,
    izin: 0,
    alpha: 0,
    persentase_hadir: 100,
  }

  const listPresensi = useMemo(() => {
    return reportData?.records || reportData?.data || []
  }, [reportData])

  // Table Columns Definition
  const columns = useMemo(
    () => [
      {
        accessorKey: 'attendance_date',
        header: 'Tanggal',
        cell: (info) => (
          <span className="font-mono text-xs text-slate-300">
            {info.getValue() || dateFilter}
          </span>
        ),
      },
      {
        id: 'subjek',
        header: 'Nama Siswa / Pegawai',
        cell: ({ row }) => {
          const item = row.original
          const nama = item.student?.nama_lengkap || item.employee?.nama_lengkap || item.metadata?.nama || item.keterangan || 'Presensi Digital'
          const tipe = item.tipe_presensi || (item.student_id ? 'Siswa' : 'Pegawai')
          return (
            <div>
              <div className="font-semibold text-slate-100">{nama}</div>
              <span className="text-[11px] text-slate-400">{tipe}</span>
            </div>
          )
        },
      },
      {
        accessorKey: 'check_in_time',
        header: 'Jam Masuk',
        cell: (info) => {
          const val = info.getValue()
          return (
            <span className="font-mono text-xs text-emerald-400">
              {val ? new Date(val).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) : '-'}
            </span>
          )
        },
      },
      {
        accessorKey: 'check_out_time',
        header: 'Jam Pulang',
        cell: (info) => {
          const val = info.getValue()
          return (
            <span className="font-mono text-xs text-amber-400">
              {val ? new Date(val).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) : '-'}
            </span>
          )
        },
      },
      {
        accessorKey: 'attendance_method',
        header: 'Metode',
        cell: (info) => <Badge variant="outline">{info.getValue() || 'MANUAL'}</Badge>,
      },
      {
        accessorKey: 'status',
        header: 'Status',
        cell: (info) => {
          const val = String(info.getValue() || 'HADIR').toUpperCase()
          const badgeMap = {
            HADIR: 'success',
            PRESENT: 'success',
            TERLAMBAT: 'warning',
            SAKIT: 'info',
            IZIN: 'outline',
            ALPHA: 'danger',
            ABSENT: 'danger',
          }
          return <Badge variant={badgeMap[val] || 'default'}>{val}</Badge>
        },
      },
      {
        id: 'actions',
        header: 'Aksi',
        cell: ({ row }) => (
          <Button
            variant="ghost"
            size="icon"
            onClick={() => handleDelete(row.original)}
            className="h-8 w-8 text-red-400 hover:bg-red-950/50"
            title="Hapus Presensi"
          >
            <LuTrash2 className="h-4 w-4" />
          </Button>
        ),
      },
    ],
    [dateFilter]
  )

  return (
    <div className="p-6 max-w-7xl mx-auto space-y-6">
      {/* Page Title */}
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
            <LuClipboardCheck className="h-7 w-7 text-emerald-400" />
            Modul Presensi Digital Terpadu
          </h1>
          <p className="text-sm text-slate-400">
            Monitoring kehadiran siswa, guru, & pegawai secara realtime.
          </p>
        </div>

        <div className="flex items-center space-x-3">
          <Button
            variant="outline"
            size="sm"
            onClick={() => {
              refetchStats()
              refetchReport()
            }}
            disabled={isFetching}
          >
            <LuRefreshCw className={`h-4 w-4 mr-1.5 ${isFetching ? 'animate-spin' : ''}`} />
            Refresh
          </Button>
          <Button size="sm" onClick={() => setIsModalOpen(true)}>
            <LuPlus className="h-4 w-4 mr-1.5" />
            Input Presensi Manual
          </Button>
        </div>
      </div>

      {/* Filter Bar */}
      <Card>
        <CardContent className="p-4 flex flex-wrap items-center gap-4">
          <div className="flex items-center space-x-2">
            <LuCalendar className="h-4 w-4 text-emerald-400" />
            <label className="text-xs font-medium text-slate-300">Tanggal:</label>
            <Input
              type="date"
              value={dateFilter}
              onChange={(e) => setDateFilter(e.target.value)}
              className="h-9 text-xs w-40 bg-slate-950"
            />
          </div>

          <div className="flex items-center space-x-2">
            <label className="text-xs font-medium text-slate-300">Tipe:</label>
            <select
              value={tipeFilter}
              onChange={(e) => setTipeFilter(e.target.value)}
              className="h-9 rounded-lg border border-slate-700 bg-slate-950 px-3 text-xs text-slate-200"
            >
              <option value="">Semua Tipe</option>
              <option value="Siswa">Siswa</option>
              <option value="Pegawai">Pegawai / Guru</option>
            </select>
          </div>

          <div className="flex items-center space-x-2">
            <label className="text-xs font-medium text-slate-300">Status:</label>
            <select
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
              className="h-9 rounded-lg border border-slate-700 bg-slate-950 px-3 text-xs text-slate-200"
            >
              <option value="">Semua Status</option>
              <option value="HADIR">Hadir</option>
              <option value="TERLAMBAT">Terlambat</option>
              <option value="SAKIT">Sakit</option>
              <option value="IZIN">Izin</option>
              <option value="ALPHA">Alpha</option>
            </select>
          </div>
        </CardContent>
      </Card>

      {/* Stat Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <Card>
          <CardContent className="p-4 flex items-center space-x-4">
            <div className="p-3 bg-emerald-950/60 text-emerald-400 border border-emerald-800/60 rounded-xl">
              <LuUserCheck className="h-6 w-6" />
            </div>
            <div>
              <p className="text-xs font-medium text-slate-400">Total Hadir</p>
              <h3 className="text-xl font-bold text-white">{stats.hadir}</h3>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-4 flex items-center space-x-4">
            <div className="p-3 bg-amber-950/60 text-amber-400 border border-amber-800/60 rounded-xl">
              <LuClock className="h-6 w-6" />
            </div>
            <div>
              <p className="text-xs font-medium text-slate-400">Terlambat</p>
              <h3 className="text-xl font-bold text-white">{stats.terlambat}</h3>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-4 flex items-center space-x-4">
            <div className="p-3 bg-blue-950/60 text-blue-400 border border-blue-800/60 rounded-xl">
              <LuFileText className="h-6 w-6" />
            </div>
            <div>
              <p className="text-xs font-medium text-slate-400">Izin / Sakit</p>
              <h3 className="text-xl font-bold text-white">{stats.izin + stats.sakit}</h3>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-4 flex items-center space-x-4">
            <div className="p-3 bg-red-950/60 text-red-400 border border-red-800/60 rounded-xl">
              <LuCircleAlert className="h-6 w-6" />
            </div>
            <div>
              <p className="text-xs font-medium text-slate-400">Persentase Kehadiran</p>
              <h3 className="text-xl font-bold text-white">{stats.persentase_hadir}%</h3>
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Main Data Table */}
      <Card>
        <CardHeader>
          <CardTitle>Log Data Presensi ({dateFilter})</CardTitle>
          <CardDescription>Daftar presensi terdaftar secara otomatis via QR Scanner maupun manual.</CardDescription>
        </CardHeader>
        <CardContent>
          <DataTable
            columns={columns}
            data={listPresensi}
            isLoading={loadingReport}
            searchPlaceholder="Cari berdasarkan nama siswa atau keterangan..."
            searchValue={search}
            onSearchChange={setSearch}
          />
        </CardContent>
      </Card>

      {/* Input Modal */}
      <Dialog isOpen={isModalOpen} onClose={() => setIsModalOpen(false)}>
        <DialogHeader>
          <DialogTitle>Input Presensi Manual</DialogTitle>
          <DialogDescription>Catat kehadiran siswa atau pegawai secara manual.</DialogDescription>
        </DialogHeader>

        <form onSubmit={handleCreateCheckin} className="space-y-4 my-2">
          <div>
            <label className="block text-xs font-semibold text-slate-300 mb-1">Tipe Presensi</label>
            <select
              value={modalForm.tipe_presensi}
              onChange={(e) => setModalForm((p) => ({ ...p, tipe_presensi: e.target.value }))}
              className="w-full h-10 rounded-lg border border-slate-700 bg-slate-900 px-3 text-sm text-slate-100"
            >
              <option value="Siswa">Siswa</option>
              <option value="Pegawai">Pegawai / Guru</option>
            </select>
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-300 mb-1">Nama Lengkap *</label>
            <Input
              value={modalForm.nama}
              onChange={(e) => setModalForm((p) => ({ ...p, nama: e.target.value }))}
              placeholder="Masukkan nama"
              required
            />
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-300 mb-1">Status Kehadiran</label>
            <select
              value={modalForm.status}
              onChange={(e) => setModalForm((p) => ({ ...p, status: e.target.value }))}
              className="w-full h-10 rounded-lg border border-slate-700 bg-slate-900 px-3 text-sm text-slate-100"
            >
              <option value="HADIR">Hadir Tepat Waktu</option>
              <option value="TERLAMBAT">Hadir Terlambat</option>
              <option value="SAKIT">Sakit</option>
              <option value="IZIN">Izin</option>
              <option value="ALPHA">Alpha</option>
            </select>
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-300 mb-1">Metode Presensi</label>
            <select
              value={modalForm.attendance_method}
              onChange={(e) => setModalForm((p) => ({ ...p, attendance_method: e.target.value }))}
              className="w-full h-10 rounded-lg border border-slate-700 bg-slate-900 px-3 text-sm text-slate-100"
            >
              <option value="MANUAL">Manual Input Operator</option>
              <option value="QRCODE">QR Code Gate</option>
              <option value="GEOLOCATION">GPS Geolocation</option>
            </select>
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-300 mb-1">Keterangan / Catatan</label>
            <Input
              value={modalForm.keterangan}
              onChange={(e) => setModalForm((p) => ({ ...p, keterangan: e.target.value }))}
              placeholder="Contoh: Izin acara keluarga"
            />
          </div>

          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setIsModalOpen(false)}>
              <LuX className="h-4 w-4 mr-1.5" /> Batal
            </Button>
            <Button type="submit" disabled={checkinMutation.isPending}>
              <LuSave className="h-4 w-4 mr-1.5" /> Simpan Presensi
            </Button>
          </DialogFooter>
        </form>
      </Dialog>
    </div>
  )
}
