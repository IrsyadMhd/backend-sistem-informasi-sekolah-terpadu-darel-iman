import { api } from './api'

export const scheduleService = {
  getDaftar: async (params = {}) => {
    const { data } = await api.get('/schedules', { params })
    return data
  },
  getOptions: async () => {
    const { data } = await api.get('/schedules-options')
    return data?.data || {}
  },
  getDetail: async (id) => {
    const { data } = await api.get(`/schedules/${id}`)
    return data?.data || {}
  },
  tambah: async (payload) => {
    const { data } = await api.post('/schedules', payload)
    return data
  },
  ubah: async ({ id, payload }) => {
    const { data } = await api.put(`/schedules/${id}`, payload)
    return data
  },
  hapus: async (id) => {
    const { data } = await api.delete(`/schedules/${id}`)
    return data
  },
}
