import { api } from './api'

export const authService = {
  login: async ({ email, password, device_name = 'web-dashboard' }) => {
    const { data } = await api.post('/auth/login', { email, password, device_name })
    return data
  },

  profile: async () => {
    const { data } = await api.get('/auth/profile')
    return data
  },

  logout: async () => {
    const { data } = await api.post('/auth/logout')
    return data
  },
}
