import { api } from './api'

export const lmsAktivitasBelajarService = {
  getDaftar: async (params = {}) => {
    const response = await api.get('/lms/aktivitas', { params })
    return response.data
  },

  getById: async (id) => {
    const response = await api.get(`/lms/aktivitas/${id}`)
    return response.data
  },

  create: async (data) => {
    const response = await api.post('/lms/aktivitas', data)
    return response.data
  },

  update: async (id, data) => {
    const response = await api.put(`/lms/aktivitas/${id}`, data)
    return response.data
  },

  delete: async (id) => {
    const response = await api.delete(`/lms/aktivitas/${id}`)
    return response.data
  },

  restore: async (id) => {
    const response = await api.post(`/lms/aktivitas/${id}/restore`)
    return response.data
  },

  getStats: async () => {
    const response = await api.get('/lms/aktivitas/stats')
    return response.data
  },

  getOptions: async () => {
    const response = await api.get('/lms/aktivitas/options')
    return response.data
  },
}
