import { api } from './api'

export const studentService = {
  getDashboard: async () => {
    const { data } = await api.get('/students/dashboard')
    return data
  },

  getDaftar: async (params = {}) => {
    const { data } = await api.get('/students', { params })
    return data
  },

  getDetail: async (id) => {
    const { data } = await api.get(`/students/${id}`)
    return data
  },

  tambah: async (payload) => {
    const { data } = await api.post('/students', payload)
    return data
  },

  ubah: async ({ id, payload }) => {
    const { data } = await api.put(`/students/${id}`, payload)
    return data
  },

  hapus: async (id) => {
    const { data } = await api.delete(`/students/${id}`)
    return data
  },
}
