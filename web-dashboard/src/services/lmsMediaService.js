import { api } from './api'

export const lmsMediaService = {
  getDaftar: async (params = {}) => {
    const response = await api.get('/lms/media', { params })
    return response.data
  },

  getById: async (id) => {
    const response = await api.get(`/lms/media/${id}`)
    return response.data
  },

  create: async (formData) => {
    const isFormData = formData instanceof FormData
    const response = await api.post('/lms/media', formData, {
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
      ? await api.post(`/lms/media/${id}`, data, { headers })
      : await api.put(`/lms/media/${id}`, data)

    return response.data
  },

  delete: async (id) => {
    const response = await api.delete(`/lms/media/${id}`)
    return response.data
  },

  reorder: async (orders) => {
    const response = await api.post('/lms/media/reorder', { orders })
    return response.data
  },

  getStats: async () => {
    const response = await api.get('/lms/media/stats')
    return response.data
  },

  getOptions: async () => {
    const response = await api.get('/lms/media/options')
    return response.data
  },
}
