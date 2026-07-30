import { api } from '../api'
const data = (response) => response.data
export const attendanceDashboardService = {
  getTeacherDashboard: async () => data(await api.get('/attendance/teacher/dashboard')),
  getHomeroomDashboard: async () => data(await api.get('/attendance/homeroom/dashboard')),
  getStudentAttendance: async (params = {}) => data(await api.get('/attendance/student/me', { params })),
  getTeacherSchedules: async (date) => data(await api.get('/attendance/teacher/schedules', { params: { date } })),
  getScheduleStudents: async (id, date) => data(await api.get(`/attendance/schedules/${id}/students`, { params: { date } })),
}
