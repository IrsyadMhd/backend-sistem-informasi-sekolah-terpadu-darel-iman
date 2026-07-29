import { api } from './api'

export const lmsDiskusiService = {
  getDaftar: async (params = {}) => {
    const response = await api.get('/lms/diskusi', { params })
    return response.data
  },

  getById: async (id) => {
    const response = await api.get(`/lms/diskusi/${id}`)
    return response.data
  },

  create: async (data) => {
    const response = await api.post('/lms/diskusi', data)
    return response.data
  },

  update: async (id, data) => {
    const response = await api.put(`/lms/diskusi/${id}`, data)
    return response.data
  },

  delete: async (id) => {
    const response = await api.delete(`/lms/diskusi/${id}`)
    return response.data
  },

  restore: async (id) => {
    const response = await api.post(`/lms/diskusi/${id}/restore`)
    return response.data
  },

  togglePin: async (id) => {
    const response = await api.post(`/lms/diskusi/${id}/toggle-pin`)
    return response.data
  },

  toggleClose: async (id) => {
    const response = await api.post(`/lms/diskusi/${id}/toggle-close`)
    return response.data
  },

  tambahKomentar: async (diskusiId, data) => {
    const response = await api.post(`/lms/diskusi/${diskusiId}/komentar`, data)
    return response.data
  },

  hapusKomentar: async (diskusiId, komentarId) => {
    const response = await api.delete(`/lms/diskusi/${diskusiId}/komentar/${komentarId}`)
    return response.data
  },

  getStats: async () => {
    const response = await api.get('/lms/diskusi/stats')
    return response.data
  },

  getOptions: async () => {
    const response = await api.get('/lms/diskusi/options')
    return response.data
  },
}
