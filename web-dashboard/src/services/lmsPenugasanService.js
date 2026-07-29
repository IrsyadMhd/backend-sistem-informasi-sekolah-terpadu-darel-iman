import { api } from './api'

export const lmsPenugasanService = {
  getDaftar: async (params = {}) => {
    const response = await api.get('/lms/penugasan', { params })
    return response.data
  },

  getById: async (id) => {
    const response = await api.get(`/lms/penugasan/${id}`)
    return response.data
  },

  create: async (data) => {
    const response = await api.post('/lms/penugasan', data)
    return response.data
  },

  update: async (id, data) => {
    const response = await api.put(`/lms/penugasan/${id}`, data)
    return response.data
  },

  delete: async (id) => {
    const response = await api.delete(`/lms/penugasan/${id}`)
    return response.data
  },

  restore: async (id) => {
    const response = await api.post(`/lms/penugasan/${id}/restore`)
    return response.data
  },

  togglePublish: async (id) => {
    const response = await api.post(`/lms/penugasan/${id}/toggle-publish`)
    return response.data
  },

  gradeSubmission: async (id, data) => {
    const response = await api.post(`/lms/penugasan/${id}/nilai`, data)
    return response.data
  },

  getStats: async () => {
    const response = await api.get('/lms/penugasan/stats')
    return response.data
  },

  getOptions: async () => {
    const response = await api.get('/lms/penugasan/options')
    return response.data
  },
}
