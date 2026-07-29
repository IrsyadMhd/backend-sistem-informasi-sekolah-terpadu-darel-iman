import { create } from 'zustand'
import { siteSettingService } from '../services/siteSettingService'

const STORAGE_KEY = 'pengaturan_dashboard'

export const defaultPengaturan = {
  application_name: 'Sistem Manajemen Sekolah',
  school_name: 'YAYASAN DAR EL - IMAN',
  logo_text: 'YDE',
  logo_url: '',
  favicon_url: '',
  footer_text: 'Jl. Pendidikan No. 1, Kota Padang',
  header_style: 'light',
  header_sticky: true,
  sidebar_style: 'gradient',
  sidebar_position: 'left',
  sidebar_collapsed: false,
  template: 'modern',
  sidebar_color: '#0E5C44',
  sidebar_accent_color: '#3FBF75',
  body_color: '#F7F9FC',
  header_color: '#FFFFFF',
}

function readCached() {
  try {
    const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}')
    return { ...defaultPengaturan, ...saved }
  } catch {
    return defaultPengaturan
  }
}

function cache(settings) {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(settings))
}

export const usePengaturanStore = create((set, get) => ({
  pengaturan: readCached(),
  loading: false,
  initialized: false,
  async muatPengaturan() {
    if (get().loading) return
    set({ loading: true })
    try {
      const pengaturan = { ...defaultPengaturan, ...(await siteSettingService.get()) }
      cache(pengaturan)
      set({ pengaturan, initialized: true })
    } catch {
      set({ initialized: true })
    } finally {
      set({ loading: false })
    }
  },
  previewPengaturan(payload) {
    set({ pengaturan: { ...get().pengaturan, ...payload } })
  },
  async simpanPengaturan(payload, files) {
    const pengaturan = { ...defaultPengaturan, ...(await siteSettingService.update(payload, files)) }
    cache(pengaturan)
    set({ pengaturan })
    return pengaturan
  },
}))
