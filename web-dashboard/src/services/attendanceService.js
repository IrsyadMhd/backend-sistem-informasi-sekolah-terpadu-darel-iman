import { api } from './api'

export const attendanceService = {
  async getDaftar(params = {}) {
    const { data } = await api.get('/attendance', { params })
    return data
  },

  async getStats(params = {}) {
    const { data } = await api.get('/attendance/stats', { params })
    return data
  },

  async checkin(payload) {
    const { data } = await api.post('/attendance/checkin', payload)
    return data
  },

  async checkout(payload) {
    const { data } = await api.post('/attendance/checkout', payload)
    return data
  },

  async report(params = {}) {
    const { data } = await api.get('/attendance/report', { params })
    return data
  },

  async detail(id) {
    const { data } = await api.get(`/attendance/${id}`)
    return data
  },

  async tambah(payload) {
    const { data } = await api.post('/attendance', payload)
    return data
  },

  async ubah({ id, payload }) {
    const { data } = await api.put(`/attendance/${id}`, payload)
    return data
  },

  async hapus(id) {
    const { data } = await api.delete(`/attendance/${id}`)
    return data
  },
}
