import { api } from './api'

export const lmsModulAjarService = {
  getAll: async (params = {}) => {
    const res = await api.get('/lms/modul-ajar', { params })
    return res.data
  },

  getStats: async () => {
    const res = await api.get('/lms/modul-ajar/stats')
    return res.data
  },

  getOptions: async () => {
    const res = await api.get('/lms/modul-ajar/options')
    return res.data
  },

  getById: async (id) => {
    const res = await api.get(`/lms/modul-ajar/${id}`)
    return res.data
  },

  create: async (data) => {
    const res = await api.post('/lms/modul-ajar', data)
    return res.data
  },

  update: async (id, data) => {
    const res = await api.put(`/lms/modul-ajar/${id}`, data)
    return res.data
  },

  delete: async (id) => {
    const res = await api.delete(`/lms/modul-ajar/${id}`)
    return res.data
  },

  restore: async (id) => {
    const res = await api.post(`/lms/modul-ajar/${id}/restore`)
    return res.data
  },

  publish: async (id) => {
    const res = await api.post(`/lms/modul-ajar/${id}/publish`)
    return res.data
  },

  duplicate: async (id) => {
    const res = await api.post(`/lms/modul-ajar/${id}/duplicate`)
    return res.data
  },

  getRevisions: async (id) => {
    const res = await api.get(`/lms/modul-ajar/${id}/revisions`)
    return res.data
  },

  exportPdf: async (id) => {
    const res = await api.get(`/lms/modul-ajar/${id}/export/pdf`)
    return res.data
  },

  importExcel: async (formData) => {
    const res = await api.post('/lms/modul-ajar/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return res.data
  },
}
