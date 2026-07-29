import { api } from './api'

function normalizeAssets(settings) {
  const apiOrigin = new URL(api.defaults.baseURL, window.location.origin).origin
  const assetUrl = (value) => {
    if (!value) return ''

    // Path relatif dari backend selalu memakai origin API, bukan origin Vite.
    if (value.startsWith('/')) return `${apiOrigin}${value}`

    // Koreksi URL lama yang dibuat dari APP_URL tanpa port API.
    const parsed = new URL(value, apiOrigin)
    if (parsed.pathname.startsWith('/storage/')) {
      return `${apiOrigin}${parsed.pathname}${parsed.search}`
    }
    return parsed.href
  }

  return {
    ...settings,
    logo_url: assetUrl(settings.logo_url),
    favicon_url: assetUrl(settings.favicon_url),
  }
}

export const siteSettingService = {
  async get() {
    const response = await api.get('/site-settings')
    return normalizeAssets(response.data.data)
  },

  async update(values, files = {}) {
    const payload = new FormData()
    Object.entries(values).forEach(([key, value]) => {
      payload.append(key, typeof value === 'boolean' ? (value ? '1' : '0') : (value ?? ''))
    })
    if (files.logo) payload.append('logo', files.logo)
    if (files.favicon) payload.append('favicon', files.favicon)

    const response = await api.post('/site-settings', payload, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return normalizeAssets(response.data.data)
  },
}
