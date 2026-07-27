import { api } from './api'

/**
 * Service API untuk Manajemen Master Data Jenis Unit Pendidikan.
 */
export const jenisUnitService = {
  getDaftar: async (params = {}) => {
    const { data } = await api.get('/master/jenis-unit', { params })
    return data
  },

  getDropdown: async () => {
    const { data } = await api.get('/master/jenis-unit/dropdown')
    return data?.data || []
  },

  getStats: async () => {
    const { data } = await api.get('/master/jenis-unit/stats')
    return data?.data || {}
  },

  getDetail: async (id) => {
    const { data } = await api.get(`/master/jenis-unit/${id}`)
    return data?.data || {}
  },

  tambah: async (payload) => {
    const { data } = await api.post('/master/jenis-unit', payload)
    return data
  },

  ubah: async ({ id, payload }) => {
    const { data } = await api.put(`/master/jenis-unit/${id}`, payload)
    return data
  },

  hapus: async (id) => {
    const { data } = await api.delete(`/master/jenis-unit/${id}`)
    return data
  },

  pulihkan: async (id) => {
    const { data } = await api.post(`/master/jenis-unit/${id}/restore`)
    return data
  },

  prosesImport: async (dataRows) => {
    const { data } = await api.post('/master/jenis-unit/import', { data: dataRows })
    return data
  },

  ekspor: async (params = {}) => {
    const { data } = await api.get('/master/jenis-unit/export', { params })
    return data?.data || []
  },
}
