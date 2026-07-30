import { api } from '../api'
const data = (response) => response.data
export const attendanceReportService = {
  getAttendanceReport: async (params = {}) => data(await api.get('/attendance/reports/summary', { params })),
  exportAttendanceReport: async (params = {}) => api.get('/attendance/reports/export', { params, responseType: 'blob' }),
}
