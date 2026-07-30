import { api } from './api'

const unwrap = (response) => response?.data?.data ?? response?.data ?? {}

export const reportService = {
  attendance: async (params = {}) => unwrap(await api.get('/attendance/reports/summary', { params })),
  employees: async (params = {}) => unwrap(await api.get('/employees', { params: { ...params, per_page: 100 } })),
  employeeStats: async () => unwrap(await api.get('/employees/dashboard')),
  grades: async (params = {}) => unwrap(await api.get('/grades', { params: { ...params, per_page: 100 } })),
  materialStats: async () => unwrap(await api.get('/lms/materi/stats')),
  assignmentStats: async () => unwrap(await api.get('/lms/penugasan/stats')),
  submissionStats: async () => unwrap(await api.get('/lms/pengumpulan-tugas/stats')),
  reportCardStats: async (params = {}) => unwrap(await api.get('/lms/rapor/stats', { params })),
  submissions: async (params = {}) => unwrap(await api.get('/lms/pengumpulan-tugas', { params: { ...params, per_page: 100 } })),
}

