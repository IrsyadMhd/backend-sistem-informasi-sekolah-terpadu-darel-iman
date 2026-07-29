import { api } from './api'

export const lmsRaporService = {
  getDaftar: async (params = {}) => {
    const response = await api.get('/lms/rapor', { params })
    return response.data
  },

  getById: async (id) => {
    const response = await api.get(`/lms/rapor/${id}`)
    return response.data
  },

  create: async (data) => {
    const response = await api.post('/lms/rapor', data)
    return response.data
  },

  update: async (id, data) => {
    const response = await api.put(`/lms/rapor/${id}`, data)
    return response.data
  },

  delete: async (id) => {
    const response = await api.delete(`/lms/rapor/${id}`)
    return response.data
  },

  restore: async (id) => {
    const response = await api.post(`/lms/rapor/${id}/restore`)
    return response.data
  },

  generateClass: async (data) => {
    const response = await api.post('/lms/rapor/generate-class', data)
    return response.data
  },

  getPdf: async (id) => {
    const response = await api.get(`/lms/rapor/${id}/pdf`)
    return response.data
  },

  getStats: async (params = {}) => {
    const response = await api.get('/lms/rapor/stats', { params })
    return response.data
  },

  getOptions: async () => {
    const response = await api.get('/lms/rapor/options')
    return response.data
  },
}
