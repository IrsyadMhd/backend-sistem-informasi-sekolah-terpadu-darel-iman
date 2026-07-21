import { useQuery } from '@tanstack/react-query'
import { api } from '../services/api'

async function getClasses(params = { per_page: 100 }) {
  const { data } = await api.get('/classes', { params })
  return data
}

async function getTeachers(params = { per_page: 100 }) {
  const { data } = await api.get('/teachers', { params })
  return data
}

export function useDaftarKelas(params = { per_page: 100 }) {
  return useQuery({
    queryKey: ['reference-data', 'classes', params],
    queryFn: () => getClasses(params),
  })
}

export function useDaftarGuru(params = { per_page: 100 }) {
  return useQuery({
    queryKey: ['reference-data', 'teachers', params],
    queryFn: () => getTeachers(params),
  })
}
