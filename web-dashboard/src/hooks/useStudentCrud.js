import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { studentCrudService } from '../services/studentCrudService'

export const STUDENTS_QUERY_KEY = ['students']

/**
 * Hook Query untuk mengambil daftar siswa (FETCH)
 */
export function useStudents(params = {}) {
  return useQuery({
    queryKey: [...STUDENTS_QUERY_KEY, params],
    queryFn: () => studentCrudService.getStudents(params),
    keepPreviousData: true,
    staleTime: 1000 * 60 * 5, // 5 menit
  })
}

/**
 * Hook Query untuk detail siswa berdasarkan ID
 */
export function useStudent(id) {
  return useQuery({
    queryKey: [...STUDENTS_QUERY_KEY, 'detail', id],
    queryFn: () => studentCrudService.getStudentById(id),
    enabled: Boolean(id),
  })
}

/**
 * Hook Mutation untuk Tambah Data Siswa (CREATE)
 */
export function useCreateStudent() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (newStudent) => studentCrudService.createStudent(newStudent),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: STUDENTS_QUERY_KEY })
    },
  })
}

/**
 * Hook Mutation untuk Update Data Siswa (UPDATE)
 */
export function useUpdateStudent() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ id, data }) => studentCrudService.updateStudent({ id, data }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: STUDENTS_QUERY_KEY })
    },
  })
}

/**
 * Hook Mutation untuk Delete Data Siswa (DELETE)
 */
export function useDeleteStudent() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id) => studentCrudService.deleteStudent(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: STUDENTS_QUERY_KEY })
    },
  })
}
