import { api } from './api'

export const lmsBankSoalService = {
  getDaftar: async (params = {}) => {
    const response = await api.get('/lms/bank-soal', { params })
    return response.data
  },

  getById: async (id) => {
    const response = await api.get(`/lms/bank-soal/${id}`)
    return response.data
  },

  create: async (data) => {
    const response = await api.post('/lms/bank-soal', data)
    return response.data
  },

  update: async (id, data) => {
    const response = await api.put(`/lms/bank-soal/${id}`, data)
    return response.data
  },

  delete: async (id) => {
    const response = await api.delete(`/lms/bank-soal/${id}`)
    return response.data
  },

  restore: async (id) => {
    const response = await api.post(`/lms/bank-soal/${id}/restore`)
    return response.data
  },

  duplicate: async (id) => {
    const response = await api.post(`/lms/bank-soal/${id}/duplicate`)
    return response.data
  },

  getStats: async (params = {}) => {
    const response = await api.get('/lms/bank-soal/stats', { params })
    return response.data
  },

  getOptions: async () => {
    const response = await api.get('/lms/bank-soal/options')
    return response.data
  },
}
