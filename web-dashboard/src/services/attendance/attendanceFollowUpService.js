import { api } from '../api'
const data = (response) => response.data
export const attendanceFollowUpService = {
  getAttendanceFollowUps: async (params = {}) => data(await api.get('/attendance-follow-ups', { params })),
  getAttendanceFollowUp: async (id) => data(await api.get(`/attendance-follow-ups/${id}`)),
  createAttendanceFollowUp: async (payload) => data(await api.post('/attendance-follow-ups', payload)),
  updateAttendanceFollowUp: async (id, payload) => data(await api.put(`/attendance-follow-ups/${id}`, payload)),
  completeAttendanceFollowUp: async (id) => data(await api.post(`/attendance-follow-ups/${id}/complete`)),
  closeAttendanceFollowUp: async (id) => data(await api.post(`/attendance-follow-ups/${id}/close`)),
}
