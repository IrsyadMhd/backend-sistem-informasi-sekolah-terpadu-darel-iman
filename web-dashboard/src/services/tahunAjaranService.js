import { api } from './api'

/**
 * Service API untuk Manajemen Master Data Tahun Ajaran (Academic Year).
 */
export const tahunAjaranService = {
  getDaftar: async (params = {}) => {
    const { data } = await api.get('/master/tahun-ajaran', { params })
    return data
  },

  getDropdown: async () => {
    const { data } = await api.get('/master/tahun-ajaran/dropdown')
    return data?.data || []
  },

  getStats: async () => {
    const { data } = await api.get('/master/tahun-ajaran/stats')
    return data?.data || {}
  },

  getDetail: async (id) => {
    const { data } = await api.get(`/master/tahun-ajaran/${id}`)
    return data?.data || {}
  },

  tambah: async (payload) => {
    const { data } = await api.post('/master/tahun-ajaran', payload)
    return data
  },

  ubah: async ({ id, payload }) => {
    const { data } = await api.put(`/master/tahun-ajaran/${id}`, payload)
    return data
  },

  setAktif: async (id) => {
    const { data } = await api.post(`/master/tahun-ajaran/${id}/set-aktif`)
    return data
  },

  hapus: async (id) => {
    const { data } = await api.delete(`/master/tahun-ajaran/${id}`)
    return data
  },

  pulihkan: async (id) => {
    const { data } = await api.post(`/master/tahun-ajaran/${id}/restore`)
    return data
  },

  prosesImport: async (rows) => {
    const { data } = await api.post('/master/tahun-ajaran/import', { rows })
    return data
  },

  ekspor: async (params = {}) => {
    const { data } = await api.get('/master/tahun-ajaran/export', { params })
    return data?.data || []
  },
}
