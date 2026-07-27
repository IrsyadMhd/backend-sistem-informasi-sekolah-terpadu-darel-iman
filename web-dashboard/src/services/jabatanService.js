import { api } from './api'

/**
 * Service API untuk Manajemen Master Data Jabatan.
 * Menyediakan metode komunikasi ke backend Laravel.
 */
export const jabatanService = {
  /**
   * Dapatkan daftar jabatan berpaginasi dengan pencarian & filter.
   */
  getDaftar: async (params = {}) => {
    const { data } = await api.get('/jabatan', { params })
    return data
  },

  /**
   * Dapatkan opsi data master dropdown (Unit Sekolah, Atasan Langsung, Role Sistem, Level Jabatan).
   */
  getOptions: async () => {
    const { data } = await api.get('/jabatan/options')
    return data?.data || {}
  },

  /**
   * Dapatkan ringkasan statistik jabatan.
   */
  getStats: async () => {
    const { data } = await api.get('/jabatan/stats')
    return data?.data || {}
  },

  /**
   * Dapatkan detail jabatan berdasarkan ID.
   */
  getDetail: async (id) => {
    const { data } = await api.get(`/jabatan/${id}`)
    return data?.data || {}
  },

  /**
   * Tambah data jabatan baru.
   */
  tambah: async (payload) => {
    const { data } = await api.post('/jabatan', payload)
    return data
  },

  /**
   * Ubah data jabatan.
   */
  ubah: async ({ id, payload }) => {
    const { data } = await api.put(`/jabatan/${id}`, payload)
    return data
  },

  /**
   * Hapus data jabatan (Soft Delete).
   */
  hapus: async (id) => {
    const { data } = await api.delete(`/jabatan/${id}`)
    return data
  },

  /**
   * Pulihkan data jabatan yang terhapus.
   */
  pulihkan: async (id) => {
    const { data } = await api.post(`/jabatan/${id}/restore`)
    return data
  },

  /**
   * Impor data jabatan batch.
   */
  prosesImport: async (dataRows) => {
    const { data } = await api.post('/jabatan/import', { data: dataRows })
    return data
  },

  /**
   * Ekspor data master jabatan.
   */
  ekspor: async (params = {}) => {
    const { data } = await api.get('/jabatan/export', { params })
    return data?.data || []
  },
}
