import { api } from './api'

export const dashboardPemantauanService = {
  getRingkasan: async () => {
    const { data } = await api.get('/dashboard-pemantauan/ringkasan')
    return data
  },

  getDaftarPemantauanDivisi: async (params = {}) => {
    const { data } = await api.get('/dashboard-pemantauan/pemantauan-divisi', { params })
    return data
  },

  tambahPemantauanDivisi: async (payload) => {
    const { data } = await api.post('/dashboard-pemantauan/pemantauan-divisi', payload)
    return data
  },

  ubahPemantauanDivisi: async ({ id, payload }) => {
    const { data } = await api.put(`/dashboard-pemantauan/pemantauan-divisi/${id}`, payload)
    return data
  },

  hapusPemantauanDivisi: async (id) => {
    const { data } = await api.delete(`/dashboard-pemantauan/pemantauan-divisi/${id}`)
    return data
  },

  getDaftarLaporanBulanan: async (params = {}) => {
    const { data } = await api.get('/dashboard-pemantauan/laporan-bulanan', { params })
    return data
  },

  tambahLaporanBulanan: async (payload) => {
    const { data } = await api.post('/dashboard-pemantauan/laporan-bulanan', payload)
    return data
  },

  ubahLaporanBulanan: async ({ id, payload }) => {
    const { data } = await api.put(`/dashboard-pemantauan/laporan-bulanan/${id}`, payload)
    return data
  },

  hapusLaporanBulanan: async (id) => {
    const { data } = await api.delete(`/dashboard-pemantauan/laporan-bulanan/${id}`)
    return data
  },

  getDaftarRekapPrestasiSiswa: async (params = {}) => {
    const { data } = await api.get('/dashboard-pemantauan/rekap-prestasi-siswa', { params })
    return data
  },

  tambahRekapPrestasiSiswa: async (payload) => {
    const { data } = await api.post('/dashboard-pemantauan/rekap-prestasi-siswa', payload)
    return data
  },

  ubahRekapPrestasiSiswa: async ({ id, payload }) => {
    const { data } = await api.put(`/dashboard-pemantauan/rekap-prestasi-siswa/${id}`, payload)
    return data
  },

  hapusRekapPrestasiSiswa: async (id) => {
    const { data } = await api.delete(`/dashboard-pemantauan/rekap-prestasi-siswa/${id}`)
    return data
  },

  getDaftarPengumumanSekolah: async (params = {}) => {
    const { data } = await api.get('/dashboard-pemantauan/pengumuman-sekolah', { params })
    return data
  },

  tambahPengumumanSekolah: async (payload) => {
    const { data } = await api.post('/dashboard-pemantauan/pengumuman-sekolah', payload)
    return data
  },

  ubahPengumumanSekolah: async ({ id, payload }) => {
    const { data } = await api.put(`/dashboard-pemantauan/pengumuman-sekolah/${id}`, payload)
    return data
  },

  hapusPengumumanSekolah: async (id) => {
    const { data } = await api.delete(`/dashboard-pemantauan/pengumuman-sekolah/${id}`)
    return data
  },

  getDaftarIndikatorKinerjaUtama: async (params = {}) => {
    const { data } = await api.get('/dashboard-pemantauan/indikator-kinerja-utama', { params })
    return data
  },

  tambahIndikatorKinerjaUtama: async (payload) => {
    const { data } = await api.post('/dashboard-pemantauan/indikator-kinerja-utama', payload)
    return data
  },

  ubahIndikatorKinerjaUtama: async ({ id, payload }) => {
    const { data } = await api.put(`/dashboard-pemantauan/indikator-kinerja-utama/${id}`, payload)
    return data
  },

  hapusIndikatorKinerjaUtama: async (id) => {
    const { data } = await api.delete(`/dashboard-pemantauan/indikator-kinerja-utama/${id}`)
    return data
  },
}
