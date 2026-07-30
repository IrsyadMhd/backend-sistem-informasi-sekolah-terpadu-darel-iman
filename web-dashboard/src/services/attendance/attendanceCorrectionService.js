import { api } from '../api'
const data = (response) => response.data
export const attendanceCorrectionService = {
  getAttendanceCorrections: async (params = {}) => data(await api.get('/attendance-corrections', { params })),
  getAttendanceCorrection: async (id) => data(await api.get(`/attendance-corrections/${id}`)),
  createAttendanceCorrection: async (payload) => data(await api.post('/attendance-corrections', payload)),
  approveAttendanceCorrection: async (id, payload = {}) => data(await api.post(`/attendance-corrections/${id}/approve`, payload)),
  rejectAttendanceCorrection: async (id, payload = {}) => data(await api.post(`/attendance-corrections/${id}/reject`, payload)),
  cancelAttendanceCorrection: async (id) => data(await api.post(`/attendance-corrections/${id}/cancel`)),
}
