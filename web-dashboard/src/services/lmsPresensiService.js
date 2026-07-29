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
}
