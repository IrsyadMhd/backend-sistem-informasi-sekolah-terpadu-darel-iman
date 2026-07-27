import { api } from './api'

/**
 * Service API untuk Manajemen Master Data Kelas / Rombongan Belajar (Rombel).
 * Menyediakan metode komunikasi ke backend Laravel.
 */
export const kelasService = {
  /**
   * Dapatkan daftar kelas berpaginasi dengan pencarian & filter.
   */
  getDaftar: async (params = {}) => {
    const { data } = await api.get('/kelas', { params })
    return data
  },

  /**
   * Dapatkan opsi data master dropdown (Unit, Tahun Ajaran, Semester, Pegawai/Guru).
   */
  getOptions: async () => {
    const { data } = await api.get('/kelas/options')
    return data?.data || {}
  },

  /**
   * Dapatkan ringkasan statistik kelas.
   */
  getStats: async () => {
    const { data } = await api.get('/kelas/stats')
    return data?.data || {}
  },

  /**
   * Dapatkan detail kelas berdasarkan ID.
   */
  getDetail: async (id) => {
    const { data } = await api.get(`/kelas/${id}`)
    return data?.data || {}
  },

  /**
   * Tambah kelas / rombel baru.
   */
  tambah: async (payload) => {
    const { data } = await api.post('/kelas', payload)
    return data
  },

  /**
   * Ubah data kelas / rombel.
   */
  ubah: async ({ id, payload }) => {
    const { data } = await api.put(`/kelas/${id}`, payload)
    return data
  },

  /**
   * Hapus data kelas (Soft Delete).
   */
  hapus: async (id) => {
    const { data } = await api.delete(`/kelas/${id}`)
    return data
  },

  /**
   * Pulihkan data kelas yang terhapus (Soft Delete Restore).
   */
  pulihkan: async (id) => {
    const { data } = await api.post(`/kelas/${id}/restore`)
    return data
  },

  /**
   * Dapatkan daftar siswa dalam kelas / rombel tertentu.
   */
  getSiswaRombel: async (id) => {
    const { data } = await api.get(`/kelas/${id}/siswa`)
    return data?.data || {}
  },

  /**
   * Impor data kelas dari file / payload JSON.
   */
  prosesImport: async (dataRows) => {
    const { data } = await api.post('/kelas/import', { data: dataRows })
    return data
  },
}
