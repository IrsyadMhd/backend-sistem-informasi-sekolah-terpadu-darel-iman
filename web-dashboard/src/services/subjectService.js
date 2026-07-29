import { api } from './api'

export const subjectService = {
  getDaftar: async (params = {}) => {
    const response = await api.get('/master/subjects', { params })
    return response.data
  },

  getDropdown: async (params = {}) => {
    const response = await api.get('/master/subjects/dropdown', { params })
    return response.data
  },

  getStats: async () => {
    const response = await api.get('/master/subjects/stats')
    return response.data
  },

  cariDetail: async (id) => {
    const response = await api.get(`/master/subjects/${id}`)
    return response.data
  },

  tambah: async (payload) => {
    const response = await api.post('/master/subjects', payload)
    return response.data
  },

  ubah: async ({ id, payload }) => {
    const response = await api.put(`/master/subjects/${id}`, payload)
    return response.data
  },

  hapus: async (id) => {
    const response = await api.delete(`/master/subjects/${id}`)
    return response.data
  },

  pulihkan: async (id) => {
    const response = await api.post(`/master/subjects/${id}/restore`)
    return response.data
  },

  bulkStatus: async (ids, status) => {
    const response = await api.post('/master/subjects/bulk-status', { ids, status })
    return response.data
  },

  bulkDelete: async (ids) => {
    const response = await api.post('/master/subjects/bulk-delete', { ids })
    return response.data
  },

  exportExcel: async (params = {}) => {
    const response = await api.get('/master/subjects/export/excel', { params })
    return response.data
  },

  exportPdf: async (params = {}) => {
    const response = await api.get('/master/subjects/export/pdf', { params })
    return response.data
  },

  importFile: async (formData) => {
    const response = await api.post('/master/subjects/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return response.data
  },
}
