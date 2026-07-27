import { api } from './api'

export const employeeService = {
  getDashboard: async () => {
    const { data } = await api.get('/employees/dashboard')
    return data
  },

  getDaftar: async (params = {}) => {
    const { data } = await api.get('/employees', { params })
    return data
  },

  getPositions: async () => {
    const { data } = await api.get('/employees/positions')
    return data
  },

  getDetail: async (id) => {
    const { data } = await api.get(`/employees/${id}`)
    return data
  },

  tambah: async (payload) => {
    const { data } = await api.post('/employees', payload)
    return data
  },

  ubah: async ({ id, payload }) => {
    const { data } = await api.put(`/employees/${id}`, payload)
    return data
  },

  hapus: async (id) => {
    const { data } = await api.delete(`/employees/${id}`)
    return data
  },

  assignTeaching: async ({ id, teachings }) => {
    const { data } = await api.post(`/employees/${id}/teachings`, { teachings })
    return data
  },

  importData: async (formData) => {
    const { data } = await api.post('/employees/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return data
  },

  exportData: async (params = {}) => {
    const { data } = await api.get('/employees/export', { params })
    return data
  },
}
