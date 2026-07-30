import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import Swal from 'sweetalert2'
import { dashboardPemantauanService } from '../services/dashboardPemantauanService'

export function useRingkasanDashboardPemantauan() {
  return useQuery({
    queryKey: ['dashboard-pemantauan', 'ringkasan'],
    queryFn: dashboardPemantauanService.getRingkasan,
  })
}

export function useDaftarPemantauanDivisi(params) {
  return useQuery({
    queryKey: ['dashboard-pemantauan', 'pemantauan-divisi', params],
    queryFn: () => dashboardPemantauanService.getDaftarPemantauanDivisi(params),
  })
}

export function useAksiPemantauanDivisi() {
  const queryClient = useQueryClient()

  const refresh = async () => {
    await queryClient.invalidateQueries({ queryKey: ['dashboard-pemantauan'] })
  }

  const tambah = useMutation({
    mutationFn: dashboardPemantauanService.tambahPemantauanDivisi,
    onSuccess: async (result) => {
      await refresh()
      await Swal.fire('Berhasil', result?.message || 'Data berhasil ditambahkan', 'success')
    },
    onError: async (error) => {
      await Swal.fire('Gagal', error?.response?.data?.message || 'Terjadi kesalahan saat menyimpan data.', 'error')
    },
  })

  const ubah = useMutation({
    mutationFn: dashboardPemantauanService.ubahPemantauanDivisi,
    onSuccess: async (result) => {
      await refresh()
      await Swal.fire('Berhasil', result?.message || 'Data berhasil diperbarui', 'success')
    },
    onError: async (error) => {
      await Swal.fire('Gagal', error?.response?.data?.message || 'Terjadi kesalahan saat memperbarui data.', 'error')
    },
  })

  const hapus = useMutation({
    mutationFn: dashboardPemantauanService.hapusPemantauanDivisi,
    onSuccess: async (result) => {
      await refresh()
      await Swal.fire('Berhasil', result?.message || 'Data berhasil dihapus', 'success')
    },
    onError: async (error) => {
      await Swal.fire('Gagal', error?.response?.data?.message || 'Terjadi kesalahan saat menghapus data.', 'error')
    },
  })

  return { tambah, ubah, hapus }
}

export function useDaftarLaporanBulanan(params) {
  return useQuery({
    queryKey: ['dashboard-pemantauan', 'laporan-bulanan', params],
    queryFn: () => dashboardPemantauanService.getDaftarLaporanBulanan(params),
  })
}

export function useDaftarRekapPrestasiSiswa(params) {
  return useQuery({
    queryKey: ['dashboard-pemantauan', 'rekap-prestasi-siswa', params],
    queryFn: () => dashboardPemantauanService.getDaftarRekapPrestasiSiswa(params),
  })
}

export function useDaftarPengumumanSekolah(params) {
  return useQuery({
    queryKey: ['dashboard-pemantauan', 'pengumuman-sekolah', params],
    queryFn: () => dashboardPemantauanService.getDaftarPengumumanSekolah(params),
  })
}

export function useDaftarIndikatorKinerjaUtama(params) {
  return useQuery({
    queryKey: ['dashboard-pemantauan', 'indikator-kinerja-utama', params],
    queryFn: () => dashboardPemantauanService.getDaftarIndikatorKinerjaUtama(params),
  })
}

function useAksiGeneric(mutationFn, successFallback, errorFallback, queryClient) {
  return useMutation({
    mutationFn,
    onSuccess: async (result) => {
      await queryClient.invalidateQueries({ queryKey: ['dashboard-pemantauan'] })
      await Swal.fire('Berhasil', result?.message || successFallback, 'success')
    },
    onError: async (error) => {
      await Swal.fire('Gagal', error?.response?.data?.message || errorFallback, 'error')
    },
  })
}

export function useAksiLaporanBulanan() {
  const queryClient = useQueryClient()

  return {
    tambah: useAksiGeneric(
      dashboardPemantauanService.tambahLaporanBulanan,
      'Laporan bulanan berhasil ditambahkan',
      'Terjadi kesalahan saat menambah laporan bulanan',
      queryClient
    ),
    ubah: useAksiGeneric(
      dashboardPemantauanService.ubahLaporanBulanan,
      'Laporan bulanan berhasil diperbarui',
      'Terjadi kesalahan saat memperbarui laporan bulanan',
      queryClient
    ),
    hapus: useAksiGeneric(
      dashboardPemantauanService.hapusLaporanBulanan,
      'Laporan bulanan berhasil dihapus',
      'Terjadi kesalahan saat menghapus laporan bulanan',
      queryClient
    ),
  }
}

export function useAksiRekapPrestasiSiswa() {
  const queryClient = useQueryClient()

  return {
    tambah: useAksiGeneric(
      dashboardPemantauanService.tambahRekapPrestasiSiswa,
      'Rekap prestasi siswa berhasil ditambahkan',
      'Terjadi kesalahan saat menambah rekap prestasi',
      queryClient
    ),
    ubah: useAksiGeneric(
      dashboardPemantauanService.ubahRekapPrestasiSiswa,
      'Rekap prestasi siswa berhasil diperbarui',
      'Terjadi kesalahan saat memperbarui rekap prestasi',
      queryClient
    ),
    hapus: useAksiGeneric(
      dashboardPemantauanService.hapusRekapPrestasiSiswa,
      'Rekap prestasi siswa berhasil dihapus',
      'Terjadi kesalahan saat menghapus rekap prestasi',
      queryClient
    ),
  }
}

export function useAksiPengumumanSekolah() {
  const queryClient = useQueryClient()

  return {
    tambah: useAksiGeneric(
      dashboardPemantauanService.tambahPengumumanSekolah,
      'Pengumuman sekolah berhasil ditambahkan',
      'Terjadi kesalahan saat menambah pengumuman',
      queryClient
    ),
    ubah: useAksiGeneric(
      dashboardPemantauanService.ubahPengumumanSekolah,
      'Pengumuman sekolah berhasil diperbarui',
      'Terjadi kesalahan saat memperbarui pengumuman',
      queryClient
    ),
    hapus: useAksiGeneric(
      dashboardPemantauanService.hapusPengumumanSekolah,
      'Pengumuman sekolah berhasil dihapus',
      'Terjadi kesalahan saat menghapus pengumuman',
      queryClient
    ),
  }
}

export function useAksiIndikatorKinerjaUtama() {
  const queryClient = useQueryClient()

  return {
    tambah: useAksiGeneric(
      dashboardPemantauanService.tambahIndikatorKinerjaUtama,
      'Indikator kinerja utama berhasil ditambahkan',
      'Terjadi kesalahan saat menambah indikator',
      queryClient
    ),
    ubah: useAksiGeneric(
      dashboardPemantauanService.ubahIndikatorKinerjaUtama,
      'Indikator kinerja utama berhasil diperbarui',
      'Terjadi kesalahan saat memperbarui indikator',
      queryClient
    ),
    hapus: useAksiGeneric(
      dashboardPemantauanService.hapusIndikatorKinerjaUtama,
      'Indikator kinerja utama berhasil dihapus',
      'Terjadi kesalahan saat menghapus indikator',
      queryClient
    ),
  }
}
