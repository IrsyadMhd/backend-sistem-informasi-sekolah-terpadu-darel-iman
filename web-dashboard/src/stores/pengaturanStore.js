import { create } from 'zustand'

const KUNCI_PENGATURAN = 'pengaturan_dashboard'

const defaultPengaturan = {
  namaDashboard: 'Dashboard Monitoring Kepala Sekolah',
  namaSekolah: 'SDIT DAR EL-IMAN',
  logoTeks: 'SDIT',
  logoUrl: '',
  alamatFooter: 'Jl. Pendidikan No. 1, Kota Padang',
}

function bacaPengaturanTersimpan() {
  try {
    const raw = localStorage.getItem(KUNCI_PENGATURAN)
    if (!raw) return defaultPengaturan
    return { ...defaultPengaturan, ...JSON.parse(raw) }
  } catch {
    return defaultPengaturan
  }
}

export const usePengaturanStore = create((set) => ({
  pengaturan: bacaPengaturanTersimpan(),
  simpanPengaturan: (payload) => {
    const dataBaru = { ...defaultPengaturan, ...payload }
    localStorage.setItem(KUNCI_PENGATURAN, JSON.stringify(dataBaru))
    set({ pengaturan: dataBaru })
  },
}))
