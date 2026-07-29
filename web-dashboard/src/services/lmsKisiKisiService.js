import { api } from './api'

export const lmsKisiKisiService = {
  getDaftar: async (params = {}) => {
    const response = await api.get('/lms/kisi-kisi', { params })
    return response.data
  },

  getById: async (id) => {
    const response = await api.get(`/lms/kisi-kisi/${id}`)
    return response.data
  },

  create: async (data) => {
    const response = await api.post('/lms/kisi-kisi', data)
    return response.data
  },

  update: async (id, data) => {
    const response = await api.put(`/lms/kisi-kisi/${id}`, data)
    return response.data
  },

  delete: async (id) => {
    const response = await api.delete(`/lms/kisi-kisi/${id}`)
    return response.data
  },

  restore: async (id) => {
    const response = await api.post(`/lms/kisi-kisi/${id}/restore`)
    return response.data
  },

  duplicate: async (id) => {
    const response = await api.post(`/lms/kisi-kisi/${id}/duplicate`)
    return response.data
  },

  getStats: async (params = {}) => {
    const response = await api.get('/lms/kisi-kisi/stats', { params })
    return response.data
  },

  getOptions: async (params = {}) => {
    const response = await api.get('/lms/kisi-kisi/options', { params })
    return response.data
  },
}
