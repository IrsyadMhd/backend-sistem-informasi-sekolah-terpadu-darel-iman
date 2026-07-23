import { api } from './api'

export const attendanceService = {
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
}
