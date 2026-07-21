import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import Swal from 'sweetalert2'
import { studentService } from '../services/studentService'

export function useDaftarSiswa(params) {
  return useQuery({
    queryKey: ['students', params],
    queryFn: () => studentService.getDaftar(params),
  })
}

export function useAksiSiswa() {
  const queryClient = useQueryClient()

  const refresh = async () => {
    await queryClient.invalidateQueries({ queryKey: ['students'] })
  }

  const tambah = useMutation({
    mutationFn: studentService.tambah,
    onSuccess: async (result) => {
      await refresh()
      await Swal.fire('Berhasil', result?.message || 'Data siswa berhasil ditambahkan.', 'success')
    },
    onError: async (error) => {
      await Swal.fire('Gagal', error?.response?.data?.message || 'Terjadi kesalahan saat menyimpan data siswa.', 'error')
    },
  })

  const ubah = useMutation({
    mutationFn: studentService.ubah,
    onSuccess: async (result) => {
      await refresh()
      await Swal.fire('Berhasil', result?.message || 'Data siswa berhasil diperbarui.', 'success')
    },
    onError: async (error) => {
      await Swal.fire('Gagal', error?.response?.data?.message || 'Terjadi kesalahan saat memperbarui data siswa.', 'error')
    },
  })

  const hapus = useMutation({
    mutationFn: studentService.hapus,
    onSuccess: async (result) => {
      await refresh()
      await Swal.fire('Berhasil', result?.message || 'Data siswa berhasil dihapus.', 'success')
    },
    onError: async (error) => {
      await Swal.fire('Gagal', error?.response?.data?.message || 'Terjadi kesalahan saat menghapus data siswa.', 'error')
    },
  })

  return { tambah, ubah, hapus }
}
