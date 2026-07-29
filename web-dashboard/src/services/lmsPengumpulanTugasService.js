import { api } from './api'

export const lmsPengumpulanTugasService = {
  getDaftar: async (params = {}) => {
    const response = await api.get('/lms/pengumpulan-tugas', { params })
    return response.data
  },

  getById: async (id) => {
    const response = await api.get(`/lms/pengumpulan-tugas/${id}`)
    return response.data
  },

  create: async (data) => {
    const response = await api.post('/lms/pengumpulan-tugas', data)
    return response.data
  },

  update: async (id, data) => {
    const response = await api.put(`/lms/pengumpulan-tugas/${id}`, data)
    return response.data
  },

  delete: async (id) => {
    const response = await api.delete(`/lms/pengumpulan-tugas/${id}`)
    return response.data
  },

  restore: async (id) => {
    const response = await api.post(`/lms/pengumpulan-tugas/${id}/restore`)
    return response.data
  },

  getStats: async () => {
    const response = await api.get('/lms/pengumpulan-tugas/stats')
    return response.data
  },

  getOptions: async () => {
    const response = await api.get('/lms/pengumpulan-tugas/options')
    return response.data
  },
}
