import { api } from './api'

export const lmsReferensiService = {
  getDaftar: async (params = {}) => {
    const response = await api.get('/lms/referensi', { params })
    return response.data
  },

  getById: async (id) => {
    const response = await api.get(`/lms/referensi/${id}`)
    return response.data
  },

  create: async (formData) => {
    const isFormData = formData instanceof FormData
    const response = await api.post('/lms/referensi', formData, {
      headers: isFormData ? { 'Content-Type': 'multipart/form-data' } : {},
    })
    return response.data
  },

  update: async (id, formData) => {
    const isFormData = formData instanceof FormData
    let data = formData
    let headers = {}

    if (isFormData) {
      data.append('_method', 'PUT')
      headers['Content-Type'] = 'multipart/form-data'
    }

    const response = isFormData
      ? await api.post(`/lms/referensi/${id}`, data, { headers })
      : await api.put(`/lms/referensi/${id}`, data)

    return response.data
  },

  delete: async (id) => {
    const response = await api.delete(`/lms/referensi/${id}`)
    return response.data
  },

  restore: async (id) => {
    const response = await api.post(`/lms/referensi/${id}/restore`)
    return response.data
  },

  getStats: async () => {
    const response = await api.get('/lms/referensi/stats')
    return response.data
  },

  getOptions: async () => {
    const response = await api.get('/lms/referensi/options')
    return response.data
  },
}
