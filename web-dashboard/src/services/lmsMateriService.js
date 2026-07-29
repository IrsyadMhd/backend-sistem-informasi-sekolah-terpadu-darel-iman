import { api } from './api'

export const lmsMateriService = {
  getDaftar: async (params = {}) => {
    const res = await api.get('/lms/materi', { params })
    return res.data
  },

  getStats: async () => {
    const res = await api.get('/lms/materi/stats')
    return res.data
  },

  getOptions: async () => {
    const res = await api.get('/lms/materi/options')
    return res.data
  },

  getDetail: async (id) => {
    const res = await api.get(`/lms/materi/${id}`)
    return res.data
  },

  simpan: async (formData) => {
    const isFormData = formData instanceof FormData
    const res = await api.post('/lms/materi', formData, {
      headers: isFormData ? { 'Content-Type': 'multipart/form-data' } : {},
    })
    return res.data
  },

  ubah: async (id, formData) => {
    const isFormData = formData instanceof FormData
    if (isFormData) {
      formData.append('_method', 'PUT')
      const res = await api.post(`/lms/materi/${id}`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      return res.data
    } else {
      const res = await api.put(`/lms/materi/${id}`, formData)
      return res.data
    }
  },

  hapus: async (id) => {
    const res = await api.delete(`/lms/materi/${id}`)
    return res.data
  },

  pulihkan: async (id) => {
    const res = await api.post(`/lms/materi/${id}/restore`)
    return res.data
  },
}
