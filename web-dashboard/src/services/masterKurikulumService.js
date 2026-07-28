import { api } from './api'

/**
 * Service API Client untuk Manajemen Master Data Kurikulum.
 */
export const masterKurikulumService = {
  getDaftar: async (params = {}) => {
    const { data } = await api.get('/master/kurikulum', { params })
    return data
  },

  getDropdown: async (unitId = null) => {
    const { data } = await api.get('/master/kurikulum/dropdown', {
      params: unitId ? { unit_pendidikan_id: unitId } : {},
    })
    return data?.data || []
  },

  getStats: async () => {
    const { data } = await api.get('/master/kurikulum/stats')
    return data?.data || {}
  },

  getDetail: async (id) => {
    const { data } = await api.get(`/master/kurikulum/${id}`)
    return data?.data || {}
  },

  tambah: async (payload) => {
    const { data } = await api.post('/master/kurikulum', payload)
    return data
  },

  ubah: async ({ id, payload }) => {
    const { data } = await api.put(`/master/kurikulum/${id}`, payload)
    return data
  },

  hapus: async (id) => {
    const { data } = await api.delete(`/master/kurikulum/${id}`)
    return data
  },

  pulihkan: async (id) => {
    const { data } = await api.post(`/master/kurikulum/${id}/restore`)
    return data
  },

  prosesImport: async (dataRows) => {
    const { data } = await api.post('/master/kurikulum/import', { data: dataRows })
    return data
  },

  ekspor: async (params = {}) => {
    const { data } = await api.get('/master/kurikulum/export', { params })
    return data?.data || []
  },
}
