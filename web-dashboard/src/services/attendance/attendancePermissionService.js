import { api } from '../api'
const data = (response) => response.data
export const attendancePermissionService = {
  getAttendancePermissions: async (params = {}) => data(await api.get('/attendance-permissions', { params })),
  getAttendancePermission: async (id) => data(await api.get(`/attendance-permissions/${id}`)),
  createAttendancePermission: async (payload) => data(await api.post('/attendance-permissions', payload)),
  updateAttendancePermission: async (id, payload) => data(await api.put(`/attendance-permissions/${id}`, payload)),
  submitAttendancePermission: async (id) => data(await api.post(`/attendance-permissions/${id}/submit`)),
  approveAttendancePermission: async (id, payload = {}) => data(await api.post(`/attendance-permissions/${id}/approve`, payload)),
  rejectAttendancePermission: async (id, payload = {}) => data(await api.post(`/attendance-permissions/${id}/reject`, payload)),
  requestAttendancePermissionRevision: async (id, payload = {}) => data(await api.post(`/attendance-permissions/${id}/revision`, payload)),
  cancelAttendancePermission: async (id) => data(await api.post(`/attendance-permissions/${id}/cancel`)),
}
