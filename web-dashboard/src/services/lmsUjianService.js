import { api } from './api'

export const lmsUjianService = {
  getDaftar: async (params = {}) => {
    const response = await api.get('/lms/ujian', { params })
    return response.data
  },

  getById: async (id) => {
    const response = await api.get(`/lms/ujian/${id}`)
    return response.data
  },

  create: async (data) => {
    const response = await api.post('/lms/ujian', data)
    return response.data
  },

  update: async (id, data) => {
    const response = await api.put(`/lms/ujian/${id}`, data)
    return response.data
  },

  delete: async (id) => {
    const response = await api.delete(`/lms/ujian/${id}`)
    return response.data
  },

  restore: async (id) => {
    const response = await api.post(`/lms/ujian/${id}/restore`)
    return response.data
  },

  duplicate: async (id) => {
    const response = await api.post(`/lms/ujian/${id}/duplicate`)
    return response.data
  },

  togglePublish: async (id, status) => {
    const response = await api.post(`/lms/ujian/${id}/toggle-publish`, { status })
    return response.data
  },

  startSession: async (id, params = {}) => {
    const response = await api.post(`/lms/ujian/${id}/start-session`, params)
    return response.data
  },

  submitAnswers: async (sesiId, jawaban) => {
    const response = await api.post(`/lms/ujian/sesi/${sesiId}/submit-answers`, { jawaban })
    return response.data
  },

  finishSession: async (sesiId, jawaban = null) => {
    const response = await api.post(`/lms/ujian/sesi/${sesiId}/finish-session`, { jawaban })
    return response.data
  },

  getResults: async (id) => {
    const response = await api.get(`/lms/ujian/${id}/results`)
    return response.data
  },

  gradeEssay: async (jawabanId, poin, catatan = '') => {
    const response = await api.post(`/lms/ujian/jawaban/${jawabanId}/grade-essay`, {
      poin_didapat: poin,
      catatan_guru: catatan,
    })
    return response.data
  },

  getStats: async (params = {}) => {
    const response = await api.get('/lms/ujian/stats', { params })
    return response.data
  },

  getOptions: async () => {
    const response = await api.get('/lms/ujian/options')
    return response.data
  },
}
