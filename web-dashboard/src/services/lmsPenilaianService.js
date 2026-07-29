import { api } from './api'

export const lmsPenilaianService = {
  getDaftar: async (params = {}) => {
    const response = await api.get('/lms/penilaian', { params })
    return response.data
  },

  getById: async (id) => {
    const response = await api.get(`/lms/penilaian/${id}`)
    return response.data
  },

  create: async (data) => {
    const response = await api.post('/lms/penilaian', data)
    return response.data
  },

  update: async (id, data) => {
    const response = await api.put(`/lms/penilaian/${id}`, data)
    return response.data
  },

  delete: async (id) => {
    const response = await api.delete(`/lms/penilaian/${id}`)
    return response.data
  },

  restore: async (id) => {
    const response = await api.post(`/lms/penilaian/${id}/restore`)
    return response.data
  },

  calculateAuto: async (params = {}) => {
    const response = await api.post('/lms/penilaian/calculate-auto', params)
    return response.data
  },

  getStats: async (params = {}) => {
    const response = await api.get('/lms/penilaian/stats', { params })
    return response.data
  },

  getOptions: async () => {
    const response = await api.get('/lms/penilaian/options')
    return response.data
  },
}
