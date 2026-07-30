import { api } from './api'

export const lmsPresensiService = {
  getDaftar: async (params = {}) => {
    const response = await api.get('/lms/presensi', { params })
    return response.data
  },

  getById: async (id) => {
    const response = await api.get(`/lms/presensi/${id}`)
    return response.data
  },

  create: async (data) => {
    const response = await api.post('/lms/presensi', data)
    return response.data
  },

  createBulk: async (data) => {
    const response = await api.post('/lms/presensi/bulk', data)
    return response.data
  },

  update: async (id, data) => {
    const response = await api.put(`/lms/presensi/${id}`, data)
    return response.data
  },

  delete: async (id) => {
    const response = await api.delete(`/lms/presensi/${id}`)
    return response.data
  },

  restore: async (id) => {
    const response = await api.post(`/lms/presensi/${id}/restore`)
    return response.data
  },

  getStats: async (params = {}) => {
    const response = await api.get('/lms/presensi/stats', { params })
    return response.data
  },

  getOptions: async () => {
    const response = await api.get('/lms/presensi/options')
    return response.data
  },

  getMySchedules: async (date) => {
    const response = await api.get('/lesson-attendance/my-schedules', { params: { date } })
    return response.data
  },

  getActiveSchedules: async () => {
    const response = await api.get('/lesson-attendance/active-schedules')
    return response.data
  },

  getScheduleStudents: async (scheduleId, date, attendanceContext = null) => {
    const response = await api.get(`/lesson-attendance/my-schedules/${scheduleId}/students`, {
      params: { date, attendance_context: attendanceContext || undefined },
    })
    return response.data
  },

  getSessions: async (params = {}) => {
    const response = await api.get('/lesson-attendance/sessions', { params })
    return response.data
  },

  getSession: async (id) => {
    const response = await api.get(`/lesson-attendance/sessions/${id}`)
    return response.data
  },

  saveDraft: async (data) => {
    const response = await api.post('/lesson-attendance/sessions', data)
    return response.data
  },

  finalize: async (sessionId) => {
    const response = await api.post(`/lesson-attendance/sessions/${sessionId}/finalize`)
    return response.data
  },

  unlock: async (sessionId, reason) => {
    const response = await api.post(`/lesson-attendance/sessions/${sessionId}/unlock`, { reason })
    return response.data
  },

  cancelSession: async (sessionId, reason) => {
    const response = await api.post(`/lesson-attendance/sessions/${sessionId}/cancel`, { reason })
    return response.data
  },

  getMyAttendance: async (params = {}) => {
    const response = await api.get('/student-attendance/me', { params })
    return response.data
  },

  getPermissionRequests: async () => {
    const response = await api.get('/student-attendance/permissions')
    return response.data
  },

  submitPermissionRequest: async (data) => {
    const response = await api.post('/student-attendance/permissions', data, {
      headers: data instanceof FormData ? { 'Content-Type': 'multipart/form-data' } : undefined,
    })
    return response.data
  },

  cancelPermissionRequest: async (id) => {
    const response = await api.post(`/student-attendance/permissions/${id}/cancel`)
    return response.data
  },

  updatePermissionRequest: async (id, data) => {
    const response = await api.put(`/student-attendance/permissions/${id}`, data)
    return response.data
  },

  sendPermissionRequest: async (id) => {
    const response = await api.post(`/student-attendance/permissions/${id}/submit`)
    return response.data
  },

  submitCorrection: async (data) => {
    const response = await api.post('/lesson-attendance/corrections', data)
    return response.data
  },

  getCorrections: async (params = {}) => {
    const response = await api.get('/lesson-attendance/corrections', { params })
    return response.data
  },

  reviewCorrection: async (id, data) => {
    const response = await api.post(`/lesson-attendance/corrections/${id}/review`, data)
    return response.data
  },

  cancelCorrection: async (id) => {
    const response = await api.post(`/lesson-attendance/corrections/${id}/cancel`)
    return response.data
  },

  getHomeroomDashboard: async () => {
    const response = await api.get('/homeroom-attendance/dashboard')
    return response.data
  },

  getHomeroomPermissions: async (params = {}) => {
    const response = await api.get('/homeroom-attendance/permissions', { params })
    return response.data
  },

  reviewPermission: async (id, data) => {
    const response = await api.post(`/homeroom-attendance/permissions/${id}/review`, data)
    return response.data
  },

  getFollowUps: async (params = {}) => {
    const response = await api.get('/homeroom-attendance/follow-ups', { params })
    return response.data
  },

  createFollowUp: async (data) => {
    const response = await api.post('/homeroom-attendance/follow-ups', data)
    return response.data
  },

  updateFollowUp: async (id, data) => {
    const response = await api.put(`/homeroom-attendance/follow-ups/${id}`, data)
    return response.data
  },

  completeFollowUp: async (id) => {
    const response = await api.post(`/homeroom-attendance/follow-ups/${id}/complete`)
    return response.data
  },

  closeFollowUp: async (id) => {
    const response = await api.post(`/homeroom-attendance/follow-ups/${id}/close`)
    return response.data
  },

  getReport: async (params = {}) => {
    const response = await api.get('/lesson-attendance/report', { params })
    return response.data
  },

  startCaptureSession: async (id, durationMinutes = 60) => {
    const response = await api.post(`/lesson-attendance/sessions/${id}/start-session`, { duration_minutes: durationMinutes })
    return response.data
  },

  closeCaptureSession: async (id) => {
    const response = await api.post(`/lesson-attendance/sessions/${id}/close-session`)
    return response.data
  },

  manualCheck: async (id, studentId) => {
    const response = await api.post(`/lesson-attendance/sessions/${id}/manual-check`, { student_id: studentId })
    return response.data
  },

  scanAttendance: async (id, method, data) => {
    const response = await api.post(`/lesson-attendance/sessions/${id}/scan/${method}`, data)
    return response.data
  },

  getScanLogs: async (id, params = {}) => {
    const response = await api.get(`/lesson-attendance/sessions/${id}/scan-logs`, { params })
    return response.data
  },
}
