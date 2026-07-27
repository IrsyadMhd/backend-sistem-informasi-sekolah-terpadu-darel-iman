import { api } from './api'

/**
 * Service Layer CRUD Siswa menggunakan Axios instance
 */
export const studentCrudService = {
  /**
   * Fetch daftar siswa dengan query params (search, filter, pagination)
   */
  getStudents: async (params = {}) => {
    const response = await api.get('/students', { params })
    return response.data
  },

  /**
   * Detail Siswa berdasarkan ID
   */
  getStudentById: async (id) => {
    const response = await api.get(`/students/${id}`)
    return response.data
  },

  /**
   * Tambah Siswa baru (CREATE)
   */
  createStudent: async (payload) => {
    const response = await api.post('/students', payload)
    return response.data
  },

  /**
   * Ubah data Siswa (UPDATE)
   */
  updateStudent: async ({ id, data }) => {
    const response = await api.put(`/students/${id}`, data)
    return response.data
  },

  /**
   * Hapus data Siswa (DELETE)
   */
  deleteStudent: async (id) => {
    const response = await api.delete(`/students/${id}`)
    return response.data
  },
}
