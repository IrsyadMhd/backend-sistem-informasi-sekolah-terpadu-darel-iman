import { api } from '../api'
const data = (response) => response.data
export const lessonAttendanceService = {
  getLessonAttendances: async (params = {}) => data(await api.get('/lesson-attendances', { params })),
  getLessonAttendance: async (id) => data(await api.get(`/lesson-attendances/${id}`)),
  createLessonAttendance: async (payload) => data(await api.post('/lesson-attendances', payload)),
  updateLessonAttendance: async (id, payload) => data(await api.put(`/lesson-attendances/${id}`, payload)),
  finalizeLessonAttendance: async (id) => data(await api.post(`/lesson-attendances/${id}/finalize`)),
  unlockLessonAttendance: async (id, reason) => data(await api.post(`/lesson-attendances/${id}/unlock`, { reason })),
  cancelLessonAttendance: async (id, reason) => data(await api.post(`/lesson-attendances/${id}/cancel`, { reason })),
}
