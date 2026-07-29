import { api } from './api'

/**
 * Service API Client untuk Modul Tujuan Pembelajaran (TP).
 */
export const tujuanPembelajaranService = {
  getDaftar: async (params = {}) => {
    const { data } = await api.get('/lms/tujuan-pembelajaran', { params })
    return data
  },

  getOptions: async () => {
    const { data } = await api.get('/lms/tujuan-pembelajaran/options')
    return data?.data || {}
  },

  getStats: async () => {
    const { data } = await api.get('/lms/tujuan-pembelajaran/stats')
    return data?.data || {}
  },

  getDetail: async (id) => {
    const { data } = await api.get(`/lms/tujuan-pembelajaran/${id}`)
    return data?.data || {}
  },

  tambah: async (payload) => {
    const { data } = await api.post('/lms/tujuan-pembelajaran', payload)
    return data
  },

  ubah: async ({ id, payload }) => {
    const { data } = await api.put(`/lms/tujuan-pembelajaran/${id}`, payload)
    return data
  },

  hapus: async (id) => {
    const { data } = await api.delete(`/lms/tujuan-pembelajaran/${id}`)
    return data
  },

  pulihkan: async (id) => {
    const { data } = await api.post(`/lms/tujuan-pembelajaran/${id}/restore`)
    return data
  },
}
