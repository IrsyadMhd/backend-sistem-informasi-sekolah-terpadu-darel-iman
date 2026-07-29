import { api } from './api'

/**
 * Service API Client untuk Modul Capaian Pembelajaran (CP).
 */
export const capaianPembelajaranService = {
  getDropdown: async (params = {}) => {
    const { data } = await api.get('/capaian-pembelajaran/dropdown', { params })
    return data?.data || []
  },

  getDaftar: async (params = {}) => {
    const { data } = await api.get('/lms/capaian-pembelajaran', { params })
    return data
  },

  getStats: async () => {
    const { data } = await api.get('/lms/capaian-pembelajaran/stats')
    return data?.data || {}
  },

  getDetail: async (id) => {
    const { data } = await api.get(`/lms/capaian-pembelajaran/${id}`)
    return data?.data || {}
  },

  tambah: async (payload) => {
    const { data } = await api.post('/lms/capaian-pembelajaran', payload)
    return data
  },

  ubah: async ({ id, payload }) => {
    const { data } = await api.put(`/lms/capaian-pembelajaran/${id}`, payload)
    return data
  },

  hapus: async (id) => {
    const { data } = await api.delete(`/lms/capaian-pembelajaran/${id}`)
    return data
  },

  pulihkan: async (id) => {
    const { data } = await api.post(`/lms/capaian-pembelajaran/${id}/restore`)
    return data
  },
}
