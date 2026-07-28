import { api } from './api'

/**
 * Service API untuk Manajemen Master Modul Semester.
 * Menyediakan metode komunikasi ke backend Laravel REST API.
 */
export const modulSemesterService = {
  getDaftar: async (params = {}) => {
    const { data } = await api.get('/master/modul-semester', { params })
    return data
  },

  getOptions: async () => {
    const { data } = await api.get('/master/modul-semester/options')
    return data?.data || {}
  },

  getStats: async () => {
    const { data } = await api.get('/master/modul-semester/stats')
    return data?.data || {}
  },

  getDetail: async (id) => {
    const { data } = await api.get(`/master/modul-semester/${id}`)
    return data?.data || {}
  },

  tambah: async (payload) => {
    const { data } = await api.post('/master/modul-semester', payload)
    return data
  },

  ubah: async ({ id, payload }) => {
    const { data } = await api.put(`/master/modul-semester/${id}`, payload)
    return data
  },

  hapus: async (id) => {
    const { data } = await api.delete(`/master/modul-semester/${id}`)
    return data
  },

  pulihkan: async (id) => {
    const { data } = await api.post(`/master/modul-semester/${id}/restore`)
    return data
  },

  duplikasi: async (id) => {
    const { data } = await api.post(`/master/modul-semester/${id}/duplicate`)
    return data
  },

  toggleStatus: async (id, status) => {
    const { data } = await api.post(`/master/modul-semester/${id}/toggle-status`, { status })
    return data
  },
}
