import { api } from './api'

export const mutabaahService = {
  options: (params = {}) => api.get('/mutabaah/options', { params }).then((r) => r.data.data),
  agendas: (params = {}) => api.get('/mutabaah/agendas', { params }).then((r) => r.data.data),
  createAgenda: (payload) => api.post('/mutabaah/agendas', payload).then((r) => r.data),
  updateAgenda: (id, payload) => api.put(`/mutabaah/agendas/${id}`, payload).then((r) => r.data),
  deleteAgenda: (id) => api.delete(`/mutabaah/agendas/${id}`).then((r) => r.data),
  daily: (studentId, date) => api.get('/mutabaah/daily', { params: { student_id: studentId, date } }).then((r) => r.data.data),
  saveDaily: (payload) => api.post('/mutabaah/daily', payload).then((r) => r.data),
  history: (studentId, params = {}) => api.get('/mutabaah/history', { params: { student_id: studentId, ...params } }).then((r) => r.data.data),
}
